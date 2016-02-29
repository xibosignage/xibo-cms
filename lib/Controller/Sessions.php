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

use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\SessionFactory;


class Sessions extends Base
{

    function displayPage()
    {
        $this->getState()->template = 'sessions-page';
    }

    function grid()
    {
        $sessions = (new SessionFactory($this->getApp()))->query($this->gridRenderSort(), $this->gridRenderFilter([
            'type' => $this->getSanitizer()->getString('type'),
            'fromDt' => $this->getSanitizer()->getString('fromDt')
        ]));

        foreach ($sessions as $row) {
            /* @var \Xibo\Entity\Session $row */

            // Edit
            $row->buttons[] = array(
                'id' => 'sessions_button_logout',
                'url' => $this->urlFor('sessions.confirm.logout.form', ['id' => $row->userId]),
                'text' => __('Logout')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = (new SessionFactory($this->getApp()))->countLast();
        $this->getState()->setData($sessions);
    }

    /**
     * Confirm Logout Form
     * @param int $userId
     */
    function confirmLogoutForm($userId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'sessions-form-confirm-logout';
        $this->getState()->setData([
            'userId' => $userId,
            'help' => $this->getHelp()->link('Sessions', 'Logout')
        ]);
    }

    /**
     * Logout
     * @param int $userId
     */
    function logout($userId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getStore()->update('UPDATE `session` SET IsExpired = 1 WHERE userID = :userId ', ['userId' => $userId]);

        // Return
        $this->getState()->hydrate([
            'message' => __('User Logged Out.')
        ]);
    }
}
