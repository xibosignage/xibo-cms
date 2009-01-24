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

class userDAO 
{
	private $db;
	private $user;
	private $sub_page;
	
	//database fields
	private $userid;
	private $username;
	private $password;
	private $usertypeid;
	private $email;
	private $homepage;
	private $groupid;

	/**
	 * Contructor
	 *
	 * @param database $db
	 * @return userDAO
	 */
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		$this->sub_page = Kit::GetParam('sp', _REQUEST, _WORD, 'view');
		$userid 		= Kit::GetParam('userID', _REQUEST, _INT, 0);

		if($userid != 0) 
		{
			$this->sub_page = "edit";
			
			$this->userid = $userid;

			$sql = " SELECT UserName, UserPassword, usertypeid, email, groupID, homepage FROM user";
			$sql .= sprintf(" WHERE userID = %d", $userid);

			if(!$results = $db->query($sql)) trigger_error("Error excuting query".$db->error(), E_USER_ERROR);

			while($aRow = $db->get_row($results)) 
			{
				$this->username 	= Kit::ValidateParam($aRow[0], _USERNAME);
				$this->password 	= Kit::ValidateParam($aRow[1], _PASSWORD);
				$this->usertypeid 	= Kit::ValidateParam($aRow[2], _INT);
				$this->email 		= Kit::ValidateParam($aRow[3], _STRING);
				$this->groupid 		= Kit::ValidateParam($aRow[4], _INT);
				$this->homepage 	= Kit::ValidateParam($aRow[5], _STRING);
			}
		}
	}

	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		echo "Users";
		return true;
	}

	/**
	 * Adds a user
	 *
	 * @return unknown
	 */
	function AddUser () 
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();

		$user		= Kit::GetParam('username', _POST, _USERNAME);
		$password 	= md5(Kit::GetParam('password', _POST, _USERNAME));
		$usertypeid = Kit::GetParam('usertypeid', _POST, _INT);
		$email 		= Kit::GetParam('email', _POST, _STRING);
		$groupid	= Kit::GetParam('groupid', _POST, _INT);
		
		//Construct the Homepage
		$homepage	= "dashboard";

		//Validation
		if ($user=="") $arh->decode_response(false, "Please enter a User Name.");
		if ($password=="") $arh->decode_response(false, "Please enter a Password.");
		if ($email == "") $arh->decode_response(false, "Please enter an Email Address.");
		
		if ($homepage == "") $homepage = "dashboard";

		//Check for duplicate user name
		$sqlcheck = " ";
		$sqlcheck .= sprintf("SELECT UserName FROM user WHERE UserName = '%s'", $db->escape_string($user));

		if(!$sqlcheckresult = $db->query($sqlcheck)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Cant get this user's name. Please try another.");			
		}
		
		if($db->num_rows($sqlcheckresult) != 0) 
		{
			$arh->decode_response(false, "Could Not Complete, Duplicate User Name Exists");
		}
		
		//Ready to enter the user into the database
		$query = "INSERT INTO user (UserName, UserPassword, usertypeid, email, homepage, groupid)";
		$query .= " VALUES ('$user', '$password', $usertypeid, '$email', '$homepage', $groupid)";
		
		if(!$id = $db->insert_query($query)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Error adding that user");
		}

		$arh->decode_response(true, "$user Added");
		return;
	}

	/**
	 * Modifys a user
	 *
	 * @return unknown
	 */
	function EditUser() 
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
			
		$error = "";

		$userID 	= $_POST['userid'];
		$username 	= $_POST['username'];
		$password 	= md5($_POST['password']);
		$email 		= $_POST['email'];
		$usertypeid = $_POST['usertypeid'];
		$homepage 	= $_POST['homepage'];
		$groupid	= $_POST['groupid'];
		$pass_change = isset($_POST['pass_change']);

		//Validation
		if ($username=="") $arh->decode_response(false, "Please enter a User Name.");
		if ($password=="") $arh->decode_response(false, "Please enter a Password.");
		if ($email == "") $arh->decode_response(false, "Please enter an Email Address.");
		
		if ($homepage == "") $homepage = "dashboard";

		//Check for duplicate user name
		$sqlcheck = " ";
		$sqlcheck .= "SELECT UserName FROM user WHERE UserName = '" . $username . "' AND userID <> $userID ";

		if (!$sqlcheckresult = $db->query($sqlcheck)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Cant get this user's name. Please try another.");			
		}
		
		if ($db->num_rows($sqlcheckresult) != 0) 
		{
			$arh->decode_response(false, "Could Not Complete, Duplicate User Name Exists");
		}

		//Everything is ok - run the update
		$sql = "UPDATE user SET UserName = '$username'";
		if ($pass_change) 
		{
			$sql .= ", UserPassword = '$password'";
		}
		
		$sql .= ", email = '$email' ";
		if ($homepage == 'dashboard')
		{
			//acts as a reset
			$sql .= ", homepage='$homepage' ";
		}
		
		if ($usertypeid != "")
		{
			$sql .= ", usertypeid =  " . $usertypeid . ", groupID = $groupid ";
		}
		$sql .= " WHERE UserID = ". $userID . "";

		if (!$db->query($sql)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Error updating that user");
		}

		$arh->decode_response(true, "User Edited");
		return;
	}

	/**
	 * Deletes a user
	 *
	 * @param int $id
	 * @return unknown
	 */
	function DeleteUser() 
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		$userid = $_POST['userid'];

		$sqldel = " ";
		$sqldel .= "DELETE FROM user";
		$sqldel .= " WHERE UserID = ". $userid . "";

		if (!$db->query($sqldel)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "This user has been active, you may only retire them.");
		}

		//We should delete this users sessions record.
		$SQL = "DELETE FROM session WHERE userID = $userID ";
		
		if (!$db->query($sqldel)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "If logged in, this user will be deleted once they log out.");
		}
		
		$arh->decode_response(true, "User Deleted");
		return;
	}

	/**
	 * Prints the user information in a table based on a check box selection
	 *
	 */
	function data_table() 
	{
		$db =& $this->db;

		$itemName = $_REQUEST['usertypeid'];
		
		$username = $_REQUEST['username'];
		

		$sql = "SELECT user.UserID, user.UserName, user.usertypeid, user.loggedin, user.lastaccessed, user.email, user.homepage, group.group ";
		$sql .= " FROM user ";
		$sql .= " INNER JOIN `group` ON user.groupid = group.groupID ";
		$sql .= " WHERE 1=1 ";
		if ($_SESSION['usertype']==3) 
		{
			$sql .= " AND usertypeid=3 AND userid = " . $_SESSION['userid'] . " ";
		}
		if($itemName!="all") 
		{
			$sql .= " AND usertypeid=\"" . $itemName . "\"";
		}
		if ($username != "") 
		{
			$sql .= " AND UserName LIKE '%$username%' ";	
		}
		$sql .= " ORDER by UserName";
		
		//get the results
		if (!$results = $db->query($sql)) 
		{
			trigger_error($db->error());
			trigger_error("Can not get the user information", E_USER_ERROR);
		}

		$table = <<<END
		<div class="info_table">
		<table style="width:100%">
			<thead>
				<tr>
					<th>Name</th>
					<th>Homepage</th>
					<th>Layout</th>
					<th>Email</th>
					<th>Group</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
END;
		echo $table;
		
		while($aRow = $db->get_row($results)) {
			$userID 	= $aRow[0];
			$userName 	= $aRow[1];
			$usertypeid = $aRow[2];
			$loggedin 	= $aRow[3];
			$lastaccessed = $aRow[4];
			$email 		= $aRow[5];
			$homepage	= $aRow[6];
			$group		= $aRow[7];

			if($loggedin==1) 
			{
				$loggedin="<img src=\"img/act.gif\">";
			}
			else 
			{
				$loggedin="<img src=\"img/disact.gif\">";
			}
			
			//parse the homepage name, split into & seperated bits.
			$homepageArray = explode('&', $homepage);
			
			if (count($homepageArray) > 1)
			{
				list($temp, $layoutid) = explode('=', $homepageArray[1]);
			
				//Look up the layout name
				$SQL = "SELECT layout FROM layout WHERE layoutID = $layoutid ";
				if (!$result = $db->query($SQL))
				{
					trigger_error("Incorrect home page setting, please contact your system admin.", E_USER_ERROR);
				}
				
				$row = $db->get_row($result);
				
				$layout = $row[0];
			}
			else
			{
				$layout = "";
			}

			if($_SESSION['usertype'] == 1 ||($userID == $_SESSION['userid'])) 
			{
				echo "<tr href='index.php?p=user&q=display_form&userID=$userID' ondblclick=\"return init_button(this,'Edit User', exec_filter_callback, set_form_size(550,350))\">";
			}
			else
			{
				echo "<tr>";
			}
			echo "<td>" . $userName . "</td>";
			echo "<td>" . $homepageArray[0] . "</td>";
			echo "<td>" . $layout . "</td>";
			echo "<td>" . $email . "</td>";
			echo "<td>" . $group . "</td>";
			echo "<td><div class='buttons'>";
			if($_SESSION['usertype'] == 1 ||($userID == $_SESSION['userid'])) 
			{
				echo "<a class='positive' href='index.php?p=user&q=display_form&userID=$userID' onclick=\"return init_button(this,'Edit User', exec_filter_callback, set_form_size(550,350))\"><span>Edit</span></a>";
				echo "<a class='negative' href='index.php?p=user&q=DeleteForm&userID=$userID' onclick=\"return init_button(this,'Delete User', exec_filter_callback, set_form_size(450,250))\"><span>Delete</span></a></div></td>";
			}
			else 
			{
				echo "</td>";
			}
			echo "</tr>";
		}
		echo "</tbody></table></div>";
		exit;
	}

	/**
	 * Controls which pages are to be displayed
	 * @return 
	 */
	function displayPage() 
	{
		$db =& $this->db;

		switch ($this->sub_page) 
		{
			
			case 'view':
				include('template/pages/user_view.php');
				break;
				
			default:
				break;
		}
	}
	
	/**
	 * Outputs the filter page
	 * @return 
	 */
	function filter() 
	{
		$db =& $this->db;
		
		$usertype_list = dropdownlist("SELECT 'all', 'All' as usertype UNION SELECT usertypeID, usertype FROM usertype ORDER BY usertype", "usertypeid", 'all');
		
		$output = <<<END
		<form id="filter_form" onsubmit="return false">
			<input type="hidden" name="p" value="user">
			<input type="hidden" name="q" value="data_table">
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="username"></td>
					<td>User Type</td>
			   		<td>$usertype_list</td>
				</tr>
			</table>
		</form>
END;
		echo $output;		
	}

	/**
	 * Displays the Add user form (from Ajax)
	 * @return 
	 */
	function display_form () 
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		
		$helpManager	= new HelpManager($db, $user);
		
		//ajax request handler
		$arh = new ResponseManager();
		
		$userid		= $this->userid;
		$username 	= $this->username;
		$password 	= $this->password;
		$usertypeid = $this->usertypeid;
		$email 		= $this->email;
		$homepage	= $this->homepage;
		$groupid	= $this->groupid;
		
		// Help UI
		$helpButton 	= $helpManager->HelpButton("content/users/overview", true);
		$nameHelp		= $helpManager->HelpIcon("The Login Name of the user.", true);
		$passHelp		= $helpManager->HelpIcon("The Password for this user.", true);
		$emailHelp		= $helpManager->HelpIcon("Users email address. E.g. user@example.com", true);
		$homepageHelp	= $helpManager->HelpIcon("The users Homepage. This should not be changed until you want to reset their homepage.", true);
		$overpassHelp	= $helpManager->HelpIcon("Do you want to override this users password with the one entered here.", true);
		$usertypeHelp	= $helpManager->HelpIcon("What is this users type? This would usually be set to 'User'", true);
		$groupHelp		= $helpManager->HelpIcon("Which group does this user belong to? User groups control media sharing and access to functional areas of Xibo.", true);
		
		//What form are we displaying
		if ($userid == "")
		{
			//add form
			$action = "index.php?p=user&q=AddUser";
		}
		else
		{
			//edit form
			$action = "index.php?p=user&q=EditUser";
			
			//split the homepage into its component parts (if it needs to be)
			if (strpos($homepage,'&') !== false) 
			{
				$homepage = substr($homepage, 0, strpos($homepage,'&'));
			}
		
			//make the homepage dropdown
			$homepage_list = listcontent("dashboard|dashboard,mediamanager|mediamanager", "homepage", $homepage);
			
			$homepageOption = <<<END
			<tr>
				<td><label for="homepage">Homepage<span class="required">*</span></label></td>
				<td>$homepageHelp $homepage_list</td>
			</tr>
END;
			
			$override_option = <<<FORM
			<td>Override Password?</td>
			<td>$overpassHelp <input type="checkbox" name="pass_change" value="0"></td>
FORM;
		}

		//get us the user type if we dont have it (for the default value)
		if($usertypeid=="") 
		{
			$usertype = Config::GetSetting($db,"defaultUsertype");

			$SQL = "SELECT usertypeid FROM usertype WHERE usertype = '$usertype'";
			if(!$results = $db->query($SQL)) 
			{
				trigger_error($db->error());
				$arh->decode_response(false, "Can not get Usertype information");
			}
			$row = $db->get_row($results);
			$usertypeid = $row['0'];
		}
		
		//group list
		$group_list = dropdownlist("SELECT groupID, `group` FROM `group` ORDER BY `group`", "groupid", $groupid);
		
		if ($_SESSION['usertype']==1)
		{
			//usertype list
			$usertype_list = dropdownlist("SELECT usertypeid, usertype FROM usertype", "usertypeid", $usertypeid);
			
			$usertypeOption = <<<END
			<tr>
				<td><label for="usertypeid">User Type <span class="required">*</span></label></td>
				<td>$usertypeHelp $usertype_list</td>
			</tr>
			<tr>
				<td><label for="groupid">Group <span class="required">*</span></label></td>
				<td>$groupHelp $group_list</td>
			</tr>	
END;
		}
		else
		{
			$usertypeOption = "";
		}
		
				
		$form = <<<END
		<form class="dialog_form" method='post' action='$action'>
			<input type='hidden' name='userid' value='$userid'>
			<table>
				<tr>
					<td><label for="username">User Name<span class="required">*</span></label></td>
					<td>$nameHelp <input type="text" id="" name="username" value="$username" /></td>
				</tr>
				<tr>
					<td><label for="password">Password<span class="required">*</span></label></td>
					<td>$passHelp <input type="password" id="password" name="password" value="$password" /></td>
					$override_option
				</tr>
				<tr>
					<td><label for="email">Email Address</label></td>
					<td>$emailHelp <input type="text" id="email" name="email" value="$email" /></td>
				</tr>
				$homepageOption
				$usertypeOption
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
		$arh->decode_response(true, $form);
		
		return;
	}
	
	/**
	 * Delete User form
	 * @return 
	 */
	function DeleteForm() 
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new ResponseManager();
		
		//expect the $userid to be set
		$userid = $this->userid;
		
		//we can delete
		$form = <<<END
		<form class="dialog_form" method="post" action="index.php?p=user&q=DeleteUser">
			<input type="hidden" name="userid" value="$userid">
			<p>Are you sure you want to delete $this->name?</p>
			<input type="submit" value="Yes">
			<input type="submit" value="No" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;

		$arh->decode_response(true, $form);
	}
	
	/**
	 * Sets the users home page
	 * @return 
	 */
	function SetUserHomepageForm()
	{
		$db 		=& $this->db;
		$response	= new ResponseManager();
		$layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT, 0);
		$regionid 	= Kit::GetParam('regionid', _REQUEST, _STRING);
		
		//Homepages are for layouts / region combinations
		//The user doesnt have to have access to the layout.
		
		//There should be a list of users on this form - that list should change according to permissions
		//Permissions being related to the logged in user (can they change the users records)
		//								the layout they are on (does the user have permission for it)
		
		//Get the layout owner and permissions
		$SQL = "SELECT userID, permissionID FROM layout WHERE layoutID = $layoutid ";
		if (!$result = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Cant get this regions permissions details.");			
		}
		
		$row = $db->get_row($result);
		
		$layoutOwnerID 		= $row[0];
		$layoutPermissionID = $row[1];
		
		//Query for the user list
		$SQL = " SELECT userID, username, $layoutPermissionID, $layoutOwnerID ";
		$SQL .= " FROM  user  ";		
		if ($_SESSION['usertype'] != "1") //if we arnt an admin then only show us.
		{
			$SQL .= " WHERE userID = " . $_SESSION['userid'];
		}
		$SQL .= " ORDER BY username  ";
		
		$user_list = dropdownlist($SQL, "userid", '', '', false, true, "", "edit", true);
		
		$form = <<<END
		<form class="XiboForm" action="index.php?p=user&q=SetUserHomepage" method="post">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			Set this region to be the homepage for: <br /><br /> $user_list 
			<input type="submit" value="Yes" />
			<input type="submit" value="No" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;
		
		$response->SetFormRequestResponse($form, 'Set as the home page for a User?', '350px', '150px');
		$response->Respond();
	}
	
	/**
	 * Sets the users homepage
	 * @return 
	 */
	function SetUserHomepage()
	{
		$db 		=& $this->db;
		$response 	= new ResponseManager();

		$userid 	= Kit::GetParam('userid', _POST, _INT, 0);
		$layoutid 	= Kit::GetParam('layoutid', _POST, _INT, 0);
		$regionid 	= Kit::GetParam('regionid', _POST, _STRING);
		
		$homepage 	= "mediamanager&layoutid=$layoutid&regionid=$regionid";
		
		$SQL = sprintf("UPDATE user SET homepage = '%s' WHERE userID = $userid ", $homepage);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$response->SetError('Unknown error setting this users homepage.');
			$response->Respond();
		}
		
		$response->SetFormSubmitResponse('Homepage has been set.');
		$response->Respond();
	}
}
?>
