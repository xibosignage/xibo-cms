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


use Slim\Middleware;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\PageFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
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
        if (isset(Config::$samlSettings['workflow']) &&
          isset(Config::$samlSettings['workflow']['slo']) &&
              Config::$samlSettings['workflow']['slo'] == true) {
            // Initiate SAML SLO
            $auth = new \OneLogin_Saml2_Auth(Config::$samlSettings);
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
        $app->user = new \Xibo\Entity\User();

        // Register SAML routes.
        $app->excludedCsrfRoutes = SAMLAuthentication::samlRoutes();

        $app->get('/saml/metadata', function () {
            $settings = new \OneLogin_Saml2_Settings(Config::$samlSettings, true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                header('Content-Type: text/xml');
                echo $metadata;
            } else {
                throw new \Xibo\Exception\ConfigurationException(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    \OneLogin_Saml2_Error::METADATA_SP_INVALID
                );
            }
        });

        $app->get('/saml/login', function () {
            // Initiate SAML SSO
            $auth = new \OneLogin_Saml2_Auth(Config::$samlSettings);
            $auth->login();
        });

        $app->get('/saml/logout', function () {
            $this->samlLogout();
        });

        $app->post('/saml/acs', function () {
            // Assertion Consumer Endpoint

            // Inject the POST parameters required by the SAML toolkit
            $_POST = $this->app->request->post();

            $auth = new \OneLogin_Saml2_Auth(Config::$samlSettings);
            $auth->processResponse();

            $errors = $auth->getErrors();

            if (!empty($errors)) {
                throw new \OneLogin_Saml2_Error(
                    'SAML SSO failed: '.implode(', ', $errors)
                );
            } else {
                $samlAttrs = $auth->getAttributes();

                if (empty($samlAttrs)) {
                    throw new AccessDeniedException(__('No attributes retrieved from the IdP'));
                }

                $userData = array();
                if (isset(Config::$samlSettings['workflow']) && isset(Config::$samlSettings['workflow']['mapping']) ) {
                    foreach (Config::$samlSettings['workflow']['mapping'] as $key => $value) {
                        if (!empty($value) && isset($samlAttrs[$value]) ) {
                            $userData[$key] = $samlAttrs[$value];
                        }
                    }
                }

                if (empty($userData)) {
                    throw new AccessDeniedException(__('No attributes could be mapped'));
                }

                if (!isset(Config::$samlSettings['workflow']['field_to_identify'])) {
                    $identityField = 'UserName';
                } else {
                    $identityField = Config::$samlSettings['workflow']['field_to_identify'];
                }

                if (!isset($userData[$identityField]) || empty($userData[$identityField])) {
                    throw new AccessDeniedException(__('%s not retrieved from the IdP and required since is the field to identify the user', $identityField));
                }

                if (!in_array($identityField, array('UserID', 'UserName', 'email'))) {
                    throw new AccessDeniedException(__('Invalid field_to_identify value. Review settings.'));
                }

                try {
                    if ($identityField == 'UserID') {
                        $user = UserFactory::getById($userData[$identityField][0]);
                    } else if ($identityField == 'UserName') {
                        $user = UserFactory::getByName($userData[$identityField][0]);
                    } else {
                        $user = UserFactory::getByEmail($userData[$identityField][0]);
                    }
                } catch (NotFoundException $e) {
                    $user = null;
                }

                if (!isset($user)) {
                    if (!isset(Config::$samlSettings['workflow']['jit']) || Config::$samlSettings['workflow']['jit'] == false) {
                        throw new AccessDeniedException(__('User logged at the IdP but the account does not exist in the CMS and Just-In-Time provisioning is disabled'));
                    } else {
                        // Provision the user
                        $user = new \Xibo\Entity\User();
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
                        if (isset(Config::$samlSettings['workflow']['homePage'])) {
                            $user->homePageId = PageFactory::getByName(Config::$samlSettings['workflow']['homePage'])->pageId;
                        } else {
                            $user->homePageId = PageFactory::getByName('dashboard')->pageId;
                        }

                        // Library Quota
                        if (isset(Config::$samlSettings['workflow']['libraryQuota'])) {
                            $user->libraryQuota = Config::$samlSettings['workflow']['libraryQuota'];
                        } else {
                            $user->libraryQuota = 0;
                        }

                        // Save the user
                        $user->save();

                        // Assign the initial group
                        if (isset(Config::$samlSettings['workflow']['group'])) {
                            $group = UserGroupFactory::getByName(Config::$samlSettings['workflow']['group']);
                        } else {
                            $group = UserGroupFactory::getByName('Users');
                        }

                        $group->assignUser($user);
                        $group->save(false);
                    }
                }

                if (isset($user) && $user->userId > 0) {
                    // Load User
                    $user->load();

                    // We are logged in!
                    $user->loggedIn = 1;

                    // Overwrite our stored user with this new object.
                    $this->app->user = $user;

                    // Switch Session ID's
                    $this->app->session->setIsExpired(0);
                    $this->app->session->regenerateSessionId(session_id());
                    $this->app->session->setUser(session_id(), $user->userId, 'user');
                }

                // Redirect to User Homepage
                $page = \Xibo\Factory\PageFactory::getById($user->homePageId);
                $this->app->redirectTo($page->getName() . '.view');
            }
            
        });

        $app->get('/saml/sls', function () {
            // Single Logout Service

            // Inject the GET parameters required by the SAML toolkit
            $_GET = $this->app->request->get();

            $auth = new \OneLogin_Saml2_Auth(Config::$samlSettings);
            $auth->processSLO();
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
            Log::debug('Request to redirect to login. Ajax = %d', $app->request->isAjax());
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
                $auth = new \OneLogin_Saml2_Auth(Config::$samlSettings);
                $auth->login();
            }
        };

        // Define a callable to check the route requested in before.dispatch
        $isAuthorised = function () use ($app, $redirectToLogin) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Check to see if this is a public resource (there are only a few, so we have them in an array)
            if (!in_array($resource, $app->publicRoutes) && !in_array($resource, SAMLAuthentication::samlRoutes())) {
                $app->public = false;
                // Need to check
                if ($user->hasIdentity() && $app->session->isExpired == 0) {
                    // Replace our user with a fully loaded one
                    $user = UserFactory::loadById($user->userId);

                    // Do they have permission?
                    $user->routeAuthentication($resource);

                    $app->user = $user;

                    // We are authenticated
                }
                else {
                    // Store the current route so we can come back to it after login
                    $app->flash('priorRoute', $resource);
                    $app->flash('priorRouteParams', $app->environment['slim.request.query_hash']);

                    $redirectToLogin();
                }
            }
            else {
                $app->public = true;
                // If we are expired and come from ping/clock, then we redirect
                if ($app->session->isExpired == 1 && ($resource == '/login/ping' || $resource == 'clock')) {
                    $redirectToLogin();
                } else if ($resource == '/login') {
                    //Force SAML SSO
                    $redirectToLogin();
                }
            }
        };

        $updateUser = function () use ($app) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            if (!$app->public && $user->hasIdentity()) {
                $user->touch();
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);
        $app->hook('slim.after.dispatch', $updateUser);

        $this->next->call();
    }
}