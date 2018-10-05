<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WebAuthentication.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Middleware;


use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Slim\Middleware;
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Random;

/**
 * Class SAMLAuthentication
 * @package Xibo\Middleware
 *
 * Provide SAML authentication to Xibo configured via settings.php.
 */
class SAMLAuthentication extends Middleware
{
    public static function samlRoutes()
    {
        return array(
            '/saml/metadata',
            '/saml/login',
            '/saml/acs',
            '/saml/logout',
            '/saml/sls'
        );
    }

    public function samlLogout()
    {
        if (isset($this->app->configService->samlSettings['workflow']) &&
          isset($this->app->configService->samlSettings['workflow']['slo']) &&
              $this->app->configService->samlSettings['workflow']['slo'] == true) {
            // Initiate SAML SLO
            $auth = new Auth($this->app->configService->samlSettings);
            $auth->logout();
        } else {
            $this->app->redirect($this->app->urlFor('logout'));
        }
    }

    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @throws \RuntimeException if there isn't a login route
     */
    public function call()
    {
        $app = $this->app;

        // Create a user
        $app->user = $app->userFactory->create();

        // Register SAML routes.
        $app->excludedCsrfRoutes = SAMLAuthentication::samlRoutes();
        $app->logoutRoute = 'saml.logout';

        $app->get('/saml/metadata', function () {
            $settings = new Settings($this->app->configService->samlSettings, true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                $app = $this->getApplication();
                $app->response()->header('Content-Type', 'text/xml');
                echo $metadata;
            } else {
                throw new \Xibo\Exception\ConfigurationException(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    Error::METADATA_SP_INVALID
                );
            }
        });

        $app->get('/saml/login', function () {
            // Initiate SAML SSO
            $auth = new Auth($this->app->configService->samlSettings);
            $auth->login();
        });

        $app->get('/saml/logout', function () {
            $this->samlLogout();
        })->setName('saml.logout');

        $app->post('/saml/acs', function () {
            // Assertion Consumer Endpoint
            $app = $this->getApplication();

            // Log some interesting things
            $app->getLog()->debug('Arrived at the ACS route with own URL: ' . Utils::getSelfRoutedURLNoQuery());

            // Inject the POST parameters required by the SAML toolkit
            $_POST = $this->app->request->post();

            // Pull out the SAML settings
            $samlSettings = $this->app->configService->samlSettings;

            $auth = new Auth($samlSettings);
            $auth->processResponse();

            $errors = $auth->getErrors();

            if (!empty($errors)) {
                throw new Error(
                    'SAML SSO failed: '.implode(', ', $errors) . '. Last Reason: ' . $auth->getLastErrorReason()
                );
            } else {
                // Pull out the SAML attributes
                $samlAttrs = $auth->getAttributes();

                // How should we look up the user?
                $identityField = (isset($samlSettings['workflow']['field_to_identify'])) ? $samlSettings['workflow']['field_to_identify'] : 'UserName';

                if ($identityField !== 'nameId' && empty($samlAttrs)) {
                    // We will need some attributes
                    throw new AccessDeniedException(__('No attributes retrieved from the IdP'));
                }

                // If appropriate convert the SAML Attributes into userData mapped against the workflow mappings.
                $userData = [];
                if (isset($samlSettings['workflow']) && isset($samlSettings['workflow']['mapping'])) {
                    foreach ($samlSettings['workflow']['mapping'] as $key => $value) {
                        if (!empty($value) && isset($samlAttrs[$value]) ) {
                            $userData[$key] = $samlAttrs[$value];
                        }
                    }

                    // If we can't map anything, then we better throw an error
                    if (empty($userData)) {
                        throw new AccessDeniedException(__('No attributes could be mapped'));
                    }
                }

                // If we're using the nameId as the identity, then we should populate our userData with that value
                if ($identityField === 'nameId') {
                    $userData[$identityField] = $auth->getNameId();
                } else {
                    // Check to ensure that our identity has been populated from attributes successfully
                    if (!isset($userData[$identityField]) || empty($userData[$identityField])) {
                        throw new AccessDeniedException(__('%s not retrieved from the IdP and required since is the field to identify the user', $identityField));
                    }
                }

                // Try and get the user record.
                $user = null;

                try {
                    switch ($identityField) {
                        case 'nameId':
                            $user = $app->userFactory->getByName($userData[$identityField]);
                            break;

                        case 'UserId':
                            $user = $app->userFactory->getById($userData[$identityField][0]);
                            break;

                        case 'UserName':
                            $user = $app->userFactory->getByName($userData[$identityField][0]);
                            break;

                        case 'email':
                            $user = $app->userFactory->getByEmail($userData[$identityField][0]);
                            break;

                        default:
                            throw new AccessDeniedException(__('Invalid field_to_identify value. Review settings.'));
                    }

                } catch (NotFoundException $e) {
                    // User does not exist - this is valid as we might create them JIT.
                }

                if (!isset($user)) {
                    if (!isset($samlSettings['workflow']['jit']) || $samlSettings['workflow']['jit'] == false) {
                        throw new AccessDeniedException(__('User logged at the IdP but the account does not exist in the CMS and Just-In-Time provisioning is disabled'));
                    } else {
                        // Provision the user
                        /** @var User $user */
                        $user = $app->userFactory->create();
                        $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);

                        if (isset($userData["UserName"])) {
                            $user->userName = $userData["UserName"][0];
                        }

                        if (isset($userData["email"])) {
                            $user->email = $userData["email"][0];
                        }

                        if (isset($userData["usertypeid"])) {
                            $user->userTypeId = $userData["usertypeid"][0];
                        } else {
                            $user->userTypeId = 3;
                        }

                        // Xibo requires a password, generate a random one (it won't ever be used by SAML)
                        $password = Random::generateString(20);
                        $user->setNewPassword($password);

                        // Home page
                        if (isset($samlSettings['workflow']['homePage'])) {
                            $user->homePageId = $app->pageFactory->getByName($samlSettings['workflow']['homePage'])->pageId;
                        } else {
                            $user->homePageId = $app->pageFactory->getByName('dashboard')->pageId;
                        }

                        // Library Quota
                        if (isset($samlSettings['workflow']['libraryQuota'])) {
                            $user->libraryQuota = $samlSettings['workflow']['libraryQuota'];
                        } else {
                            $user->libraryQuota = 0;
                        }

                        // Match references
                        if (isset($samlSettings['workflow']['ref1']) && isset($userData['ref1'])) {
                            $user->ref1 = $userData['ref1'];
                        }

                        if (isset($samlSettings['workflow']['ref2']) && isset($userData['ref2'])) {
                            $user->ref2 = $userData['ref2'];
                        }

                        if (isset($samlSettings['workflow']['ref3']) && isset($userData['ref3'])) {
                            $user->ref3 = $userData['ref3'];
                        }

                        if (isset($samlSettings['workflow']['ref4']) && isset($userData['ref4'])) {
                            $user->ref4 = $userData['ref4'];
                        }

                        if (isset($samlSettings['workflow']['ref5']) && isset($userData['ref5'])) {
                            $user->ref5 = $userData['ref5'];
                        }

                        // Save the user
                        $user->save();

                        // Assign the initial group
                        if (isset($samlSettings['workflow']['group'])) {
                            $group = $app->userGroupFactory->getByName($samlSettings['workflow']['group']);
                        } else {
                            $group = $app->userGroupFactory->getByName('Users');
                        }

                        /** @var \Xibo\Entity\UserGroup $group */
                        $group->assignUser($user);
                        $group->save(['validate' => false]);

                        // Audit Log
                        $this->app->logService->audit('User', $user->userId, 'User created with SAML workflow', [
                            'UserName' => $user->userName,
                            'IPAddress' => $this->app->request()->getIp(),
                            'UserAgent' => $this->app->request()->getUserAgent()
                        ]);
                    }
                }

                if (isset($user) && $user->userId > 0) {
                    // Load User
                    $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);
                    $user->load();

                    // Overwrite our stored user with this new object.
                    $this->app->user = $user;

                    // Set the user factory ACL dependencies (used for working out intra-user permissions)
                    $app->userFactory->setAclDependencies($user, $app->userFactory);

                    // Switch Session ID's
                    $this->app->session->setIsExpired(0);
                    $this->app->session->regenerateSessionId();
                    $this->app->session->setUser($user->userId);

                    // Audit Log
                    // Set the userId on the log object
                    $this->app->logService->setUserId($user->userId);
                    $this->app->logService->audit('User', $user->userId, 'Login Granted via SAML', [
                        'IPAddress' => $this->app->request()->getIp(),
                        'UserAgent' => $this->app->request()->getUserAgent()
                    ]);
                }

                // Redirect to User Homepage
                $page = $app->pageFactory->getById($user->homePageId);
                $this->app->redirectTo($page->getName() . '.view');
            }
        });

        $app->get('/saml/sls', function () use ($app) {
            // Single Logout Service

            // Inject the GET parameters required by the SAML toolkit
            $_GET = $this->app->request->get();

            $auth = new Auth($this->app->configService->samlSettings);
            $auth->processSLO(false, null, false, function() use ($app) {
                // Grab a login controller
                /** @var \Xibo\Controller\Login $loginController */
                $loginController = $app->container->get('\Xibo\Controller\Login');
                $loginController->logout(false);
            });

            $errors = $auth->getErrors();

            if (empty($errors)) {
                $this->app->redirect($this->app->urlFor('logout'));
            } else {
                throw new AccessDeniedException("SLO failed. ".implode(', ', $errors));
            }
        });

        // Create a function which we will call should the request be for a protected page
        // and the user not yet be logged in.
        $redirectToLogin = function () use ($app) {

            if ($app->request->isAjax()) {
                $state = $app->state;
                /* @var ApplicationState $state */
                // Return a JSON response which tells the App to redirect to the login page
                $app->response()->header('Content-Type', 'application/json');
                $state->Login();
                echo $state->asJson();
                $app->stop();
            }
            else {
                // Initiate SAML SSO
                $auth = new Auth($this->app->configService->samlSettings);
                $auth->login();
            }
        };

        // Define a callable to check the route requested in before.dispatch
        $isAuthorised = function () use ($app, $redirectToLogin) {
            /** @var \Xibo\Entity\User $user */
            $user = $app->user;

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Pass the page factory into the user object, so that it can check its page permissions
            $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);

            // Check to see if this is a public resource (there are only a few, so we have them in an array)
            if (!in_array($resource, $app->publicRoutes) && !in_array($resource, SAMLAuthentication::samlRoutes())) {
                $app->public = false;
                // Need to check
                if ($user->hasIdentity() && !$app->session->isExpired()) {
                    // Replace our user with a fully loaded one
                    $user = $app->userFactory->getById($user->userId);

                    // Pass the page factory into the user object, so that it can check its page permissions
                    $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);

                    // Load the user
                    $user->load();

                    // Configure the log service with the logged in user id
                    $app->logService->setUserId($user->userId);

                    // Do they have permission?
                    $user->routeAuthentication($resource);

                    // We are authenticated, override with the populated user object
                    $app->user = $user;
                }
                else {
                    // Store the current route so we can come back to it after login
                    $app->flash('priorRoute', $app->request()->getRootUri() . $app->request()->getResourceUri());

                    $redirectToLogin();
                }
            }
            else {
                $app->public = true;
                // If we are expired and come from ping/clock, then we redirect
                if ($app->session->isExpired() && ($resource == '/login/ping' || $resource == 'clock')) {
                    $redirectToLogin();
                } else if ($resource == '/login') {
                    //Force SAML SSO
                    $redirectToLogin();
                }
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);

        $this->next->call();
    }
}