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

use baseDAO;
use Xibo\Factory\TransitionFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Config;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Theme;


class Transition extends Base
{
    /**
     * No display page functionaility
     */
    function displayPage()
    {
        $this->getState()->template = 'transition-page';
    }

    public function Grid()
    {
        $transitions = TransitionFactory::query();

        foreach ($transitions as $transition) {
            /* @var \Xibo\Entity\Transition $transition */

            // If the module config is not locked, present some buttons
            if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') != 'Checked') {

                // Edit button
                $transition->buttons[] = array(
                    'id' => 'transition_button_edit',
                    'url' => 'index.php?p=transition&q=EditForm&TransitionID=' . $transition->transitionId,
                    'text' => __('Edit')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($transitions);
    }

    public function EditForm()
    {

        $user = $this->getUser();
        $response = $this->getState();
        $helpManager = new Help($db, $user);

        // Can we edit?
        if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = \Xibo\Helper\Sanitize::getInt('TransitionID');

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT Transition, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' WHERE TransitionID = %d ';

        $SQL = sprintf($SQL, $transitionId);

        if (!$row = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Transition'));
        }

        $name = \Xibo\Helper\Sanitize::string($row['Transition']);

        // Set some information about the form
        Theme::Set('form_id', 'TransitionEditForm');
        Theme::Set('form_action', 'index.php?p=transition&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="TransitionID" value="' . $transitionId . '" />');

        $formFields = array();

        $formFields[] = Form::AddCheckbox('EnabledForIn', __('Available for In Transitions?'),
            \Xibo\Helper\Sanitize::int($row['AvailableAsIn']), __('Can this transition be used for media start?'),
            'i');

        $formFields[] = Form::AddCheckbox('EnabledForOut', __('Available for Out Transitions?'),
            \Xibo\Helper\Sanitize::int($row['AvailableAsOut']), __('Can this transition be used for media end?'),
            'o');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, sprintf(__('Edit %s'), $name), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Transition', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TransitionEditForm").submit()');

    }

    /**
     * Edit Transition
     */
    public function Edit()
    {


        $response = $this->getState();

        // Can we edit?
        if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = \Xibo\Helper\Sanitize::getInt('TransitionID');
        $enabledForIn = \Xibo\Helper\Sanitize::getCheckbox('EnabledForIn');
        $enabledForOut = \Xibo\Helper\Sanitize::getCheckbox('EnabledForOut');

        // Validation
        if ($transitionId == 0 || $transitionId == '')
            trigger_error(__('Transition ID is missing'), E_USER_ERROR);

        // Deal with the Edit
        $SQL = "UPDATE `transition` SET AvailableAsIn = %d, AvailableAsOut = %d WHERE TransitionID = %d";
        $SQL = sprintf($SQL, $enabledForIn, $enabledForOut, $transitionId);

        if (!$db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to update transition'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Transition Edited'), false);

    }
}

?>