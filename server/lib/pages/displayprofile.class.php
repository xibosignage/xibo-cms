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

class displayprofileDAO {
    private $db;
    private $user;

    function __construct(database $db, user $user) {
        $this->db   =& $db;
        $this->user =& $user;
    }

    /**
     * Include display page template page based on sub page selected
     * @return
     */
    function displayPage() {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_add_url', 'index.php?p=displayprofile&q=AddForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displayprofile"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('displayprofile_page');
    }

    function Grid() {

        $rows = array();

        foreach ($this->user->DisplayProfileList() as $profile) {
            
            // Default Layout
            $profile['buttons'][] = array(
                    'id' => 'display_button_defaultlayout',
                    'url' => 'index.php?p=displayprofile&q=EditForm&displayprofileid=' . $profile['displayprofileid'],
                    'text' => __('Edit')
                );

            $rows[] = $profile;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('displayprofile_page_grid');

        $response = new ResponseManager();
        $response->SetGridResponse($output);
        $response->Respond();
    }

    function AddForm() {
        // Show a form for adding a display profile.
        Theme::Set('form_id', 'ProfileForm');
        Theme::Set('form_action', 'index.php?p=displayprofile&q=Add');

        // A list of types
        Theme::Set('type_field_list', array(
                array('typeid' => 'windows', 'type' => 'Windows'), 
                array('typeid' => 'ubuntu', 'type' => 'Ubuntu'),
                array('typeid' => 'android', 'type' => 'Android')
            ));

        // Initialise the template and capture the output
        $form = Theme::RenderReturn('displayprofile_form_add');

        $response = new ResponseManager();
        $response->SetFormRequestResponse($form, 'Add Profile', '350px', '275px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ProfileForm").submit()');
        $response->Respond();
    }

    public function Add() {
        $displayProfile = new DisplayProfile();
        $displayProfile->name = Kit::GetParam('name', _POST, _STRING);
        $displayProfile->type = Kit::GetParam('type', _POST, _STRING);
        $displayProfile->isDefault = Kit::GetParam('isdefault', _POST, _INT);
        $displayProfile->userId = $this->user->userid;

        if (!$displayProfile->Save())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        $response = new ResponseManager();
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
        include_once('config/client.config.php');

        if (!isset($CLIENT_CONFIG[$displayProfile->type]))
            trigger_error(__('CMS Config not supported for ' . $displayProfile->type . ' displays.'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayConfigForm');
        Theme::Set('form_action', 'index.php?p=displayprofile&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="displayprofileid" value="' . $displayProfile->displayProfileId . '" />');

        // Set the known theme fields
        Theme::Set('name', $displayProfile->name);
        Theme::Set('isdefault', $displayProfile->isDefault);

        // Go through each setting and output a form control to the theme.
        $formFields = array();

        foreach($CLIENT_CONFIG[$displayProfile->type]['settings'] as $setting) {

            // Check to see if we have a value for this setting as yet, if so we use that.
            // TODO: there must be a way to improve this?
            foreach ($displayProfile->config as $set) {
                if ($set['name'] == $setting['name'])
                    $setting['value'] = $set['value'];
            }

            // Each field needs to have a type, a name and a default
            $formFields[] = array(
                    'name' => $setting['name'],
                    'fieldType' => $setting['fieldType'],
                    'helpText' => $setting['helpText'],
                    'title' => $setting['title'],
                    'options' => ((isset($setting['options']) ? $setting['options'] : array())),
                    'validation' => ((isset($setting['validation']) ? $setting['validation'] : '')),
                    'value' => ((isset($setting['value']) && !empty($setting['value'])) ? Kit::ValidateParam($setting['value'], $setting['type']) : $setting['default'])
                );
        }

        Theme::Set('form_fields', $formFields);

        // Render the form and output
        $form = Theme::RenderReturn('displayprofile_form_config');

        $response = new ResponseManager();
        $response->SetFormRequestResponse($form, __('Edit Profile'), '650px', '350px');
        $response->dialogClass = 'modal-big';
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Display', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayConfigForm").submit()');
        $response->Respond();
    }  

    public function Edit() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error('Token does not match', E_USER_ERROR);
        
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
        include_once('config/client.config.php');

        if (!isset($CLIENT_CONFIG[$displayProfile->type]))
            trigger_error(__('CMS Config not supported for ' . $displayProfile->type . ' displays.'), E_USER_ERROR);

        $combined = array();

        foreach($CLIENT_CONFIG[$displayProfile->type]['settings'] as $setting) {
            // Validate the parameter
            $value = Kit::GetParam($setting['name'], _POST, $setting['type'], $setting['default']);

            // Add to the combined array
            $combined[] = array(
                    'name' => $setting['name'],
                    'value' => $value
                );
        }

        // Recursively merge the arrays and update
        $displayProfile->config = $combined;

        if (!$displayProfile->Save())
            trigger_error($displayProfile->GetErrorMessage(), E_USER_ERROR);

        $response = new ResponseManager();
        $response->SetFormSubmitResponse(__('Display Configuration Saved.'));
        $response->Respond();
    }
}
?>
