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
class groupDAO {
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
		$this->db 	=& $db;
		$this->user =& $user;
		
		if ($_SESSION['usertype']==1) $this->isadmin = true;
		
		if (isset($_REQUEST['sp'])) {
			$this->sub_page = $_REQUEST['sp'];
		}
		else {
			$this->sub_page = "view";
		}
		
		if (isset($_REQUEST['groupid']) && $_REQUEST['groupid'] != "") {
								
			$this->groupid = $_REQUEST['groupid'];
			
			$SQL = <<<END
			SELECT 	group.groupID,
					group.group
			FROM `group`
			WHERE groupID = $this->groupid
END;
			
			if (!$results = $db->query($SQL)) {
				trigger_error($db->error());
				trigger_error("Can not get Group information.", E_USER_ERROR);
			}
			
			$aRow = $db->get_row($results);
			
			$this->group 			= $aRow[1];
		}
	}
	
	function on_page_load() {
		return "";
	}
	
	function echo_page_heading() {
		echo "Group Admin";
		return true;
	}
	
	function group_filter() {
		$db =& $this->db;
		
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['group']['name'])) $filter_name = $_SESSION['group']['name'];
		
		$output = <<<END
		<form id="filter_form">
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
		echo $output;
	}
	
	function group_view() {
		$db =& $this->db;
		
		$filter_name = clean_input($_REQUEST['name'], VAR_FOR_SQL, $db);
		setSession('group', 'name', $filter_name);
	
		$SQL = <<<END
		SELECT 	group.group,
				group.groupID
		FROM `group`
		WHERE 1=1
END;
		if ($filter_name != "") {
			$SQL .= " AND group.group LIKE '%$filter_name%' ";
		}
		
		$SQL .= " ORDER BY group.group ";
		
		if (!$results = $db->query($SQL)) {
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
		echo $table;
		
		while ($row = $db->get_row($results)) {
		
			$group 		= $row[0];
			$groupid	= $row[1];
			
			//we only want to show certain buttons, depending on the user logged in
			if ($_SESSION['usertype']!="1") {
				//dont any actions
				$buttons = "No available Actions";
			}
			else {
				$buttons = <<<END
				<a class="positive" href="index.php?p=group&q=group_form&groupid=$groupid" onclick="return grid_form(this,'Edit Group',dialog_filter,'group_filter_form','pages_grid',600,570)"><span>Edit</span></a>
				<a class="negative" href="index.php?p=group&q=delete_form&groupid=$groupid" onclick="return init_button(this,'Delete Group',exec_filter_callback,set_form_size(350,160))"><span>Delete</span></a>
END;
			}
			
			$table = <<<END
			<tr>
				<td>$group</td>
				<td>
					<div class="buttons">
						$buttons
					</div>
				</td>
			</tr>
END;
			echo $table;
		}
		echo "</tbody></table></div>";
		
	}
	
	function displayPage() {
		$db =& $this->db;
		
		if (!$this->has_permissions) {
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) {
				
			case 'view':
				require("template/pages/group_view.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	function group_form() 
	{
		$db				=& $this->db;
		$user			=& $this->user;
		
		$helpManager		= new HelpManager($db, $user);
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		//alter the action variable depending on which form we are after
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
		<form class="dialog_form" action="$action" method="post">
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

		if ($this->groupid == "") 
		{
			//add form, so finish
			$arh->decode_response(true,$form);
		}

		//if we get here we are an edit form - and therefore want a grid showing all the assigned / unassigned pages
		$form .= <<<END
	<form id="group_filter_form" onsubmit="false">
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
	<div id="paging_dialog">
		<form>
			<img src="img/forms/first.png" class="first"/>
			<img src="img/forms/previous.png" class="prev"/>
			<input type="text" class="pagedisplay" readonly size="5"/>
			<img src="img/forms/next.png" class="next"/>
			<img src="img/forms/last.png" class="last"/>
			<select class="pagesize">
				<option selected="selected" value="10">10</option>
				<option value="20">20</option>
				<option value="30">30</option>
				<option  value="40">40</option>
			</select>
		</form>
	</div>
	<div id="pages_grid"></div>
END;
	
		$arh->decode_response(true,$form);
		return true;
	}
	
	function data_grid() {
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
		if(!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can't get this groups information", E_USER_ERROR);
		}
		
		if ($db->num_rows($results)==0) {
			echo "";
			exit;
		}
		
		//some table headings
		$form = <<<END
		<form id="group_media" method="post" action="index.php?p=group&q=assign" onsubmit="return ajax_submit_form(this,'#div_dialog',dialog_filter)">
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

		//while loop
		while ($row = $db->get_row($results)) {
			
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
		echo $form;
		exit;
	}
	
	function delete_form() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		//expect the playlistID to be set
		$groupid = $this->groupid;
		
		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=group&q=delete">
			<input type="hidden" name="groupid" value="$groupid">
			<p>Are you sure you want to delete $this->group?</p>
			<input type="submit" value="Yes">
			<input type="submit" value="No" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;
		
		
		$arh->decode_response(true, $form);
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
	
	function edit() {
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
		
		$SQL = "UPDATE `group` SET `group` = '$group' WHERE groupid = $groupid ";
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			$arh->decode_response(false,"Can not edit the group record");
		}

		
		$arh->decode_response(true,'Group Edited');
		
		return true;	
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
	function assign() {
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