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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class transitionDAO extends baseDAO {
	private $transition;
	
    /**
     * No display page functionaility
     * @return
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="transition"><input type="hidden" name="q" value="Grid">');
        Theme::Set('pager', ResponseManager::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Transitions'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $SQL = '';
        $SQL .= 'SELECT TransitionID, ';
        $SQL .= '   Transition, ';
        $SQL .= '   Code, ';
        $SQL .= '   HasDuration, ';
        $SQL .= '   HasDirection, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' ORDER BY Transition ';

        if (!$transitions = $db->GetArray($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the list of transitions'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'name', 'title' => __('Name')),
                array('name' => 'hasduration', 'title' => __('Has Duration'), 'icons' => true),
                array('name' => 'hasdirection', 'title' => __('Has Direction'), 'icons' => true),
                array('name' => 'enabledforin', 'title' => __('Enabled for In'), 'icons' => true),
                array('name' => 'enabledforout', 'title' => __('Enabled for Out'), 'icons' => true)
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach($transitions as $transition)
        {
            $row = array();
            $row['transitionid'] = Kit::ValidateParam($transition['TransitionID'], _INT);
            $row['name'] = Kit::ValidateParam($transition['Transition'], _STRING);
            $row['hasduration'] = Kit::ValidateParam($transition['HasDuration'], _INT);
            $row['hasdirection'] = Kit::ValidateParam($transition['HasDirection'], _INT);
            $row['enabledforin'] = Kit::ValidateParam($transition['AvailableAsIn'], _INT);
            $row['enabledforout'] = Kit::ValidateParam($transition['AvailableAsOut'], _INT);
            
            // Initialise array of buttons, because we might not have any
            $row['buttons'] = array();

            // If the module config is not locked, present some buttons
            if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') != 'Checked') {
                
                // Edit button
                $row['buttons'][] = array(
                        'id' => 'transition_button_edit',
                        'url' => 'index.php?p=transition&q=EditForm&TransitionID=' . $row['transitionid'],
                        'text' => __('Edit')
                    );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Can we edit?
        if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = Kit::GetParam('TransitionID', _GET, _INT);

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT Transition, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' WHERE TransitionID = %d ';

        $SQL = sprintf($SQL, $transitionId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Transition'));
        }

        $name = Kit::ValidateParam($row['Transition'], _STRING);      

        // Set some information about the form
        Theme::Set('form_id', 'TransitionEditForm');
        Theme::Set('form_action', 'index.php?p=transition&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="TransitionID" value="'. $transitionId . '" />');
        
        $formFields = array();
        
        $formFields[] = FormManager::AddCheckbox('EnabledForIn', __('Available for In Transitions?'), 
            Kit::ValidateParam($row['AvailableAsIn'], _INT), __('Can this transition be used for media start?'), 
            'i');
        
        $formFields[] = FormManager::AddCheckbox('EnabledForOut', __('Available for Out Transitions?'), 
            Kit::ValidateParam($row['AvailableAsOut'], _INT), __('Can this transition be used for media end?'), 
            'o');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, sprintf(__('Edit %s'), $name), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Transition', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TransitionEditForm").submit()');
        $response->Respond();
    }

    /**
     * Edit Transition
     */
    public function Edit()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        // Can we edit?
        if (Config::GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Transition Config Locked'), E_USER_ERROR);

        $transitionId = Kit::GetParam('TransitionID', _POST, _INT);
        $enabledForIn = Kit::GetParam('EnabledForIn', _POST, _CHECKBOX);
        $enabledForOut = Kit::GetParam('EnabledForOut', _POST, _CHECKBOX);

        // Validation
        if ($transitionId == 0 || $transitionId == '')
            trigger_error(__('Transition ID is missing'), E_USER_ERROR);

        // Deal with the Edit
        $SQL = "UPDATE `transition` SET AvailableAsIn = %d, AvailableAsOut = %d WHERE TransitionID = %d";
        $SQL = sprintf($SQL, $enabledForIn, $enabledForOut, $transitionId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to update transition'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Transition Edited'), false);
        $response->Respond();
    }
}
?>