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
use Carbon\Carbon;
use Slim\App;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Xibo\Entity\User;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;

/**
 * Class Base
 * @package Xibo\Controller
 *
 * Base for all Controllers.
 *
 */
class Base
{
    use DataTablesDotNetTrait;

    /**
     * @var App
     */
    protected $app;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /** @var  SanitizerService */
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

    /** @var EventDispatcher */
    private $dispatcher;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param Twig $view
     * @return $this
     */
    protected function setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config, Twig $view = null)
    {
        $this->log = $log;
        $this->sanitizerService = $sanitizerService;
        $this->state = $state;
        $this->user = $user;
        $this->helpService = $help;
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
     * Get Config
     * @return ConfigServiceInterface
     */
    public function getConfig()
    {
        return $this->configService;
    }

    /**
     * @return \Slim\Views\Twig
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher(): EventDispatcher
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new EventDispatcher();
        }

        return $this->dispatcher;
    }

    /**
     * @param EventDispatcher $dispatcher
     * @return EventDispatcher
     */
    public function useDispatcher(EventDispatcher $dispatcher): EventDispatcher
    {
        $this->dispatcher = $dispatcher;
        return $this->dispatcher;
    }

    /**
     * Is this the Api?
     * @param Request $request
     * @return bool
     */
    protected function isApi(Request $request)
    {
        return ($request->getAttribute('name') != 'web');
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
     * @throws ControllerNotImplemented if the controller is not implemented correctly
     * @throws GeneralException
     */
    public function render(Request $request, Response $response)
    {
        if ($this->noOutput) {
            return $response;
        }

        // State will contain the current ApplicationState, including a success flag that can be used to determine
        // if we are in error or not.
        $state = $this->getState();
        $data = $state->getData();

        // Grid requests require some extra info appended.
        // they can come from any application, hence being dealt with first
        $grid = ($state->template === 'grid');

        if ($grid) {
            $params = $this->getSanitizer($request->getParams());
            $recordsTotal = ($state->recordsTotal == null) ? count($data) : $state->recordsTotal;
            $recordsFiltered = ($state->recordsFiltered == null) ? $recordsTotal : $state->recordsFiltered;

            $data = [
                'draw' => intval($params->getInt('draw')),
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

            return $this->renderApiResponse($request, $response->withStatus($state->httpStatus));

        } else if ($request->isXhr()) {
            // WEB Ajax
            // --------
            // Are we a template that should be rendered to HTML
            // and then returned?
            if ($state->template != '' && $state->template != 'grid') {
                return $this->renderTwigAjaxReturn($request, $response);
            }

            // We always return 200's
            // TODO: we might want to change this (we'd need to change the javascript to suit)
            if ($grid) {
                $json = $data;
            } else {
                $json = $state->asArray();
            }

           return $response->withJson($json, 200);
        } else {
            // WEB Normal
            // ----------
            if (empty($state->template)) {
                $this->getLog()->debug(sprintf('Template Missing. State: %s', json_encode($state)));
                throw new ControllerNotImplemented(__('Template Missing'));
            }

            // Append the side bar content
            $data['clock'] = Carbon::now()->format('H:i T');
            $data['currentUser'] = $this->getUser();

            try {
                $response = $this->view->render($response, $state->template . '.twig', $data);
            } catch (LoaderError | RuntimeError | SyntaxError $e) {
                throw new GeneralException(__('Twig Error ') . $e->getMessage());
            }
        }
        $this->rendered = true;
        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function renderTwigAjaxReturn(Request $request, Response $response)
    {
        $data = $this->getState()->getData();
        $state = $this->getState();

        // Supply the current user to the view
        $data['currentUser'] = $this->getUser();

        // Render the view manually with Twig, parse it and pull out various bits
        try {
            $view = $this->view->render($response,$state->template . '.twig', $data);
        } catch (LoaderError | RuntimeError | SyntaxError $e) {
            throw new GeneralException(__('Twig Error ') . $e->getMessage());
        }

        $view = $view->getBody();

        // Log Rendered View
        $this->getLog()->debug(sprintf('%s View: %s', $state->template, $view));

        if (!$view = json_decode($view, true)) {
            $this->getLog()->error(sprintf('Problem with Template: View = %s, Error = %s ', $state->template, json_last_error_msg()));
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
        return $response = $response->withJson($json, 200);
    }

    /**
     * Render a template to string
     * @param string $template
     * @param array $data
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTemplateToString($template, $data)
    {
        return $this->view->fetch($template . '.twig', $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     */
    public function renderApiResponse(Request $request, Response $response)
    {
        $data = $this->getState()->getData();

        // Don't envelope unless requested
        if ($request->getParam('envelope', 0) == 1
            || $request->getAttribute('name') === 'test'
        ) {
            // Envelope
            // append error bool
            if (!$data['success']) {
                $data['success'] = false;
            }

            // append status code
            $data['status'] = $response->getStatusCode();

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
            } else {
                // Are we a grid?
                if ($data['grid'] == true) {
                    // Set the response to our data['data'] object
                    $grid = $data['data'];
                    $data = $grid['data'];

                    // Total Number of Rows
                    $totalRows = $grid['recordsTotal'];

                    // Set some headers indicating our next/previous pages
                    $sanitizedParams = $this->getSanitizer($request->getParams());
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

        return $response->withJson($data);

    }

    /**
     * @param string $form The form name
     * @return bool
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAutoSubmit(string $form)
    {
        return $this->getUser()->getOptionValue('autoSubmit.' . $form, 'false') === 'true';
    }
}