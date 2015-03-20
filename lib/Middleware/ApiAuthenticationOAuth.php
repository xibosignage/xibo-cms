<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiAuthenticationOAuth.php) is part of Xibo.
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


use League\OAuth2\Server\Exception\OAuthException;
use League\OAuth2\Server\ResourceServer;
use Slim\Middleware;

class ApiAuthenticationOAuth extends Middleware
{
    /**
     * @var ResourceServer $server
     */
    private $server;

    public function ApiOAuth($server)
    {
        $this->server = $server;
    }

    public function call()
    {
        try {
            $this->server->isValidRequest();

            // Call the next middleware
            $this->next->call();
        }
        catch (OAuthException $e) {
            $response = $this->app->response();

            $response->setBody(json_encode(array(
                'error' =>  $e->getMessage()
            )));
            $response->status(403);

            foreach ($e->getHttpHeaders() as $header) {
                $response->headers($header);
            }
        }
    }
}