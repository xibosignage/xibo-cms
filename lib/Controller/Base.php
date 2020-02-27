<?php
/**
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
use Slim\App;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Xibo\Entity\User;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

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
     * @var App
     */
    protected $app;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /** @var  \Xibo\Helper\SanitizerService */
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

    /** @var Twig */
    private $view;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param \Xibo\Helper\SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Twig $view
     * @return $this
     */
    protected function setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, Twig $view = null)
    {
        $this->log = $log;
        $this->sanitizerService = $sanitizerService;
        $this->state = $state;
        $this->user = $user;
        $this->helpService = $help;
        $this->dateService = $date;
        $this->configService = $config;
        $this->view = $view;

        return $this;
    }

    /**
     * Get User
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get the Application State
     * @return \Xibo\Helper\ApplicationState
     */
    public function getState()
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
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
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
     * @param Request $request
     * @return bool
     * @throws ConfigurationException
     */
    protected function isApi(Request $request)
    {
        return ($request->getAttribute('name') == 'API');
    }

    /**
     * Get Url For Route
     * @param Request $request
     * @param string $route
     * @param array $data
     * @param array $params
     * @return string
     */
    protected function urlFor(Request $request, $route, $data = [], $params = [])
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        return $routeParser->urlFor($route, $data, $params);
    }

    /**
     * Get Flash Message
     * @param $key
     * @return string
     */
    protected function getFlash($key)
    {
        // TODO
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws ControllerNotImplemented if the controller is not implemented correctly
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(Request $request, Response $response)
    {
        $parsedBody = $this->getSanitizer($request->getParsedBody());

        // TODO not sure what to do with it yet  $this->rendered:o
        if ($this->noOutput) {
            return $response;
        }

       // $app = $this->getApp();

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
                'draw' => intval($parsedBody->getInt('draw')),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ];
        }

        // API Request
        if ($this->isApi($request)) {

            // Envelope by default - the APIView will un-pack if necessary
            $this->getState()->setData( [
                'grid' => $grid,
                'success' => $state->success,
                'status' => $state->httpStatus,
                'message' => $state->message,
                'id' => $state->id,
                'data' => $data
            ]);

           // $this->getApp()->render('', $data, $state->httpStatus);
            return $this->renderApiResponse($request, $response);
        } else if ($request->isXhr()) {
            // WEB Ajax

            // Are we a template that should be rendered to HTML
            // and then returned?
            if ($state->template != '' && $state->template != 'grid') {
                return $this->renderTwigAjaxReturn($request, $response);
            }

            // We always return 200's
            // TODO: we might want to change this (we'd need to change the javascript to suit)
          //  $response->withStatus(200);
            if ($grid) {
                $json = $data;
            } else {
                // TODO might be better to remove json_encode in application state
                $json = json_decode($state->asJson());
            }

           return $response->withJson($json, 200);
        }
        else {
            // WEB Normal
            if (empty($state->template)) {
                $this->getLog()->debug(sprintf('Template Missing. State: %s', json_encode($state)));
                throw new ControllerNotImplemented(__('Template Missing'));
            }

            // Append the side bar content
            $data['clock'] = $this->getDate()->getLocalDate(null, 'H:i T');
            $data['currentUser'] = $this->getUser();

            $response = $this->view->render($response, $state->template . '.twig', $data);
        }
        $this->rendered = true;
        return $response;

    }

    /**
     * Set the filter
     * @param array[Optional] $extraFilter
     * @param Request $request
     * @return array
     */
    protected function gridRenderFilter($extraFilter = [], Request $request)
    {
        $parsedFilter = $this->getSanitizer($request->getParams());
        // Handle filtering
        $filter = [
            'start' => $parsedFilter->getInt('start', ['default' => 0]),
            'length' => $parsedFilter->getInt('length', ['default' => 10])
        ];

        $search = $request->getParam('search', array());
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
     * @param Request $request
     * @return array
     */
    protected function gridRenderSort(Request $request)
    {
        $columns = $request->getParam('columns');

        if ($columns == null || !is_array($columns))
            return null;

        $order = array_map(function ($element) use ($columns) {
            return ((isset($columns[$element['column']]['name']) && $columns[$element['column']]['name'] != '') ? '`' . $columns[$element['column']]['name'] . '`' : '`' . $columns[$element['column']]['data'] . '`') . (($element['dir'] == 'desc') ? ' DESC' : '');
        },  $request->getParam('order', array()));

        return $order;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTwigAjaxReturn(Request $request, Response $response)
    {
        $data = $this->getState()->getData();
        $state = $this->getState();

        // Supply the current user to the view
        $data['currentUser'] = $this->getUser();

        // Render the view manually with Twig, parse it and pull out various bits
        $view = $this->view->render($response,$state->template . '.twig', $data);
        $view = $view->getBody();
        // Log Rendered View
         $this->getLog()->debug(sprintf('%s View: %s', $state->template, $view));

        if (!$view = json_decode($view, true)) {
            $this->getLog()->error(sprintf('Problem with Template: View = %s ', $state->template));
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
                    $this->getLog()->error(sprintf('There is a problem with the buttons in the template: %s. Buttons: %s.', $state->template, var_export($view['buttons'], true)));
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

        $json = json_decode($state->asJson());
        return $response->withJson($json, 200);
    }

    /**
     * Render a template to string
     * @param string $template
     * @param array $data
     * @param Response $response
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTemplateToString($template, $data, Response $response)
    {
        return $this->view->render($response, $template . '.twig', $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     */
    public function renderApiResponse(Request $request, Response $response)
    {
        // JSONP Callback?
        $jsonPCallback = $request->getParam('callback', null);
        $data = $this->getState()->getData();
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Don't envelope unless requested
        if ($jsonPCallback != null ||  $request->getParam('envelope', 0) == 1) {
            // Envelope

            // append error bool
            if (!$data['success']) {
                $data['success'] = false;
            }

            // append status code
            $data['status'] = $response->getStatusCode();
/* TODO
            // add flash messages
            if (isset($this->data->flash) && is_object($this->data->flash)){
                $flash = $this->data->flash->getMessages();
                if (count($flash)) {
                    $response['flash'] = $flash;
                } else {
                    unset($response['flash']);
                }
            }
*/
            // Enveloped responses always return 200
           $response = $response->withStatus(200);
        } else {
            // Don't envelope
            // Set status
            $response = $response->withStatus($data['status']);

            // Are we successful?
            if (!$data['success']) {
                // Error condition
                $data = [
                    'error' => [
                        'message' => $data['message'],
                        'code' => $data['status'],
                        'data' => $data['data']
                    ]
                ];
            }
            else {
                // Are we a grid?
                if ($data['grid'] == true) {
                    // Set the response to our data['data'] object
                    $grid = $data['data'];
                    $data = $grid['data'];

                    // Total Number of Rows
                    $totalRows = $grid['recordsTotal'];

                    // Set some headers indicating our next/previous pages
                    $start = $sanitizedParams->getInt('start', ['default' => 0]);
                    $size = $sanitizedParams->getInt('length', ['default' => 10]);

                    $linkHeader = '';
                    $url = (new HttpsDetect())->getUrl() . $request->getUri()->getPath();

                    // Is there a next page?
                    if ($start + $size < $totalRows) {
                        $linkHeader .= '<' . $url . '?start=' . ($start + $size) . '&length=' . $size . '>; rel="next", ';
                    }

                    // Is there a previous page?
                    if ($start > 0) {
                        $linkHeader .= '<' . $url . '?start=' . ($start - $size) . '&length=' . $size . '>; rel="prev", ';
                    }

                    // The first page
                    $linkHeader .= '<' . $url . '?start=0&length=' . $size . '>; rel="first"';

                    $response = $response
                        ->withHeader('X-Total-Count', $totalRows)
                        ->withHeader('Link', $linkHeader);
                } else {
                    // Set the response to our data object
                    $data = $data['data'];
                }
            }
        }

        // JSON header
        /**
        if ($jsonPCallback !== null) {
            $app->response()->body($jsonPCallback.'('.json_encode($response).')');
        } else {
            $app->response()->body(json_encode($response, JSON_PRETTY_PRINT));
        }
        */
        $response = $response->withJson($data);

        return $response;

    }
}