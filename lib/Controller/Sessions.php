<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\SessionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;

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
     * @param StorageServiceInterface $store
     * @param SessionFactory $sessionFactory
     */
    public function __construct($store, $sessionFactory)
    {
        $this->store = $store;
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $sessions = $this->sessionFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter([
            'type' => $sanitizedQueryParams->getString('type'),
            'fromDt' => $sanitizedQueryParams->getString('fromDt')
        ], $sanitizedQueryParams));

        foreach ($sessions as $row) {
            /* @var \Xibo\Entity\Session $row */

            // Normalise the date
            $row->lastAccessed =
                Carbon::createFromTimeString($row->lastAccessed)?->format(DateFormatHelper::getSystemFormat());

            if (!$this->isApi($request) && $this->getUser()->isSuperAdmin()) {
                $row->includeProperty('buttons');

                // No buttons on expired sessions
                if ($row->isExpired == 1) {
                    continue;
                }

                // logout, current user/session
                if ($row->userId === $this->getUser()->userId && session_id() === $row->sessionId) {
                    $url = $this->urlFor($request, 'logout');
                } else {
                    // logout, different user/session
                    $url = $this->urlFor(
                        $request,
                        'sessions.confirm.logout.form',
                        ['id' => $row->userId]
                    );
                }

                $row->buttons[] = [
                    'id' => 'sessions_button_logout',
                    'url' => $url,
                    'text' => __('Logout')
                ];
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
     * @param int $id The UserID
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function confirmLogoutForm(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'sessions-form-confirm-logout';
        $this->getState()->setData([
            'userId' => $id,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Logout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function logout(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        // We log out all of this user's sessions.
        $this->sessionFactory->expireByUserId($id);

        // Return
        $this->getState()->hydrate([
            'message' => __('User Logged Out.')
        ]);

        return $this->render($request, $response);
    }
}
