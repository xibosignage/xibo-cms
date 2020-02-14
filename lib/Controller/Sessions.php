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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Jenssegers\Date\Date;
use Slim\Views\Twig;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\SessionFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Sessions
 * @package Xibo\Controller
 */
class Sessions extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var SessionFactory
     */
    private $sessionFactory;

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
     * @param SessionFactory $sessionFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $sessionFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->store = $store;
        $this->sessionFactory = $sessionFactory;
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
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'sessions-page';

        return $this->render($request, $response);
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
     * @throws \Xibo\Exception\NotFoundException
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $sessions = $this->sessionFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'type' => $sanitizedQueryParams->getString('type'),
            'fromDt' => $sanitizedQueryParams->getString('fromDt')
        ], $request));

        foreach ($sessions as $row) {
            /* @var \Xibo\Entity\Session $row */

            // Normalise the date
            $row->lastAccessed = $this->getDate()->getLocalDate(Date::createFromFormat($this->getDate()->getSystemFormat(), $row->lastAccessed));

            if (!$this->isApi($request) && $this->getUser()->isSuperAdmin()) {

                $row->includeProperty('buttons');

                // Edit
                $row->buttons[] = array(
                    'id' => 'sessions_button_logout',
                    'url' => $this->urlFor($request,'sessions.confirm.logout.form', ['id' => $row->sessionId]),
                    'text' => __('Logout')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->sessionFactory->countLast();
        $this->getState()->setData($sessions);

        return $this->render($request, $response);
    }

    /**
     * Confirm Logout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    function confirmLogoutForm(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'sessions-form-confirm-logout';
        $this->getState()->setData([
            'sessionId' => $id,
            'help' => $this->getHelp()->link('Sessions', 'Logout')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Logout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    function logout(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $session = $this->sessionFactory->getById($id);

        if ($session->userId != 0) {
            $this->store->update('UPDATE `session` SET IsExpired = 1 WHERE userID = :userId ',
                ['userId' => $session->userId]);
        } else {
            $this->store->update('UPDATE `session` SET IsExpired = 1 WHERE session_id = :session_id ',
                ['session_id' => $id]);
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('User Logged Out.')
        ]);

        return $this->render($request, $response);
    }
}
