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
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
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
        $data = $state->getData();

        $grid = ($state->template == 'grid');

        if ($grid) {
            $recordsTotal = ($state->recordsTotal == null) ? count($data) : $state->recordsTotal;
            $recordsFiltered = ($state->recordsFiltered == null) ? $recordsTotal : $state->recordsFiltered;

            $data = [
                'draw' => intval(Sanitize::getInt('draw')),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ];
        }

        if ($this->isApi()) {
            // API
            if (!is_array($data))
                throw new ControllerNotImplemented();

            if (!$grid) {
                $data = [
                    'message' => $state->message,
                    'id' => $state->id,
                    'data' => $data
                ];
            }

            $this->app->render(200, $data);
        }
        else if ($this->app->request->isAjax()) {
            // WEB Ajax
            $app->response()->header('Content-Type', 'application/json');

            // Standard output handler
            if ($state->template != '' && $state->template != 'grid') {
                $data['currentUser'] = $this->getUser();
                $view = $app->view()->getInstance()->render($state->template . '.twig', $data);

                if (!$view = json_decode($view, true))
                    throw new ControllerNotImplemented(__('Problem with Form Template'));

                $state->html = $view['html'];
                $state->dialogTitle = trim($view['title']);
                $state->callBack = $view['callBack'];

                // Process the buttons
                // Expect each button on a new line
                if (trim($view['buttons']) == '') {
                    $state->buttons = [];
                } else {
                    // Convert to an array
                    $buttons = explode(PHP_EOL, $view['buttons']);

                    foreach ($buttons as $button) {
                        if ($button == '')
                            continue;

                        $button = explode(',', trim($button));

                        for ($i = 0; $i < count($button); $i++) {
                            $state->buttons[trim($button[$i])] = trim($button[$i+1]);
                            $i++;
                        }

                        foreach ($button as $key => $value) {

                        }
                    }
                }

                // Process the fieldActions
                // Expect each fieldAction on a new line
                if (trim($view['fieldActions']) == '') {
                    $state->fieldActions = [];
                } else {
                    // Convert to an array
                    Log::error($view['fieldActions']);
                    $state->fieldActions = json_decode($view['fieldActions']);
                }
            }

            echo ($grid) ? json_encode($data) : $state->asJson();
        }
        else {
            // WEB Normal
            if (empty($state->template))
                throw new ControllerNotImplemented(__('Template Missing'));

            // Append the side bar content
            $data['navigation'] = Theme::getConsolidatedMenu();
            $data['clock'] = Date::GetClock();
            $data['currentUser'] = $this->getUser();

            $this->app->render($state->template . '.twig', $data);
        }

        $this->rendered = true;

        //Log::debug('Updating Session Data.' . json_encode($_SESSION, JSON_PRETTY_PRINT));
    }

    /**
     * Set the filter
     * @param array[Optional] $extraFilter
     * @return array
     */
    protected function gridRenderFilter($extraFilter = [])
    {
        $app = $this->getApp();

        // Handle filtering
        $filter = [
            'start' => Sanitize::getInt('start', 0),
            'length' => Sanitize::getInt('length', 10)
        ];

        $search = $app->request->get('search', array());
        if (is_array($search) && isset($search['value'])) {
            $filter['search'] = $search['value'];
        }
        else if ($search != '') {
            $filter['search'] = $search;
        }

        // Merge with any extra filter items that have been provided
        $filter = array_merge($extraFilter, $filter);

        return $filter;
    }

    /**
     * Set the sort order
     * @return array
     */
    protected function gridRenderSort()
    {
        $app = $this->getApp();

        $columns = $app->request()->get('columns');

        if ($columns == null || !is_array($columns))
            return null;

        $order = array_map(function ($element) use ($columns) {
            return (($columns[$element['column']]['name'] != '') ? '`' . $columns[$element['column']]['name'] . '`' : '`' . $columns[$element['column']]['data']) . '`' . (($element['dir'] == 'desc') ? ' DESC' : '');
        }, $app->request()->get('order', array()));

        return $order;
    }
}