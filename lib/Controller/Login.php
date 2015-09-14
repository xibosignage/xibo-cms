<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Login.php) is part of Xibo.
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
namespace Xibo\Controller;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class Login extends Base
{
    /**
     * Output a login form
     */
    public function loginForm()
    {
        // Template
        $this->getState()->template = 'login';
        $this->getState()->setData(['version' => VERSION]);
    }

    /**
     * login
     */
    public function login()
    {
        // Get our username and password
        $username = Sanitize::getUserName('username');
        $password = Sanitize::getPassword('password');

        Log::debug('Login with username %s', $username);

        // Get our user
        try {
            $user = UserFactory::getByName($username);

            // Log::debug($user);

            // Check password
            $user->checkPassword($password);

            // We are authenticated, so upgrade the user to the salted mechanism if necessary
            if (!$user->isSalted()) {
                // TODO: Call User controller to change the password
            }

            // We are logged in!
            $user->loggedIn = 1;

            // Overwrite our stored user with this new object.
            $this->app->user = $user;

            // Switch Session ID's
            $session = $this->getSession();
            $session->setIsExpired(0);
            $session->regenerateSessionId(session_id());
            $session->setUser(session_id(), $user->userId, 'user');
        }
        catch (NotFoundException $e) {
            Log::debug('User not found');
            throw new AccessDeniedException();
        }
    }

    /**
     * Log out
     */
    function logout()
    {
        $this->getUser()->loggedIn = 0;

        // to log out a user we need only to clear out some session vars
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        $session = $this->getSession();
        $session->setIsExpired(1);
        $this->getApp()->redirectTo('login');
    }

    /**
     * User Welcome
     */
    public function userWelcome()
    {
        $this->getState()->template = 'user-welcome-page';
        $this->getState()->setData([
            'help' => [
                'dashboard' => Help::Link('Dashboard', 'General'),
                'display' => Help::Link('Display', 'General'),
                'layout' => Help::Link('Layout', 'General'),
                'schedule' => Help::Link('Schedule', 'General'),
                'windows' => Help::rawLink('install_windows_client')
            ]
        ]);
    }

    /**
     * Ping Pong
     */
    public function PingPong()
    {
        $this->getApp()->session->refreshExpiry = (Sanitize::getCheckbox('refreshSession') == 1);
        $this->getState()->success = true;
    }

    /**
     * Shows information about Xibo
     *
     * @SWG\Get(
     *  path="/about",
     *  operationId="about",
     *  tags={"misc"},
     *  summary="About",
     *  description="Information about this API, such as Version code, etc",
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={
     *              "title"="version",
     *              "type"="string"
     *          }
     *      )
     *  )
     * )
     */
    function about()
    {
        $response = $this->getState();

        if ($this->getApp()->request()->isAjax()) {
            $response->template = 'about-text';
        }
        else {
            $response->template = 'about-page';
        }

        $response->setData(['version' => VERSION]);
    }
}
