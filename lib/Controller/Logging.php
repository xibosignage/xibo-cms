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

use Jenssegers\Date\Date;
use Slim\Views\Twig;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LogFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Logging
 * @package Xibo\Controller
 */
class Logging extends Base
{
    /**
     * @var LogFactory
     */
    private $logFactory;

    /** @var StorageServiceInterface  */
    private $store;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /** @var  UserFactory */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param LogFactory $logFactory
     * @param DisplayFactory $displayFactory
     * @param UserFactory $userFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $logFactory, $displayFactory, $userFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->store = $store;
        $this->logFactory = $logFactory;
        $this->displayFactory = $displayFactory;
        $this->userFactory = $userFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'log-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query()
        ]);

        return $this->render($request, $response);
    }

    function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Date time criteria
        $seconds = $parsedQueryParams->getInt('seconds', ['default' => 120]);
        $intervalType = $parsedQueryParams->getInt('intervalType', ['default' => 1]);
        $fromDt = $parsedQueryParams->getDate('fromDt', ['default' => new Date($this->getDate()->getLocalDate())]);

        $logs = $this->logFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'fromDt' => $fromDt->format('U') - ($seconds * $intervalType),
            'toDt' => $fromDt->format('U'),
            'type' => $parsedQueryParams->getString('level'),
            'page' => $parsedQueryParams->getString('page'),
            'channel' => $parsedQueryParams->getString('channel'),
            'function' => $parsedQueryParams->getString('function'),
            'displayId' => $parsedQueryParams->getInt('displayId'),
            'userId' => $parsedQueryParams->getInt('userId'),
            'excludeLog' => $parsedQueryParams->getCheckbox('excludeLog'),
            'runNo' => $parsedQueryParams->getString('runNo'),
            'message' => $parsedQueryParams->getString('message'),
            'display' => $parsedQueryParams->getString('display'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'displayGroupId' => $parsedQueryParams->getInt('displayGroupId'),
        ], $request));

        foreach ($logs as $log) {
            // Normalise the date
            $log->logDate = $this->getDate()->getLocalDate(Date::createFromFormat($this->getDate()->getSystemFormat(), $log->logDate));
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->logFactory->countLast();
        $this->getState()->setData($logs);

        return $this->render($request, $response);
    }

    /**
     * Truncate Log Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function truncateForm(Request $request, Response $response)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));
        }

        $this->getState()->template = 'log-form-truncate';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Log', 'Truncate')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Truncate the Log
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function truncate(Request $request, Response $response)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));
        }

        $this->store->update('TRUNCATE TABLE log', array());

        // Return
        $this->getState()->hydrate([
            'message' => __('Log Truncated')
        ]);

        return $this->render($request, $response);
    }
}
