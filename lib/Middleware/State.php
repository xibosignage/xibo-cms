<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (State.php) is part of Xibo.
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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Log;

class State extends Middleware
{
    public function call()
    {
        // Inject
        // The state of the application response
        $this->app->container->singleton('state', function() { return new ApplicationState(); });

        // Create a session
        $this->app->container->singleton('session', function() { return new \Session(); });
        $this->app->session->Get('nothing');

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() { Log::debug('called'); });

        // Next middleware
        $this->next->call();
    }
}