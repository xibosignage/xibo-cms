<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (CsrfGuard.php) is part of Xibo.
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
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\TokenExpiredException;
use Xibo\Helper\Theme;

class CsrfGuard extends Middleware
{
    /**
     * CSRF token key name.
     *
     * @var string
     */
    protected $key;

    /**
     * Constructor.
     *
     * @param string    $key        The CSRF token key name.
     */
    public function __construct($key = 'csrfToken')
    {
        if (! is_string($key) || empty($key) || preg_match('/[^a-zA-Z0-9\-\_]/', $key)) {
            throw new \OutOfBoundsException('Invalid CSRF token key "' . $key . '"');
        }

        $this->key = $key;
    }

    /**
     * Call middleware.
     *
     * @return void
     */
    public function call()
    {
        // Attach as hook.
        $this->app->hook('slim.before', array($this, 'check'));

        // Call next middleware.
        $this->next->call();
    }

    /**
     * Check CSRF token is valid.
     */
    public function check() {
        // Check sessions are enabled.
        if (session_id() === '') {
            throw new \Exception('Sessions are required to use the CSRF Guard middleware.');
        }

        if (! isset($_SESSION[$this->key])) {
            $_SESSION[$this->key] = sha1(serialize($_SERVER) . rand(0, 0xffffffff));
        }

        $token = $_SESSION[$this->key];

        // Validate the CSRF token.
        if (in_array($this->app->request()->getMethod(), array('POST', 'PUT', 'DELETE'))) {
            if (!in_array($this->app->request()->getPath(), SAMLAuthentication::samlRoutes())) {
                $userToken = $this->app->request()->headers('X-XSRF-TOKEN');
                if ($userToken == '') {
                    $userToken = $this->app->request()->params($this->key);
                }

                if ($token !== $userToken) {
                    throw new TokenExpiredException('Sorry the form has expired. Please refresh.');
                }
            }
        }

        // Assign CSRF token key and value to view.
        $this->app->view()->appendData(array(
            'csrfKey'=> $this->key,
            'csrfToken' => $token
        ));
    }
}