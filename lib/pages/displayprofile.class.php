<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

// Companion classes
Kit::ClassLoader('displayprofile');

class displayprofileDAO extends baseDAO {
    /**
     * Include display page template page based on sub page selected
     * @return
     */
    function displayPage() {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displayprofile"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Display Setting Profiles'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    function actionMenu() {

        return array(
                array('title' => __('Add Profile'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=displayprofile&q=AddForm',
                    'help' => __('Add a new Display Settings Profile'),
                    'onclick' => ''
                    )
            );                   
    }

    function Grid() {

        $cols = array(
                array('name' => 'name', 'title' => __('Name')),
                array('name' => 'type', 'title' => __('Type')),
                array('name' => 'isdefault', 'title' => __('Default'), 'icons' => true)
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($this->user->DisplayProfileList() as $profile) {
            
            // Default Layout
            $profile['buttons'][] = array(
                    'id' => 'displayprofile_button_edit',
                    'url' => 'index.php?p=displayprofile&q=EditForm&displayprofileid=' . $profile['displayprofileid'],
                    'text' => __('Edit')
                );

            if ($profile['del'] == 1) {
                $profile['buttons'][] = array(
                    'id' => 'displayprofile_button_delete',
                    'url' => 'index.php?p=displayprofile&q=DeleteForm&displayprofileid=' . $profile['displayprofileid'],
                    'text' => __('Delete')
                );
            }

            $rows[] = $profile;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response = new ResponseManager();
        $response->SetGridResponse($output);
        $response->Respond();
    }

    function AddForm() {
        // Show a form for adding a display profile.
        Theme::Set('form_id', 'ProfileForm');
        Theme::Set('form_action', 'index.php?p=displayprofile&q=Add');

        $formFields = array();
        $formFields[] = FormManager::AddText('name', __('Name'), NULL, 
            __('The Name of the Profile - (1 - 50 characters)'), 'n', 'maxlength="50" required');

        $formFields[] = FormManager::AddCombo(
                    'type', 
                    __('Client Type'), 
                    NULL,
                    array(
                        array('typeid' => 'windows', 'type' => 'Windows'), 
                        array('typeid' => 'ubuntu', 'type' => 'Ubuntu'),
                        array('typeid' => 'android', 'type' => 'Android')
                    ),
                    'typeid',
                    'type',
                    __('What type of display client is this profile intended for?'), 
                    't');

        $formFields[] = FormManager::AddCheckbox('isdefault', __('Default Profile?'), 
            NULL, __('Is this the default profile for all Displays of this type? Only 1 profile can be the default.'), 
            'd');

        Theme::Set('form_fields', $formFields);

        $response = new ResponseManager();
        $response->SetFormRequestResponse(NULL, 'Add Profile', '350px', '275px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ProfileForm").submit()');
        $response->Respond();
    }

    public function Add() {
        $response = new ResponseManager();
        $displayProfile = new DisplayProfile();
        $displayProfile->name = Kit::GetParam('name', _POST, _STRING);
        $displayProfile->type = Kit::GetParam('type', _POST, _STRING);
        $displayProfile->isDefault = Kit::GetParam('isdefault', _POST, _CHECKBOX);
        $displayProfile->userId = $this->user->userid;

        if (!$displayProfile->Save())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Profile Saved.'));
        $response->Respond();
    }

    public function EditForm() {
        // Create a form out of the config object.
        $displayProfile  = new DisplayProfile();
        $displayProfile->displayProfileId = Kit::GetParam('displayprofileid', _GET, _INT);

        if (!$displayProfile->Load())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        if ($this->user->usertypeid != 1 && $this->user->userid != $displayProfile->userId)
            trigger_error(__('You do not have permission to edit this profile'), E_USER_ERROR);

        if (empty($displayProfile->type))
            trigger_error(__('Unknown Client Type'), E_USER_ERROR);

        // Capture and validate the posted form parameters in accordance with the display config object.
        include('config/client.config.php');

        if (!isset($CLIENT_CONFIG[$displayProfile->type]))
            trigger_error(__('CMS Config not supported for ' . $displayProfile->type . ' displays.'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayConfigForm');
        Theme::Set('form_action', 'index.php?p=displayprofile&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="displayprofileid" value="' . $displayProfile->displayProfileId . '" />');

        $formFields = array();
        $formTabs = array();

        // Tabs?
        foreach($CLIENT_CONFIG[$displayProfile->type]['tabs'] as $tab) {
            // Create an empty array of form fields for this tab.
            $formFields[$tab['id']] = array();

            // Also add the tab
            $formTabs[] = FormManager::AddTab($tab['id'], $tab['name']);
        }

        // Go through each setting and output a form control to the theme.
        $formFields['general'][] = FormManager::AddText('name', __('Name'), $displayProfile->name, 
            __('The Name of the Profile - (1 - 50 characters)'), 'n', 'maxlength="50" required');

        $formFields['general'][] = FormManager::AddCheckbox('isdefault', __('Default Profile?'), 
            $displayProfile->isDefault, __('Is this the default profile for all Displays of this type? Only 1 profile can be the default.'), 
            'd');

        foreach($CLIENT_CONFIG[$displayProfile->type]['settings'] as $setting) {

            // Check to see if we have a value for this setting as yet, if so we use that.
            // TODO: there must be a way to improve this?
            foreach ($displayProfile->config as $set) {
                if ($set['name'] == $setting['name'])
                    $setting['value'] = $set['value'];
            }

            if ($setting['type'] == 'checkbox' && isset($setting['value']))
                $validated = $setting['value'];
            else if ($setting['fieldType'] == 'timePicker') {
                // Check if we are 0, if so then set to 00:00
                if ($setting['value'] == 0)
                    $validated = '00:00';
                else {
                    $validated = DateManager::getLocalDate($setting['value'] / 1000, 'H:i');
                }
            }
            else if (isset($setting['value']))
                $validated = Kit::ValidateParam($setting['value'], $setting['type']);
            else
                $validated = $setting['default'];

            //Debug::LogEntry('audit', 'Validated ' . $setting['name'] . '. [' . $setting['value'] . '] as [' . $validated . ']. With type ' . $setting['type']);

            // Each field needs to have a type, a name and a default
            $formFields[$setting['tabId']][] = array(
                    'name' => $setting['name'],
                    'fieldType' => $setting['fieldType'],
                    'helpText' => $setting['helpText'],
                    'title' => $setting['title'],
                    'options' => ((isset($setting['options']) ? $setting['options'] : array())),
                    'optionId' => 'id',
                    'optionValue' => 'value',
                    'validation' => ((isset($setting['validation']) ? $setting['validation'] : '')),
                    'value' => $validated,
                    'enabled' => $setting['enabled'],
                    'groupClass' => NULL,
                    'accesskey' => ''
                );
        }

        Theme::Set('form_tabs', $formTabs);

        foreach($CLIENT_CONFIG[$displayProfile->type]['tabs'] as $tab) {
            Theme::Set('form_fields_' . $tab['id'], $formFields[$tab['id']]);
        }

        $response = new ResponseManager();
        $response->SetFormRequestResponse(NULL, __('Edit Profile'), '650px', '350px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayProfile', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayConfigForm").submit()');
        $response->Respond();
    }

    public function Edit() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error('Token does not match', E_USER_ERROR);

        $response = new ResponseManager();
        
        // Create a form out of the config object.
        $displayProfile  = new DisplayProfile();
        $displayProfile->displayProfileId = Kit::GetParam('displayprofileid', _POST, _INT);

        if (!$displayProfile->Load())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        if ($this->user->usertypeid != 1 && $this->user->userid != $displayProfile->userId)
            trigger_error(__('You do not have permission to edit this profile'), E_USER_ERROR);

        if (empty($displayProfile->type))
            trigger_error(__('Unknown Client Type'), E_USER_ERROR);

        $displayProfile->name = Kit::GetParam('name', _POST, _STRING);
        $displayProfile->isDefault = Kit::GetParam('isdefault', _POST, _CHECKBOX);

        // Capture and validate the posted form parameters in accordance with the display config object.
        include('config/client.config.php');

        if (!isset($CLIENT_CONFIG[$displayProfile->type]))
            trigger_error(__('CMS Config not supported for ' . $displayProfile->type . ' displays.'), E_USER_ERROR);

        $combined = array();

        foreach($CLIENT_CONFIG[$displayProfile->type]['settings'] as $setting) {
            // Validate the parameter
            $value = Kit::GetParam($setting['name'], _POST, $setting['type'], (($setting['type'] == 'checkbox') ? NULL : $setting['default']));

            // If we are a time picker, then process the received time
            if ($setting['fieldType'] == 'timePicker') {
                $value = ($value == '00:00') ? '0' : DateManager::getTimestampFromTimeString($value) * 1000;
            }

            // Add to the combined array
            $combined[] = array(
                    'name' => $setting['name'],
                    'value' => $value,
                    'type' => $setting['type']
                );
        }

        // Recursively merge the arrays and update
        $displayProfile->config = $combined;

        if (!$displayProfile->Save())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Configuration Saved.'));
        $response->Respond();
    }

    /**
     * Shows the Delete Group Form
     */
    function DeleteForm() {
        
        $displayProfile  = new DisplayProfile();
        $displayProfile->displayProfileId = Kit::GetParam('displayprofileid', _GET, _INT);

        if (!$displayProfile->Load())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        if ($this->user->usertypeid != 1 && $this->user->userid != $displayProfile->userId)
            trigger_error(__('You do not have permission to edit this profile'), E_USER_ERROR);
        
        // Set some information about the form
        Theme::Set('form_id', 'DisplayProfileDeleteForm');
        Theme::Set('form_action', 'index.php?p=displayprofile&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="displayprofileid" value="' . $displayProfile->displayProfileId . '" />');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete?'))));
        
        $response  = new ResponseManager();
        $response->SetFormRequestResponse(NULL, __('Delete Display Profile'), '350px', '175px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayProfile', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DisplayProfileDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Deletes a Group
     * @return 
     */
    function Delete() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error('Token does not match', E_USER_ERROR);

        $response = new ResponseManager();
        
        $displayProfile  = new DisplayProfile();
        $displayProfile->displayProfileId = Kit::GetParam('displayprofileid', _POST, _INT);

        if (!$displayProfile->Load())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        if ($this->user->usertypeid != 1 && $this->user->userid != $displayProfile->userId)
            trigger_error(__('You do not have permission to edit this profile'), E_USER_ERROR);
        
        if (!$displayProfile->Delete($displayProfile->displayProfileId))
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Profile Deleted'), false);
        $response->Respond();
    }
}
?>
