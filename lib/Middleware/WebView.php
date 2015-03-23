<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WebView.php) is part of Xibo.
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


use Slim\Slim;
use Slim\View;
use Xibo\Helper\Log;

class WebView extends View
{
    public function render($template, $data = NULL)
    {
        // Render type (ajax or otherwise)
        $app = Slim::getInstance();

        // Get the application status
        $state = $this->all()[$template];
        /* @var \Xibo\Helper\ApplicationState $state */

        if ($app->request->isAjax())
            return $state->asJson();
        else
            return $state->html;
    }
}