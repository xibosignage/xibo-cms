<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();	
					
		//display the display table
		$SQL = <<<SQL
		SELECT DisplayGroupID, DisplayGroup
		FROM displaygroup
		WHERE IsDisplaySpecific = 0
		ORDER BY DisplayGroup
SQL;

		if(!($results = $db->query($SQL))) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not list Display Groups"), E_USER_ERROR);
		}
		
		$msgSave			= __('Save');
		$msgCancel			= __('Cancel');
		$msgAction			= __('Action');
		$msgEdit			= __('Edit');
		$msgDelete			= __('Delete');
		$msgDisplayGroup 	= __('Display Group');
		$msgMembers			= __('Group Members');
		$msgGroupSecurity	= __('Group Security');
		
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
		
		while($row = $db->get_assoc_row($results))
		{
			$displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
			$displayGroup	= Kit::ValidateParam($row['DisplayGroup'], _STRING);
			
			// we only want to show certain buttons, depending on the user logged in
			if ($user->GetUserTypeID() != 1) 
			{
				//dont any actions
				$buttons = __("No available Actions");
			}
			else 
			{
				$buttons = <<<END
				<button class="XiboFormButton" href="index.php?p=displaygroup&q=MembersForm&DisplayGroupID=$displayGroupID&DisplayGroup=$displayGroup"><span>$msgMembers</span></button>
				<button class="XiboFormButton" href="index.php?p=displaygroup&q=GroupSecurityForm&DisplayGroupID=$displayGroupID&DisplayGroup=$displayGroup"><span>$msgGroupSecurity</span></button>
				<button class="XiboFormButton" href="index.php?p=displaygroup&q=EditForm&DisplayGroupID=$displayGroupID"><span>$msgEdit</span></button>
				<button class="XiboFormButton" href="index.php?p=displaygroup&q=DeleteForm&DisplayGroupID=$displayGroupID"><span>$msgDelete</span></button>
END;
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
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=DisplayGroup&Category=Add')");
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
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=DisplayGroup&Category=Edit')");
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
		
		$msgWarn		= __('Are you sure you want to delete?');
		
		//we can delete
		$form = <<<END
		<form id="DisplayGroupDeleteForm" class="XiboForm" method="post" action="index.php?p=displaygroup&q=Delete">
			<input type="hidden" name="DisplayGroupID" value="$displayGroupID" />
			<p>$msgWarn</p>
		</form>
END;
		
		$response->SetFormRequestResponse($form, __('Delete Display Group'), '350px', '175px');
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=DisplayGroup&Category=Delete')");
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
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=DisplayGroup&Category=Members')");
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), 'MembersSubmit()');
		$response->Respond();
	}
	
	/**
	 * Shows a list of groups that have permission on a particular display.
	 * @return 
	 */
	public function GroupSecurityForm()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);
		$displayGroup	= Kit::GetParam('DisplayGroup', _REQUEST, _STRING);
		
		// There needs to be two lists here.

		// Groups allowed access
		$SQL  = "";
		$SQL .= "SELECT group.GroupID, ";
		$SQL .= "       group.Group ";
		$SQL .= "FROM   `group` ";
		$SQL .= "       INNER JOIN lkgroupdg ";
		$SQL .= "       ON     lkgroupdg.GroupID = group.GroupID ";
		$SQL .= sprintf("WHERE  lkgroupdg.DisplayGroupID   = %d", $displayGroupID);
		
		if(!$resultIn = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Displays'));
		}
		
		// Displays not in group
		$SQL  = "";
		$SQL .= "SELECT group.GroupID, ";
		$SQL .= "       group.Group ";
		$SQL .= "FROM   `group` ";
		$SQL .= " WHERE group.GroupID NOT  IN ( ";
		$SQL .= "		SELECT group.GroupID ";
		$SQL .= "		FROM   `group` ";
		$SQL .= "		       INNER JOIN lkgroupdg ";
		$SQL .= "		       ON     lkgroupdg.GroupID = group.GroupID ";
		$SQL .= sprintf("	WHERE  lkgroupdg.DisplayGroupID   = %d", $displayGroupID);
		$SQL .= " ) AND `group`.IsEveryone = 0      ";
		
		if(!$resultOut = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Displays'));
		}
		
		// Now we have an IN and an OUT results object which we can use to build our lists
		$listIn 	= '<ul id="groupsIn" href="index.php?p=displaygroup&q=SetGroupSecurity&DisplayGroupID=' . $displayGroupID . '" class="connectedSortable">';
		
		while($row = $db->get_assoc_row($resultIn))
		{
			// For each item output a LI
			$groupID	= Kit::ValidateParam($row['GroupID'], _INT);
			$group		= Kit::ValidateParam($row['Group'], _STRING);
			
			$listIn		.= '<li id="GroupID_' . $groupID . '"class="li-sortable">' . $group . '</li>';
		}
		$listIn		.= '</ul>';
		
		$listOut 	= '<ul id="groupsOut" class="connectedSortable">';
		
		while($row = $db->get_assoc_row($resultOut))
		{
			// For each item output a LI
			$groupID	= Kit::ValidateParam($row['GroupID'], _INT);
			$group		= Kit::ValidateParam($row['Group'], _STRING);
			
			$listOut	.= '<li id="GroupID_' . $groupID . '" class="li-sortable">' . $group . '</li>';
		}
		$listOut 	.= '</ul>';
		
		// Build the final form.
                $helpText       = '<center>' . __('Drag or double click to move items between lists') . '</center>';
		$form		= $helpText . '<div class="connectedlist firstList"><h3>Members</h3>' . $listIn . '</div><div class="connectedlist"><h3>Non-members</h3>' . $listOut . '</div>';
		
		$response->SetFormRequestResponse($form, __('Manage Group Security for' . ' ' . $displayGroup), '400', '375', 'GroupSecurityCallBack');
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=DisplayGroup&Category=GroupSecurity')");
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), 'GroupSecuritySubmit()');
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
		
		// Deal with the Edit
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
	 * Sets the Group Security
	 * @return 
	 */
	public function SetGroupSecurity()
	{
		$db 			=& $this->db;	
		$response		= new ResponseManager();
		$displayGroupSecurityObject = new DisplayGroupSecurity($db);
	
		$displayGroupID	= Kit::GetParam('DisplayGroupID', _REQUEST, _INT);
		$groups			= Kit::GetParam('GroupID', _POST, _ARRAY, array());
		$members		= array();
		
		// Current Members
		$SQL  = "";
		$SQL .= "SELECT group.GroupID, ";
		$SQL .= "       group.Group ";
		$SQL .= "FROM   `group` ";
		$SQL .= "       INNER JOIN lkgroupdg ";
		$SQL .= "       ON     lkgroupdg.GroupID = group.GroupID ";
		$SQL .= sprintf("WHERE  lkgroupdg.DisplayGroupID   = %d", $displayGroupID);
		
		if(!$resultIn = $db->query($SQL))
		{
                    trigger_error($db->error());
                    trigger_error(__('Error getting Groups with Security permissions for this Display Group.'));
		}
		
		while($row = $db->get_assoc_row($resultIn))
		{
			// Test whether this ID is in the array or not
			$groupID	= Kit::ValidateParam($row['GroupID'], _INT);
			
			if(!in_array($groupID, $groups))
			{
				// Its currently assigned but not in the $displays array
				//  so we unassign
				if (!$displayGroupSecurityObject->Unlink($displayGroupID, $groupID))
				{
					trigger_error($displayGroupSecurityObject->GetErrorMessage(), E_USER_ERROR);
				}
			}
			else
			{
				$members[] = $groupID;
			}
		}
		
		foreach($groups as $groupID)
		{
			// Add any that are missing
			if(!in_array($groupID, $members))
			{
				if (!$displayGroupSecurityObject->Link($displayGroupID, $groupID))
				{
					trigger_error($displayGroupSecurityObject->GetErrorMessage(), E_USER_ERROR);
				}
			}
		}
		
		$response->SetFormSubmitResponse(__('Group security set'), false);
		$response->Respond();
	}
}  
?>