<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2013 Daniel Garner
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

class campaignDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;
    }

    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('campaign_form_add_url', 'index.php?p=campaign&q=AddForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="campaign"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('campaign_page');
    }

    /**
     * Returns a Grid of Campaigns
     */
    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $campaigns = $user->CampaignList();

        if (!is_array($campaigns))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get list of campaigns'), E_USER_ERROR);
        }

        $rows = array();

        foreach($campaigns as $row)
        {
            if ($row['islayoutspecific'] == 1)
                continue;

            // Schedule Now
            $row['buttons'][] = array(
                    'id' => 'campaign_button_schedulenow',
                    'url' => 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $row['campaignid'],
                    'text' => __('Schedule Now')
                );
            
            // Buttons based on permissions
            if ($row['edit'] == 1)
            {
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

            if ($row['del'] == 1)
            {
                // Delete Campaign
                $row['buttons'][] = array(
                        'id' => 'campaign_button_delete',
                        'url' => 'index.php?p=campaign&q=DeleteForm&CampaignID=' . $row['campaignid'],
                        'text' => __('Delete')
                    );
            }

            if ($row['modifypermissions'] == 1)
            {
                // Permissions for Campaign
                $row['buttons'][] = array(
                        'id' => 'campaign_button_delete',
                        'url' => 'index.php?p=campaign&q=PermissionsForm&CampaignID=' . $row['campaignid'],
                        'text' => __('Permissions')
                    );
            }

            // Assign this to the table row
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('campaign_page_grid');

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
        $response = new ResponseManager();

        Theme::Set('form_id', 'CampaignAddForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Add');

        $form = Theme::RenderReturn('campaign_form_add');

        $response->SetFormRequestResponse($form, __('Add Campaign'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Add') . '")');
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
        $response = new ResponseManager();

        $name = Kit::GetParam('Name', _POST, _STRING);

        Kit::ClassLoader('campaign');
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
        $response = new ResponseManager();
        
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

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

        $campaign = Kit::ValidateParam($row['Campaign'], _STRING);

        // Set some information about the form
        Theme::Set('form_id', 'CampaignEditForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="CampaignID" value="' . $campaignId . '" />');
        Theme::Set('campaign', $campaign);
        
        $form = Theme::RenderReturn('campaign_form_edit');

        $response->SetFormRequestResponse($form, __('Edit Campaign'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Edit') . '")');
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
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT);
        $name = Kit::GetParam('Name', _POST, _STRING);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        if ($name == '')
            trigger_error(__('Name is a required field.'), E_USER_ERROR);

        Kit::ClassLoader('campaign');
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
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'CampaignDeleteForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="CampaignID" value="' . $campaignId . '" />');

        $form = Theme::RenderReturn('campaign_form_delete');

        $response->SetFormRequestResponse($form, __('Delete Campaign'), '350px', '175px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Delete') . '")');
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
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT);

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to delete this campaign'), E_USER_ERROR);

        // Validation
        if ($campaignId == 0 || $campaignId == '')
            trigger_error(__('Campaign ID is missing'), E_USER_ERROR);

        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        if (!$campaignObject->Delete($campaignId))
            trigger_error($campaignObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Campaign Deleted'), false);
        $response->Respond();
    }

    /**
     * Show the Permissions for this Campaign
     */
    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        $auth = $this->user->CampaignAuth($campaignId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this campaign'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'CampaignPermissionsForm');
        Theme::Set('form_action', 'index.php?p=campaign&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="campaignId" value="' . $campaignId . '" />');

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lkcampaigngroup ';
        $SQL .= '   ON lkcampaigngroup.GroupID = group.GroupID ';
        $SQL .= '       AND lkcampaigngroup.CampaignID = %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $campaignId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this Campaign'), E_USER_ERROR);
        }

        $checkboxes = array();

        while ($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $rowClass = ($row['IsUserSpecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                    'id' => $groupId,
                    'name' => Kit::ValidateParam($row['Group'], _STRING),
                    'class' => $rowClass,
                    'value_view' => $groupId . '_view',
                    'value_view_checked' => (($row['View'] == 1) ? 'checked' : ''),
                    'value_edit' => $groupId . '_edit',
                    'value_edit_checked' => (($row['Edit'] == 1) ? 'checked' : ''),
                    'value_del' => $groupId . '_del',
                    'value_del_checked' => (($row['Del'] == 1) ? 'checked' : ''),
                );

            $checkboxes[] = $checkbox;
        }

        Theme::Set('form_rows', $checkboxes);

        $form = Theme::RenderReturn('campaign_form_permissions');

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#CampaignPermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('campaignId', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->CampaignAuth($campaignId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this campaign'), E_USER_ERROR);

        // Unlink all
        Kit::ClassLoader('campaignsecurity');
        $security = new CampaignSecurity($db);
        if (!$security->UnlinkAll($campaignId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($campaignId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first)
        {
            if (!$security->Link($campaignId, $lastGroupId, $view, $edit, $del))
                trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }

    /**
     * Sets the Members of a group
     * @return
     */
    public function SetMembers()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();
        Kit::ClassLoader('campaign');
        $campaignObject = new Campaign($db);

        $campaignId = Kit::GetParam('CampaignID', _REQUEST, _INT);
        $layouts = Kit::GetParam('LayoutID', _POST, _ARRAY, array());

        // Authenticate this user
        $auth = $this->user->CampaignAuth($campaignId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Remove all current members
        $campaignObject->UnlinkAll($campaignId);

        // Add all new members
        $displayOrder = 1;

        foreach($layouts as $layoutId)
        {
            // Authenticate
            $auth = $this->user->LayoutAuth($layoutId, true);
            if (!$auth->view)
                trigger_error(__('Your permissions to view a layout you are adding have been revoked. Please reload the Layouts form.'), E_USER_ERROR);

            $campaignObject->Link($campaignId, $layoutId, $displayOrder);

            $displayOrder++;
        }

        $response->SetFormSubmitResponse(__('Layouts Added to Campaign'), false);
        $response->Respond();
    }

    /**
     * Displays the Library Assign form
     * @return
     */
    function LayoutAssignForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        // Input vars
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT);

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="campaign"><input type="hidden" name="q" value="LayoutAssignView">');
        Theme::Set('pager', ResponseManager::Pager($id));
        
        // Get the currently assigned layouts and put them in the "well"
        // // Layouts in group
        $SQL  = "SELECT layout.Layoutid, ";
        $SQL .= "       layout.layout, ";
        $SQL .= "       CONCAT('LayoutID_', layout.LayoutID) AS list_id ";
        $SQL .= "FROM   layout ";
        $SQL .= "       INNER JOIN lkcampaignlayout ";
        $SQL .= "       ON     lkcampaignlayout.LayoutID = layout.LayoutID ";
        $SQL .= sprintf("WHERE  lkcampaignlayout.CampaignID = %d", $campaignId);
        $SQL .= " ORDER BY lkcampaignlayout.DisplayOrder ";

        $layoutsAssigned = $db->GetArray($SQL);

        if (!is_array($layoutsAssigned))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Layouts'), E_USER_ERROR);
        }

        Debug::LogEntry('audit', count($layoutsAssigned) . ' layouts assigned already');

        // Set the layouts assigned
        Theme::Set('layouts_assigned', $layoutsAssigned);

        // Call to render the template
        $output = Theme::RenderReturn('campaign_form_layout_assign');

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Layouts on Campaign');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Layouts') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'LayoutsSubmit("' . $campaignId . '")');

        $response->Respond();
    }
    
    /**
     * Show the library
     * @return 
     */
    function LayoutAssignView() 
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        //Input vars
        $name = Kit::GetParam('filter_name', _POST, _STRING);

        // Get a list of media
        $layoutList = $user->LayoutList($name);

        $rows = array();

        // Add some extra information
        foreach ($layoutList as $row) {

            $row['list_id'] = 'LayoutID_' . $row['layoutid'];

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response->SetGridResponse(Theme::RenderReturn('campaign_form_layout_assign_list'));
        $response->callBack = 'LayoutAssignCallback';
        $response->pageSize = 5;
        $response->Respond();
    }
}
?>
