<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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


use Slim\Middleware;

/**
 * Class Xtr
 *  Middleware for XTR.
 *   - sets the theme
 *   - sets the module theme files
 * @package Xibo\Middleware
 */
class Xtr extends Middleware
{
    public function call()
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->getApplication();

        $app->configService->loadTheme();

        $app->hook('slim.before.dispatch', function() use($app) {
            // Provide the view path to Twig
            $twig = $app->view()->getInstance()->getLoader();
            /* @var \Twig_Loader_Filesystem $twig */

            // Append the module view paths
            $twig->setPaths(array_merge($app->moduleFactory->getViewPaths(), [PROJECT_ROOT . '/views', PROJECT_ROOT . '/reports']));

            // Does this theme provide an alternative view path?
            if ($app->configService->getThemeConfig('view_path') != '') {
                $twig->prependPath(str_replace_first('..', PROJECT_ROOT, $app->configService->getThemeConfig('view_path')));
            }
        });

        // Call Next
        $this->next->call();
    }
}