<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\LogoutTrait;
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
    use LogoutTrait;
    /**
     * @return $this
     */
    public function addRoutes()
    {
        $app = $this->app;
        $app->getContainer()->set('logoutRoute', 'saml.logout');

        // Route providing SAML metadata
        $app->get('/saml/metadata', function (Request $request, Response $response) {
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
        $app->get('/saml/login', function (Request $request, Response $response) {
            // Initiate SAML SSO
            $auth = new Auth($this->getConfig()->samlSettings);
            return $auth->login();
        });

        // SAML Logout
        $app->get('/saml/logout', function (Request $request, Response $response) {
            return $this->samlLogout($request, $response);
        })->setName('saml.logout');

        // SAML Assertion Consumer Endpoint
        $app->post('/saml/acs', function (Request $request, Response $response) {
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

                // Are we going to try and match our Xibo groups to our Idp groups?
                $isMatchGroupFromIdp = ($samlSettings['workflow']['matchGroups']['enabled'] ?? false) === true
                    && ($samlSettings['workflow']['matchGroups']['attribute'] ?? null) !== null;

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
                        $user->homeFolderId = 1;

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
                            try {
                                $user->homePageId = $this->getUserGroupFactory()->getHomepageByName(
                                    $samlSettings['workflow']['homePage']
                                )->homepage;
                            } catch (NotFoundException $exception) {
                                $this->getLog()->info(
                                    sprintf(
                                        'Provided homepage %s, does not exist,
                                         setting the icondashboard.view as homepage',
                                        $samlSettings['workflow']['homePage']
                                    )
                                );
                                $user->homePageId = 'icondashboard.view';
                            }
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
                        if (isset($samlSettings['workflow']['group']) && !$isMatchGroupFromIdp) {
                            $group = $this->getUserGroupFactory()->getByName($samlSettings['workflow']['group']);
                        } else {
                            $group = $this->getUserGroupFactory()->getByName('Users');
                        }

                        $group->assignUser($user);
                        $group->save(['validate' => false]);
                        $this->getLog()->setIpAddress($request->getAttribute('ip_address'));

                        // Audit Log
                        $this->getLog()->audit('User', $user->userId, 'User created with SAML workflow', [
                            'UserName' => $user->userName,
                            'UserAgent' => $request->getHeader('User-Agent')
                        ]);
                    }
                }

                if (isset($user) && $user->userId > 0) {
                    // Load User
                    $user = $this->getUser(
                        $user->userId,
                        $request->getAttribute('ip_address'),
                        $this->getSession()->get('sessionHistoryId')
                    );

                    // Overwrite our stored user with this new object.
                    $this->setUserForRequest($user);

                    // Switch Session ID's
                    $this->getSession()->setIsExpired(0);
                    $this->getSession()->regenerateSessionId();
                    $this->getSession()->setUser($user->userId);

                    $user->touch();

                    // Audit Log
                    $this->getLog()->audit('User', $user->userId, 'Login Granted via SAML', [
                        'UserAgent' => $request->getHeader('User-Agent')
                    ]);
                }

                // Match groups from IdP?
                if ($isMatchGroupFromIdp) {
                    $this->getLog()->debug('group matching enabled');

                    // Match groups is enabled, and we have an attribute to get groups from.
                    $idpGroups = [];
                    $extractionRegEx = $samlSettings['workflow']['matchGroups']['extractionRegEx'] ?? null;

                    // Get groups.
                    foreach ($samlAttrs[$samlSettings['workflow']['matchGroups']['attribute']] as $groupAttr) {
                        // Regex?
                        if (!empty($extractionRegEx)) {
                            $matches = [];
                            preg_match_all($extractionRegEx, $groupAttr, $matches);

                            if (count($matches[1]) > 0) {
                                $groupAttr = $matches[1][0];
                            }
                        }

                        $this->getLog()->debug('checking for group ' . $groupAttr);

                        // Does this group exist?
                        try {
                            $idpGroups[$groupAttr] = $this->getUserGroupFactory()->getByName($groupAttr);
                        } catch (NotFoundException) {
                            $this->getLog()->debug('group ' . $groupAttr . ' does not exist');
                        }
                    }

                    // Go through the users groups
                    $usersGroups = [];
                    foreach ($user->groups as $userGroup) {
                        $usersGroups[$userGroup->group] = $userGroup;
                    }

                    foreach ($user->groups as $userGroup) {
                        // Does this group exist in the Idp? If not, remove.
                        if (!array_key_exists($userGroup->group, $idpGroups)) {
                            // Group exists in Xibo, does not exist in the response, so remove.
                            $userGroup->unassignUser($user);
                            $userGroup->save(['validate' => false]);

                            $this->getLog()->debug($userGroup->group
                                . ' not matched to any IdP groups linked, removing');

                            unset($usersGroups[$userGroup->group]);
                        } else {
                            // Matched, so remove from idpGroups
                            unset($idpGroups[$userGroup->group]);

                            $this->getLog()->debug($userGroup->group . ' already linked.');
                        }
                    }

                    // Go through remaining groups and assign the user to them.
                    foreach ($idpGroups as $idpGroup) {
                        $this->getLog()->debug($idpGroup->group . ' already linked.');

                        $idpGroup->assignUser($user);
                        $idpGroup->save(['validate' => false]);
                    }

                    // Does this user still not have any groups?
                    if (count($usersGroups) <= 0) {
                        $group = $this->getUserGroupFactory()->getByName($samlSettings['workflow']['group'] ?? 'Users');
                        $group->assignUser($user);
                        $group->save(['validate' => false]);
                    }
                }

                // Redirect back to the originally-requested url, if provided
                // it is not clear why basename is used here, it seems to be something to do with a logout loop
                $params =  $request->getParams();
                $relayState = $params['RelayState'] ?? null;
                $redirect = empty($relayState) || basename($relayState) === 'login'
                    ? $this->getRouteParser()->urlFor('home')
                    : $relayState;

                $this->getLog()->debug('redirecting to ' . $redirect);

                return $response->withRedirect($redirect);
            }
        });

        // Single Logout Service
        $app->map(['GET', 'POST'], '/saml/sls', function (Request $request, Response $response) use ($app) {
            // Make request to IDP
            $auth = new Auth($app->getContainer()->get('configService')->samlSettings);
            try {
                $auth->processSLO(false, null, false, function () use ($request) {
                    // Audit that the IDP has completed this request.
                    $this->getLog()->setIpAddress($request->getAttribute('ip_address'));
                    $this->getLog()->setSessionHistoryId($this->getSession()->get('sessionHistoryId'));
                    $this->getLog()->audit('User', 0, 'Idp SLO completed', [
                        'UserAgent' => $request->getHeader('User-Agent')
                    ]);
                });
            } catch (\Exception $e) {
                // Ignored - get with getErrors()
            }

            $errors = $auth->getErrors();

            if (empty($errors)) {
                return $response->withRedirect($this->getRouteParser()->urlFor('home'));
            } else {
                throw new AccessDeniedException('SLO failed. ' . implode(', ', $errors));
            }
        });

        return $this;
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
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
            // Complete our own logout flow
            $this->completeLogoutFlow(
                $this->getUser(
                    $_SESSION['userid'],
                    $request->getAttribute('ip_address'),
                    $_SESSION['sessionHistoryId']
                ),
                $this->getSession(),
                $this->getLog(),
                $request
            );

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
    public function redirectToLogin(\Psr\Http\Message\ServerRequestInterface $request)
    {
        if ($this->isAjax($request)) {
            return $this->createResponse($request)->withJson(ApplicationState::asRequiresLogin());
        } else {
            // Initiate SAML SSO
            $auth = new Auth($this->getConfig()->samlSettings);
            return $this->createResponse($request)->withRedirect($auth->login());
        }
    }

    /** @inheritDoc */
    public function getPublicRoutes(\Psr\Http\Message\ServerRequestInterface $request)
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
    public function addToRequest(\Psr\Http\Message\ServerRequestInterface $request)
    {
        return $request->withAttribute(
            'excludedCsrfRoutes',
            array_merge($request->getAttribute('excludedCsrfRoutes', []), ['/saml/acs', '/saml/sls'])
        );
    }
}
