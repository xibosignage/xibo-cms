<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2010 Daniel Garner and James Packer
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
	
	/**
	 * Contructor
	 *
	 * @param database $db
	 * @return userDAO
	 */
	function __construct(database $db, user $user) 
	{
            $this->db   =& $db;
            $this->user =& $user;

            // Include the group data classes
            include_once('lib/data/usergroup.data.class.php');
	}

	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		echo 'Users';
		return true;
	}

	/**
	 * Adds a user
	 *
	 * @return unknown
	 */
	function AddUser () 
	{
            $db 	=& $this->db;
            $response	= new ResponseManager();

            $username   = Kit::GetParam('username', _POST, _STRING);
            $password   = Kit::GetParam('password', _POST, _STRING);
            $password   = md5($password);
            $email      = Kit::GetParam('email', _POST, _STRING);
            $usertypeid	= Kit::GetParam('usertypeid', _POST, _INT, 0);
            $homepage   = Kit::GetParam('homepage', _POST, _STRING);
            $pass_change = isset($_POST['pass_change']);
            $initialGroupId = Kit::GetParam('groupid', _POST, _INT);

            // Construct the Homepage
            $homepage	= "dashboard";

            // Validation
            if ($username=="")
            {
                trigger_error("Please enter a User Name.", E_USER_ERROR);
            }
            if ($password=="")
            {
                trigger_error("Please enter a Password.", E_USER_ERROR);
            }
            if ($email == "")
            {
                trigger_error("Please enter an Email Address.", E_USER_ERROR);
            }

            if ($homepage == "") $homepage = "dashboard";

            //Check for duplicate user name
            $sqlcheck = " ";
            $sqlcheck .= sprintf("SELECT UserName FROM user WHERE UserName = '%s'", $db->escape_string($username));

            if(!$sqlcheckresult = $db->query($sqlcheck))
            {
                trigger_error($db->error());
                trigger_error("Cant get this user's name. Please try another.", E_USER_ERROR);
            }

            if($db->num_rows($sqlcheckresult) != 0)
            {
                trigger_error("Could Not Complete, Duplicate User Name Exists", E_USER_ERROR);
            }

            //Ready to enter the user into the database
            $query = "INSERT INTO user (UserName, UserPassword, usertypeid, email, homepage)";
            $query .= " VALUES ('$username', '$password', $usertypeid, '$email', '$homepage')";

            if(!$id = $db->insert_query($query))
            {
                trigger_error($db->error());
                trigger_error("Error adding that user", E_USER_ERROR);
            }

            // Add the user group
            $userGroupObject = new UserGroup($db);

            if (!$groupID = $userGroupObject->Add($username, 1))
            {
                // We really want to delete the new user...
                //TODO: Delete the new user
                
                // And then error
                trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);
            }

            $userGroupObject->Link($groupID, $id);

            // Link the initial group
            $userGroupObject->Link($initialGroupId, $id);

            $response->SetFormSubmitResponse('User Saved.');
            $response->Respond();
	}

	/**
	 * Modifys a user
	 *
	 * @return unknown
	 */
	function EditUser() 
	{
            $db 	=& $this->db;
            $response	= new ResponseManager();

            $userID	= Kit::GetParam('userid', _POST, _INT, 0);
            $username   = Kit::GetParam('username', _POST, _STRING);
            $password   = Kit::GetParam('password', _POST, _STRING);
            $password   = md5($password);
            $email      = Kit::GetParam('email', _POST, _STRING);
            $usertypeid	= Kit::GetParam('usertypeid', _POST, _INT, 0);
            $homepage   = Kit::GetParam('homepage', _POST, _STRING, 'dashboard');
            $pass_change = isset($_POST['pass_change']);

            // Validation
            if ($username == "")
            {
                trigger_error("Please enter a User Name.", E_USER_ERROR);
            }
            if ($password == "")
            {
                trigger_error("Please enter a Password.", E_USER_ERROR);
            }
            
            // Check for duplicate user name
            $sqlcheck = " ";
            $sqlcheck .= "SELECT UserName FROM user WHERE UserName = '" . $username . "' AND userID <> $userID ";

            if (!$sqlcheckresult = $db->query($sqlcheck))
            {
                trigger_error($db->error());
                trigger_error(__("Cant get this user's name. Please try another."), E_USER_ERROR);
            }

            if ($db->num_rows($sqlcheckresult) != 0)
            {
                trigger_error(__("Could Not Complete, Duplicate User Name Exists"), E_USER_ERROR);
            }

            // Everything is ok - run the update
            $sql = sprintf("UPDATE user SET UserName = '%s', HomePage = '%s', Email = '%s' ", $username, $homepage, $email);

            if ($pass_change)
                $sql .= sprintf(", UserPassword = '%s'", $password);

            if ($usertypeid != 0)
                $sql .= sprintf(", usertypeid = %d ", $usertypeid);

            $sql .= sprintf(" WHERE UserID = %d ", $userID);

            if (!$db->query($sql))
            {
                trigger_error($db->error());
                trigger_error(__('Error updating user'), E_USER_ERROR);
            }

            // Update the group to follow suit
            $userGroupObject = new UserGroup($db);

            if (!$userGroupObject->EditUserGroup($userID, $username))
            {
                // We really want to delete the new user...
                //TODO: Delete the new user

                // And then error
                trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);
            }

            $response->SetFormSubmitResponse('User Saved.');
            $response->Respond();
	}

	/**
	 * Deletes a user
	 *
	 * @param int $id
	 * @return unknown
	 */
	function DeleteUser() 
	{
            $db 	=& $this->db;
            $user       =& $this->user;

            $response	= new ResponseManager();
            $userid 	= Kit::GetParam('userid', _POST, _INT, 0);
            $groupID    = $user->getGroupFromID($userid, true);

            // Can we delete this user? Dont even try if we cant. Check tables that have this userid or this groupid
            if ($this->db->GetCountOfRows(sprintf('SELECT LayoutID FROM layout WHERE UserID = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT MediaID FROM media WHERE UserID = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have media'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT EventID FROM schedule WHERE UserID = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have scheduled layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT Schedule_DetailID FROM schedule_detail WHERE UserID = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have schedule detail records'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT TemplateID FROM template WHERE UserID = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have templates'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT osr_id FROM oauth_server_registry WHERE osr_usa_id_ref = %d', $userid)) > 0)
                trigger_error(__('Cannot delete this user, they have applications'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkdatasetgroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to data sets'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkgroupdg WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to display groups'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutgroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutmediagroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to media on layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutregiongroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to regions on layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkmediagroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to media'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkmenuitemgroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to menu items'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkpagegroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to pages'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lktemplategroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to templates'), E_USER_ERROR);

            // Firstly delete the group for this user
            $userGroupObject = new UserGroup($db);
            
            // Remove this user from all user groups (including their own)
            $userGroupObject->UnlinkAllGroups($userid);

            // Delete the user specific group
            if (!$userGroupObject->Delete($groupID))
                trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);

            // Delete the user
            $sqldel = "DELETE FROM user";
            $sqldel .= " WHERE UserID = %d"; 

            if (!$db->query(sprintf($sqldel, $userid)))
            {
                trigger_error($db->error());
                trigger_error(__("This user has been active, you may only retire them."), E_USER_ERROR);
            }

            // We should delete this users sessions record.
            $SQL = "DELETE FROM session WHERE userID = %d ";

            if (!$db->query(sprintf($SQL, $userid)))
            {
                trigger_error($db->error());
                trigger_error(__("If logged in, this user will be deleted once they log out."), E_USER_ERROR);
            }

            $response->SetFormSubmitResponse(__('User Deleted.'));
            $response->Respond();
	}

	/**
	 * Prints the user information in a table based on a check box selection
	 *
	 */
	function UserGrid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();

		$itemName = $_REQUEST['usertypeid'];
		$username = $_REQUEST['username'];

		$sql = "SELECT user.UserID, user.UserName, user.usertypeid, user.loggedin, user.lastaccessed, user.email, user.homepage ";
		$sql .= " FROM user ";
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
						<th>Email</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
END;
		
		while($aRow = $db->get_row($results)) 
		{
			$userID 	= $aRow[0];
			$userName 	= $aRow[1];
			$usertypeid     = $aRow[2];
			$loggedin 	= $aRow[3];
			$lastaccessed   = $aRow[4];
			$email 		= $aRow[5];
			$homepage	= $aRow[6];
                        $groupid        = $user->getGroupFromID($userID, true);

			if($loggedin==1) 
			{
				$loggedin="<img src=\"img/act.gif\">";
			}
			else 
			{
				$loggedin="<img src=\"img/disact.gif\">";
			}
			

			if($this->user->usertypeid == 1)
			{
				$table .= '<tr ondblclick="XiboFormRender(\'index.php?p=user&q=DisplayForm&userID=' . $userID . '\')">';
			}
			else
			{
				$table .= "<tr>";
			}
			$table .= "<td>" . $userName . "</td>";
			$table .= "<td>" . $homepage . "</td>";
			$table .= "<td>" . $email . "</td>";
			$table .= "<td>";
			
			if($this->user->usertypeid == 1)
			{
                            $msgPageSec	= __('Page Security');
                            $msgMenuSec	= __('Menu Security');
                            $msgApps	= __('Applications');
                            $msgHomepage	= __('Set Homepage');

                            $table .= '<button class="XiboFormButton" href="index.php?p=user&q=DisplayForm&userID=' . $userID . '"><span>Edit</span></button>';
                            $table .= '<button class="XiboFormButton" href="index.php?p=user&q=DeleteForm&userID=' . $userID . '" ><span>Delete</span></button>';
                            $table .= '<button class="XiboFormButton" href="index.php?p=group&q=PageSecurityForm&groupid=' . $groupid . '"><span>' . $msgPageSec . '</span></button>';
                            $table .= '<button class="XiboFormButton" href="index.php?p=group&q=MenuItemSecurityForm&groupid=' . $groupid . '"><span>' . $msgMenuSec . '</span></button>';
                            $table .= '<button class="XiboFormButton" href="index.php?p=oauth&q=UserTokens&userID=' . $userID. '"><span>' . $msgApps . '</span></button>';
                            $table .= '<button class="XiboFormButton" href="index.php?p=user&q=SetUserHomePageForm&userid=' . $userID. '"><span>' . $msgHomepage . '</span></button>';
			}
                        $table .= "</td>";
			$table .= "</tr>";
		}
		$table .= "</tbody></table></div>";
		
		$response->SetGridResponse($table);
		$response->Respond();
	}

	/**
	 * Controls which pages are to be displayed
	 * @return 
	 */
	function displayPage() 
	{
            $db =& $this->db;
            include('template/pages/user_view.php');
	}
	
	/**
	 * Outputs the filter page
	 * @return 
	 */
	function UserFilter() 
	{
		$db =& $this->db;
		
		$usertype_list = dropdownlist("SELECT 'all', 'All' as usertype UNION SELECT usertypeID, usertype FROM usertype ORDER BY usertype", "usertypeid", 'all');
		
		$filterForm = <<<END
		<div class="FilterDiv" id="UserFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="user">
				<input type="hidden" name="q" value="UserGrid">
				<table>
					<tr>
						<td>Name</td>
						<td><input type="text" name="username"></td>
						<td>User Type</td>
				   		<td>$usertype_list</td>
					</tr>
				</table>
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
	 * Displays the User form (from Ajax)
	 * @return 
	 */
	function DisplayForm() 
	{
            $db             =& $this->db;
            $user           =& $this->user;
            $response       = new ResponseManager();
            $helpManager    = new HelpManager($db, $user);

            $userid         = Kit::GetParam('userID', _GET, _INT);

            if ($userid != 0)
            {
                // We are editing a user
                $SQL  = "";
                $SQL .= "SELECT UserName    , ";
                $SQL .= "       UserPassword, ";
                $SQL .= "       usertypeid  , ";
                $SQL .= "       email       , ";
                $SQL .= "       homepage ";
                $SQL .= "FROM   `user`";
                $SQL .= sprintf(" WHERE userID = %d", $userid);

                if(!$aRow = $db->GetSingleRow($SQL))
                {
                    trigger_error($db->error());
                    trigger_error(__('Error getting user information.'), E_USER_ERROR);
                }

                $username 	= Kit::ValidateParam($aRow['UserName'], _USERNAME);
                $password 	= Kit::ValidateParam($aRow['UserPassword'], _PASSWORD);
                $usertypeid	= Kit::ValidateParam($aRow['usertypeid'], _INT);
                $email          = Kit::ValidateParam($aRow['email'], _STRING);
                $homepage 	= Kit::ValidateParam($aRow['homepage'], _STRING);
            }
            else
            {
                // We are adding a new user
                $usertype = Config::GetSetting($db, 'defaultUsertype');
                $username = '';
                $password = '';
                $email    = '';

                $SQL = sprintf("SELECT usertypeid FROM usertype WHERE usertype = '%s'", $db->escape_string($usertype));

                if(!$usertypeid = $db->GetSingleValue($SQL, 'usertypeid', _INT))
                {
                    trigger_error($db->error());
                    trigger_error("Can not get Usertype information", E_USER_ERROR);
                }
            }

            // Help UI
            $nameHelp       = $helpManager->HelpIcon("The Login Name of the user.", true);
            $passHelp       = $helpManager->HelpIcon("The Password for this user.", true);
            $emailHelp      = $helpManager->HelpIcon("Users email address. E.g. user@example.com", true);
            $homepageHelp   = $helpManager->HelpIcon("The users Homepage. This should not be changed until you want to reset their homepage.", true);
            $overpassHelp   = $helpManager->HelpIcon("Do you want to override this users password with the one entered here.", true);
            $usertypeHelp   = $helpManager->HelpIcon("What is this users type? This would usually be set to 'User'", true);
            $userGroupHelp = $helpManager->HelpIcon(__('What is the initial user group for this user?'), true);

            $homepageOption = '';
            $override_option = '';
            $userGroupOption = '';

            //What form are we displaying
            if ($userid == "")
            {
                    //add form
                    $action = "index.php?p=user&q=AddUser";

                // Build a list of groups to choose from
                $msgGroupSelect = __('Initial User Group');
                $userGroupList = dropdownlist('SELECT GroupID, `Group` FROM `group` WHERE IsUserSpecific = 0 AND IsEveryone = 0 ORDER BY 2', 'groupid');
                $userGroupOption = <<<END
                    <tr>
                        <td><label for="groupid">$msgGroupSelect<span class="required">*</span></label></td>
                        <td>$userGroupHelp $userGroupList</td>
                    </tr>
END;
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

            if ($_SESSION['usertype']==1)
            {
                    //usertype list
                    $usertype_list = dropdownlist("SELECT usertypeid, usertype FROM usertype", "usertypeid", $usertypeid);

                    $usertypeOption = <<<END
                    <tr>
                            <td><label for="usertypeid">User Type <span class="required">*</span></label></td>
                            <td>$usertypeHelp $usertype_list</td>
                    </tr>
END;
            }
            else
            {
                    $usertypeOption = "";
            }


            $form = <<<END
            <form id="UserForm" class="XiboForm" method='post' action='$action'>
                    <input type='hidden' name='userid' value='$userid'>
                    <table>
                            <tr>
                                    <td><label for="username">User Name<span class="required">*</span></label></td>
                                    <td>$nameHelp <input type="text" id="" name="username" value="$username" class="required" /></td>
                            </tr>
                            <tr>
                                    <td><label for="password">Password<span class="required">*</span></label></td>
                                    <td>$passHelp <input type="password" id="password" name="password" value="$password" /></td>
                                    $override_option
                            </tr>
                            <tr>
                                    <td><label for="email">Email Address<span class="required email">*</span></label></td>
                                    <td>$emailHelp <input type="text" id="email" name="email" value="$email" class="required" /></td>
                            </tr>
                            $homepageOption
                            $usertypeOption
                            $userGroupOption
                    </table>
            </form>
END;

            $response->SetFormRequestResponse($form, 'Add/Edit a User.', '550px', '320px');
            $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('User', 'Add') . '")');
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');
            $response->AddButton(__('Save'), '$("#UserForm").submit()');
            $response->Respond();
	}
	
	/**
	 * Delete User form
	 * @return 
	 */
	function DeleteForm() 
	{
		$db 		=& $this->db;
                $user           =& $this->user;
		$response 	= new ResponseManager();
                $helpManager    = new HelpManager($db, $user);
		
		//expect the $userid to be set
		$userid 	= Kit::GetParam('userID', _REQUEST, _INT);
		
		//we can delete
		$form = <<<END
		<form id="UserDeleteForm" class="XiboForm" method="post" action="index.php?p=user&q=DeleteUser">
			<input type="hidden" name="userid" value="$userid">
			<p>Are you sure you want to delete this user?</p>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Delete this User?'), '260px', '180px');
                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('User', 'Delete') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#UserDeleteForm").submit()');
		$response->Respond();
	}
	
    /**
     * Sets the users home page
     * @return
     */
    function SetUserHomepageForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $userid = Kit::GetParam('userid', _GET, _INT);

        $listValues = array(array('homepage' => 'dashboard'), array('homepage' => 'mediamanager'));

        $msgHomePage = __('Homepage');
        $homePageList = Kit::SelectList('homepage', $listValues, 'homepage', 'homepage', $this->user->GetHomePage($userid));

        $form = <<<END
        <form id="SetUserHomePageForm" class="XiboForm" action="index.php?p=user&q=SetUserHomepage" method="post">
        <input type="hidden" name="userid" value="$userid" />
        <table>
            <tr>
                <td><label for="homepage">$msgHomePage</label></td>
                <td>$homePageList</td>
            </tr>
        </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Set the homepage for this user'), '350px', '150px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#SetUserHomePageForm").submit()');
        $response->Respond();
    }

    /**
     * Sets the users homepage
     * @return
     */
    function SetUserHomepage()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        if (!$this->user->usertypeid == 1)
            trigger_error(__('You do not have permission to change this users homepage'));

        $userid	= Kit::GetParam('userid', _POST, _INT, 0);
        $homepage = Kit::GetParam('homepage', _POST, _WORD);

        $SQL = sprintf("UPDATE user SET homepage = '%s' WHERE userID = %d", $homepage, $userid);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $response->SetError(__('Unknown error setting this users homepage'));
            $response->Respond();
        }

        $response->SetFormSubmitResponse(__('Homepage has been set'));
        $response->Respond();
    }

    /**
     * Shows the Authorised applications this user has
     */
    public function MyApplications()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $store = OAuthStore::instance();

        try
        {
            $list = $store->listConsumerTokens($this->user->userid);
        }
        catch (OAuthException $e)
        {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        $output  = '<div class="info_table">';
        $output .= '    <table style="width:100%">';
        $output .= '        <thead>';
        $output .= sprintf('    <th>%s</th>', __('Application'));
        $output .= sprintf('    <th>%s</th>', __('Enabled'));
        $output .= sprintf('    <th>%s</th>', __('Status'));
        $output .= '        </thead>';
        $output .= '        <tbody>';

        foreach($list as $app)
        {
            $title      = Kit::ValidateParam($app['application_title'], _STRING);
            $enabled    = Kit::ValidateParam($app['enabled'], _STRING);
            $status     = Kit::ValidateParam($app['status'], _STRING);

            $output .= '<tr>';
            $output .= '<td>' . $title . '</td>';
            $output .= '<td>' . $enabled . '</td>';
            $output .= '<td>' . $status . '</td>';
            $output .= '</tr>';
        }

        $output .= '        </tbody>';
        $output .= '    </table>';
        $output .= '</div>';

        $response->SetFormRequestResponse($output, __('My Applications'), '650', '450');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }
}
?>
