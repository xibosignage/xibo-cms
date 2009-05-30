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
		
		$msgSave	= __('Save');
		$msgCancel	= __('Cancel');
		$msgAction	= __('Action');
		$msgEdit	= __('Edit');
		$msgDelete	= __('Delete');
		$msgDisplayGroup = __('Display Group');
		
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
			
			$output .= <<<END
			<tr>
				<td>$displayGroup</td>
				<td></td>
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
		$helpButton 	= $helpManager->HelpButton("displays/groups", true);
		$nameHelp		= $helpManager->HelpIcon(__("The Name of this Group."), true);
		$descHelp		= $helpManager->HelpIcon(__("A short description of this Group."), true);
		
		$msgName		= __('Name');
		$msgDesc		= __('Description');
		$msgSave		= __('Save');
		$msgCancel		= __('Cancel');
		
		$form = <<<END
		<form class="XiboForm" action="index.php?p=displaygroup&q=Add" method="post">
			<table>
				<tr>
					<td>$msgName</td>
					<td>$nameHelp <input class="required" type="text" name="group" value="" maxlength="50"></td>
				</tr>
				<tr>
					<td>$msgDesc</span></td>
					<td>$descHelp <input type="text" name="desc" value="" maxlength="254"></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type='submit' value="$msgSave" / >
						<input id="btnCancel" type="button" title="$msgCancel" onclick="$('#div_dialog').dialog('close');return false; " value="$msgCancel" />	
						$helpButton
					</td>
				</tr>
			</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Add Display Group'), '350px', '275px');
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
		
		if ($result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting Display Group'));
		}
		
		//TODO: The rest
		
		// Help UI
		$helpButton 	= $helpManager->HelpButton("displays/groups", true);
		$nameHelp		= $helpManager->HelpIcon(__("The Name of this Group."), true);
		$descHelp		= $helpManager->HelpIcon(__("A short description of this Group."), true);
		
		$msgName		= __('Name');
		$msgDesc		= __('Description');
		$msgSave		= __('Save');
		$msgCancel		= __('Cancel');
		
		$form = <<<END
		<form class="XiboForm" action="index.php?p=displaygroup&q=Edit" method="post">
			<input type="hidden" name="DisplayGroupID" value="$displayGroupID" />
			<table>
				<tr>
					<td>$msgName</td>
					<td>$nameHelp <input class="required" type="text" name="group" value="" maxlength="50"></td>
				</tr>
				<tr>
					<td>$msgDesc</span></td>
					<td>$descHelp <input type="text" name="desc" value="" maxlength="254"></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type='submit' value="$msgSave" / >
						<input id="btnCancel" type="button" title="$msgCancel" onclick="$('#div_dialog').dialog('close');return false; " value="$msgCancel" />	
						$helpButton
					</td>
				</tr>
			</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Add Display Group'), '350px', '275px');
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
			$response->SetError(sprintf(__("You already own a display group called '%s'.") .  __("Please choose another.", $displayGroup)));
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
}  
?>