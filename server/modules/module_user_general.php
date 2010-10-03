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
 
 class User 
 {
 	private $db;
        
	public $userid;
	public $usertypeid;
        public $userName;
	
	private $displayGroupIDs;
	private $authedDisplayGroupIDs;
	
 	public function __construct(database $db)
	{
            $this->db           =& $db;
            $this->userid 	= Kit::GetParam('userid', _SESSION, _INT);
            $this->usertypeid   = Kit::GetParam('usertype', _SESSION, _INT);

            // We havent authed yet
            $this->authedDisplayGroupIDs = false;
	}
	
	/**
	 * Validate the User is Logged In
	 * @return 
	 * @param $ajax Object[optional] Indicates if this request came from an AJAX call or otherwise
	 */
	function attempt_login($ajax = false) 
	{
		$db 		=& $this->db;

                // Referring Page is anything after the ?
		$requestUri = rawurlencode(Kit::GetCurrentPage());
		
		if(!$this->checkforUserid()) 
		{
			//print out the login form
			if ($ajax) 
			{
                            //create the AJAX request object
                            $response = new ResponseManager();

                            $response->Login();
                            $response->Respond();
			}
			else 
			{
                            $this->printLoginBox($requestUri);
			}
			
			return false;
		}
		else 
		{
			$userid = Kit::GetParam('userid', _SESSION, _INT);
			
			//write out to the db that the logged in user has accessed the page still
			$SQL = sprintf("UPDATE user SET lastaccessed = '" . date("Y-m-d H:i:s") . "', loggedin = 1 WHERE userid = %d ", $userid);
			
			$results = $db->query($SQL) or trigger_error("Can not write last accessed info.", E_USER_ERROR);
			
			$this->usertypeid		= $_SESSION['usertype'];
			$this->userid			= $_SESSION['userid'];
			
			return true;
		}
	}

	/**
	 * Login a user
	 * @return 
	 * @param $username Object
	 * @param $password Object
	 */
	function login($username, $password) 
	{
		$db 		=& $this->db;
		global $session;
		
		$sql = sprintf("SELECT UserID, UserName, UserPassword, usertypeid FROM user WHERE UserName = '%s' AND UserPassword = '%s'", $db->escape_string($username), $db->escape_string($password));
		
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
		$_SESSION['userid']	= Kit::ValidateParam($results[0], _INT);
		$_SESSION['username']	= Kit::ValidateParam($results[1], _USERNAME);
		$_SESSION['usertype']	= Kit::ValidateParam($results[3], _INT);

		$this->usertypeid	= $_SESSION['usertype'];
		$this->userid           = $_SESSION['userid'];

		// update the db
		// write out to the db that the logged in user has accessed the page
		$SQL = sprintf("UPDATE user SET lastaccessed = '" . date("Y-m-d H:i:s") . "', loggedin = 1 WHERE userid = %d", $_SESSION['userid']);
		
		$db->query($SQL) or trigger_error("Can not write last accessed info.", E_USER_ERROR);

		$session->setIsExpired(0);
		$session->RegenerateSessionID(session_id());

		return true;
	}

        /**
         * Logs in a specific userID
         * @param <int> $userID
         */
        function LoginServices($userID)
        {
            $db =& $this->db;

            $SQL = sprintf("SELECT UserName, usertypeid FROM user WHERE userID = '%d'", $userID);

            if (!$results = $this->db->GetSingleRow($SQL))
                return false;

            $this->userName     = Kit::ValidateParam($results['UserName'], _USERNAME);
            $this->usertypeid	= Kit::ValidateParam($results['usertypeid'], _INT);
            $this->userid	= $userID;

            return true;
        }

	/**
	 * Logout the user associated with this user object
	 * @return 
	 */
	function logout() 
	{
		$db 		=& $this->db;
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
		$db 		=& $this->db;
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
		$db 		=& $this->db;
		
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

        /**
         * Get an array of user groups for the given user id
         * @param <type> $id User ID
         * @param <type> $returnID Whether to return ID's or Names
         * @return <array>
         */
        function GetUserGroups($id, $returnID = false)
	{
            $db =& $this->db;

            $groupIDs = array();
            $groups = array();

            $SQL  = "";
            $SQL .= "SELECT group.group, ";
            $SQL .= "       group.groupID ";
            $SQL .= "FROM   `user` ";
            $SQL .= "       INNER JOIN lkusergroup ";
            $SQL .= "       ON     lkusergroup.UserID = user.UserID ";
            $SQL .= "       INNER JOIN `group` ";
            $SQL .= "       ON     group.groupID       = lkusergroup.GroupID ";
            $SQL .= sprintf("WHERE  `user`.userid                     = %d ", $id);

            if(!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                trigger_error("Error looking up user information (group)", E_USER_ERROR);
            }

            if ($db->num_rows($results) == 0)
            {
                // Every user should have a group?
                // Add one in!
                include_once('lib/data/usergroup.data.class.php');

                $userGroupObject = new UserGroup($db);
                if (!$groupID = $userGroupObject->Add('Unknown user id: ' . $id, 1))
                {
                    // Error
                    trigger_error(__('User does not have a group and Xibo is unable to add one.'), E_USER_ERROR);
                }

                // Link the two
                $userGroupObject->Link($groupID, $id);

                if ($returnID)
                    return array($groupID);

                return array('Unknown');
            }

            // Build an array of the groups to return
            while($row = $db->get_assoc_row($results))
            {
                $groupIDs[] = Kit::ValidateParam($row['groupID'], _INT);
                $groups[] = Kit::ValidateParam($row['group'], _STRING);
            }

            if ($returnID)
                return $groupIDs;


            return $groups;
	}

	function getGroupFromID($id, $returnID = false) 
	{
            $db =& $this->db;
		
            $SQL  = "";
            $SQL .= "SELECT group.group, ";
            $SQL .= "       group.groupID ";
            $SQL .= "FROM   `user` ";
            $SQL .= "       INNER JOIN lkusergroup ";
            $SQL .= "       ON     lkusergroup.UserID = user.UserID ";
            $SQL .= "       INNER JOIN `group` ";
            $SQL .= "       ON     group.groupID       = lkusergroup.GroupID ";
            $SQL .= sprintf("WHERE  `user`.userid                     = %d ", $id);
            $SQL .= "AND    `group`.IsUserSpecific = 1";
		
            if(!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                trigger_error("Error looking up user information (group)", E_USER_ERROR);
            }
		
            if ($db->num_rows($results) == 0)
            {
                // Every user should have a group?
                // Add one in!
                include_once('lib/data/usergroup.data.class.php');

                $userGroupObject = new UserGroup($db);
                if (!$groupID = $userGroupObject->Add('Unknown user id: ' . $id, 1))
                {
                    // Error
                    trigger_error(__('User does not have a group and Xibo is unable to add one.'), E_USER_ERROR);
                }

                // Link the two
                $userGroupObject->Link($groupID, $id);

                if ($returnID)
                    return $groupID;

                return 'Unknown';
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
		$db 		=& $this->db;
		
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
		$db 		=& $this->db;
		
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
		$db 		=& $this->db;
		
		$SQL = sprintf("SELECT homepage FROM user WHERE userid = %d", $id);
		
		if(!$results = $db->query($SQL)) trigger_error("Unknown user id in the system", E_USER_NOTICE);
		
		if ($db->num_rows($results) ==0 ) 
		{
			return "dashboard";
		}
		
		$row = $db->get_row($results);
		return $row[0];
	}
	
	/**
	 * Evaulates the permissons and returns an array [canSee,canEdit]
	 * @return 
	 * @param $ownerid Object
	 * @param $permissionid Object
	 * @param $userid Object[optional]
	 */
	function eval_permission($ownerid, $permissionid, $userid = '')
	{
            $db =& $this->db;

            // If a user ID is provided with the call then evaluate against that, otherwise use the logged in user
            if ($userid != '')
            {
                // Use the userid provided
                $groupids    = $this->GetUserGroups($userid, true);
                $usertypeid  = $this->getUserTypeFromID($userid, true);
            }
            else
            {
                // Use the logged in user
                $userid     =& $this->userid;	// the logged in user
                $usertypeid =& $this->usertypeid; // the logged in users group (admin, group admin, user)
                $groupids   = $this->GetUserGroups($userid, true);	// the logged in users groups
            }

            // If we are a super admin we can view/edit anything we like regardless of settings
            if ($usertypeid == 1)
                return array(true,true);

            // Get the groups that the owner belongs to
            $ownerGroupIDs  = $this->GetUserGroups($ownerid, true); 	// the owners groupid

            // set both the flags to false
            $see = false;
            $edit = false;

            switch ($permissionid)
            {
                case '1': // Private
                    // to see we need to be a group admin in this group OR the owner
                    // to edit we need to be: a group admin in this group - or the owner
                    if ((count(array_intersect($ownerGroupIDs, $groupids)) > 0 && $usertypeid == 2) || $ownerid == $userid)
                    {
                        $see = true;
                        $edit = true;
                    }
                    break;

                case '2': // Group
                    // to see we need to be in this group
                    if (count(array_intersect($ownerGroupIDs, $groupids)) > 0)
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

                    // group admins (and owners) can edit
                    if (count(array_intersect($ownerGroupIDs, $groupids)) > 0)
                    {
                        // to edit we need to be a group admin in this group (or the owner)
                        if ($usertypeid == 2 || ($ownerid == $userid))
                        {
                            $edit = true;
                        }
                    }

                    break;
            }

            return array($see,$edit);
	}
	
	/**
	 * Authenticates the page given against the user credentials held.
	 * TODO: Would like to improve performance here by making these credentials cached
	 * @return 
	 * @param $page Object
	 */
	public function PageAuth($page)
	{
		$db 		=& $this->db;
		$userid		=& $this->userid;
		$usertype 	=& $this->usertypeid;
		
		// Check the security
		if ($usertype == 1) 
		{
			// if the usertype is 1 (admin) then we have access to all the pages
			Debug::LogEntry($db, 'audit', 'Granted admin access to page: ' . $page);
			
			return true;
		}
		
		// Allow access to the error page
		if ($page == 'error')
		{
			Debug::LogEntry($db, 'audit', 'Granted access to page: ' . $page);
			
			return true;
		}
		
		// we have access to only the pages assigned to this group
		$SQL = "SELECT pages.pageID FROM pages INNER JOIN lkpagegroup ON lkpagegroup.pageid = pages.pageid ";
                $SQL .= "       INNER JOIN lkusergroup ";
                $SQL .= "       ON     lkpagegroup.groupID       = lkusergroup.GroupID ";
		$SQL .= sprintf(" WHERE lkusergroup.UserID = %d AND pages.name = '%s' ", $userid, $db->escape_string($page));
	
		Debug::LogEntry($db, 'audit', $SQL);
	
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error('Can not get the page security for this user [' . $userid . '] and page [' . $page . ']');
		}
		
		if ($db->num_rows($results) < 1)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * Return a Menu for this user
	 * TODO: Would like to cache this menu array for future requests
	 * @return 
	 * @param $menu Object
	 */
	public function MenuAuth($menu)
	{
		$db 		=& $this->db;
		$userid		=& $this->userid;
		$usertypeid     =& $this->usertypeid;
		
		Debug::LogEntry($db, 'audit', sprintf('Authing the menu for usertypeid [%d]', $usertypeid));
		
		// Get some information about this menu
		// I.e. get the Menu Items this user has access to
		$SQL  = "";
		$SQL .= "SELECT DISTINCT pages.name     , ";
		$SQL .= "         menuitem.Args , ";
		$SQL .= "         menuitem.Text , ";
		$SQL .= "         menuitem.Class, ";
		$SQL .= "         menuitem.Img ";
		$SQL .= "FROM     menuitem ";
		$SQL .= "         INNER JOIN menu ";
		$SQL .= "         ON       menuitem.MenuID = menu.MenuID ";
		$SQL .= "         INNER JOIN pages ";
		$SQL .= "         ON       pages.pageID = menuitem.PageID ";
		if ($usertypeid != 1) 
		{
			$SQL .= "       INNER JOIN lkmenuitemgroup ";
			$SQL .= "       ON       lkmenuitemgroup.MenuItemID = menuitem.MenuItemID ";
			$SQL .= "       INNER JOIN `group` ";
			$SQL .= "       ON       lkmenuitemgroup.GroupID = group.GroupID ";
                        $SQL .= "       INNER JOIN lkusergroup ";
                        $SQL .= "       ON     group.groupID       = lkusergroup.GroupID ";
		}
		$SQL .= sprintf("WHERE    menu.Menu              = '%s' ", $db->escape_string($menu));
		if ($usertypeid != 1) 
		{
			$SQL .= sprintf(" AND lkusergroup.UserID = %d", $userid);
		}
		$SQL .= " ORDER BY menuitem.Sequence";
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			
			return false;
		}
		
		// No permissions to see any of it
		if ($db->num_rows($result) == 0)
		{
			return false;
		}
		
		$theMenu = array();

		// Load the results into a menu array
		while ($row = $db->get_assoc_row($result))
		{
			$theMenu[] = $row;
		}
		
		return $theMenu;
	}
	
	/**
	 * Authenticates this user against the given module
	 * or if none provided returns an array of optional modules
	 * @return Array
	 * @param [Optional] $module String
	 */
	public function ModuleAuth($regionSpecific, $module = '')
	{
		$db 		=& $this->db;
		$userid		=& $this->userid;
		
		// Check that the module is enabled
		$SQL  = "SELECT * FROM module WHERE Enabled = 1 ";
		if ($regionSpecific != -1)
		{
			$SQL .= sprintf(" AND RegionSpecific = %d ", $regionSpecific);
		}
		if ($module != '')
		{
			$SQL .= sprintf(" AND Module = '%s' ", $db->escape_string($module));
		}
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			return false;
		}
		
		if ($db->num_rows($result) == 0)
		{
			return false;
		}
		
		// Put all these into a normal array
		$modules = array();

		while ($row = $db->get_assoc_row($result))
		{
			$modules[] = $row;
		}
		
		// Return this array		
		return $modules;
	}
	
	/**
	 * Authenticates the current user and returns an array of display group ID's this user is authenticated on
	 * @return 
	 */
	public function DisplayGroupAuth()
	{
		$db 		=& $this->db;
		$userid		=& $this->userid;
		
		// If it is already set then just return it
		if ($this->authedDisplayGroupIDs) return $this->displayGroupIDs;
		
		// Populate the array of display group ids we are authed against
		$usertype 	= $this->usertypeid;
		
		$SQL  = "SELECT DISTINCT displaygroup.DisplayGroupID, displaygroup.DisplayGroup, IsDisplaySpecific ";
		$SQL .= "  FROM displaygroup ";
                $SQL .= "  INNER JOIN lkdisplaydg ON displaygroup.DisplayGroupID = lkdisplaydg.DisplayGroupID ";
                $SQL .= " INNER JOIN display ON display.DisplayID = lkdisplaydg.DisplayID ";
		
		// If the usertype is not 1 (admin) then we need to include the link table for display groups.
		if ($usertype != 1)
		{
			$SQL .= " INNER JOIN lkgroupdg ON lkgroupdg.DisplayGroupID = displaygroup.DisplayGroupID ";
			$SQL .= " INNER JOIN lkusergroup ON lkgroupdg.GroupID = lkusergroup.GroupID ";
                }
                
                $SQL .= " WHERE display.licensed = 1 ";

                if ($usertype != 1)
                {
                    $SQL .= sprintf(" AND lkusergroup.UserID = %d ", $userid);
                }
		
		Debug::LogEntry($db, 'audit', $SQL, 'User', 'DisplayGroupAuth');

		if(!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			return false;
		}
		
		$ids = array();
		
		
		// For each display that is returned - add it to the array
		while ($row = $db->get_assoc_row($results))
		{
			$displayGroupID = Kit::ValidateParam($row['DisplayGroupID'], _INT);
			
			$ids[] 			= $displayGroupID;
		}
		
		Debug::LogEntry($db, 'audit', count($ids) . ' authenticated displays.', 'User', 'DisplayGroupAuth');
		
		// Set this for later (incase we call this again from the same session)
		$this->displayGroupIDs 			= $ids;
		$this->authedDisplayGroupIDs 	= true;
		
		return $ids;
	}
	
	/**
	 * Returns the usertypeid for this user object.
	 * @return 
	 */
	public function GetUserTypeID()
	{
		return $this->usertypeid;
	}
	
	/**
	 * Form for outputting the User Details reminder
	 * @return 
	 */
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

    /**
     * Authenticates a user against a fileId
     * @param <type> $fileId
     * @return <bool> true on granted
     */
    public function FileAuth($fileId)
    {
        // Need to check this user has permission to upload this file (i.e. is it theirs)
        return ($db->GetSingleValue(sprintf("SELECT UserID FROM file WHERE FileID = %d", $fileId), 'UserID', _INT) != $this->userid);
    }

    /**
     * Authorizes a user against a media ID
     * @param <int> $mediaID
     */
    public function MediaAuth($mediaID)
    {
        return false;
    }

    /**
     * Returns an array of Media the current user has access to
     */
    public function MediaList()
    {
        $SQL  = "";
        $SQL .= "SELECT  media.mediaID, ";
        $SQL .= "        media.name, ";
        $SQL .= "        media.type, ";
        $SQL .= "        media.duration, ";
        $SQL .= "        media.userID, ";
        $SQL .= "        media.permissionID ";
        $SQL .= "FROM    media ";
        $SQL .= "WHERE   1 = 1  AND isEdited = 0 ";

        Debug::LogEntry($this->db, 'audit', sprintf('Retreiving list of media for %s with SQL: %s', $this->userName, $SQL));

        if (!$result = $this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return false;
        }

        $media = array();

        while($row = $this->db->get_assoc_row($result))
        {
            $mediaItem = array();

            // Validate each param and add it to the array.
            $mediaItem['mediaid']   = Kit::ValidateParam($row['mediaID'], _INT);
            $mediaItem['media']     = Kit::ValidateParam($row['name'], _STRING);
            $mediaItem['mediatype'] = Kit::ValidateParam($row['type'], _WORD);
            $mediaItem['length']    = Kit::ValidateParam($row['duration'], _DOUBLE);
            $mediaItem['ownerid']   = Kit::ValidateParam($row['userID'], _INT);

            list($see, $edit) = $this->eval_permission($mediaItem['ownerid'], Kit::ValidateParam($row['permissionID'], _INT));

            if ($see)
            {
                $mediaItem['read']      = (int) $see;
                $mediaItem['write']     = (int) $edit;
                $media[] = $mediaItem;
            }
        }

        return $media;
    }

    public function LayoutAuth()
    {

    }

    /**
     * Returns an array of layouts that this user has access to
     */
    public function LayoutList()
    {
        $SQL  = "";
        $SQL .= "SELECT layoutID, ";
        $SQL .= "        layout, ";
        $SQL .= "        description, ";
        $SQL .= "        tags, ";
        $SQL .= "        permissionID, ";
        $SQL .= "        userID ";
        $SQL .= "   FROM layout ";

         Debug::LogEntry($this->db, 'audit', sprintf('Retreiving list of layouts for %s with SQL: %s', $this->userName, $SQL));

        if (!$result = $this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return false;
        }

        $layouts = array();

        while ($row = $this->db->get_assoc_row($result))
        {
            $layoutItem = array();

            // Validate each param and add it to the array.
            $layoutItem['layoutid'] = Kit::ValidateParam($row['layoutID'], _INT);
            $layoutItem['layout']   = Kit::ValidateParam($row['layout'], _STRING);
            $layoutItem['description'] = Kit::ValidateParam($row['description'], _STRING);
            $layoutItem['tags']     = Kit::ValidateParam($row['tags'], _STRING);
            $layoutItem['ownerid']  = Kit::ValidateParam($row['userID'], _INT);

            list($see, $edit) = $this->eval_permission($layoutItem['ownerid'], Kit::ValidateParam($row['permissionID'], _INT));

            if ($see)
            {
                $layoutItem['read']      = (int) $see;
                $layoutItem['write']     = (int) $edit;
                
                $layouts[] = $layoutItem;
            }
        }

        return $layouts;
    }
}
?>