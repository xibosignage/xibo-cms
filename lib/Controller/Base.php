<?php
/*
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
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
use Slim\Views\Twig;
use Xibo\Entity\User;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @var LogServiceInterface
     */
    private $log;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /**
     * @var \Xibo\Helper\ApplicationState
     */
    private $state;

    /**
     * @var \Xibo\Service\HelpServiceInterface
     */
    private $helpService;

    /**
     * @var \Xibo\Service\DateServiceInterface
     */
    private $dateService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var User
     */
    private $user;

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
     * Called by Slim when the Controller is instantiated from a route definition
     * @param Slim $app
     * @param bool $setController
     * @return $this
     */
    public function setApp($app, $setController = true)
    {
        $this->app = $app;

        // Reference back to this from the app
        // but only the first time
        if ($app->controller == null && $setController)
            $app->controller = $this;

        return $this;
    }

    /**
     * Get the App
     * @return Slim
     * @throws ConfigurationException
     */
    public function getApp()
    {
        if ($this->app == null)
            throw new ConfigurationException(__('Controller called before Slim has been setup'));

        return $this->app;
    }

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @return $this
     */
    protected function setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config)
    {
        $this->log = $log;
        $this->sanitizerService = $sanitizerService;
        $this->state = $state;
        $this->user = $user;
        $this->helpService = $help;
        $this->dateService = $date;
        $this->configService = $config;

        return $this;
    }

    /**
     * Get the Current User
     * @return \Xibo\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get the Application State
     * @return \Xibo\Helper\ApplicationState
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * Get Help
     * @return \Xibo\Service\HelpServiceInterface
     */
    protected function getHelp()
    {
        return $this->helpService;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->dateService;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    public function getConfig()
    {
        return $this->configService;
    }

    /**
     * Is this the Api?
     * @return bool
     */
    protected function isApi()
    {
        return ($this->getApp()->getName() != 'web');
    }

    /**
     * Get Url For Route
     * @param string $route
     * @param array[mixed] $params
     * @return string
     */
    protected function urlFor($route, $params = array())
    {
        return $this->getApp()->urlFor($route, $params);
    }

    /**
     * Get Flash Message
     * @param $key
     * @return string
     */
    protected function getFlash($key)
    {
        $template = $this->getApp()->view()->get('flash');
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

        // State will contain the current ApplicationState, including a success flag that can be used to determine
        // if we are in error or not.
        $state = $this->getState();
        $data = $state->getData();

        // Grid requests require some extra info appended.
        // they can come from any application, hence being dealt with first
        $grid = ($state->template === 'grid');
        if ($grid) {
            $recordsTotal = ($state->recordsTotal == null) ? count($data) : $state->recordsTotal;
            $recordsFiltered = ($state->recordsFiltered == null) ? $recordsTotal : $state->recordsFiltered;

            $data = [
                'draw' => intval($this->getSanitizer()->getInt('draw')),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ];
        }

        // API Request
        if ($this->isApi()) {

            // Envelope by default - the APIView will un-pack if necessary
            $data = [
                'grid' => $grid,
                'success' => $state->success,
                'status' => $state->httpStatus,
                'message' => $state->message,
                'id' => $state->id,
                'data' => $data
            ];

            $this->getApp()->render('', $data, $state->httpStatus);
        }
        else if ($this->getApp()->request->isAjax()) {
            // WEB Ajax
            $app->response()->header('Content-Type', 'application/json');

            // Are we a template that should be rendered to HTML
            // and then returned?
            if ($state->template != '' && $state->template != 'grid') {
                $this->renderTwigAjaxReturn($data, $app, $state);
            }

            // We always return 200's
            // TODO: we might want to change this (we'd need to change the javascript to suit)
            $app->status(200);

            $app->response()->body(($grid) ? json_encode($data) : $state->asJson());
        }
        else {
            // WEB Normal
            if (empty($state->template)) {
                $this->getLog()->debug('Template Missing. State: %s', json_encode($state));
                throw new ControllerNotImplemented(__('Template Missing'));
            }

            // Append the side bar content
            $data['clock'] = $this->getDate()->getLocalDate(null, 'H:i T');
            $data['currentUser'] = $this->getUser();

            $app->render($state->template . '.twig', $data, $state->httpStatus);
        }

        $this->rendered = true;
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
            'start' => $this->getSanitizer()->getInt('start', 0),
            'length' => $this->getSanitizer()->getInt('length', 10)
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
     * @throws \Xibo\Exception\ConfigurationException
     */
    protected function gridRenderSort()
    {
        $app = $this->getApp();

        $columns = $app->request()->get('columns');
        if ($columns === null || !is_array($columns) || count($columns) <= 0) {
            return null;
        }

        $order = $app->request()->get('order');
        if ($order === null || !is_array($order) || count($order) <= 0) {
            return null;
        }

        return array_map(function ($element) use ($columns) {
            return ((isset($columns[$element['column']]['name']) && $columns[$element['column']]['name'] != '')
                    ? '`' . $columns[$element['column']]['name'] . '`'
                    : '`' . $columns[$element['column']]['data'] . '`')
                . (($element['dir'] == 'desc') ? ' DESC' : '');
        }, $order);
    }

    /**
     * @param $data
     * @param $app
     * @param $state
     * @throws ControllerNotImplemented
     */
    public function renderTwigAjaxReturn($data, $app, $state)
    {
        // Supply the current user to the view
        $data['currentUser'] = $this->getUser();

        // Render the view manually with Twig, parse it and pull out various bits
        $view = $app->view()->render($state->template . '.twig', $data);

        // Log Rendered View
        // $this->getLog()->debug('%s View: %s', $state->template, $view);

        if (!$view = json_decode($view, true)) {
            $this->getLog()->error('Problem with Template: View = %s ', $state->template);
            throw new ControllerNotImplemented(__('Problem with Form Template'));
        }

        $state->html = $view['html'];
        $state->dialogTitle = trim($view['title']);
        $state->callBack = $view['callBack'];
        $state->extra = $view['extra'];

        // Process the buttons
        $state->buttons = [];
        // Expect each button on a new line
        if (trim($view['buttons']) != '') {

            // Convert to an array
            $view['buttons'] = str_replace("\n\r", "\n", $view['buttons']);
            $buttons = explode("\n", $view['buttons']);

            foreach ($buttons as $button) {
                if ($button == '')
                    continue;

                $this->getLog()->debug('Button is ' . $button);

                $button = explode(',', trim($button));

                if (count($button) != 2) {
                    $this->getLog()->error('There is a problem with the buttons in the template: %s. Buttons: %s.', $state->template, var_export($view['buttons'], true));
                    throw new ControllerNotImplemented(__('Problem with Form Template'));
                }

                $state->buttons[trim($button[0])] = str_replace('|', ',', trim($button[1]));
            }
        }

        // Process the fieldActions
        if (trim($view['fieldActions']) == '') {
            $state->fieldActions = [];
        } else {
            // Convert to an array
            $state->fieldActions = json_decode($view['fieldActions']);
        }
    }

    /**
     * Render a template to string
     * @param string $template
     * @param array $data
     * @return string
     * @throws ConfigurationException
     */
    public function renderTemplateToString($template, $data)
    {
        /** @var Twig $view */
        $view = $this->getApp()->view();
        return $view->render($template . '.twig', $data);
    }
}