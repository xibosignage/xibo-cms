<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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

class displaygroupDAO
{
	private $db;
	private $user;
	
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	
		include_once('lib/data/displaygroup.data.class.php');
		include_once('lib/data/displaygroupsecurity.data.class.php');		
	}
	
	/**
	 * Display Group Page Render
	 */
	public function displayPage()
	{
		// Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('displaygroup_form_add_url', 'index.php?p=displaygroup&q=AddForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displaygroup"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('displaygroup_page');
	}
	
	/**
	 * Shows the Display groups
	 * @return 
	 */
	public function Grid()
	{
        $db =& $this->db;
        $user =& $this->user;
        $response	= new ResponseManager();

        $displayGroups = $this->user->DisplayGroupList();

        if (!is_array($displayGroups))
            trigger_error(__('Cannot get list of display groups.'), E_USER_ERROR);

		$rows = array();

		foreach ($displayGroups as $row)
        {
        	if ($row['isdisplayspecific'] != 0)
                continue;

			if ($row['edit'] == 1)
            {
                // Show the edit button, members button
                
                // Group Members
	            $row['buttons'][] = array(
	                    'id' => 'displaygroup_button_group_members',
	                    'url' => 'index.php?p=displaygroup&q=MembersForm&DisplayGroupID=' . $row['displaygroupid'] . '&DisplayGroup=' . $row['displaygroup'],
	                    'text' => __('Group Members')
	                );

	            // Edit
	            $row['buttons'][] = array(
	                    'id' => 'displaygroup_button_edit',
	                    'url' => 'index.php?p=displaygroup&q=EditForm&DisplayGroupID=' . $row['displaygroupid'],
	                    'text' => __('Edit')
	                );
            }

            if ($row['del'] == 1)
            {
                // Show the delete button
	            $row['buttons'][] = array(
	                    'id' => 'displaygroup_button_delete',
	                    'url' => 'index.php?p=displaygroup&q=DeleteForm&DisplayGroupID=' . $row['displaygroupid'],
	                    'text' => __('Delete')
	                );
            }

            if ($row['modifypermissions'] == 1)
            {
                // Show the modify permissions button
	            $row['buttons'][] = array(
	                    'id' => 'displaygroup_button_permissions',
	                    'url' => 'index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $row['displaygroupid'],
	                    'text' => __('Permissions')
	                );
            }

            // Assign this to the table row
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('displaygroup_page_grid');

        $response->SetGridResponse($output);
        $response->Respond();
	}
	
	/**
	 * Shows an add form for a display group
	 */
	public function AddForm()
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();
		
		Theme::Set('form_id', 'DisplayGroupAddForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Add');

        $form = Theme::RenderReturn('displaygroup_form_add');

		$response->SetFormRequestResponse($form, __('Add Display Group'), '350px', '275px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Add') . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#DisplayGroupAddForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Shows an edit form for a display group
	 */
	public function EditForm()
	{
		$db				=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();
		$helpManager	= new HelpManager($db, $user);
		
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);
		
		// Pull the currently known info from the DB
		$SQL = "SELECT DisplayGroupID, DisplayGroup, Description FROM displaygroup WHERE DisplayGroupID = %d AND IsDisplaySpecific = 0";
		$SQL = sprintf($SQL, $displayGroupID);
		
		if (!$row = $db->GetSingleRow($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Display Group'), E_USER_ERROR);
		}
		
		// Pull out these columns
		if (count($row) <= 0)
			trigger_error(__('No display group found.'), E_USER_ERROR);
		
		Theme::Set('displaygroup', Kit::ValidateParam($row['DisplayGroup'], _STRING));
		Theme::Set('description', Kit::ValidateParam($row['Description'], _STRING));
		
		// Set some information about the form
        Theme::Set('form_id', 'DisplayGroupEditForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayGroupID" value="' . $displayGroupID . '" />');
        
        $form = Theme::RenderReturn('displaygroup_form_edit');

		$response->SetFormRequestResponse($form, __('Edit Display Group'), '350px', '275px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Edit') . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#DisplayGroupEditForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Shows the Delete Group Form
	 */
	function DeleteForm() 
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);
		
		// Set some information about the form
        Theme::Set('form_id', 'DisplayGroupDeleteForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayGroupID" value="' . $displayGroupID . '" />');

        $form = Theme::RenderReturn('displaygroup_form_delete');
		
		$response->SetFormRequestResponse($form, __('Delete Display Group'), '350px', '175px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Delete') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#DisplayGroupDeleteForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Display Group Members form
	 */
	public function MembersForm()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);
		
		// There needs to be two lists here.
		// One of which is the Displays currently assigned to this group
		// The other is a list of displays that are available to be assigned (i.e. the opposite of the first list)

		// Set some information about the form
        Theme::Set('displays_assigned_id', 'displaysIn');
        Theme::Set('displays_available_id', 'displaysOut');
        Theme::Set('displays_assigned_url', 'index.php?p=displaygroup&q=SetMembers&DisplayGroupID=' . $displayGroupID);

		// Displays in group
		$SQL  = "";
		$SQL .= "SELECT display.DisplayID, ";
		$SQL .= "       display.Display, ";
		$SQL .= "       CONCAT('DisplayID_', display.DisplayID) AS list_id ";
		$SQL .= "FROM   display ";
		$SQL .= "       INNER JOIN lkdisplaydg ";
		$SQL .= "       ON     lkdisplaydg.DisplayID = display.DisplayID ";
		$SQL .= sprintf("WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
		$SQL .= " ORDER BY display.Display ";
		
		$displaysAssigned = $db->GetArray($SQL);

        if (!is_array($displaysAssigned))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Displays'), E_USER_ERROR);
        }

        Theme::Set('displays_assigned', $displaysAssigned);
		
		// Displays not in group
		$SQL  = "";
		$SQL .= "SELECT display.DisplayID, ";
		$SQL .= "       display.Display, ";
		$SQL .= "       CONCAT('DisplayID_', display.DisplayID) AS list_id ";
		$SQL .= "FROM   display ";
		$SQL .= " WHERE display.DisplayID NOT       IN ";
		$SQL .= "       (SELECT display.DisplayID ";
		$SQL .= "       FROM    display ";
		$SQL .= "               INNER JOIN lkdisplaydg ";
		$SQL .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
		$SQL .= sprintf("	WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
		$SQL .= "       )";
		$SQL .= " ORDER BY display.Display ";

		$displaysAvailable = $db->GetArray($SQL);
		
		if (!is_array($displaysAvailable))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Displays'), E_USER_ERROR);
        }

        Theme::Set('displays_available', $displaysAvailable);
		
		
        $form = Theme::RenderReturn('displaygroup_form_display_assign');

		$response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'ManageMembersCallBack');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Members') . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), 'MembersSubmit()');
		$response->Respond();
	}
	
	/**
	 * Adds a Display Group
	 * @return 
	 */
	public function Add()
	{
		$db =& $this->db;
		$response = new ResponseManager();

		$displayGroup = Kit::GetParam('group', _POST, _STRING);
		$description = Kit::GetParam('desc', _POST, _STRING);
		
		$displayGroupObject = new DisplayGroup($db);
		
		if (!$displayGroupObject->Add($displayGroup, 0, $description))
		{
			trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Display Group Added'), false);
		$response->Respond();
	}
	
	/**
	 * Edits a Display Group
	 * @return 
	 */
	public function Edit()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$displayGroupID	= Kit::GetParam('DisplayGroupID', _POST, _INT);
		$displayGroup	= Kit::GetParam('group', _POST, _STRING);
		$description 	= Kit::GetParam('desc', _POST, _STRING);

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);
		
		// Deal with the Edit
		$displayGroupObject = new DisplayGroup($db);
		
		if (!$displayGroupObject->Edit($displayGroupID, $displayGroup, $description))
		{
			trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Display Group Edited'), false);
		$response->Respond();
	}
	
	/**
	 * Deletes a Group
	 * @return 
	 */
	function Delete() 
	{
		$db 			=& $this->db;	
		$response		= new ResponseManager();
	
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _POST, _INT);

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

		// Deal with the Delete
		$displayGroupObject = new DisplayGroup($db);
		
		if (!$displayGroupObject->Delete($displayGroupID))
		{
			trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Display Group Deleted'), false);
		$response->Respond();
	}
	
	/**
	 * Sets the Members of a group
	 * @return 
	 */
	public function SetMembers()
	{
		$db 			=& $this->db;	
		$response		= new ResponseManager();
		$displayGroupObject = new DisplayGroup($db);
	
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);
		$displays		= Kit::GetParam('DisplayID', _POST, _ARRAY, array());
		$members		= array();
		
		// Get a list of current members
		$SQL  = "";
		$SQL .= "SELECT display.DisplayID ";
		$SQL .= "FROM   display ";
		$SQL .= "       INNER JOIN lkdisplaydg ";
		$SQL .= "       ON     lkdisplaydg.DisplayID = display.DisplayID ";
		$SQL .= sprintf("WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
		
		if(!$resultIn = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Displays'), E_USER_ERROR);
		}
		
		while($row = $db->get_assoc_row($resultIn))
		{
			// Test whether this ID is in the array or not
			$displayID	= Kit::ValidateParam($row['DisplayID'], _INT);
			
			if(!in_array($displayID, $displays))
			{
				// Its currently assigned but not in the $displays array
				//  so we unassign
				if (!$displayGroupObject->Unlink($displayGroupID, $displayID))
				{
					trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
				}
			}
			else
			{
				$members[] = $displayID;
			}
		}
		
		foreach($displays as $displayID)
		{
			// Add any that are missing
			if(!in_array($displayID, $members))
			{
				if (!$displayGroupObject->Link($displayGroupID, $displayID))
				{
					trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
				}
			}
		}
		
		$response->SetFormSubmitResponse(__('Group membership set'), false);
		$response->Respond();
	}

    /**
     * Show the Permissions for this Display Group
     */
    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $displayGroupId = Kit::GetParam('DisplayGroupID', _GET, _INT);

        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this display group'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayGroupPermissionsForm');
    	Theme::Set('form_action', 'index.php?p=displaygroup&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="displayGroupId" value="' . $displayGroupId . '" />');

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lkdisplaygroupgroup ';
        $SQL .= '   ON lkdisplaygroupgroup.GroupID = group.GroupID ';
        $SQL .= '       AND lkdisplaygroupgroup.DisplayGroupID = %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $displayGroupId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this DisplayGroup'), E_USER_ERROR);
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

        $form = Theme::RenderReturn('displaygroup_form_permissions');

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayGroupPermissionsForm").submit()');
        $response->Respond();
    }

    /**
     * Add/Modify Permissions
     */
    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $displayGroupId = Kit::GetParam('displayGroupId', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Unlink all
        $security = new DisplayGroupSecurity($db);
        if (!$security->UnlinkAll($displayGroupId))
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
                if (!$security->Link($displayGroupId, $lastGroupId, $view, $edit, $del))
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
            if (!$security->Link($displayGroupId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }
}
?>