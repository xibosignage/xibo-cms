<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
 
class groupDAO 
{
	private $db;
	private $user;
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";
	
	//general fields
	private $groupid;
	private $group = "";
	
	//lkpage group
	private $lkpagegroupid;
	private $pageid;
	
	//init
	function __construct(database $db, user $user) 
	{
		$this->db 		=& $db;
		$this->user 	=& $user;
		$this->sub_page = Kit::GetParam('sp', _REQUEST, _WORD, 'view');
		
		$usertype 		= Kit::GetParam('usertype', _SESSION, _INT, 0);
		$this->groupid	= Kit::GetParam('groupid', _REQUEST, _INT, 0);
		
		if ($usertype == 1) $this->isadmin = true;
		
		// Do we have a user group selected?
		if ($this->groupid != 0) 
		{						
			// If so then we will need to get some information about it
			$SQL = <<<END
			SELECT 	group.GroupID,
					group.Group
			FROM `group`
			WHERE groupID = %d
END;
			
			$SQL = sprintf($SQL, $this->groupid);
			
			if (!$results = $db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error("Can not get Group information.", E_USER_ERROR);
			}
			
			$aRow = $db->get_assoc_row($results);
			
			$this->group = $aRow['Group'];
		}
	}
	
	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		echo "Group Admin";
		return true;
	}
	
	/**
	 * Filter Form for the group page
	 * Included by the template view
	 * Not AJAX
	 * @return 
	 */
	function GroupGrid() 
	{
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['group']['name'])) $filter_name = $_SESSION['group']['name'];
		
		$filterForm = <<<END
		<form>
			<input type="hidden" name="p" value="group">
			<input type="hidden" name="q" value="group_view">
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" value="$filter_name"></td>
				</tr>
			</table>
		</form>
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
	 * Group Grid
	 * Called by AJAX
	 * @return 
	 */
	function group_view() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		
		$filter_name = Kit::GetParam('name', _POST, _STRING);
		
		setSession('group', 'name', $filter_name);
	
		$SQL = <<<END
		SELECT 	group.group,
				group.groupID
		FROM `group`
		WHERE 1 = 1
END;
		if ($filter_name != '') 
		{
			$SQL .= sprintf(" AND group.group LIKE '%%%s%%' ", $db->escape_string($filter_name));
		}
		
		$SQL .= " ORDER BY group.group ";
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can not get group information.", E_USER_ERROR);
		}
		
		$table = <<<END
		<div class="info_table">
		<table style="width:100%;">
			<thead>
				<tr>
					<th>Name</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
END;
		
		while ($row = $db->get_assoc_row($results)) 
		{
			$group 		= Kit::ValidateParam($row['group'], _STRING);
			$groupid	= Kit::ValidateParam($row['groupID'], _INT);
			
			$editButtonId 		= uniqid();
			$pageSecButtonId 	= uniqid();
			$deleteButtonId 	= uniqid();
			
			Debug::LogEntry($db, 'audit', 'UserTypeID is: ' . $user->GetUserTypeID());
			
			// we only want to show certain buttons, depending on the user logged in
			if ($user->GetUserTypeID() != 1) 
			{
				//dont any actions
				$buttons = "No available Actions";
			}
			else 
			{
				$buttons = <<<END
				<a id="$editButtonId" class="XiboFormButton positive" href="index.php?p=group&q=GroupForm&groupid=$groupid"><span>Edit</span></a>
				<a id="$pageSecButtonId" class="XiboFormButton positive" href="index.php?p=group&q=PageSecurityForm&groupid=$groupid"><span>Page Security</span></a>
				<a id="$deleteButtonId" class="XiboFormButton negative" href="index.php?p=group&q=delete_form&groupid=$groupid"><span>Delete</span></a>
END;
			}
			
			$table .= <<<END
			<tr>
				<td>$group</td>
				<td>
					<div class="buttons">
						$buttons
					</div>
				</td>
			</tr>
END;
		}
		$table .= "</tbody></table></div>";
		
		// Construct the Response
		$response 				= array();
		$response['html'] 		= $table;
		$response['success']	= true;
		$response['sortable']	= true;
		$response['sortingDiv']	= '.info_table table';
		
		Kit::Redirect($response);
	}
	
	function displayPage() 
	{
		if (!$this->has_permissions) 
		{
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) 
		{
				
			case 'view':
				require("template/pages/group_view.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	function GroupForm() 
	{
		$db				=& $this->db;
		$user			=& $this->user;
		
		$helpManager	= new HelpManager($db, $user);
				
		// alter the action variable depending on which form we are after
		if ($this->groupid == "") 
		{
			$action = "index.php?p=group&q=add";
		}
		else 
		{
			$action = "index.php?p=group&q=edit";
		}
		
		// Help UI
		$helpButton 	= $helpManager->HelpButton("content/users/groups", true);
		$nameHelp		= $helpManager->HelpIcon("The Name of this Group.", true);
		
		$form = <<<END
		<form class="XiboForm" action="$action" method="post">
			<input type="hidden" name="groupid" value="$this->groupid">
			<table>
				<tr>
					<td>Name<span class="required">*</span></td>
					<td>$nameHelp <input type="text" name="group" value="$this->group"></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type='submit' value="Save" / >
						<input id="btnCancel" type="button" title="No / Cancel" onclick="$('#div_dialog').dialog('close');return false; " value="Cancel" />	
						$helpButton
					</td>
				</tr>
			</table>
		</form>
END;

		// Construct the Response
		$response 					= array();
		$response['html'] 			= $form;
		$response['success']		= true;
		$response['dialogSize']		= true;
		$response['dialogWidth']	= '400px';
		$response['dialogHeight'] 	= '180px';
		$response['dialogTitle']	= 'Add/Edit Group';
		
		Kit::Redirect($response);

		return true;
	}
	
	function PageSecurityForm()
	{
		$form = <<<HTML
		<form>
			<input type="hidden" name="p" value="group">
			<input type="hidden" name="q" value="data_grid">
			<input type="hidden" name="groupid" value="$this->groupid">
			<table style="display:none;" id="group_filterform" class="filterform">
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" id="name"></td>
				</tr>
			</table>
		</form>
HTML;
		
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$form
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		
		// Construct the Response
		$response 					= array();
		$response['html'] 			= $xiboGrid;
		$response['success']		= true;
		$response['dialogSize']		= true;
		$response['dialogWidth']	= '500px';
		$response['dialogHeight'] 	= '380px';
		$response['dialogTitle']	= 'Page Security';
		
		Kit::Redirect($response);

		return true;
	}
	
	function data_grid() 
	{
		$db =& $this->db;
		
		$groupid = $_REQUEST['groupid'];
		
		$SQL = <<<END
		SELECT 	pagegroup.pagegroup,
				pagegroup.pagegroupID,
				CASE WHEN pages_assigned.pagegroupID IS NULL 
					THEN '<img src="img/disact.gif">'
		        	ELSE '<img src="img/act.gif">'
		        END AS Assigned,
				CASE WHEN pages_assigned.pagegroupID IS NULL 
					THEN 0
		        	ELSE 1
		        END AS AssignedID
		FROM	pagegroup
		LEFT OUTER JOIN 
				(SELECT DISTINCT pages.pagegroupID
				 FROM	lkpagegroup
				 INNER JOIN pages ON lkpagegroup.pageID = pages.pageID
				 WHERE  groupID = $groupid
				) pages_assigned
		ON pagegroup.pagegroupID = pages_assigned.pagegroupID
END;
		if(!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can't get this groups information", E_USER_ERROR);
		}
		
		if ($db->num_rows($results) == 0) 
		{
			echo "";
			exit;
		}
		
		//some table headings
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=group&q=assign">
			<input type="hidden" name="groupid" value="$groupid">
			<div class="dialog_table">
			<table style="width:100%">
				<thead>
					<tr>
					<th></th>
					<th>Security Group</th>
					<th>Assigned</th>
					</tr>
				</thead>
				<tbody>
END;

		// while loop
		while ($row = $db->get_row($results)) 
		{
			$name		= $row[0];
			$pageid		= $row[1];
			$assigned	= $row[2];
			$assignedid	= $row[3];
			
			$form .= "<tr>";
			$form .= "<td><input type='checkbox' name='pageids[]' value='$assignedid,$pageid'></td>";
			$form .= "<td>$name</td>";
			$form .= "<td>$assigned</td>";
			$form .= "</tr>";
		}

		//table ending
		$form .= <<<END
			</tbody>
		</table>
		</div>
		<input type='submit' value="Assign / Unassign" / >
	</form>
END;
		
		// Construct the Response
		$response 				= array();
		$response['html'] 		= $form;
		$response['success']	= true;
		$response['sortable']	= false;
		$response['sortingDiv']	= '.info_table table';
		
		Kit::Redirect($response);
	}
	
	function delete_form() 
	{
		$db =& $this->db;
		
		//expect the playlistID to be set
		$groupid = $this->groupid;
		
		//we can delete
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=group&q=delete">
			<input type="hidden" name="groupid" value="$groupid">
			<p>Are you sure you want to delete $this->group?</p>
			<input type="submit" value="Yes">
			<input type="submit" value="No" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;
		
		// Construct the Response
		$response 					= array();
		$response['html'] 			= $form;
		$response['success']		= true;
		$response['dialogSize']		= true;
		$response['dialogWidth']	= '400px';
		$response['dialogHeight'] 	= '180px';
		$response['dialogTitle']	= 'Delete Group';
		
		Kit::Redirect($response);
	}
	
	function add() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$group 		= $_POST['group'];
		$groupid 	= $_POST['groupid'];
		
		$userid 	= $_SESSION['userid'];
		
		//check on required fields
		if ($group == "") {
			$arh->decode_response(false,'group must have a value');
		}
		
		//add the group record
		$SQL = "INSERT INTO `group` (`group`) ";
		$SQL.= " VALUES ('$group') ";
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			$arh->decode_response(false,"Can not add the group");
		}
		
		$arh->decode_response(true,'group Added');
		
		return true;	
	}
	
	/**
	 * Edits the Group Information
	 * @return 
	 */
	function edit() 
	{
		$db 		=& $this->db;
		
		$groupid 	= Kit::GetParam('groupid', _POST, _INT);
		$group 		= Kit::GetParam('group', _POST, _STRING);
		
		$userid 	= $_SESSION['userid'];
		
		//check on required fields
		if ($group == "") 
		{
			Kit::Redirect(array('success'=>false, 'message' => 'Group Name cannot be empty.'));
		}
		
		$SQL = sprintf("UPDATE `group` SET `group` = '%s' WHERE groupid = %d ", $db->escape_string($group), $groupid);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			Kit::Redirect(array('success'=>false, 'message' => 'Unexpected error editing the Group'));
		}

		// Construct the Response
		$response 					= array();
		$response['success']		= true;
		$response['message']		= 'Edited the Group';
		
		Kit::Redirect($response);	
	}
	
	function delete() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$groupid 	= $_REQUEST['groupid'];
		
		$SQL = "DELETE FROM `group` WHERE groupid = $groupid";
	
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			$arh->decode_response(false,'You can not delete this group. There are either page permissions assigned, or users with this group');
		}
		
		$arh->decode_response(true,'Group Deleted');
	
		return true;
	}
	
	/**
	 * Assigns and unassigns pages from groups
	 * @return ajax request handler
	 */
	function assign() 
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$pageids 	= $_POST['pageids'];
		$groupid 	= $_POST['groupid'];
		
		foreach ($pageids as $pagegroupid) {
			
			$row = explode(",",$pagegroupid);
			
			//the page ID actuall refers to the pagegroup ID - we have to look up all the page ID's for this
			//pagegroupid
			$SQL = "SELECT pageID FROM pages WHERE pagegroupID = " . $row[1];
			if(!$results = $db->query($SQL)) {
				trigger_error($db->error());
				$arh->decode_response(false,"Can't assign this page to this group [error getting pages]");
			}
			
			while ($page_row = $db->get_row($results)) {
				
				$pageid = $page_row[0];
			
				if ($row[0]=="0") {
					//it isnt assigned and we should assign it
					$SQL = "INSERT INTO lkpagegroup (groupID, pageID) VALUES ($groupid, $pageid)";
					
					if(!$db->query($SQL)) {
						trigger_error($db->error());
						$arh->decode_response(false,"Can't assign this page to this group");
					}
				}
				else { 
					//it is already assigned and we should remove it
					$SQL = "DELETE FROM lkpagegroup WHERE groupid = $groupid AND pageID = $pageid";
					
					if(!$db->query($SQL)) {
						trigger_error($db->error());
						$arh->decode_response(false,"Can't remove this page from this group");
					}
				}	
			}
		}
		
		$arh->response(AJAX_SUCCESS_NOREDIRECT,"Pages Assigned");
		
		return false;
	}
}
?>