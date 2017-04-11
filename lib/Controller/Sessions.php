<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\SessionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
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
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param SessionFactory $sessionFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $sessionFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->sessionFactory = $sessionFactory;
    }

    function displayPage()
    {
        $this->getState()->template = 'sessions-page';
    }

    function grid()
    {
        $sessions = $this->sessionFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'type' => $this->getSanitizer()->getString('type'),
            'fromDt' => $this->getSanitizer()->getString('fromDt')
        ]));

        foreach ($sessions as $row) {
            /* @var \Xibo\Entity\Session $row */

            // Normalise the date
            $row->lastAccessed = $this->getDate()->getLocalDate(Date::createFromFormat($this->getDate()->getSystemFormat(), $row->lastAccessed));

            if (!$this->isApi() && $this->getUser()->isSuperAdmin()) {

                $row->includeProperty('buttons');

                // Edit
                $row->buttons[] = array(
                    'id' => 'sessions_button_logout',
                    'url' => $this->urlFor('sessions.confirm.logout.form', ['id' => $row->sessionId]),
                    'text' => __('Logout')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->sessionFactory->countLast();
        $this->getState()->setData($sessions);
    }

    /**
     * Confirm Logout Form
     * @param int $sessionId
     */
    function confirmLogoutForm($sessionId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'sessions-form-confirm-logout';
        $this->getState()->setData([
            'sessionId' => $sessionId,
            'help' => $this->getHelp()->link('Sessions', 'Logout')
        ]);
    }

    /**
     * Logout
     * @param int $sessionId
     */
    function logout($sessionId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $session = $this->sessionFactory->getById($sessionId);

        if ($session->userId != 0)
            $this->store->update('UPDATE `session` SET IsExpired = 1 WHERE userID = :userId ', ['userId' => $session->userId]);
        else
            $this->store->update('UPDATE `session` SET IsExpired = 1 WHERE session_id = :session_id ', ['session_id' => $sessionId]);

        // Return
        $this->getState()->hydrate([
            'message' => __('User Logged Out.')
        ]);
    }
}
