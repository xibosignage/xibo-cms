<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Actions.php) is part of Xibo.
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
use Xibo\Helper\Theme;

class Actions extends Middleware
{
    public function call()
    {
        // Process Actions
        if (\Config::GetSetting('DEFAULTS_IMPORTED') == 0) {

            //$layout = new Layout();
            //$layout->importFolder('theme' . DIRECTORY_SEPARATOR . Theme::ThemeFolder() . DIRECTORY_SEPARATOR . 'layouts');

            \Config::ChangeSetting('DEFAULTS_IMPORTED', 1);
        }

        // Process notifications
        if ($this->app->user->userTypeId == 1 && file_exists('install.php')) {
            Theme::Set('notifications', array(__('There is a problem with this installation. "install.php" should be deleted.')));
        }

        $this->next->call();
    }
}