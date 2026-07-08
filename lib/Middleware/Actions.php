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
        if (!$this->isAjax($request) && $user->isPasswordChangeRequired == 1) {
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
