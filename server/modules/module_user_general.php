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
 
 class user 
 {
	//attempt to validate the login params
	//$ajax specifies whether this is an ajax request, or not
	function attempt_login($ajax = false) 
	{
		global $db;
		$referingPage = Kit::GetParam('pagename', _SESSION, _WORD);
		
		if(!$this->checkforUserid()) 
		{
			//print out the login form
			if ($ajax) 
			{
				//create the AJAX request object
				$arh = new AjaxRequest();
				
				$arh->login();
			}
			else 
			{
				$this->printLoginBox($referingPage);
			}
			
			return false;
		}
		else 
		{
			$userid = Kit::GetParam('userid', _SESSION, _INT);
			//write out to the db that the logged in user has accessed the page still
			$SQL = sprintf("UPDATE user SET lastaccessed = '" . date("Y-m-d H:i:s") . "', loggedin = 1 WHERE userid = %d ", $userid);
			
			$results = $db->query($SQL) or trigger_error("Can not write last accessed info.", E_USER_ERROR);
			
			return true;
		}
	}

	function login($username, $password) 
	{
		global $db;
		global $session;
		
		$sql = sprintf("SELECT UserID, UserName, UserPassword, usertypeid, groupID FROM user WHERE UserName = '%s' AND UserPassword = '%s'", $db->escape_string($username), $db->escape_string($password));
		
		Debug::LogEntry($db, 'audit', $sql);
		
		if(!$result = $db->query($sql)) trigger_error('A database error occurred while checking your login details.', E_USER_ERROR);

		if ($db->num_rows($result)==0) 
		{
			setMessage("Your user name or password is incorrect.");
			
			$remote = Kit::ValidateParam($_SERVER['REMOTE_ADDR'], _STRING);
			
			Debug::LogEntry($db, "error", sprintf('Incorrect password for user [%s] from [' . $remote . ']', $username));
			
			return false;
		}

		$results = $db->get_row($result);
		// there is a result so we store the userID in the session variable
		$_SESSION['userid']		= Kit::ValidateParam($results[0], _INT);
		$_SESSION['username']	= Kit::ValidateParam($results[1], _USERNAME);
		$_SESSION['usertype']	= Kit::ValidateParam($results[3], _INT);
		$_SESSION['groupid']	= Kit::ValidateParam($results[4], _INT);

		// update the db
		// write out to the db that the logged in user has accessed the page
		$SQL = sprintf("UPDATE user SET lastaccessed = '" . date("Y-m-d H:i:s") . "', loggedin = 1 WHERE userid = %d", $_SESSION['userid']);
		
		$db->query($SQL) or trigger_error("Can not write last accessed info.", E_USER_ERROR);

		$session->setIsExpired(0);

		return true;
	}

	//Logout someone that wants to logout
	function logout() 
	{
		global $db;
		global $session;
		
		$userid = Kit::GetParam('userid', _SESSION, _INT);

		//write out to the db that the logged in user has accessed the page still
		$SQL = sprintf("UPDATE user SET loggedin = 0 WHERE userid = %d", $userid);
		if(!$results = $db->query($SQL)) trigger_error("Can not write last accessed info.", E_USER_ERROR);

		//to log out a user we need only to clear out some session vars
		unset($_SESSION['userid']);
		unset($_SESSION['username']);
		unset($_SESSION['password']);
		
		$session->setIsExpired(1);

		return true;
	}

	//Check to see if a user id is in the session information
	function checkforUserid() 
	{
		global $db;
		global $session;
		
		$userid = Kit::GetParam('userid', _SESSION, _INT, 0);
		
		// Checks for a user ID in the session variable
		if($userid == 0) 
		{
			return false;
		}
		else 
		{
			if(!is_numeric($_SESSION['userid'])) 
			{
				unset($_SESSION['userid']);
				return false;
			}
			elseif ($session->isExpired == 1) 
			{
				unset($_SESSION['userid']);
				return false;
			}
			else 
			{
				// check to see that the ID is still valid
				$SQL = sprintf("SELECT UserID FROM user WHERE loggedin = 1 AND userid = %d", $userid);
				
				$result = $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);
				
				if($db->num_rows($result)==0) 
				{
					unset($_SESSION['userid']);
					return false;
				}
				return true;
			}
		}
	}

	//prints the login box
	function printLoginBox($referingPage) 
	{
		global $pageObject;
		
		include("template/pages/login_box.php");

        exit;
	}
	
	function getNameFromID($id) 
	{
		global $db;
		
		$SQL = sprintf("SELECT username FROM user WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) trigger_error("Unknown user id in the system", E_USER_NOTICE);
		
		// if no user is returned
		if ($db->num_rows($results) == 0) 
		{
			// assume that is the xibo_admin
			return "None";
		}

		$row = $db->get_row($results);
		
		return $row[0];
	}

	function getGroupFromID($id, $returnID = false) 
	{
		global $db;
		
		$SQL = sprintf("SELECT group.group, group.groupID FROM user INNER JOIN `group` ON group.groupID = user.groupID WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) 
		{
			trigger_error("Error looking up user information (group)");
			trigger_error($db->error());
		}
		
		if ($db->num_rows($results)==0) 
		{
			if ($returnID) 
			{
				return "1";
			}
			return "Users";
		}
		
		$row = $db->get_row($results);

		if ($returnID) 
		{
			return $row[1];
		}
		return $row[0];
	}
	
	function getUserTypeFromID($id, $returnID = false) 
	{
		global $db;
		
		$SQL = sprintf("SELECT usertype.usertype, usertype.usertypeid FROM user INNER JOIN usertype ON usertype.usertypeid = user.usertypeid WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) 
		{
			trigger_error("Error looking up user information (usertype)");
			trigger_error($db->error());
		}
		
		if ($db->num_rows($results)==0) 
		{
			if ($returnID) 
			{
				return "3";
			}
			return "User";
		}
		
		$row = $db->get_row($results);
		
		if ($returnID) 
		{
			return $row[1];
		}
		return $row[0];
	}
	
	function getEmailFromID($id) 
	{
		global $db;
		
		$SQL = sprintf("SELECT email FROM user WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) trigger_error("Unknown user id in the system", E_USER_NOTICE);
		
		if ($db->num_rows($results)==0) 
		{
			$SQL = "SELECT email FROM user WHERE userid = 1";
		
			if(!$results = $db->query($SQL)) 
			{
				trigger_error("Unknown user id in the system [$id]");
			}
		}
		
		$row = $db->get_row($results);
		return $row[1];
	}
	
	/**
	 * Gets the users homepage
	 * @return 
	 */
	function homepage($id) 
	{
		global $db;
		
		$SQL = sprintf("SELECT homepage FROM user WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) trigger_error("Unknown user id in the system", E_USER_NOTICE);
		
		if ($db->num_rows($results) ==0 ) 
		{
			return "dashboard";
		}
		
		$row = $db->get_row($results);
		return $row[0];
	}
	
	function eval_permission($ownerid, $permissionid, $userid = "") 
	{
		//evaulates the permissons and returns an array [canSee,canEdit]
		
		if ($userid != "") 
		{ //use the userid provided
			$groupid 		= $this->getGroupFromID($userid, true);
			$usertypeid		= $this->getUserTypeFromID($userid, true);
		}
		else 
		{
			$userid 	= Kit::GetParam('userid', _SESSION, _INT);		//the logged in user
			$groupid 	= Kit::GetParam('groupid', _SESSION, _INT);		//the logged in users group
			$usertypeid = Kit::GetParam('usertype', _SESSION, _INT);	//the logged in users group (admin, group admin, user)			
		}
		
		$ownerGroupID 	= $this->getGroupFromID($ownerid, true); //the owners groupid
		
		//if we are a super admin we can view/edit anything we like regardless of settings
		if ($usertypeid == 1) 
		{
			return array(true,true);
		}
		
		//set both the flags to false
		$see = false;
		$edit = false;
		
		switch ($permissionid) 
		{
			//the permission options
			case '1': //Private
				//to see we need to be a--- group admin in this group OR the owner
				//to edit we need to be: a group admin in this group - or the owner
				if (($groupid == $ownerGroupID && $usertypeid == 2) || $ownerid == $userid) 
				{
					$see = true;
					$edit = true;
				}
				break;
				
			case '2': //Group
				//to see we need to be in this group
				if ($groupid == $ownerGroupID) 
				{
					$see = true;
					
					//to edit we need to be a group admin in this group (or the owner)
					if ($usertypeid == 2 || ($ownerid == $userid)) 
					{
						$edit = true;
					}
				}
			
				break;
				
			case '3': //Public
					$see = true; //everyone can see it
					
					//group admins (and owners) can edit
					if ($groupid == $ownerGroupID) 
					{				
						//to edit we need to be a group admin in this group (or the owner)
						if ($usertypeid == 2 || ($ownerid == $userid)) 
						{
							$edit = true;
						}
					}
			
				break;
		}
		
		return array($see,$edit);
	}
	
	function forget_details() 
	{
		$output = <<<END
		<p>To recover your details, please enter them in the form below.<br /> A password will then be sent to you</p>
		<form method="post" action="index.php?q=forgotten">
			<div class="login_table">
				<table>
					<tr>
						<td><label for="f_username">User Name </label></td>
						<td><input id="f_username" class="username" type="text" name="f_username" tabindex="4" size="12" /></td>
					</tr>
					<tr>
						<td><label for="f_email">Email Address </label></td>
						<td><input id="f_email" class="password" type="text" name="f_email" tabindex="5" size="12" /></td>
					</tr>
					<tr>
						<td colspan="2"><div class="loginbuton"><button type="submit" tabindex="6">Request New Password</button></div></td>
					</tr>
				</table>
			</div>
		</form>
END;
		echo $output;
	}
}

/**
 * User data object
 *
 */
class userDAO 
{
	private $db;
	
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
	function userDAO(database $db) 
	{
		$this->db =& $db;
		
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
		$arh = new AjaxRequest();

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
		$arh = new AjaxRequest();
			
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
		$arh = new AjaxRequest();
		
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
		$db =& $this->db;
		
		global $user;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$userid		= $this->userid;
		$username 	= $this->username;
		$password 	= $this->password;
		$usertypeid = $this->usertypeid;
		$email 		= $this->email;
		$homepage	= $this->homepage;
		$groupid	= $this->groupid;
		
		// Help UI
		$helpButton 	= HelpButton("content/users/overview", true);
		$nameHelp		= HelpIcon("The Login Name of the user.", true);
		$passHelp		= HelpIcon("The Password for this user.", true);
		$emailHelp		= HelpIcon("Users email address. E.g. user@example.com", true);
		$homepageHelp	= HelpIcon("The users Homepage. This should not be changed until you want to reset their homepage.", true);
		$overpassHelp	= HelpIcon("Do you want to override this users password with the one entered here.", true);
		$usertypeHelp	= HelpIcon("What is this users type? This would usually be set to 'User'", true);
		$groupHelp		= HelpIcon("Which group does this user belong to? User groups control media sharing and access to functional areas of Xibo.", true);
		
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
		$arh = new AjaxRequest();
		
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
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$layoutid	= $_REQUEST['layoutid'];
		$regionid	= $_REQUEST['regionid'];
		
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
		<form class="dialog_form" action="index.php?p=user&q=SetUserHomepage" method="post">
			<input type="hidden" name="layoutid" value="$layoutid" />
			<input type="hidden" name="regionid" value="$regionid" />
			Set this region to be the homepage for $user_list 
			<br />
			<div class="buttons">
				<button class="positive" type="submit">Save</button>
			</div>
		</form>
END;
		
		$arh->decode_response(true, $form);
		return;
	}
	
	/**
	 * Sets the users homepage
	 * @return 
	 */
	function SetUserHomepage()
	{
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$userid		= $_POST['userid'];
		$layoutid	= $_POST['layoutid'];
		$regionid	= $_POST['regionid'];
		
		$homepage = "mediamanager&layoutid=$layoutid&regionid=$regionid";
		
		$SQL = "UPDATE user SET homepage = '$homepage' WHERE userID = $userid ";
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$arh->decode_response(false, "Unknown error setting this users homepage.");
		}
		
		$arh->decode_response(true, "User homepage set");
		return;
	}
}
?>