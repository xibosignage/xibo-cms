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

use Nyholm\Psr7\Factory\Psr17Factory;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Http\Response as SlimResponse;
use Slim\Http\ServerRequest as SlimRequest;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
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
class SAMLAuthentication implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public static function samlRoutes()
    {
        return [
            '/saml/metadata',
            '/saml/login',
            '/saml/acs',
            '/saml/logout',
            '/saml/sls'
        ];
    }

    public function samlLogout(SlimRequest $request, SlimResponse $response)
    {
        $container = $this->app->getContainer();
        $routeParser = $this->app->getRouteCollector()->getRouteParser();

        if (isset($container->get('configService')->samlSettings['workflow']) &&
          isset($container->get('configService')->samlSettings['workflow']['slo']) &&
            $container->get('configService')->samlSettings['workflow']['slo'] == true) {
            // Initiate SAML SLO
            $auth = new Auth($container->get('configService')->samlSettings);
            $auth->logout();
        } else {
            return $response->withRedirect($routeParser->urlFor('logout'));
        }
    }

    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws NotFoundException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        $container = $app->getContainer();

        /** @var User $user */
        $user = $container->get('userFactory')->create();
        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();

        // Register SAML routes.
        $request = $request->withAttribute('excludedCsrfRoutes', SAMLAuthentication::samlRoutes());
        $app->logoutRoute = 'saml.logout';

        $app->get('/saml/metadata', function (SlimRequest $request, SlimResponse $response) {
            $settings = new Settings($this->app->getContainer()->get('configService')->samlSettings, true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                $response = $response->withHeader('Content-Type', 'text/xml')
                                     ->write($metadata);

                return $response;
            } else {
                throw new ConfigurationException(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    Error::METADATA_SP_INVALID
                );
            }
        });

        $app->get('/saml/login', function (SlimRequest $request, SlimResponse $response) {
            // Initiate SAML SSO
            $auth = new Auth($this->app->getContainer()->get('configService')->samlSettings);
            $auth->login();
        });

        $app->get('/saml/logout', function (SlimRequest $request, SlimResponse $response) {
            $this->samlLogout($request,  $response);
        })->setName('saml.logout');

        $app->post('/saml/acs', function (SlimRequest $request, SlimResponse $response) {
            // Assertion Consumer Endpoint
            $app = $this->app;

            // Log some interesting things
            $app->getContainer()->get('logger')->debug('Arrived at the ACS route with own URL: ' . Utils::getSelfRoutedURLNoQuery());

            // Inject the POST parameters required by the SAML toolkit
            //$_POST = $this->app->request->post();
            $_POST = $request->getParams();
            // Pull out the SAML settings
            $samlSettings = $this->app->getContainer()->get('configService')->samlSettings;

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
                        throw new AccessDeniedException(sprintf(__('%s not retrieved from the IdP and required since is the field to identify the user'), $identityField));
                    }
                }

                // Try and get the user record.
                $user = null;

                try {
                    switch ($identityField) {
                        case 'nameId':
                            $user = $app->getContainer()->get('userFactory')->getByName($userData[$identityField]);
                            break;

                        case 'UserId':
                            $user = $app->getContainer()->get('userFactory')->getById($userData[$identityField][0]);
                            break;

                        case 'UserName':
                            $user = $app->getContainer()->get('userFactory')->getByName($userData[$identityField][0]);
                            break;

                        case 'email':
                            $user = $app->getContainer()->get('userFactory')->getByEmail($userData[$identityField][0]);
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
                        $user = $app->getContainer()->get('userFactory')->create();
                        $user->setChildAclDependencies($app->getContainer()->get('userGroupFactory'), $app->getContainer()->get('pageFactory'));

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
                            $user->homePageId = $app->getContainer()->get('pageFactory')->getByName($samlSettings['workflow']['homePage'])->pageId;
                        } else {
                            $user->homePageId = $app->getContainer()->get('pageFactory')->getByName('dashboard')->pageId;
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
                            $group = $app->getContainer()->get('userGroupFactory')->getByName($samlSettings['workflow']['group']);
                        } else {
                            $group = $app->getContainer()->get('userGroupFactory')->getByName('Users');
                        }

                        /** @var \Xibo\Entity\UserGroup $group */
                        $group->assignUser($user);
                        $group->save(['validate' => false]);

                        // Audit Log
                        $app->getContainer()->get('logger')->audit('User', $user->userId, 'User created with SAML workflow', [
                            'UserName' => $user->userName,
                            'IPAddress' => $request->getAttribute('ip_address'),
                            'UserAgent' => $request->getHeader('User-Agent')
                        ]);
                    }
                }

                if (isset($user) && $user->userId > 0) {
                    // Load User
                    $user->setChildAclDependencies($app->getContainer()->get('userGroupFactory'), $app->getContainer()->get('pageFactory'));
                    $user->load();

                    // Overwrite our stored user with this new object.
                    $app->getContainer()->set('user', $user);

                    // Set the user factory ACL dependencies (used for working out intra-user permissions)
                    $app->getContainer()->get('userFactory')->setAclDependencies($user, $app->getContainer()->get('userFactory'));

                    // Switch Session ID's
                    $app->getContainer()->get('session')->setIsExpired(0);
                    $app->getContainer()->get('session')->regenerateSessionId();
                    $app->getContainer()->get('session')->setUser($user->userId);

                    // Audit Log
                    // Set the userId on the log object
                    $app->getContainer()->get('logService')->setUserId($user->userId);
                    $app->getContainer()->get('logService')->audit('User', $user->userId, 'Login Granted via SAML', [
                        'IPAddress' => $request->getAttribute('ip_address'),
                        'UserAgent' => $request->getHeader('User-Agent')
                    ]);
                }

                // Redirect to User Homepage
                /** @var \Xibo\Entity\Page $page */
                $page = $app->getContainer()->get('pageFactory')->getById($user->homePageId);
                $routeParser = $app->getRouteCollector()->getRouteParser();

                return $response->withRedirect($routeParser->urlFor($page->getName() . '.view'));
            }
        });

        $app->get('/saml/sls', function (SlimRequest $request, SlimResponse $response) use ($app) {
            // Single Logout Service

            // Inject the GET parameters required by the SAML toolkit
            $_GET = $request->getQueryParams();
            $routeParser = $app->getRouteCollector()->getRouteParser();

            $auth = new Auth( $app->getContainer()->get('configService')->samlSettings);
            $auth->processSLO(false, null, false, function() use ($app, $request, $response) {
                // Grab a login controller
                /** @var \Xibo\Controller\Login $loginController */
                $loginController = $app->getContainer()->get('\Xibo\Controller\Login');
                $loginController->logout($request, $response);
            });

            $errors = $auth->getErrors();

            if (empty($errors)) {
                return $response->withRedirect($routeParser->urlFor('logout'));
            } else {
                throw new AccessDeniedException("SLO failed. ".implode(', ', $errors));
            }
        });

        // Create a function which we will call should the request be for a protected page
        // and the user not yet be logged in.
        $redirectToLogin = function (Request $request, SlimResponse $response) use ($app) {

            if ($this->isAjax($request)) {
                /* @var ApplicationState $state */
                $state = $app->getContainer()->get('state');
                /* @var ApplicationState $state */
                // Return a JSON response which tells the App to redirect to the login page
                $response = $response->withHeader('Content-Type', 'application/json');
                $state->Login();
                return $response->withJson($state->asArray());
            }
            else {
                // Initiate SAML SSO
                $auth = new Auth($app->getContainer()->get('configService')->samlSettings);
                return $auth->login();
            }
        };

        if (!in_array($resource, $request->getAttribute('publicRoutes', [])) && !in_array($resource, $request->getAttribute('excludedCsrfRoutes', [])) ) {

            $request = $request->withAttribute('public', false);

            // Need to check
            if ($user->hasIdentity() && $container->get('session')->isExpired() == 0) {

                // Replace our user with a fully loaded one
                $user = $container->get('userFactory')->getById($user->userId);

                // Pass the page factory into the user object, so that it can check its page permissions
                $user->setChildAclDependencies($container->get('userGroupFactory'), $container->get('pageFactory'));

                // Load the user
                $user->load(false);

                // Configure the log service with the logged in user id
                $container->get('logService')->setUserId($user->userId);

                // Do they have permission?
                $user->routeAuthentication($resource);

                // We are authenticated, override with the populated user object
                $container->set('user', $user);

                $request = $request->withAttribute('name', 'web');

                return $handler->handle($request);
            } else {
                $app->getContainer()->get('flash')->addMessage('priorRoute', $resource);

                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                return $redirectToLogin($request, $response);
            }
        } else {
            $request = $request->withAttribute('public', true);

            // If we are expired and come from ping/clock, then we redirect
            if ( ( $container->get('session')->isExpired() && ($resource == '/login/ping' || $resource == 'clock') ) || $resource == '/login' ) {
                $app->getContainer()->get('logger')->debug('should redirect to login , resource is ' . $resource);

                $nyholmFactory = new Psr17Factory();
                $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
                $response = $decoratedResponseFactory->createResponse();

                return $redirectToLogin($request, $response);
            }
        }

       return $handler->handle($request);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}