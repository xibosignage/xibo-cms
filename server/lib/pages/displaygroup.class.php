<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-12 Daniel Garner
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
	
	function on_page_load() 
	{
		return "";
	}

	function echo_page_heading() 
	{
		echo __("Display Group Administration");
		return true;
	}
	
	public function displayPage()
	{
		require("template/pages/displaygroup_view.php");
		
		return false;
	}
	
	/**
	 * Shows the Filter form for display groups
	 * @return 
	 */
	public function Filter()
	{
		$filterForm = <<<END
		<div class="FilterDiv" id="DisplayGroupFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="displaygroup">
				<input type="hidden" name="q" value="Grid">
			</form>
		</div>
END;
		
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$filterForm
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		echo $xiboGrid;
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

            $msgSave = __('Save');
            $msgCancel = __('Cancel');
            $msgAction = __('Action');
            $msgEdit = __('Edit');
            $msgDelete = __('Delete');
            $msgDisplayGroup = __('Display Group');
            $msgMembers = __('Group Members');
            $msgPermissions = __('Permissions');

            $output = <<<END
            <div class="info_table">
            <table style="width:100%">
                <thead>
                    <tr>
                            <th>$msgDisplayGroup</th>
                            <th>$msgAction</th>
                    </tr>
                </thead>
                <tbody>
END;

            foreach ($displayGroups as $row)
            {
                $buttons = '';

                $displayGroupID	= $row['displaygroupid'];
                $displayGroup = $row['displaygroup'];

                if ($row['isdisplayspecific'] != 0)
                    continue;

                if ($row['edit'] == 1)
                {
                    // Show the edit button, members button
                    $buttons .= '<button class="XiboFormButton" href="index.php?p=displaygroup&q=MembersForm&DisplayGroupID=' . $displayGroupID . '&DisplayGroup=' . $displayGroup . '"><span>' . $msgMembers . '</span></button>';
                    $buttons .= '<button class="XiboFormButton" href="index.php?p=displaygroup&q=EditForm&DisplayGroupID=' . $displayGroupID . '"><span>' . $msgEdit . '</span></button>';
                }

                if ($row['del'] == 1)
                {
                    // Show the delete button
                    $buttons .= '<button class="XiboFormButton" href="index.php?p=displaygroup&q=DeleteForm&DisplayGroupID=' . $displayGroupID . '"><span>' . $msgDelete . '</span></button>';
                }

                if ($row['modifypermissions'] == 1)
                {
                    // Show the modify permissions button
                    $buttons .= '<button class="XiboFormButton" href="index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $displayGroupID . '"><span>' . $msgPermissions . '</span></button>';
                }

                $output .= <<<END
                <tr>
                    <td>$displayGroup</td>
                    <td>$buttons</td>
                </tr>
END;
            }

            $output .= "</tbody></table></div>";

            $response->SetGridResponse($output);
            $response->Respond();
	}
	
	/**
	 * Shows an add form for a display group
	 * @return 
	 */
	public function AddForm()
	{
		$db				=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();
		$helpManager	= new HelpManager($db, $user);
		
		// Help UI
		$nameHelp		= $helpManager->HelpIcon(__("The Name of this Group."), true);
		$descHelp		= $helpManager->HelpIcon(__("A short description of this Group."), true);
		
		$msgName		= __('Name');
		$msgDesc		= __('Description');
		$msgSave		= __('Save');
		$msgCancel		= __('Cancel');
		
		$form = <<<END
		<form id="DisplayGroupAddForm" class="XiboForm" action="index.php?p=displaygroup&q=Add" method="post">
			<table>
				<tr>
					<td>$msgName</td>
					<td>$nameHelp <input class="required" type="text" name="group" value="" maxlength="50"></td>
				</tr>
				<tr>
					<td>$msgDesc</span></td>
					<td>$descHelp <input type="text" name="desc" value="" maxlength="254"></td>
				</tr>
			</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Add Display Group'), '350px', '275px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Add') . '")');
		$response->AddButton($msgCancel, 'XiboDialogClose()');
		$response->AddButton($msgSave, '$("#DisplayGroupAddForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Shows an edit form for a display group
	 * @return 
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
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Display Group'));
		}
		
		// Pull out these columns
		if ($db->num_rows($result) == 0)
		{
			trigger_error(__('No display group found.'), E_USER_ERROR);
		}
		
		$row 			= $db->get_assoc_row($result);
		
		$displayGroup	= Kit::ValidateParam($row['DisplayGroup'], _STRING);
		$description	= Kit::ValidateParam($row['Description'], _STRING);
		
		// Help UI
		$nameHelp		= $helpManager->HelpIcon(__("The Name of this Group."), true);
		$descHelp		= $helpManager->HelpIcon(__("A short description of this Group."), true);
		
		$msgName		= __('Name');
		$msgDesc		= __('Description');
		$msgSave		= __('Save');
		$msgCancel		= __('Cancel');
		
		$form = <<<END
		<form id="DisplayGroupEditForm" class="XiboForm" action="index.php?p=displaygroup&q=Edit" method="post">
			<input type="hidden" name="DisplayGroupID" value="$displayGroupID" />
			<table>
				<tr>
					<td>$msgName</td>
					<td>$nameHelp <input class="required" type="text" name="group" value="$displayGroup" maxlength="50"></td>
				</tr>
				<tr>
					<td>$msgDesc</span></td>
					<td>$descHelp <input type="text" name="desc" value="$description" maxlength="254"></td>
				</tr>
			</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Edit Display Group'), '350px', '275px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Edit') . '")');
		$response->AddButton($msgCancel, 'XiboDialogClose()');
		$response->AddButton($msgSave, '$("#DisplayGroupEditForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Shows the Delete Group Form
	 * @return 
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
		
		$msgWarn		= __('Are you sure you want to delete?');
		
		//we can delete
		$form = <<<END
		<form id="DisplayGroupDeleteForm" class="XiboForm" method="post" action="index.php?p=displaygroup&q=Delete">
			<input type="hidden" name="DisplayGroupID" value="$displayGroupID" />
			<p>$msgWarn</p>
		</form>
END;
		
		$response->SetFormRequestResponse($form, __('Delete Display Group'), '350px', '175px');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Delete') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#DisplayGroupDeleteForm").submit()');
		$response->Respond();
	}
	
	public function MembersForm()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);
		
		// There needs to be two lists here.
		// One of which is the Displays currently assigned to this group
		// The other is a list of displays that are available to be assigned (i.e. the opposite of the first list)

		// Displays in group
		$SQL  = "";
		$SQL .= "SELECT display.DisplayID, ";
		$SQL .= "       display.Display ";
		$SQL .= "FROM   display ";
		$SQL .= "       INNER JOIN lkdisplaydg ";
		$SQL .= "       ON     lkdisplaydg.DisplayID = display.DisplayID ";
		$SQL .= sprintf("WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
		$SQL .= " ORDER BY display.Display ";
		
		if(!$resultIn = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Displays'), E_USER_ERROR);
		}
		
		// Displays not in group
		$SQL  = "";
		$SQL .= "SELECT display.DisplayID, ";
		$SQL .= "       display.Display ";
		$SQL .= "FROM   display ";
		$SQL .= " WHERE display.DisplayID NOT       IN ";
		$SQL .= "       (SELECT display.DisplayID ";
		$SQL .= "       FROM    display ";
		$SQL .= "               INNER JOIN lkdisplaydg ";
		$SQL .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
		$SQL .= sprintf("	WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
		$SQL .= "       )";
		$SQL .= " ORDER BY display.Display ";
		
		if(!$resultOut = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Displays'), E_USER_ERROR);
		}
		
		// Now we have an IN and an OUT results object which we can use to build our lists
		$listIn 	= '<ul id="displaysIn" href="index.php?p=displaygroup&q=SetMembers&DisplayGroupID=' . $displayGroupID . '" class="connectedSortable">';
		
		while($row = $db->get_assoc_row($resultIn))
		{
			// For each item output a LI
			$displayID	= Kit::ValidateParam($row['DisplayID'], _INT);
			$display	= Kit::ValidateParam($row['Display'], _STRING);
			
			$listIn		.= '<li id="DisplayID_' . $displayID . '"class="li-sortable">' . $display . '</li>';
		}
		$listIn		.= '</ul>';
		
		$listOut 	= '<ul id="displaysOut" class="connectedSortable">';
		
		while($row = $db->get_assoc_row($resultOut))
		{
			// For each item output a LI
			$displayID	= Kit::ValidateParam($row['DisplayID'], _INT);
			$display	= Kit::ValidateParam($row['Display'], _STRING);
			
			$listOut	.= '<li id="DisplayID_' . $displayID . '" class="li-sortable">' . $display . '</li>';
		}
		$listOut 	.= '</ul>';
		
		// Build the final form.
                $helpText   = '<center>' . __('Drag or double click to move items between lists') . '</center>';
		$form       = $helpText . '<div class="connectedlist"><h3>Members</h3>' . $listIn . '</div><div class="connectedlist"><h3>Non-members</h3>' . $listOut . '</div>';
		
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
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$displayGroup	= Kit::GetParam('group', _POST, _STRING);
		$description 	= Kit::GetParam('desc', _POST, _STRING);
		
		// Validation
		if ($displayGroup == '')
		{
			trigger_error(__('Please enter a display group name'), E_USER_ERROR);
		}
		
		if (strlen($description) > 254) 
		{
			trigger_error(__("Description can not be longer than 254 characters"), E_USER_ERROR);
		}
		
		$check 	= sprintf("SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = '%s' AND IsDisplaySpecific = 0", $displayGroup);
		$result = $db->query($check) or trigger_error($db->error());
		
		// Check for groups with the same name?
		if($db->num_rows($result) != 0) 
		{
			$response->SetError(sprintf(__("You already own a display group called '%s'.") .  __("Please choose another."), $displayGroup));
			$response->Respond();
		}
		
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
		
		// Validation
		if ($displayGroup == '')
		{
			trigger_error(__('Please enter a display group name'), E_USER_ERROR);
		}
		
		if (strlen($description) > 254) 
		{
			trigger_error(__("Description can not be longer than 254 characters"), E_USER_ERROR);
		}
		
		$check 	= sprintf("SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = '%s' AND IsDisplaySpecific = 0 AND DisplayGroupID <> %d ", $displayGroup, $displayGroupID);
		$result = $db->query($check) or trigger_error($db->error());
		
		// Check for groups with the same name?
		if($db->num_rows($result) != 0) 
		{
			$response->SetError(sprintf(__("You already own a display group called '%s'.") .  __("Please choose another.", $displayGroup)));
			$response->Respond();
		}
		
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

        // Form content
        $form = '<form id="DisplayGroupPermissionsForm" class="XiboForm" method="post" action="index.php?p=displaygroup&q=Permissions">';
	$form .= '<input type="hidden" name="displayGroupId" value="' . $displayGroupId . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

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

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayGroupPermissionsForm").submit()');
        $response->Respond();
    }

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