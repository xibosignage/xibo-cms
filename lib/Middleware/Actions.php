<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Entity\User;
use Xibo\Entity\UserNotification;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\Environment;

/**
 * Class Actions
 * Web Actions
 * @package Xibo\Middleware
 */
class Actions implements Middleware
{
    /**
     * A request attribute: when true, someone other than the signed-in user is making this request as its user
     * (set by an extension, never in CE). What belongs to that user is then left alone: the request isn't sent
     * to their forced password change, and controllers don't mark their notifications read or change their
     * welcome state.
     */
    public const ON_BEHALF_OF_USER = 'onBehalfOfUser';

    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Do not proceed unless we have completed an upgrade
        if (Environment::migrationPending()) {
            return $handler->handle($request);
        }

        $app = $this->app;
        $container = $app->getContainer();

        // Do we have a user set?
        /** @var User $user */
        $user = $container->get('user');

        // Force a password change by redirecting to the React force-change-password page.
        // The React SPA guards this client-side too, but this catches full-page loads of the
        // remaining server-rendered legacy pages.
        if (!$this->isAjax($request)
            && $user->isPasswordChangeRequired == 1
            && $request->getAttribute(self::ON_BEHALF_OF_USER) !== true
        ) {
            return $handler->handle($request)
                ->withStatus(302)
                ->withHeader(
                    'Location',
                    $container->get('configService')->rootUri() . 'user/force-change-password'
                );
        }

        return $handler->handle($request);
    }

    /**
     * Is the provided request from AJAX
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}
