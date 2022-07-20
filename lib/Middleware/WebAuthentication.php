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

use Psr\Http\Message\ServerRequestInterface as Request;
use Xibo\Helper\ApplicationState;

/**
 * Class WebAuthentication
 * @package Xibo\Middleware
 */
class WebAuthentication extends AuthenticationBase
{
    /** @inheritDoc */
    public function addRoutes()
    {
        return $this;
    }

    /** @inheritDoc */
    public function redirectToLogin(Request $request)
    {
        if ($this->isAjax($request)) {
            return $this->createResponse($request)
                ->withJson(ApplicationState::asRequiresLogin());
        } else {
            return $this->createResponse($request)->withRedirect($this->getRouteParser()->urlFor('login'));
        }
    }

    /** @inheritDoc */
    public function getPublicRoutes(Request $request)
    {
        return $request->getAttribute('publicRoutes', []);
    }

    /** @inheritDoc */
    public function shouldRedirectPublicRoute($route)
    {
        return $this->getSession()->isExpired() && ($route == '/login/ping' || $route == 'clock');
    }

    /** @inheritDoc */
    public function addToRequest(Request $request)
    {
        return $request;
    }
}