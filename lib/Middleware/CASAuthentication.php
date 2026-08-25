<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\LogoutTrait;
use Xibo\Helper\SsoLoginTrait;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CASAuthentication
 * @package Xibo\Middleware
 *
 * Provide CAS authentication to Xibo configured via settings.php.
 */
class CASAuthentication extends AuthenticationBase
{
    use LogoutTrait;
    use SsoLoginTrait;

    /**
     * @return $this
     */
    public function addRoutes()
    {
        $app = $this->app;
        $app->getContainer()->set('logoutRoute', 'cas.logout');

        $app->map(['GET', 'POST'], '/cas/login', function (ServerRequest $request, Response $response) {
            // Initiate CAS SSO
            $this->initCasClient();

            // Login happens here
            \phpCAS::forceAuthentication();

            $username = \phpCAS::getUser();

            try {
                $user = $this->getUserFactory()->getByName($username);
            } catch (NotFoundException $e) {
                throw new AccessDeniedException('Unable to authenticate');
            }

            if ($user->retired === 1) {
                throw new AccessDeniedException('Sorry this account does not exist or cannot be authenticated.');
            }

            if (isset($user) && $user->userId > 0) {
                $user = $this->completeSsoLogin($user, $request, 'CAS');
            }

            return $response->withRedirect($this->getRouteParser()->urlFor('home'));
        })->setName('cas.login');

        // Service for the logout of the user.
        // End the CAS session and the application session
        $app->get('/cas/logout', function (ServerRequest $request, Response $response) {
            // The order is first: local session to destroy, second the cas session
            // because phpCAS::logout() redirects to CAS server
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

            $this->initCasClient();
            \phpCAS::logout();
        })->setName('cas.logout');

        return $this;
    }

    /**
     * Initialise the CAS client
     */
    private function initCasClient()
    {
        $settings = $this->getConfig()->casSettings['config'];
        \phpCAS::client(
            CAS_VERSION_2_0,
            $settings['server'],
            intval($settings['port']),
            $settings['uri'],
            $settings['service_base_url']
        );

        if (!empty($settings['insecure_skip_verify'])) {
            // Explicit opt-in only — bypasses CAS server certificate validation.
            // Intended for local development/testing, never production.
            \phpCAS::setNoCasServerValidation();
        } else {
            \phpCAS::setCasServerCACert($settings['ca_cert'] ?? '/etc/ssl/certs/ca-certificates.crt');
        }
    }

    /** @inheritDoc */
    public function redirectToLogin(Request $request)
    {
        if ($this->isAjax($request)) {
            return $this->createResponse($request)
                ->withJson(ApplicationState::asRequiresLogin());
        } else {
            return $this->createResponse($request)
                ->withRedirect($this->getRouteParser()->urlFor('login'));
        }
    }

    /** @inheritDoc */
    public function getPublicRoutes(Request $request)
    {
        return array_merge($request->getAttribute('publicRoutes', []), [
            '/cas/login',
            '/cas/logout',
        ]);
    }

    /** @inheritDoc */
    public function shouldRedirectPublicRoute($route)
    {
        return $this->getSession()->isExpired() && ($route == '/login/ping' || $route == 'clock');
    }

    /** @inheritDoc */
    public function addToRequest(Request $request)
    {
        return $request->withAttribute(
            'excludedCsrfRoutes',
            array_merge($request->getAttribute('excludedCsrfRoutes', []), ['/cas/login', '/cas/logout'])
        );
    }
}
