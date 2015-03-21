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
     * Create the controller
     * @param Slim $app
     */
    public function __construct(Slim $app)
    {
        $this->app = $app;
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
     * @return \Session
     */
    protected function getSession()
    {
        return $this->app->session;
    }

    /**
     * Get Url For Route
     * @param $route
     * @return string
     */
    protected function urlFor($route)
    {
        return $this->app->urlFor($route);
    }

    /**
     * Get param
     * @param $param
     * @param $default
     * @return mixed
     */
    protected function param($param = null, $default = null)
    {
        switch ($this->app->request->getMethod()) {
            case 'get':
                return $this->app->request->get($param, $default);
            case 'post':
            case 'delete':
                return $this->app->request->post($param, $default);
            case 'put':
                return $this->app->request->put($param, $default);
            default:
                return $default;
        }
    }

    /**
     * Set to not output a full page automatically
     */
    public function setNotAutomaticFullPage()
    {
        $this->fullPage = false;
    }

    /**
     * End the controller execution, calling render
     *
     * @param string $method
     */
    public function render($method = null)
    {
        if ($method != null && method_exists($this, $method))
            $this->$method();

        if ($this->isApi()) {
            $this->app->render(200, $this->getState()->getData());
        }
        else {
            // Web App, either AJAX requested or normal
            if (!$this->app->request->isAjax() && $this->fullPage) {
                \Xibo\Helper\Theme::Set('sidebar_html', $this->sideBarContent());
                \Xibo\Helper\Theme::Set('action_menu', $this->actionMenu());

                // Display a page instead
                $this->app->state->html = \Xibo\Helper\Theme::RenderReturn('header');
            }

            $this->app->render('response', array('response' => $this->getState()));

            if (!$this->app->request->isAjax() && $this->fullPage) {
                $this->app->state->html .= \Xibo\Helper\Theme::RenderReturn('footer');
            }
        }
    }

    /**
     * Does the controller belong to the API application?
     * @return bool
     */
    private function isApi()
    {
        return ($this->app->getName() == 'api');
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