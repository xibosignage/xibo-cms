<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner and James Packer
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

            // Test the password
            Kit::ClassLoader('userdata');
            $userData = new Userdata($db);

            if (!$userData->TestPasswordAgainstPolicy($password))
                trigger_error($userData->GetErrorMessage(), E_USER_ERROR);

            // Check for duplicate user name
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

            // Ready to enter the user into the database
            $password = md5($password);

            // Run the INSERT statement
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
            $email      = Kit::GetParam('email', _POST, _STRING);
            $usertypeid	= Kit::GetParam('usertypeid', _POST, _INT, 0);
            $homepage   = Kit::GetParam('homepage', _POST, _STRING, 'dashboard');
            $pass_change = isset($_POST['pass_change']);
            $oldPassword = Kit::GetParam('oldPassword', _POST, _STRING);
            $newPassword = Kit::GetParam('newPassword', _POST, _STRING);
            $retypeNewPassword = Kit::GetParam('retypeNewPassword', _POST, _STRING);
            $retired = Kit::GetParam('retired', _POST, _CHECKBOX);

            // Validation
            if ($username == "")
            {
                trigger_error(__("Please enter a User Name."), E_USER_ERROR);
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
            $sql = sprintf("UPDATE user SET UserName = '%s', HomePage = '%s', Email = '%s', Retired = %d ", $username, $homepage, $email, $retired);

            if ($usertypeid != 0)
                $sql .= sprintf(", usertypeid = %d ", $usertypeid);

            $sql .= sprintf(" WHERE UserID = %d ", $userID);

            if (!$db->query($sql))
            {
                trigger_error($db->error());
                trigger_error(__('Error updating user'), E_USER_ERROR);
            }

            // Check that we have permission to get to this point
            if ($this->user->usertypeid != 1 && $pass_change)
                trigger_error(__('You do not have permissions to change this users password'));

            // Handle the Password Change
            if ($newPassword != '' || $pass_change)
            {
                Kit::ClassLoader('userdata');
                $userData = new Userdata($db);

                if (!$userData->ChangePassword($userID, $oldPassword, $newPassword, $retypeNewPassword, $pass_change))
                    trigger_error($userData->GetErrorMessage(), E_USER_ERROR);
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

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkdisplaygroupgroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to display groups'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutgroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutmediagroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to media on layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lklayoutregiongroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to regions on layouts'), E_USER_ERROR);

            if ($this->db->GetCountOfRows(sprintf('SELECT GroupID FROM lkmediagroup WHERE GroupID = %d', $groupID)) > 0)
                trigger_error(__('Cannot delete this user, they have permissions to media'), E_USER_ERROR);

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

		$itemName = Kit::GetParam('usertypeid', _POST, _STRING);
		$username = Kit::GetParam('username', _POST, _USERNAME);
                setSession('user', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

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
		
                $msgKeepFilterOpen = __('Keep filter open');
                $filterPinned = (Kit::IsFilterPinned('user', 'Filter')) ? 'checked' : '';
                $filterId = uniqid('filter');
                
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
                                                <td><label for="XiboFilterPinned$filterId">$msgKeepFilterOpen</label></td>
                                                <td><input type="checkbox" id="XiboFilterPinned$filterId" name="XiboFilterPinned" class="XiboFilterPinned" $filterPinned /></td>
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
                $SQL .= "       homepage, ";
                $SQL .= "       Retired ";
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
                $retired = Kit::ValidateParam($aRow['Retired'], _INT);
            }
            else
            {
                // We are adding a new user
                $usertype = Config::GetSetting($db, 'defaultUsertype');
                $username = '';
                $password = '';
                $email    = '';
                $retired = 0;

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
            $retypePassHelp = $helpManager->HelpIcon(__('Retype the users new password'), true);
            $oldPassHelp    = $helpManager->HelpIcon(__('To change the password you must enter the old password, if password is to remain the same, leave blank.'), true);
            $emailHelp      = $helpManager->HelpIcon("Users email address. E.g. user@example.com", true);
            $homepageHelp   = $helpManager->HelpIcon("The users Homepage. This should not be changed until you want to reset their homepage.", true);
            $overpassHelp   = $helpManager->HelpIcon(__("As an admin, do you want to force this users password to change?."), true);
            $usertypeHelp   = $helpManager->HelpIcon("What is this users type? This would usually be set to 'User'", true);
            $userGroupHelp = $helpManager->HelpIcon(__('What is the initial user group for this user?'), true);

            $homepageOption = '';
            $override_option = '';
            $userGroupOption = '';

            $msgUserName = __('User Name');
            $msgEmailAddress = __('Email Address');
            $msgOldPassword = __('Old Password');
            $msgNewPassword = __('New Password');
            $msgRetype = __('Retype New Password');
            $msgPassword = __('Password');
            $msgHomePage = __('Homepage');
            $msgOverrideOption = __('Override Password?');
            $msgUserType = __('User Type');
            $msgRetired = __('Retired');

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

                $passwordOptions = <<<END
                    <tr>
                        <td><label for="password">$msgPassword</label></td>
                        <td>$passHelp<input type="password" name="password" /></td>
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
                            <td><label for="homepage">$msgHomePage<span class="required">*</span></label></td>
                            <td>$homepageHelp $homepage_list</td>
                    </tr>
END;

                    $override_option = <<<FORM
                    <td>$msgOverrideOption</td>
                    <td>$overpassHelp <input type="checkbox" name="pass_change"></td>
FORM;

                    // Only show the override option if we are a super admin
                    $override_option = ($user->usertypeid == 1) ? $override_option : '';

                    $passwordOptions = <<<END
                    <tr>
                        <td><label for="oldPassword">$msgOldPassword</label></td>
                        <td>$oldPassHelp<input type="password" name="oldPassword" /></td>
                        $override_option
                    </tr>
                    <tr>
                        <td><label for="newPassword">$msgNewPassword</label></td>
                        <td>$passHelp<input type="password" name="newPassword" /></td>
                    </tr>
                    <tr>
                        <td><label for="retypeNewPassword">$msgRetype</label></td>
                        <td>$retypePassHelp<input type="password" name="retypeNewPassword" /></td>
                    </tr>
END;
            }

            if ($user->usertypeid == 1)
            {
                    //usertype list
                    $usertype_list = dropdownlist('SELECT usertypeid, usertype FROM usertype', "usertypeid", $usertypeid);

                    $usertypeOption = <<<END
                    <tr>
                            <td><label for="usertypeid">$msgUserType<span class="required">*</span></label></td>
                            <td>$usertypeHelp $usertype_list</td>
                    </tr>
END;

                    $retiredOptionChecked = ($retired == 0) ? '' : ' checked';
                    $retiredOption = <<<END
                        <tr>
                            <td><label for="retired">$msgRetired</label></td>
                            <td><input type="checkbox" name="retired" $retiredOptionChecked /></td>
                        </tr>
END;
            }
            else
            {
                    $usertypeOption = "";
                    $retiredOption = '';
            }

            $form = <<<END
            <form id="UserForm" class="XiboForm" method='post' action='$action'>
                    <input type='hidden' name='userid' value='$userid'>
                    <table>
                            <tr>
                                    <td><label for="username">$msgUserName<span class="required">*</span></label></td>
                                    <td>$nameHelp <input type="text" id="" name="username" value="$username" class="required" /></td>
                            </tr>
                            $passwordOptions
                            <tr>
                                    <td><label for="email">$msgEmailAddress<span class="required email">*</span></label></td>
                                    <td>$emailHelp <input type="text" id="email" name="email" value="$email" class="required email" /></td>
                            </tr>
                            $homepageOption
                            $usertypeOption
                            $userGroupOption
                            $retiredOption
                    </table>
            </form>
END;

            $response->SetFormRequestResponse($form, (($userid == "") ? __('Add User') : __('Edit User')), '550px', '320px');
            $response->AddButton(__('Help'), 'XiboHelpRender("' . (($userid == "") ? HelpManager::Link('User', 'Add') : HelpManager::Link('User', 'Edit')) . '")');
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

                $msgWarn = __('Are you sure you want to delete this user?');
                $msgExplain = __('You may not be able to delete this user if they have associated content. You can retire users by using the Edit Button.');
		
		//we can delete
		$form = <<<END
		<form id="UserDeleteForm" class="XiboForm" method="post" action="index.php?p=user&q=DeleteUser">
			<input type="hidden" name="userid" value="$userid">
			<p>$msgWarn</p>
                        <p>$msgExplain</p>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Delete this User?'), '430px', '200px');
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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'SetHomepage') . '")');
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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'Applications') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * Change my password form
     */
    public function ChangePasswordForm()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $msgOldPassword = __('Old Password');
        $msgNewPassword = __('New Password');
        $msgRetype = __('Retype New Password');

        $form = <<<END
        <form id="ChangePasswordForm" class="XiboForm" action="index.php?p=user&q=ChangePassword" method="post">
        <table>
            <tr>
                <td><label for="oldPassword">$msgOldPassword</label></td>
                <td><input type="password" name="oldPassword" class="required" /></td>
            </tr>
            <tr>
                <td><label for="newPassword">$msgNewPassword</label></td>
                <td><input type="password" name="newPassword" class="required" /></td>
            </tr>
            <tr>
                <td><label for="retypeNewPassword">$msgRetype</label></td>
                <td><input type="password" name="retypeNewPassword" class="required" /></td>
            </tr>
        </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Change Password'), '450', '300');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'ChangePassword') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ChangePasswordForm").submit()');
        $response->Respond();
    }

    /**
     * Change my Password
     */
    public function ChangePassword()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $oldPassword = Kit::GetParam('oldPassword', _POST, _STRING);
        $newPassword = Kit::GetParam('newPassword', _POST, _STRING);
        $retypeNewPassword = Kit::GetParam('retypeNewPassword', _POST, _STRING);

        Kit::ClassLoader('userdata');
        $userData = new Userdata($db);

        if (!$userData->ChangePassword($this->user->userid, $oldPassword, $newPassword, $retypeNewPassword))
            trigger_error($userData->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Password Changed'));
        $response->Respond();
    }
}
?>
