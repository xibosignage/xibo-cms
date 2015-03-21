<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2014 Daniel Garner
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
use Xibo\Helper\Help;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Theme;

defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class campaignDAO extends baseDAO
{
    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="campaign"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Campaigns'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    function actionMenu() {

        return array(
                array('title' => __('Add Campaign'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=campaign&q=AddForm',
                    'help' => __('Add a new Campaign'),
                    'onclick' => ''
                    )
            );
    }

    /**
     * Returns a Grid of Campaigns
     */
    public function Grid()
    {
        $user =& $this->user;
        $response = new ApplicationState();

        $campaigns = $user->CampaignList();

        $cols = array(
                array('name' => 'campaign', 'title' => __('Name')),
                array('name' => 'numlayouts', 'title' => __('# Layouts'))
            );
        Theme::Set('table_cols', $cols);
        
        $rows = array();

        foreach($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            if ($campaign->isLayout)
                continue;

            $row = array();
            $row['campaignid'] = $campaign->campaignId;
            $row['campaign'] = $campaign->campaign;
            $row['numlayouts'] = $campaign->numberLayouts;

            // Schedule Now
            $row['buttons'][] = array(
                    'id' => 'campaign_button_schedulenow',
                    'url' => 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $row['campaignid'],
                    'text' => __('Schedule Now')
                );
            
            // Buttons based on permissions
            if ($this->user->checkEditable($campaign)) {
                // Assign Layouts
                $row['buttons'][] = array(
                        'id' => 'campaign_button_layouts',
                        'url' => 'index.php?p=campaign&q=LayoutAssignForm&CampaignID=' . $row['campaignid'] . '&Campaign=' . $row['campaign'],
                        'text' => __('Layouts')
                    );
                
                // Edit the Campaign
                $row['buttons'][] = array(
                        'id' => 'campaign_button_edit',
                        'url' => 'index.php?p=campaign&q=EditForm&CampaignID=' . $row['campaignid'],
                        'text' => __('Edit')
                    );
            }

            if ($this->user->checkDeleteable($campaign)) {
                // Delete Campaign
                $row['buttons'][] = array(
                        'id' => 'campaign_button_delete',
                        'url' => 'index.php?p=campaign&q=DeleteForm&CampaignID=' . $row['campaignid'],
                        'text' => __('Delete')
                    );
            }

            if ($this->user->checkPermissionsModifyable($campaign)) {
                // Permissions for Campaign
                $row['buttons'][] = array(
                        'id' => 'campaign_button_delete',
                        'url' => 'index.php?p=user&q=permissionsForm&entity=Campaign&objectId=' . $row['campaignid'],
                        'text' => __('Permissions')
                    );
            }

            // Assign this to the table row
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Campaign Add Form
     */
    public function AddForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        Theme::Set('form_id', 'CampaignAddForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Add');

        $formFields = array();
        $formFields[] = FormManager::AddText('Name', __('Name'), NULL, __('The Name for this Campaign'), 'n', 'required');
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(Theme::RenderReturn('form_render'), __('Add Campaign'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#CampaignAddForm").submit()');
        $response->Respond();
    }

    /**
     * Add a Campaign
     */
    public function Add()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ApplicationState();

        $name = \Kit::GetParam('Name', _POST, _STRING);

        \Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Add($name, 0, $this->user->userid))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Added'), false);
        $response->Respond();
    }

    /**
     * Campaign Edit Form
     */
    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();
        
        $campaignId = \Kit::GetParam('CampaignID', _GET, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Pull the currently known info from the DB
        $SQL  = "SELECT CampaignID, Campaign, IsLayoutSpecific ";
        $SQL .= "  FROM `campaign` ";
        $SQL .= " WHERE CampaignID = %d ";

        $SQL = sprintf($SQL, $campaignId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Campaign'));
        }

        $campaign = \Kit::ValidateParam($row['Campaign'], _STRING);

        $formFields = array();
        $formFields[] = FormManager::AddText('Name', __('Name'), $campaign, __('The Name for this Campaign'), 'n', 'required');
        Theme::Set('form_fields', $formFields);

        // Set some information about the form
        Theme::Set('form_id', 'CampaignEditForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="CampaignID" value="' . $campaignId . '" />');

        $response->SetFormRequestResponse(Theme::RenderReturn('form_render'), __('Edit Campaign'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#CampaignEditForm").submit()');
        $response->Respond();
    }

    /**
     * Edit a Campaign
     */
    public function Edit()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ApplicationState();

        $campaignId = \Kit::GetParam('CampaignID', _POST, _INT);
        $name = \Kit::GetParam('Name', _POST, _STRING);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        if ($name == '')
            trigger_error(__('Name is a required field.'), E_USER_ERROR);

        \Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Edit($campaignId, $name))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Edited'), false);
        $response->Respond();
    }

    /**
     * Shows the Delete Group Form
     * @return
     */
    function DeleteForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();
        $helpManager = new Help($db, $user);

        $campaignId = \Kit::GetParam('CampaignID', _GET, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'CampaignDeleteForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="CampaignID" value="' . $campaignId . '" />');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(Theme::RenderReturn('form_render'), __('Delete Campaign'), '350px', '175px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#CampaignDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Campaign
     */
    public function Delete()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ApplicationState();

        $campaignId = \Kit::GetParam('CampaignID', _POST, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        \Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Delete($campaignId))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Deleted'), false);
        $response->Respond();
    }

    /**
     * Sets the Members of a group
     */
    public function SetMembers()
    {
        // Check the token
        if (!Kit::CheckToken('assign_token'))
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response = new ApplicationState();

        $campaignObject = new Campaign();

        $campaign = \Xibo\Factory\CampaignFactory::getById(Kit::GetParam('CampaignID', _REQUEST, _INT));
        $layouts = \Kit::GetParam('LayoutID', _POST, _ARRAY, array());

        // Authenticate this user
        if (!$this->user->checkEditable($campaign))
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Get all current members
        $currentMembers = \Xibo\Factory\LayoutFactory::query(null, array('campaignId' => $campaign->campaignId));

        // Flatten
        $currentLayouts = array_map(function($element) { return $element->layoutId; }, $currentMembers);

        // Work out which ones are NEW
        $newLayouts = array_diff($currentLayouts, $layouts);

        // Check permissions to all new layouts that have been selected
        foreach ($newLayouts as $layoutId) {
            // Authenticate
            if (!$this->user->checkViewable(\Xibo\Factory\LayoutFactory::getById($layoutId)))
                trigger_error(__('Your permissions to view a layout you are adding have been revoked. Please reload the Layouts form.'), E_USER_ERROR);
        }

        // Remove all current members
        $campaignObject->UnlinkAll($campaign->campaignId);

        // Add all new members
        $displayOrder = 1;

        foreach($layouts as $layoutId) {
            // By this point everything should be authenticated
            $campaignObject->Link($campaign->campaignId, $layoutId, $displayOrder);
            $displayOrder++;
        }

        $response->SetFormSubmitResponse(__('Layouts Added to Campaign'), false);
        $response->Respond();
    }

    /**
     * Displays the Library Assign form
     */
    function LayoutAssignForm()
    {
        $response = new ApplicationState();

        $campaign = \Xibo\Factory\CampaignFactory::getById(Kit::GetParam('CampaignID', _GET, _INT));

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="campaign"><input type="hidden" name="q" value="LayoutAssignView">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Get the currently assigned layouts and put them in the "well"
        $layoutsAssigned = \Xibo\Factory\LayoutFactory::query(array('lkcl.DisplayOrder'), array('campaignId' => $campaign->campaignId));

        Debug::LogEntry('audit', count($layoutsAssigned) . ' layouts assigned already');

        $formFields = array();
        $formFields[] = FormManager::AddText('filter_name', __('Name'), NULL, NULL, 'l');
        $formFields[] = FormManager::AddText('filter_tags', __('Tags'), NULL, NULL, 't');
        Theme::Set('form_fields', $formFields);

        // Set the layouts assigned
        Theme::Set('layouts_assigned', $layoutsAssigned);
        Theme::Set('append', Theme::RenderReturn('campaign_form_layout_assign'));

        // Call to render the template
        Theme::Set('header_text', __('Choose Layouts'));
        $output = Theme::RenderReturn('grid_render');

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Layouts on Campaign');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Layouts') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'LayoutsSubmit("' . $campaign->campaignId . '")');

        $response->Respond();
    }
    
    /**
     * Show the library
     */
    function LayoutAssignView() 
    {
        $response = new ApplicationState();

        // Input vars
        $name = \Kit::GetParam('filter_name', _POST, _STRING);
        $tags = \Kit::GetParam('filter_tags', _POST, _STRING);

        // Get a list of media
        $layoutList = $this->user->LayoutList(NULL, array('layout' => $name, 'tags' => $tags));

        $cols = array(
                array('name' => 'layout', 'title' => __('Name'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        // Add some extra information
        foreach ($layoutList as $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            $row = array();
            $row['layoutid'] = $layout->layoutId;
            $row['layout'] = $layout->layout;

            $row['list_id'] = 'LayoutID_' . $row['layoutid'];
            $row['assign_icons'][] = array(
                    'assign_icons_class' => 'layout_assign_list_select'
                );
            $row['dataAttributes'] = array(
                    array('name' => 'rowid', 'value' => $row['list_id']),
                    array('name' => 'litext', 'value' => $row['layout'])
                );

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response->SetGridResponse(Theme::RenderReturn('table_render'));
        $response->callBack = 'LayoutAssignCallback';
        $response->pageSize = 5;
        $response->Respond();
    }
}
