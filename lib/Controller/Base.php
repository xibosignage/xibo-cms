<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Base.php) is part of Xibo.
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
use Slim\Slim;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Helper\Theme;

/**
 * Class Base
 * @package Xibo\Controller
 *
 * Base for all Controllers.
 *
 * Controllers are initialised with setApp($app) where $app is the hosting Slim application.
 * Controllers should manipulate the Slim applications $app->state object to represent the data which will be output
 * to the view layer (either app or API).
 */
class Base
{
    /**
     * @var Slim
     */
    protected $app;

    /**
     * Automatically output a full page if non-ajax request arrives
     * @var bool
     */
    private $fullPage = true;

    /**
     * Have we already rendered this controller.
     * @var bool
     */
    private $rendered = false;

    /**
     * Is this controller expected to output anything?
     * @var bool
     */
    private $noOutput = false;

    /**
     * Create the controller
     */
    public function __construct()
    {
        $this->app = Slim::getInstance();

        // Reference back to this from the app
        $this->app->controller = $this;
    }

    /**
     * Get the App
     * @return Slim
     */
    protected function getApp()
    {
        return $this->app;
    }

    /**
     * Get the Current User
     * @return \Xibo\Entity\User
     */
    protected function getUser()
    {
        return $this->app->user;
    }

    /**
     * Get the Application State
     * @return \Xibo\Helper\ApplicationState
     */
    protected function getState()
    {
        return $this->app->state;
    }

    /**
     * Get the Session
     * @return \Xibo\Helper\Session
     */
    protected function getSession()
    {
        return $this->app->session;
    }

    /**
     * Is this the Api?
     * @return bool
     */
    protected function isApi()
    {
        return ($this->getApp()->getName() == 'api');
    }

    /**
     * Get Url For Route
     * @param string $route
     * @param array[mixed] $params
     * @return string
     */
    protected function urlFor($route, $params = array())
    {
        return $this->app->urlFor($route, $params);
    }

    /**
     * Get Flash Message
     * @param $key
     * @return string
     */
    protected function getFlash($key)
    {
        $template = $this->app->view()->get('flash');
        return isset($template[$key]) ? $template[$key] : '';
    }

    /**
     * Set to not output a full page automatically
     */
    public function setNotAutomaticFullPage()
    {
        $this->fullPage = false;
    }

    /**
     * Set No output
     * @param bool $bool
     */
    public function setNoOutput($bool = true)
    {
        $this->noOutput = $bool;
    }

    /**
     * End the controller execution, calling render
     * @throws ControllerNotImplemented if the controller is not implemented correctly
     */
    public function render()
    {
        if ($this->rendered || $this->noOutput)
            return;

        $app = $this->getApp();
        $state = $this->getState();

        if ($this->isApi()) {
            $data = $state->getData();

            if (!is_array($data))
                throw new ControllerNotImplemented();

            $this->app->render(200, $data);
        }
        else if ($this->app->request->isAjax()) {

            if ($state->template != '') {
                $state->html = $app->view()->getInstance()->render($state->template . '.twig', $state->getData());
            }

            // AJAX web app
            echo $state->asJson();
        }
        else {
            if (empty($state->template))
                throw new ControllerNotImplemented(__('Template Missing'));

            $this->app->render($state->template . '.twig', []);
        }

        $this->rendered = true;
    }

    /**
     * Action Menu
     * @return string
     */
    public function actionMenu()
    {
        return '';
    }

    /**
     * Side Bar Content
     * @return string
     */
    public function sideBarContent()
    {
        return '';
    }

    /**
     * Display the main page
     * @return string
     */
    public function displayPage()
    {
        return '';
    }
}