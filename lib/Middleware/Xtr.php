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

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Support\Exception\InstanceSuspendedException;

/**
 * Class Xtr
 *  Middleware for XTR.
 *   - sets the theme
 *   - sets the module theme files
 *
 * @package Xibo\Middleware
 */
class Xtr implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param  Request        $request
     * @param  RequestHandler $handler
     * @return Response
     * @throws \Twig\Error\LoaderError
     * @throws \Xibo\Support\Exception\InstanceSuspendedException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->app;
        $container = $app->getContainer();

        // Check to see if the instance has been suspended, if so call the special route
        $instanceSuspended = $container->get('configService')->getSetting('INSTANCE_SUSPENDED');
        if ($instanceSuspended == 'yes' || $instanceSuspended == 'partial') {
            throw new InstanceSuspendedException();
        }

        $container->get('configService')->loadTheme($container->get('brandService'));
        $view = $container->get('view');
        // Provide the view path to Twig
        /* @var \Twig\Loader\FilesystemLoader $twig */
        $twig = $view->getLoader();
        $twig->setPaths([PROJECT_ROOT . '/views', PROJECT_ROOT . '/reports']);

        // Does this theme provide an alternative view path?
        if ($container->get('configService')->getThemeConfig('view_path') != '') {
            $twig->prependPath(
                Str::replaceFirst(
                    '..',
                    PROJECT_ROOT,
                    $container->get('configService')->getThemeConfig('view_path'),
                )
            );
        }

        // Call Next
        return $handler->handle($request);
    }
}
