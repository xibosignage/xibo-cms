<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
	 */
	function __construct(database $db, user $user) 
	{
        $this->db   =& $db;
        $this->user =& $user;

        // Include the group data classes
        include_once('lib/data/usergroup.data.class.php');
	}

    /**
     * Controls which pages are to be displayed
     * @return 
     */
    function displayPage() 
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('displaygroup_form_add_url', 'index.php?p=displaygroup&q=AddForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="user"><input type="hidden" name="q" value="UserGrid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Button URL's
        Theme::Set('user_form_add_url', 'index.php?p=user&q=DisplayForm');
        Theme::Set('myapplications_form_add_url', 'index.php?p=user&q=MyApplications');

        if (Kit::IsFilterPinned('user_admin', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_username', Session::Get('user_admin', 'filter_username'));
            Theme::Set('filter_usertypeid', Session::Get('user_admin', 'filter_usertypeid'));
        }
        else {
            Theme::Set('filter_usertypeid', 0);
        }

        // List of Displays this user has permission for
        $usertypes = $this->db->GetArray("SELECT usertypeID, usertype FROM usertype ORDER BY usertype");
        array_unshift($usertypes, array('usertypeID' => 0, 'usertype' => 'All'));
        Theme::Set('usertype_field_list', $usertypes);

        // Render the Theme and output
        Theme::Render('user_page');
    }

    /**
     * Prints the user information in a table based on a check box selection
     *
     */
    function UserGrid() 
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        // Capture the filter options
        // User ID
        $filter_username = Kit::GetParam('filter_username', _POST, _STRING);
        setSession('user_admin', 'filter_username', $filter_username);
        
        // User Type ID
        $filter_usertypeid = Kit::GetParam('filter_usertypeid', _POST, _INT);
        setSession('user_admin', 'filter_usertypeid', $filter_usertypeid);

        // Pinned option?        
        setSession('user_admin', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        // Generate the results
        $sql  = "SELECT user.UserID, user.UserName, user.usertypeid, user.loggedin, user.lastaccessed, user.email, user.homepage ";
        $sql .= " FROM `user` ";
        $sql .= " WHERE 1 = 1 ";

        // Normal users can only see themselves
        if ($user->usertypeid == 3) 
            $sql .= sprintf(" AND userid = %d ", $user->userid);

        // Filter on User Type
        if($filter_usertypeid != 0) 
            $sql .= sprintf(" AND usertypeid = %d ", $filter_usertypeid);

        // Filter on User Name
        if ($filter_username != '') 
            $sql .= " AND UserName LIKE '%" . sprintf("%s", $db->escape_string($filter_username) . "%' ");

        $sql .= " ORDER by UserName";
        
        Debug::LogEntry('audit', $sql);

        // Load results into an array
        $users = $db->GetArray($sql);

        if (!is_array($users)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting list of users'), E_USER_ERROR);
        }

        $rows = array();

        foreach ($users as $row) {

            $row['loggedin'] = ($row['loggedin'] == 1) ? Theme::Image('act.gif') : Theme::Image('disact.gif');
            $row['groupid'] = $user->getGroupFromID($row['UserID'], true);

            // Super admins have some buttons
            if ($user->usertypeid == 1) {
                
                // Edit        
                $row['buttons'][] = array(
                        'id' => 'user_button_edit',
                        'url' => 'index.php?p=user&q=DisplayForm&userID=' . $row['UserID'],
                        'text' => __('Edit')
                    );

                // Delete
                $row['buttons'][] = array(
                        'id' => 'user_button_delete',
                        'url' => 'index.php?p=user&q=DeleteForm&userID=' . $row['UserID'],
                        'text' => __('Delete')
                    );

                // Page Security
                $row['buttons'][] = array(
                        'id' => 'user_button_page_security',
                        'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $row['groupid'],
                        'text' => __('Page Security')
                    );

                // Menu Security
                $row['buttons'][] = array(
                        'id' => 'user_button_menu_security',
                        'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $row['groupid'],
                        'text' => __('Menu Security')
                    );

                // Applications
                $row['buttons'][] = array(
                        'id' => 'user_button_applications',
                        'url' => 'index.php?p=oauth&q=UserTokens&userID=' . $row['UserID'],
                        'text' => __('Applications')
                    );

                // Set Home Page
                $row['buttons'][] = array(
                        'id' => 'user_button_homepage',
                        'url' => 'index.php?p=user&q=SetUserHomePageForm&userid=' . $row['UserID'],
                        'text' => __('Set Homepage')
                    );

                // Set Password
                $row['buttons'][] = array(
                        'id' => 'user_button_delete',
                        'url' => 'index.php?p=user&q=SetPasswordForm&userid=' . $row['UserID'],
                        'text' => __('Set Password')
                    );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        
        $table = Theme::RenderReturn('user_page_grid');
        
        $response->SetGridResponse($table);
        $response->Respond();
    }

	/**
	 * Adds a user
	 *
	 * @return unknown
	 */
	function AddUser () 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $username = Kit::GetParam('username', _POST, _STRING);
        $password = Kit::GetParam('password', _POST, _STRING);
        $email = Kit::GetParam('email', _POST, _STRING);
        $usertypeid	= Kit::GetParam('usertypeid', _POST, _INT);
        $homepage = Kit::GetParam('homepage', _POST, _STRING);
        $initialGroupId = Kit::GetParam('groupid', _POST, _INT);

        // Validation
        if ($username=="")
        {
            trigger_error("Please enter a User Name.", E_USER_ERROR);
        }
        if ($password=="")
        {
            trigger_error("Please enter a Password.", E_USER_ERROR);
        }

        if ($homepage == "") 
            $homepage = "dashboard";

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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db 	=& $this->db;
        $response	= new ResponseManager();

        $userID	= Kit::GetParam('userid', _POST, _INT, 0);
        $username   = Kit::GetParam('username', _POST, _STRING);
        $email      = Kit::GetParam('email', _POST, _STRING);
        $usertypeid	= Kit::GetParam('usertypeid', _POST, _INT, 0);
        $homepage   = Kit::GetParam('homepage', _POST, _STRING, 'dashboard');
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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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
	 * Displays the User form (from Ajax)
	 * @return 
	 */
	function DisplayForm() 
	{
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $userid = Kit::GetParam('userID', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'UserForm');
        
        if ($userid != 0) {

            Theme::Set('form_action', 'index.php?p=user&q=EditUser');
            Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userid . '" />');

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

            Theme::Set('username', Kit::ValidateParam($aRow['UserName'], _USERNAME));
            Theme::Set('password', Kit::ValidateParam($aRow['UserPassword'], _PASSWORD));
            Theme::Set('usertypeid', Kit::ValidateParam($aRow['usertypeid'], _INT));
            Theme::Set('email', Kit::ValidateParam($aRow['email'], _STRING));
            Theme::Set('homepage', Kit::ValidateParam($aRow['homepage'], _STRING));
            Theme::Set('retired', Kit::ValidateParam($aRow['Retired'], _INT));
            Theme::Set('retired_option_checked', ((Kit::ValidateParam($aRow['Retired'], _INT) == 0) ? '' : ' checked'));

            $theme_file = 'user_form_edit';
            $form_title = 'Edit Form';
            $form_help_link = HelpManager::Link('User', 'Edit');
        }
        else {

            Theme::Set('form_action', 'index.php?p=user&q=AddUser');

            // We are adding a new user
            $usertype = Config::GetSetting('defaultUsertype');
            
            $SQL = sprintf("SELECT usertypeid FROM usertype WHERE usertype = '%s'", $db->escape_string($usertype));

            if (!$usertypeid = $db->GetSingleValue($SQL, 'usertypeid', _INT))
            {
                trigger_error($db->error());
                trigger_error("Can not get Usertype information", E_USER_ERROR);
            }

            Theme::Set('usertypeid', $usertypeid);

            // List of values for the inital user group
            $userGroupList = $db->GetArray('SELECT GroupID, `Group` FROM `group` WHERE IsUserSpecific = 0 AND IsEveryone = 0 ORDER BY 2');
            Theme::Set('user_group_field_list', $userGroupList);

            $theme_file = 'user_form_add';
            $form_title = 'Add Form';
            $form_help_link = HelpManager::Link('User', 'Add');
        }

        // List of homepages for both forms
        Theme::Set('homepage_field_list', array(array("homepageid" => "dashboard", 'homepage' => 'Icon Dashboard'), 
            array("homepageid" => "mediamanager", 'homepage' => 'Media Dashboard'), 
            array("homepageid" => "statusdashboard", 'homepage' => 'Status Dashboard')));

        // Only allow the selection of a usertype if we are a super admin
        $SQL = 'SELECT usertypeid, usertype FROM usertype';
        if ($user->usertypeid != 1)
            $SQL .= ' WHERE UserTypeID = 3';

        Theme::Set('usertype_field_list', $db->GetArray($SQL));

        // Render the return and output
        $form = Theme::RenderReturn($theme_file);

        $response->SetFormRequestResponse($form, $form_title, '550px', '320px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $form_help_link . '")');
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
		$db =& $this->db;
        $user =& $this->user;
		$response = new ResponseManager();
		
		$userid = Kit::GetParam('userID', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'UserDeleteForm');
        Theme::Set('form_action', 'index.php?p=user&q=DeleteUser');
        Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userid . '" />');

        $form = Theme::RenderReturn('user_form_delete');

		$response->SetFormRequestResponse($form, __('Delete this User?'), '430px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'Delete') . '")');
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

        // Set some information about the form
        Theme::Set('form_id', 'SetUserHomePageForm');
        Theme::Set('form_action', 'index.php?p=user&q=SetUserHomepage');
        Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userid . '" />');

        // List of homepages
        Theme::Set('homepage_field_list', array(array("homepageid" => "dashboard", 'homepage' => 'dashboard'), array("homepageid" => "mediamanager", 'homepage' => 'mediamanager')));
        Theme::Set('homepage', $this->user->GetHomePage($userid));

        $form = Theme::RenderReturn('user_form_set_homepage');

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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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

        Theme::Set('table_rows', $list);

        $output = Theme::RenderReturn('user_form_my_applications');

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

        $userId = Kit::GetParam('userid', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'ChangePasswordForm');
        Theme::Set('form_action', 'index.php?p=user&q=ChangePassword');

        $form = Theme::RenderReturn('user_form_change_password');

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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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

    /**
     * Change my password form
     */
    public function SetPasswordForm()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        $userId = Kit::GetParam('userid', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'SetPasswordForm');
        Theme::Set('form_action', 'index.php?p=user&q=SetPassword');
        Theme::Set('form_meta', '<input type="hidden" name="UserId" value="' . $userId . '" />');

        $form = Theme::RenderReturn('user_form_set_password');

        $response->SetFormRequestResponse($form, __('Set Password'), '450', '300');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'SetPassword') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#SetPasswordForm").submit()');
        $response->Respond();
    }

    /**
     * Set a users password
     */
    public function SetPassword()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $newPassword = Kit::GetParam('newPassword', _POST, _STRING);
        $retypeNewPassword = Kit::GetParam('retypeNewPassword', _POST, _STRING);

        $userId = Kit::GetParam('UserId', _POST, _INT);
        
        // Check we are an admin
        if ($this->user->usertypeid != 1)
            trigger_error(__('Trying to change the password for another user denied'), E_USER_ERROR);

        Kit::ClassLoader('userdata');
        $userData = new Userdata($db);

        if (!$userData->ChangePassword($userId, null, $newPassword, $retypeNewPassword, true))
            trigger_error($userData->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Password Changed'));
        $response->Respond();
    }
}
?>
