<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Random;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SAMLAuthentication
 * @package Xibo\Middleware
 *
 * Provide SAML authentication to Xibo configured via settings.php.
 */
class SAMLAuthentication extends AuthenticationBase
{
    /**
     * @return $this
     */
    public function addRoutes()
    {
        $app = $this->app;
        $app->getContainer()->logoutRoute = 'saml.logout';

        // Route providing SAML metadata
        $app->get('/saml/metadata', function (\Slim\Http\ServerRequest $request, \Slim\Http\Response $response) {
            $settings = new Settings($this->getConfig()->samlSettings, true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                return $response
                    ->withHeader('Content-Type', 'text/xml')
                    ->write($metadata);
            } else {
                throw new ConfigurationException(
                    'Invalid SP metadata: ' . implode(', ', $errors),
                    Error::METADATA_SP_INVALID
                );
            }
        });

        // SAML Login
        $app->get('/saml/login', function (\Slim\Http\ServerRequest $request, \Slim\Http\Response $response) {
            // Initiate SAML SSO
            $auth = new Auth($this->getConfig()->samlSettings);
            return $auth->login();
        });

        // SAML Logout
        $app->get('/saml/logout', function (\Slim\Http\ServerRequest $request, \Slim\Http\Response $response) {
            return $this->samlLogout($request, $response);
        })->setName('saml.logout');

        // SAML Assertion Consumer Endpoint
        $app->post('/saml/acs', function (\Slim\Http\ServerRequest $request, \Slim\Http\Response $response) {
            // Log some interesting things
            $this->getLog()->debug('Arrived at the ACS route with own URL: ' . Utils::getSelfRoutedURLNoQuery());

            // Pull out the SAML settings
            $samlSettings = $this->getConfig()->samlSettings;
            $auth = new Auth($samlSettings);
            $auth->processResponse();

            // Check for errors
            $errors = $auth->getErrors();

            if (!empty($errors)) {
                $this->getLog()->error('Single Sign on Failed: ' . implode(', ', $errors)
                    . '. Last Reason: ' . $auth->getLastErrorReason());

                throw new AccessDeniedException(__('Your authentication provider could not log you in.'));
            } else {
                // Pull out the SAML attributes
                $samlAttrs = $auth->getAttributes();

                $this->getLog()->debug('SAML attributes: ' . json_encode($samlAttrs));

                // How should we look up the user?
                $identityField = (isset($samlSettings['workflow']['field_to_identify']))
                    ? $samlSettings['workflow']['field_to_identify']
                    : 'UserName';

                if ($identityField !== 'nameId' && empty($samlAttrs)) {
                    // We will need some attributes
                    throw new AccessDeniedException(__('No attributes retrieved from the IdP'));
                }

                // If appropriate convert the SAML Attributes into userData mapped against the workflow mappings.
                $userData = [];
                if (isset($samlSettings['workflow']) && isset($samlSettings['workflow']['mapping'])) {
                    foreach ($samlSettings['workflow']['mapping'] as $key => $value) {
                        if (!empty($value) && isset($samlAttrs[$value])) {
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
                        throw new AccessDeniedException(sprintf(__('%s not retrieved from the IdP and required since is the field to identify the user'), $identityField));
                    }
                }

                // Try and get the user record.
                $user = null;

                try {
                    switch ($identityField) {
                        case 'nameId':
                            $user = $this->getUserFactory()->getByName($userData[$identityField]);
                            break;

                        case 'UserID':
                            $user = $this->getUserFactory()->getById($userData[$identityField][0]);
                            break;

                        case 'UserName':
                            $user = $this->getUserFactory()->getByName($userData[$identityField][0]);
                            break;

                        case 'email':
                            $user = $this->getUserFactory()->getByEmail($userData[$identityField][0]);
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
                        $user = $this->getEmptyUser();

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
                            $user->homePageId = $this->getUserGroupFactory()->getHomepageByName($samlSettings['workflow']['homePage']);
                        } else {
                            $user->homePageId = 'icondashboard.view';
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
                            $group = $this->getUserGroupFactory()->getByName($samlSettings['workflow']['group']);
                        } else {
                            $group = $this->getUserGroupFactory()->getByName('Users');
                        }

                        $group->assignUser($user);
                        $group->save(['validate' => false]);

                        // Audit Log
                        $this->getLog()->audit('User', $user->userId, 'User created with SAML workflow', [
                            'UserName' => $user->userName,
                            'IPAddress' => $request->getAttribute('ip_address'),
                            'UserAgent' => $request->getHeader('User-Agent')
                        ]);
                    }
                }

                if (isset($user) && $user->userId > 0) {
                    // Load User
                    $this->getUser($user->userId);

                    // Overwrite our stored user with this new object.
                    $this->setUserForRequest($user);

                    // Switch Session ID's
                    $this->getSession()->setIsExpired(0);
                    $this->getSession()->regenerateSessionId();
                    $this->getSession()->setUser($user->userId);

                    // Audit Log
                    $this->getLog()->audit('User', $user->userId, 'Login Granted via SAML', [
                        'IPAddress' => $request->getAttribute('ip_address'),
                        'UserAgent' => $request->getHeader('User-Agent')
                    ]);
                }

                // Redirect back to the originally-requested url
                $params =  $request->getParams();
                $redirect = $params['RelayState'] ?? $this->getRouteParser()->urlFor('home');

                return $response->withRedirect($redirect);
            }
        });

        // Single Logout Service
        $app->get('/saml/sls', function (\Slim\Http\ServerRequest $request, \Slim\Http\Response $response) use ($app) {

            $auth = new Auth( $app->getContainer()->get('configService')->samlSettings);
            $auth->processSLO(false, null, false, function() use ($app, $request, $response) {
                // Grab a login controller
                /** @var \Xibo\Controller\Login $loginController */
                $loginController = $app->getContainer()->get('\Xibo\Controller\Login');
                $loginController->logout($request, $response);
            });

            $errors = $auth->getErrors();

            if (empty($errors)) {
                return $response->withRedirect($this->getRouteParser()->urlFor('logout'));
            } else {
                throw new AccessDeniedException("SLO failed. " . implode(', ', $errors));
            }
        });

        return $this;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     * @throws \OneLogin\Saml2\Error
     */
    public function samlLogout(Request $request, Response $response)
    {
        $samlSettings = $this->getConfig()->samlSettings;

        if (isset($samlSettings['workflow'])
            && isset($samlSettings['workflow']['slo'])
            && $samlSettings['workflow']['slo'] == true
        ) {
            // Initiate SAML SLO
            $auth = new Auth($samlSettings);
            return $response->withRedirect($auth->logout());
        } else {
            return $response->withRedirect($this->getRouteParser()->urlFor('logout'));
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \OneLogin\Saml2\Error
     */
    public function redirectToLogin(Request $request)
    {
        if ($this->isAjax($request)) {
            return $this->createResponse()->withJson(ApplicationState::asRequiresLogin());
        } else {
            // Initiate SAML SSO
            $auth = new Auth($this->getConfig()->samlSettings);
            return $this->createResponse()->withRedirect($auth->login());
        }
    }

    /** @inheritDoc */
    public function getPublicRoutes(Request $request)
    {
        return array_merge($request->getAttribute('publicRoutes', []), [
            '/saml/metadata',
            '/saml/login',
            '/saml/acs',
            '/saml/logout',
            '/saml/sls'
        ]);
    }

    /** @inheritDoc */
    public function shouldRedirectPublicRoute($route)
    {
        return ($this->getSession()->isExpired()
                && ($route == '/login/ping' || $route == 'clock'))
            || $route == '/login';
    }

    /** @inheritDoc */
    public function addToRequest(Request $request)
    {
        return $request->withAttribute('excludedCsrfRoutes', array_merge($request->getAttribute('excludedCsrfRoutes', []), ['/saml/acs']));
    }
}