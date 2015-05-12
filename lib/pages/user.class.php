<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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

include_once('lib/data/usergroup.data.class.php');

class userDAO extends baseDAO {
	
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

        if (Kit::IsFilterPinned('user_admin', 'Filter')) {
            $filter_pinned = 1;
            $filter_username = Session::Get('user_admin', 'filter_username');
            $filter_usertypeid = Session::Get('user_admin', 'filter_usertypeid');
            $filterRetired = Session::Get('user_admin', 'filterRetired');
        }
        else {
            $filter_pinned = 0;
            $filter_username = NULL;
            $filter_usertypeid = NULL;
            $filterRetired = 0;
        }

        $formFields = array();
        $formFields[] = FormManager::AddText('filter_username', __('Name'), $filter_username, NULL, 'n');

        $usertypes = $this->db->GetArray("SELECT usertypeID, usertype FROM usertype ORDER BY usertype");
        array_unshift($usertypes, array('usertypeID' => 0, 'usertype' => 'All'));
        $formFields[] = FormManager::AddCombo(
            'filter_usertypeid', 
            __('User Type'), 
            $filter_usertypeid,
            $usertypes,
            'usertypeID',
            'usertype',
            NULL, 
            't');

        $formFields[] = FormManager::AddCombo(
            'filterRetired',
            __('Retired?'),
            $filterRetired,
            array(
                array('retiredId' => -1, 'retired' => 'All'),
                array('retiredId' => 1, 'retired' => 'Yes'),
                array('retiredId' => 0, 'retired' => 'No')
            ),
            'retiredId',
            'retired',
            NULL,
            'r');
        
        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $filter_pinned, NULL, 
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Users'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
    }

    function actionMenu() {

        return array(
                array('title' => __('Add User'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=user&q=DisplayForm',
                    'help' => __('Add a new User'),
                    'onclick' => ''
                    ),
                array('title' => __('My Applications'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=user&q=MyApplications',
                    'help' => __('View my authenticated applications'),
                    'onclick' => ''
                    ),
                array('title' => __('Filter'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Open the filter form'),
                    'onclick' => 'ToggleFilterView(\'Filter\')'
                    )
            );                   
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

        $filterRetired = Kit::GetParam('filterRetired', _POST, _INT);
        setSession('user_admin', 'filterRetired', $filterRetired);

        // Pinned option?        
        setSession('user_admin', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        // Filter our users?
        $filterBy = array();

        // Filter on User Type
        if ($filter_usertypeid != 0)
            $filterBy['userTypeId'] = $filter_usertypeid;

        // Filter on User Name
        if ($filter_username != '') 
            $filterBy['userName'] = $filter_username;

        $filterBy['retired'] = $filterRetired;

        // Load results into an array
        $users = $user->userList(array('userName'), $filterBy);

        if (!is_array($users)) 
            trigger_error(__('Error getting list of users'), E_USER_ERROR);
        
        $cols = array(
                array('name' => 'username', 'title' => __('Name')),
                array('name' => 'homepage', 'title' => __('Homepage')),
                array('name' => 'email', 'title' => __('Email')),
                array('name' => 'libraryQuotaText', 'title' => __('Library Quota')),
                array('name' => 'retired', 'title' => __('Retired?'), 'icons' => true)
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($users as $row) {

            $row['libraryQuotaText'] = ($row['object']->libraryQuota == 0) ? '' : Kit::formatBytes($row['object']->libraryQuota * 1024);

            // Super admins have some buttons
            if ($user->usertypeid == 1) {
                
                // Edit        
                $row['buttons'][] = array(
                        'id' => 'user_button_edit',
                        'url' => 'index.php?p=user&q=DisplayForm&userID=' . $row['userid'],
                        'text' => __('Edit')
                    );

                // Delete
                $row['buttons'][] = array(
                        'id' => 'user_button_delete',
                        'url' => 'index.php?p=user&q=DeleteForm&userID=' . $row['userid'],
                        'text' => __('Delete')
                    );

                // Page Security
                $row['buttons'][] = array(
                        'id' => 'user_button_page_security',
                        'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $row['object']->groupId,
                        'text' => __('Page Security')
                    );

                // Menu Security
                $row['buttons'][] = array(
                        'id' => 'user_button_menu_security',
                        'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $row['object']->groupId,
                        'text' => __('Menu Security')
                    );

                // Applications
                $row['buttons'][] = array(
                        'id' => 'user_button_applications',
                        'url' => 'index.php?p=oauth&q=UserTokens&userID=' . $row['userid'],
                        'text' => __('Applications')
                    );

                // Set Home Page
                $row['buttons'][] = array(
                        'id' => 'user_button_homepage',
                        'url' => 'index.php?p=user&q=SetUserHomePageForm&userid=' . $row['userid'],
                        'text' => __('Set Homepage')
                    );

                // Set Password
                $row['buttons'][] = array(
                        'id' => 'user_button_delete',
                        'url' => 'index.php?p=user&q=SetPasswordForm&userid=' . $row['userid'],
                        'text' => __('Set Password')
                    );

                // User Quota
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_quota',
                    'url' => 'index.php?p=group&q=quotaForm&groupid=' . $row['object']->groupId,
                    'text' => __('Set User Quota')
                );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        
        $table = Theme::RenderReturn('table_render');
        
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
        
        $response = new ResponseManager();

        $user = new Userdata();
        $user->userName = Kit::GetParam('edit_username', _POST, _STRING);
        $password = Kit::GetParam('edit_password', _POST, _STRING);
        $user->email = Kit::GetParam('email', _POST, _STRING);
        $user->userTypeId	= Kit::GetParam('usertypeid', _POST, _INT, 3);
        $user->homePage = Kit::GetParam('homepage', _POST, _STRING);
        $initialGroupId = Kit::GetParam('groupid', _POST, _INT);

        // Add the user
        if (!$user->add($password, $initialGroupId))
            trigger_error($user->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('User Saved.');
        $response->Respond();
	}

	/**
	 * Modify a user
	 */
	function EditUser() 
	{
        $response = new ResponseManager();

        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);

        // Do we have permission?
        $entries = $this->user->userList(null, array('userId' => Kit::GetParam('userid', _POST, _INT)));

        if (count($entries) == 0)
            trigger_error(__('You do not have permission to edit this user'), E_USER_ERROR);
        else
            $user = $entries[0]['object'];

        // Create our user object
        $user->userName = Kit::GetParam('edit_username', _POST, _STRING);
        $user->email = Kit::GetParam('email', _POST, _STRING);
        $user->homePage = Kit::GetParam('homepage', _POST, _STRING, 'dashboard');
        $user->retired = Kit::GetParam('retired', _POST, _CHECKBOX);

        // Super Admins can change the user type
        if ($this->user->usertypeid == 1)
            $user->userTypeId = Kit::GetParam('usertypeid', _POST, _INT);

        if (!$user->edit())
            trigger_error($user->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('User Saved.');
        $response->Respond();
	}

	/**
	 * Deletes a user
	 */
	function DeleteUser() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);

        $response = new ResponseManager();
        $deleteAllItems = (Kit::GetParam('deleteAllItems', _POST, _CHECKBOX) == 1);

        $userId = Kit::GetParam('userid', _POST, _INT, 0);
        $groupId = $this->user->getGroupFromID($userId, true);

        $user = new Userdata();
        $user->userId = $userId;

        $userGroup = new UserGroup();

        if (!$deleteAllItems) {
            // Can we delete this user? Don't even try if we cant.
            $children = $user->getChildTypes();

            if (count($children) > 0)
                trigger_error(sprintf(__('Cannot delete user, they own %s'), implode(', ', $children)), E_USER_ERROR);

            // Can we delete this group?
            $children = $userGroup->getChildTypes($groupId);

            if (count($children) > 0)
                trigger_error(sprintf(__('Cannot delete user, they own %s'), implode(', ', $children)), E_USER_ERROR);
        }

        // Delete all items has been selected, so call delete on the group, then the user
        $userGroup->UnlinkAllGroups($userId);

        // Delete the user specific group
        if (!$userGroup->Delete($groupId))
            trigger_error($userGroup->GetErrorMessage(), E_USER_ERROR);

        // Delete the user
        if (!$user->Delete())
            trigger_error($user->GetErrorMessage(), E_USER_ERROR);

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

        $userId = Kit::GetParam('userID', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'UserForm');

        // Are we an edit?
        if ($userId != 0) {

            $form_title = 'Edit Form';
            $form_help_link = HelpManager::Link('User', 'Edit');
            Theme::Set('form_action', 'index.php?p=user&q=EditUser');
            Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userId . '" />');

            // We are editing a user
            $entries = $this->user->userList(null, array('userId' => $userId));

            if (count($entries) == 0)
                trigger_error(__('You do not have permission to edit this user.'), E_USER_ERROR);
            else
                $aRow = $entries[0];

            // Store some information for later use
            $username = Kit::ValidateParam($aRow['username'], _USERNAME);
            $usertypeid = Kit::ValidateParam($aRow['usertypeid'], _INT);
            $email = Kit::ValidateParam($aRow['email'], _STRING);
            $homepage = Kit::ValidateParam($aRow['homepage'], _STRING);
            $retired = Kit::ValidateParam($aRow['retired'], _INT);

            $retiredFormField = FormManager::AddCheckbox('retired', __('Retired?'), 
                $retired, __('Is this user retired?'),
                'r');
        }
        else {

            $form_title = 'Add Form';
            $form_help_link = HelpManager::Link('User', 'Add');
            Theme::Set('form_action', 'index.php?p=user&q=AddUser');

            // We are adding a new user
            $usertype = Config::GetSetting('defaultUsertype');
            
            $SQL = sprintf("SELECT usertypeid FROM usertype WHERE usertype = '%s'", $db->escape_string($usertype));

            if (!$usertypeid = $db->GetSingleValue($SQL, 'usertypeid', _INT))
            {
                trigger_error($db->error());
                trigger_error("Can not get Usertype information", E_USER_ERROR);
            }

            // Defaults
            $username = NULL;
            $password = NULL;
            $email = NULL;
            $homepage = NULL;
            $retired = NULL;

            // List of values for the initial user group
            $userGroupField = FormManager::AddCombo(
                    'groupid', 
                    __('Initial User Group'), 
                    NULL,
                    $db->GetArray('SELECT GroupID, `Group` FROM `group` WHERE IsUserSpecific = 0 AND IsEveryone = 0 ORDER BY 2'),
                    'GroupID',
                    'Group',
                    __('What is the initial user group for this user?'), 
                    'g');

            $passwordField = FormManager::AddPassword('edit_password', __('Password'), $password,
                __('The Password for this user.'), 'p', 'required');
        }

        // Render the return and output
        $formFields = array();
        $formFields[] = FormManager::AddText('edit_username', __('User Name'), $username, 
            __('The Login Name of the user.'), 'n', 'required maxlength="50"');

        $formFields[] = FormManager::AddText('email', __('Email'), $email,
            __('The Email Address for this user.'), 'e', NULL);

        $formFields[] = FormManager::AddCombo(
                    'homepage', 
                    __('Homepage'), 
                    $homepage,
                    array(
                        array("homepageid" => "dashboard", 'homepage' => 'Icon Dashboard'), 
                        array("homepageid" => "mediamanager", 'homepage' => 'Media Dashboard'), 
                        array("homepageid" => "statusdashboard", 'homepage' => 'Status Dashboard')
                    ),
                    'homepageid',
                    'homepage',
                    __('Homepage for this user. This is the page they will be taken to when they login.'), 
                    'h');

        // Only allow the selection of a usertype if we are a super admin
        $SQL = 'SELECT usertypeid, usertype FROM usertype';
        if ($user->usertypeid != 1)
            $SQL .= ' WHERE UserTypeID = 3';

        $formFields[] = FormManager::AddCombo(
                    'usertypeid', 
                    __('User Type'), 
                    $usertypeid,
                    $db->GetArray($SQL),
                    'usertypeid',
                    'usertype',
                    __('What is this users type?'),
                    't', NULL, ($user->usertypeid == 1));

        // Add the user group field if set
        if (isset($passwordField) && is_array($passwordField))
            $formFields[] = $passwordField;

        if (isset($userGroupField) && is_array($userGroupField))
            $formFields[] = $userGroupField;

        if (isset($retiredFormField) && is_array($retiredFormField))
            $formFields[] = $retiredFormField;

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, $form_title, '550px', '320px');
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

        $formFields = array(FormManager::AddMessage(__('Are you sure you want to delete? You may not be able to delete this user if they have associated content. You can retire users by using the Edit Button.')));
        $formFields[] = FormManager::AddCheckbox('deleteAllItems',
            __('Delete all items owned by this User?'),
            0,
            __('Check to delete all items owned by this user, including Layouts, Media, Schedules, etc.'),
            'd');

        Theme::Set('form_fields', $formFields);

		$response->SetFormRequestResponse(NULL, __('Delete this User?'), '430px', '200px');
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

        // Render the return and output
        $formFields = array();

        $formFields[] = FormManager::AddCombo(
                    'homepage', 
                    __('Homepage'), 
                    $this->user->GetHomePage($userid),
                    array(
                        array("homepageid" => "dashboard", 'homepage' => 'Icon Dashboard'), 
                        array("homepageid" => "mediamanager", 'homepage' => 'Media Dashboard'), 
                        array("homepageid" => "statusdashboard", 'homepage' => 'Status Dashboard')
                    ),
                    'homepageid',
                    'homepage',
                    __('The users Homepage. This should not be changed until you want to reset their homepage.'), 
                    'h');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Set the homepage for this user'), '350px', '150px');
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

        $formFields = array();
        $formFields[] = FormManager::AddPassword('oldPassword', __('Current Password'), NULL, 
            __('Please enter your current password'), 'p', 'required');

        $formFields[] = FormManager::AddPassword('newPassword', __('New Password'), NULL, 
            __('Please enter your new password'), 'n', 'required');

        $formFields[] = FormManager::AddPassword('retypeNewPassword', __('Retype New Password'), NULL, 
            __('Please repeat the new Password.'), 'r', 'required');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Change Password'), '450', '300');
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

        $formFields = array();
        $formFields[] = FormManager::AddPassword('newPassword', __('New Password'), NULL, 
            __('The new Password for this user.'), 'p', 'required');

        $formFields[] = FormManager::AddPassword('retypeNewPassword', __('Retype New Password'), NULL, 
            __('Repeat the new Password for this user.'), 'r', 'required');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Set Password'), '450', '300');
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
