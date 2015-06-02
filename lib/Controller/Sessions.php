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

use Xibo\Factory\SessionFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class Sessions extends Base
{

    function displayPage()
    {
        // Construct Filter Form
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_type = Session::Get(get_class(), 'filter_type');
            $filter_fromdt = Session::Get(get_class(), 'filter_fromdt');
        } else {
            $filter_pinned = 0;
            $filter_type = '0';
            $filter_fromdt = NULL;
        }

        $data = [
            'defaults' => [
                'fromDate' => $filter_fromdt,
                'type' => $filter_type,
                'filterPinned' => $filter_pinned
            ],
            'options' => [
                'type' => array(
                    array('id' => '0', 'value' => 'All'),
                    array('id' => 'active', 'value' => 'Active'),
                    array('id' => 'guest', 'value' => 'Guest'),
                    array('id' => 'expired', 'value' => 'Expired'))
            ]
        ];

        $this->getState()->template = 'sessions-page';
        $this->getState()->setData($data);
    }

    function grid()
    {
        $type = \Kit::GetParam('filter_type', _POST, _WORD);
        $fromDt = Sanitize::getString('filter_fromdt');

        Session::Set('sessions', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));
        Session::Set('sessions', 'filter_type', $type);
        Session::Set('sessions', 'filter_fromdt', $fromDt);

        $sessions = SessionFactory::query($this->gridRenderSort(), ['type' => $type, 'fromDt' => $fromDt]);

        foreach ($sessions as $row) {
            /* @var \Xibo\Entity\Session $row */

            // Edit
            $row->buttons[] = array(
                'id' => 'sessions_button_logout',
                'url' => 'index.php?p=sessions&q=ConfirmLogout&userid=' . $row->userId,
                'text' => __('Logout')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($sessions);
    }

    function ConfirmLogout()
    {

        $response = $this->getState();

        $userid = Sanitize::getInt('userid');

        // Set some information about the form
        Theme::Set('form_id', 'SessionsLogoutForm');
        Theme::Set('form_action', 'index.php?p=sessions&q=LogoutUser');
        Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userid . '" />');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to logout this user?'))));

        $response->SetFormRequestResponse(NULL, __('Logout User'), '430px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Sessions', 'Logout') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#SessionsLogoutForm").submit()');

    }

    /**
     * Logs out a user
     * @return
     */
    function LogoutUser()
    {


        //ajax request handler
        $response = $this->getState();
        $userID = Sanitize::getInt('userid');

        $SQL = sprintf("UPDATE session SET IsExpired = 1 WHERE userID = %d", $userID);

        if (!$db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__("Unable to log out this user"), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('User Logged Out.'));

    }
}

?>