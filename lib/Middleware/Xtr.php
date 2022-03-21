<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2020 Spring Signage Ltd
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

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;

/**
 * Class Xtr
 *  Middleware for XTR.
 *   - sets the theme
 *   - sets the module theme files
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
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws \Twig\Error\LoaderError
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->app;
        $container = $app->getContainer();

        $container->get('configService')->loadTheme();
        $view = $container->get('view');
        // Provide the view path to Twig
        /* @var \Twig\Loader\FilesystemLoader $twig */
        $twig = $view->getLoader();
        $twig->setPaths([PROJECT_ROOT . '/views', PROJECT_ROOT . '/custom', PROJECT_ROOT . '/reports']);

        // Does this theme provide an alternative view path?
        if ($container->get('configService')->getThemeConfig('view_path') != '') {
            $twig->prependPath(Str::replaceFirst('..', PROJECT_ROOT, $container->get('configService')->getThemeConfig('view_path')));
        }

        // Call Next
        return $handler->handle($request);
    }
}