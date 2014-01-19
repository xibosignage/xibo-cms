<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
class user {
	private $ldap_connected = true;

	private $dn;
	private $ds;

	private $ldap_name = "";
	private $ldap_admin = "";
	private $ldap_search = "";
	private $ldap_base = "";
	private $ldap_pass = "";
	private $ldap_admin_group = "";

	//Any start up code should go in here
	function user() {
		global $db;
		
		$this->ldap_name = Config::GetSetting("ldap_host");
		$this->ldap_admin = Config::GetSetting("ldap_admin");
		$this->ldap_search = Config::GetSetting("ldap_user_group");
		$this->ldap_base = Config::GetSetting("ldap_base");
		$this->ldap_pass = Config::GetSetting("ldap_pass");
		$this->ldap_admin_group = Config::GetSetting("ldap_admin_group");
		
		//bind to LDAP
		$ds = @ldap_connect($this->ldap_name);
		
		@ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		
		if(!$dn = @ldap_bind($ds, $this->ldap_admin, $this->ldap_pass)) $this->ldap_connected = false;
		
		$this->ds = $ds;
		$this->dn = $dn;
	}

	//attempt to validate the login params
	function attempt_login() {

		$referingPage = $_SESSION['pagename'];

		if(!$this->checkforUserid()) {
			#we need to log the user in
			$this->printLoginBox($referingPage);
		}
		else {
			#write out to the db that the logged in user has accessed the page still
			$_SESSION['lastaccessed'] = date("Y-m-d H:i:s");
		}
	}

	function login($username, $password) {
		global $db;
		$ds =& $this->ds;
		$dn =& $this->dn;
		
		$uidnumber 	 = Config::GetSetting("ldap_uidnumber_field");
		$uid		 = Config::GetSetting("ldap_uid_field");
		$ldap_password	 = Config::GetSetting("ldap_userpassword_field");

		if ($username=="xibo_admin") { //xsm backup admin user
			
			$sql = "SELECT UserID, UserName, UserPassword, usertypeid FROM user WHERE UserName = '" . $username . "' AND UserPassword = '". $password . "'";
			
			if(!$result = $db->query($sql)) trigger_error('A database error occurred while checking your back up admin login details.', E_USER_ERROR);

			if ($db->num_rows($result)==0)  {
				setMessage("Your username / password is incorrect. To try logging in again, click back."); 
				return false;
			}
				
			$results = $db->get_row($result);
				
			#there is a result so we store the userID in the session variable
			$_SESSION['userid']=$results[0];
			$_SESSION['username']=$results[1];
			$_SESSION['usertype']=$results[3];
			$_SESSION['lastaccessed'] = date("Y-m-d H:i:s");
			
			return true;
		}
		
		//check credentials
		$filter = "$uid=$username";
		$these_entries = array("$uid", "$uidnumber", "$ldap_password");

		if (!$results = ldap_search($ds, $this->ldap_base, $filter, $these_entries)) trigger_error("Can not query LDAP search string for $filter", E_USER_ERROR);

		if(!$entry = ldap_get_entries($ds, $results)) trigger_error("Can not get LDAP entries", E_USER_ERROR);

		if ($entry["count"]==0) { //there are no LDAP entries
			setMessage("You have entered an incorrect User Name or Password.");
			return false;
		}

		$entry_pass = $entry[0]["$ldap_password"][0];
		$entry_userid = $entry[0]["$uidnumber"][0];

		if ($password!=md5($entry_pass)) {
			setMessage("Your Username or Password is incorrect.");
			return false;
		}
		
		#there is a result so we store the userID in the session variable
		$_SESSION['userid']=$entry_userid;
		$_SESSION['username']=$username;
		$_SESSION['lastaccessed']= date("Y-m-d H:i:s");
		
		/**
		 * Getting this far means that we have a valid user
		 * Now we need to determine which user group they are a member of.
		 */
		$usertype = $this->getUsertype($username);	
		$_SESSION['usertype']=$usertype;

		/**
		 * Check for the users directory
		 */
		$this->checkforUserDir($_SESSION['userid']);
		
		return true;
	}

	//Logout someone that wants to logout
	function logout() {
		#to log out a user we need only to clear out some session vars
		unset($_SESSION['userid']);
		unset($_SESSION['username']);
		unset($_SESSION['password']);
		session_destroy();
		
		return true;
	}

	//Check to see if a user id is in the session information
	function checkforUserid() {
		$ds =& $this->ds;
		$dn =& $this->dn;

		#Checks for a user ID in the session variable
		if(!isset($_SESSION['userid'])) {
			return false;
		}
		else {
			if(!is_numeric($_SESSION['userid'])) {
				unset($_SESSION['userid']);
				return false;
			}
			else {

				if($_SESSION['lastaccessed']==0) {
					unset($_SESSION['userid']);
					unset($_SESSION['username']);
					
					return false;
				}
			}
		}
		return true;
	}

	//prints the login box
	function printLoginBox($referingPage) {
		global $pageObject;
		
		if (!$this->ldap_connected) trigger_error("Warning, LDAP is down", E_USER_WARNING);
		
		include("template/pages/login_box.php");

        exit;
	}
	
	function getNameFromID($id) {
		global $db;
		$ds =& $this->ds;
		$dn =& $this->dn;
		
		$uidnumber 	 = Config::GetSetting("ldap_uidnumber_field");
		$uid		 = Config::GetSetting("ldap_uid_field");
		
		//check credentials
		$filter = "$uidnumber=$id";
		$these_entries = array($uid, $uidnumber);

		if (!$results = ldap_search($ds, $this->ldap_base, $filter, $these_entries)) trigger_error("Can not query LDAP search string", E_USER_ERROR);

		if(!$entry = ldap_get_entries($ds, $results)) trigger_error("Can not get LDAP entries", E_USER_ERROR);
		
		if ($entry["count"]==0) { //there are no LDAP entries			
			return "xibo_admin";
		}

		return $entry[0]["$uid"][0]; //return the name
	}
	
	function getEmailFromID($id) {
		$ds =& $this->ds;
		$dn =& $this->dn;
		
		$uidnumber 	 = Config::GetSetting("ldap_uidnumber_field");
		$email		 = Config::GetSetting("ldap_email_field");
		
		//check credentials
		$filter = "$uidnumber=$id";
		$these_entries = array("$email", "$uidnumber");

		if (!$results = ldap_search($ds, $this->ldap_base, $filter, $these_entries)) trigger_error("Can not query LDAP search string", E_USER_ERROR);

		if(!$entry = ldap_get_entries($ds, $results)) trigger_error("Can not get LDAP entries", E_USER_ERROR);
		
		if ($entry["count"]==0) { //there are no LDAP entries
			return ""; //return the ID
		}

		return $entry[0]["$email"][0]; //return the name
	}
	
	function getUsertype($uidnumber) {
		global $db;
		$ds =& $this->ds;
		$dn =& $this->dn;
		
		//check credentials
		$admin_group = Config::GetSetting("ldap_admin_group");

		$filter = "memberUID=$uidnumber";
		$these_entries = array("memberuid");

		if (!$results = ldap_search($ds, "$admin_group,".$this->ldap_base, $filter, $these_entries)) trigger_error("getUsertype: Can not query LDAP search string", E_USER_ERROR);

		if(!$entry = ldap_get_entries($ds, $results)) trigger_error("getUsertype: Can not get LDAP entries", E_USER_ERROR);

		
		if ($entry["count"]==0) {
			return 3;
		}
		else {
			return 1;
		}
	}
	
	/**
	 * Gets the users homepage
	 * @return 
	 */
	function homepage($id) {
		return "dashboard";
	}
	
	function getGroupFromID($id, $returnID = false) {
		global $db;
		
		
		return 1;
	}
	
	function getUserTypeFromID($id, $returnID = false) {
		global $db;
		
		return $this->getUserType($id);
	}
	
	function eval_permission($ownerid, $permissionid) {
		//evaulates the permissons and returns an array [canSee,canEdit]
		
		if ($userid != "") { //use the userid provided
			$groupid 		= $this->getGroupFromID($userid, true);
			$usertypeid		= $this->getUserTypeFromID($userid, true);
		}
		else {
			$userid 		= $_SESSION['userid'];		//the logged in user
			$groupid 		= $_SESSION['groupid'];		//the logged in users group
			$usertypeid		= $_SESSION['usertype'];	//the logged in users group (admin, group admin, user)			
		}
		
		$ownerGroupID 	= $this->getGroupFromID($ownerid, true); //the owners groupid
		
		//if we are a super admin we can view/edit anything we like regardless of settings
		if ($usertypeid == 1) {
			return array(true,true);
		}
		
		//set both the flags to false
		$see = false;
		$edit = false;
		
		switch ($permissionid) {
			//the permission options
			case '1': //Private
				//to see we need to be a--- group admin in this group OR the owner
				//to edit we need to be: a group admin in this group - or the owner
				if (($groupid == $ownerGroupID && $usertypeid == 2) || $ownerid == $userid) {
					$see = true;
					$edit = true;
				}
				break;
				
			case '2': //Group
				//to see we need to be in this group
				if ($groupid == $ownerGroupID) {
					$see = true;
					
					//to edit we need to be a group admin in this group (or the owner)
					if ($usertypeid == 2 || ($ownerid == $userid)) {
						$edit = true;
					}
				}
			
				break;
				
			case '3': //Public
					$see = true; //everyone can see it
					
					//group admins (and owners) can edit
					if ($groupid == $ownerGroupID) {				
						//to edit we need to be a group admin in this group (or the owner)
						if ($usertypeid == 2 || ($ownerid == $userid)) {
							$edit = true;
						}
					}
			
				break;
		}
		
		return array($see,$edit);
	}
	
	function forget_details() {
	
		$output = <<<END
		<p>To recover your password details you will need to contact your LDAP network manager</p>
END;
		echo $output;
	}
}

/**
 * User data object
 *
 */
class userDAO {
	private $db;

	/**
	 * Contructor
	 *
	 * @param database $db
	 * @return userDAO
	 */
	function userDAO(database $db) {
		$this->db =& $db;
	}

	function on_page_load() {
		return "onload=\"getResults('user',1,'data_table')\"";
	}

	function echo_page_heading() {
		echo "Users";
		return true;
	}

	function addFormFromIndex () {

		$_SESSION['newuser']=True;
		echo "<h4>Add A User</h4>";

		echo 	"<form method='post' action=\"index.php?p=user&q=add\" onsubmit='return validateForm()'>";
		echo	"<table>";
		echo	"<tr>";
		echo 	"<td>User Name</td>";
		echo	"<td><input name='UserName' type='text' id='UserName'></td>";
		echo	"</tr>";
		echo	"<tr>";
		echo	"<td>Password</td>";
		echo	"<td><input name='Password' type='text' id='Password'></td>";
		echo	"</tr>";
		echo	"<tr>";
		echo	"<td>&nbsp;</td>";
		echo	"<td><input name='add' type='submit' id='add' value='Add New User'></td>";
		exit;
	}

	function displayPage() {
		$db =& $this->db;

		$output = <<<END
		<h2>Users controlled by LDAP</h2>
END;
		echo $output;
	}
}

?>