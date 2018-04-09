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
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Login
 * @package Xibo\Controller
 */
class Login extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     * @param UserFactory $userFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $userFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->session = $session;
        $this->userFactory = $userFactory;
    }

    /**
     * Output a login form
     */
    public function loginForm()
    {
        $this->getLog()->debug($this->getApp()->flashData());
        // Template
        $this->getState()->template = 'login';
        $this->getState()->setData(['version' => Environment::$WEBSITE_VERSION_NAME]);
    }

    /**
     * login
     */
    public function login()
    {
        // Capture the prior route (if there is one)
        $redirect = 'login';
        $priorRoute = ($this->getSanitizer()->getString('priorRoute'));

        try {
            // Get our username and password
            $username = $this->getSanitizer()->getUserName('username');
            $password = $this->getSanitizer()->getPassword('password');

            $this->getLog()->debug('Login with username %s', $username);

            // Get our user
            try {
                /* @var User $user */
                $user = $this->userFactory->getByName($username);

                // Check password
                $user->checkPassword($password);

                $user->touch();

                $this->getLog()->info('%s user logged in.', $user->userName);

                // Set the userId on the log object
                $this->getLog()->setUserId($user->userId);

                // Overwrite our stored user with this new object.
                $this->getApp()->user = $user;

                // Switch Session ID's
                $session = $this->session;
                $session->setIsExpired(0);
                $session->regenerateSessionId();
                $session->setUser($user->userId);

                // Audit Log
                $this->getLog()->audit('User', $user->userId, 'Login Granted', [
                    'IPAddress' => $this->getApp()->request()->getIp(),
                    'UserAgent' => $this->getApp()->request()->getUserAgent()
                ]);
            }
            catch (NotFoundException $e) {
                throw new AccessDeniedException('User not found');
            }

            $redirect = ($priorRoute == '' || $priorRoute == '/' || stripos($priorRoute, $this->getApp()->urlFor('login'))) ? $this->getApp()->urlFor('home') : $priorRoute;
        }
        catch (\Xibo\Exception\AccessDeniedException $e) {
            $this->getLog()->warning($e->getMessage());
            $this->getApp()->flash('login_message', __('Username or Password incorrect'));
            $this->getApp()->flash('priorRoute', $priorRoute);
        }
        catch (\Xibo\Exception\FormExpiredException $e) {
            $this->getApp()->flash('priorRoute', $priorRoute);
        }

        $this->setNoOutput(true);
        $this->getLog()->debug('Redirect to %s', $redirect);
        $this->getApp()->redirect($redirect);
    }

    /**
     * Log out
     * @param bool $redirect
     */
    public function logout($redirect = true)
    {
        $this->getUser()->touch();

        // to log out a user we need only to clear out some session vars
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        $session = $this->session;
        $session->setIsExpired(1);

        if ($redirect)
            $this->getApp()->redirectTo('login');
    }

    /**
     * Ping Pong
     */
    public function PingPong()
    {
        $this->session->refreshExpiry = ($this->getSanitizer()->getCheckbox('refreshSession') == 1);
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

        $response->setData(['version' => Environment::$WEBSITE_VERSION_NAME, 'sourceUrl' => $this->getConfig()->getThemeConfig('cms_source_url')]);
    }
}
