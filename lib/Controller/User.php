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
namespace Xibo\Controller;

use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;

defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

include_once('lib/data/usergroup.data.class.php');

class User extends Base
{

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
        Theme::Set('pager', ApplicationState::Pager($id));

        if (\Kit::IsFilterPinned('user_admin', 'Filter')) {
            $filter_pinned = 1;
            $filter_username = Session::Get('user_admin', 'filter_username');
            $filter_usertypeid = Session::Get('user_admin', 'filter_usertypeid');
        } else {
            $filter_pinned = 0;
            $filter_username = NULL;
            $filter_usertypeid = NULL;
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

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $filter_pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Users'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

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
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();
        // Capture the filter options
        // User ID
        $filter_username = \Xibo\Helper\Sanitize::getString('filter_username');
        \Session::Set('user_admin', 'filter_username', $filter_username);

        // User Type ID
        $filter_usertypeid = \Xibo\Helper\Sanitize::getInt('filter_usertypeid');
        \Session::Set('user_admin', 'filter_usertypeid', $filter_usertypeid);

        // Pinned option?
        \Session::Set('user_admin', 'Filter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        // Filter our users?
        $filterBy = array();

        // Filter on User Type
        if ($filter_usertypeid != 0)
            $filterBy['userTypeId'] = $filter_usertypeid;

        // Filter on User Name
        if ($filter_username != '')
            $filterBy['userName'] = $filter_username;

        // Load results into an array
        $users = $user->userList(array('userName'), $filterBy);

        if (!is_array($users))
            trigger_error(__('Error getting list of users'), E_USER_ERROR);

        $cols = array(
            array('name' => 'username', 'title' => __('Name')),
            array('name' => 'homepage', 'title' => __('Homepage')),
            array('name' => 'email', 'title' => __('Email')),
            array('name' => 'retired', 'title' => __('Retired?'), 'icons' => true)
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($users as $row) {

            $row['groupid'] = $user->getGroupFromID($row['userid'], true);

            // Super admins have some buttons
            if ($user->userTypeId == 1) {

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
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $table = Theme::RenderReturn('table_render');

        $response->SetGridResponse($table);

    }

    /**
     * Adds a user
     *
     * @return unknown
     */
    function AddUser()
    {


        $response = $this->getState();

        $user = new Userdata();
        $user->userName = \Xibo\Helper\Sanitize::getString('edit_username');
        $password = \Xibo\Helper\Sanitize::getString('edit_password');
        $user->email = \Xibo\Helper\Sanitize::getString('email');
        $user->userTypeId = \Kit::GetParam('usertypeid', _POST, _INT, 3);
        $user->homePage = \Xibo\Helper\Sanitize::getString('homepage');
        $initialGroupId = \Xibo\Helper\Sanitize::getInt('groupid');

        // Add the user
        if (!$user->add($password, $initialGroupId))
            trigger_error($user->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('User Saved.');

    }

    /**
     * Modify a user
     */
    function EditUser()
    {
        $response = $this->getState();


        // Do we have permission?
        $entries = $this->user->userList(null, array('userId' => \Kit::GetParam('userid', _POST, _INT)));

        if (count($entries) == 0)
            trigger_error(__('You do not have permission to edit this user'), E_USER_ERROR);
        else
            $user = $entries[0]['object'];

        // Create our user object
        $user->userName = \Xibo\Helper\Sanitize::getString('edit_username');
        $user->email = \Xibo\Helper\Sanitize::getString('email');
        $user->homePage = \Kit::GetParam('homepage', _POST, _STRING, 'dashboard');
        $user->retired = \Xibo\Helper\Sanitize::getCheckbox('retired');

        // Super Admins can change the user type
        if ($this->user->userTypeId == 1)
            $user->userTypeId = \Xibo\Helper\Sanitize::getInt('usertypeid');

        if (!$user->edit())
            trigger_error($user->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('User Saved.');

    }

    /**
     * Deletes a user
     */
    function DeleteUser()
    {


        $response = $this->getState();
        $deleteAllItems = (\Kit::GetParam('deleteAllItems', _POST, _CHECKBOX) == 1);

        $userId = \Kit::GetParam('userid', _POST, _INT, 0);
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

    }

    /**
     * Displays the User form (from Ajax)
     * @return
     */
    function DisplayForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $userId = \Xibo\Helper\Sanitize::getInt('userID');

        // Set some information about the form
        Theme::Set('form_id', 'UserForm');

        // Are we an edit?
        if ($userId != 0) {

            $form_title = 'Edit Form';
            $form_help_link = Help::Link('User', 'Edit');
            Theme::Set('form_action', 'index.php?p=user&q=EditUser');
            Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userId . '" />');

            // We are editing a user
            $entries = $this->user->userList(null, array('userId' => $userId));

            if (count($entries) == 0)
                trigger_error(__('You do not have permission to edit this user.'), E_USER_ERROR);
            else
                $aRow = $entries[0];

            // Store some information for later use
            $username = \Kit::ValidateParam($aRow['username'], _USERNAME);
            $usertypeid = \Xibo\Helper\Sanitize::int($aRow['usertypeid']);
            $email = \Xibo\Helper\Sanitize::string($aRow['email']);
            $homepage = \Xibo\Helper\Sanitize::string($aRow['homepage']);
            $retired = \Xibo\Helper\Sanitize::int($aRow['retired']);

            $retiredFormField = FormManager::AddCheckbox('retired', __('Retired?'),
                $retired, __('Is this user retired?'),
                'r');
        } else {

            $form_title = 'Add Form';
            $form_help_link = Help::Link('User', 'Add');
            Theme::Set('form_action', 'index.php?p=user&q=AddUser');

            // We are adding a new user
            $usertype = Config::GetSetting('defaultUsertype');

            $SQL = sprintf("SELECT usertypeid FROM usertype WHERE usertype = '%s'", $db->escape_string($usertype));

            if (!$usertypeid = $db->GetSingleValue($SQL, 'usertypeid', _INT)) {
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

    }

    /**
     * Delete User form
     * @return
     */
    function DeleteForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $userid = \Xibo\Helper\Sanitize::getInt('userID');

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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#UserDeleteForm").submit()');

    }

    /**
     * Sets the users home page
     * @return
     */
    function SetUserHomepageForm()
    {

        $response = $this->getState();
        $userid = \Xibo\Helper\Sanitize::getInt('userid');

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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'SetHomepage') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#SetUserHomePageForm").submit()');

    }

    /**
     * Sets the users homepage
     * @return
     */
    function SetUserHomepage()
    {


        $response = $this->getState();

        if (!$this->user->userTypeId == 1)
            trigger_error(__('You do not have permission to change this users homepage'));

        $userid = \Kit::GetParam('userid', _POST, _INT, 0);
        $homepage = \Kit::GetParam('homepage', _POST, _WORD);

        $SQL = sprintf("UPDATE user SET homepage = '%s' WHERE userID = %d", $homepage, $userid);

        if (!$db->query($SQL)) {
            trigger_error($db->error());
            $response->SetError(__('Unknown error setting this users homepage'));

        }

        $response->SetFormSubmitResponse(__('Homepage has been set'));

    }

    /**
     * Shows the Authorised applications this user has
     */
    public function MyApplications()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        $store = OAuthStore::instance();

        try {
            $list = $store->listConsumerTokens($this->user->userId);
        } catch (OAuthException $e) {
            trigger_error($e->getMessage());
            trigger_error(__('Error listing Log.'), E_USER_ERROR);
        }

        Theme::Set('table_rows', $list);

        $output = Theme::RenderReturn('user_form_my_applications');

        $response->SetFormRequestResponse($output, __('My Applications'), '650', '450');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'Applications') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    /**
     * Change my password form
     */
    public function ChangePasswordForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        $msgOldPassword = __('Old Password');
        $msgNewPassword = __('New Password');
        $msgRetype = __('Retype New Password');

        $userId = \Xibo\Helper\Sanitize::getInt('userid');

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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'ChangePassword') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ChangePasswordForm").submit()');

    }

    /**
     * Change my Password
     */
    public function ChangePassword()
    {


        $response = $this->getState();

        $oldPassword = \Xibo\Helper\Sanitize::getString('oldPassword');
        $newPassword = \Xibo\Helper\Sanitize::getString('newPassword');
        $retypeNewPassword = \Xibo\Helper\Sanitize::getString('retypeNewPassword');


        $userData = new Userdata($db);

        if (!$userData->ChangePassword($this->user->userId, $oldPassword, $newPassword, $retypeNewPassword))
            trigger_error($userData->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Password Changed'));

    }

    /**
     * Change my password form
     */
    public function SetPasswordForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ApplicationState();

        $userId = \Xibo\Helper\Sanitize::getInt('userid');

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
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'SetPassword') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#SetPasswordForm").submit()');

    }

    /**
     * Set a users password
     */
    public function SetPassword()
    {


        $response = $this->getState();

        $newPassword = \Xibo\Helper\Sanitize::getString('newPassword');
        $retypeNewPassword = \Xibo\Helper\Sanitize::getString('retypeNewPassword');

        $userId = \Xibo\Helper\Sanitize::getInt('UserId');

        // Check we are an admin
        if ($this->user->userTypeId != 1)
            trigger_error(__('Trying to change the password for another user denied'), E_USER_ERROR);


        $userData = new Userdata($db);

        if (!$userData->ChangePassword($userId, null, $newPassword, $retypeNewPassword, true))
            trigger_error($userData->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Password Changed'));

    }

    /**
     * Show the Permissions for this Campaign
     */
    public function permissionsForm()
    {
        $response = $this->getState();

        $entity = \Xibo\Helper\Sanitize::getString('entity');
        if ($entity == '')
            throw new InvalidArgumentException(__('Permissions form requested without an entity'));

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        $objectId = \Xibo\Helper\Sanitize::getInt('objectId');
        if ($objectId == 0)
            throw new InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->user->checkPermissionsModifyable($object))
            throw new Exception(__('You do not have permission to edit these permissions.'));

        // Set some information about the form
        Theme::Set('form_id', 'PermissionsForm');
        Theme::Set('form_action', 'index.php?p=user&q=permissions');
        Theme::Set('form_meta', '<input type="hidden" name="objectId" value="' . $objectId . '" /><input type="hidden" name="entity" value="' . \Kit::GetParam('entity', _GET, _STRING) . '" />');

        // List of all Groups with a view / edit / delete check box
        $permissions = \Xibo\Factory\PermissionFactory::getAllByObjectId(get_class($object), $objectId);

        $checkboxes = array();

        foreach ($permissions as $row) {
            /* @var \Xibo\Entity\Permission $row */
            $groupId = $row->groupId;
            $rowClass = ($row->isUser == 0) ? 'strong_text' : '';

            $checkbox = array(
                'id' => $groupId,
                'name' => $row->group,
                'class' => $rowClass,
                'value_view' => $groupId . '_view',
                'value_view_checked' => (($row->view == 1) ? 'checked' : ''),
                'value_edit' => $groupId . '_edit',
                'value_edit_checked' => (($row->edit == 1) ? 'checked' : ''),
                'value_del' => $groupId . '_del',
                'value_del_checked' => (($row->delete == 1) ? 'checked' : ''),
            );

            $checkboxes[] = $checkbox;
        }

        $formFields = array();
        $formFields[] = FormManager::AddPermissions('groupids[]', $checkboxes);
        $formFields[] = FormManager::AddCheckbox('cascade',
            __('Cascade permissions to all items underneath this one.'), 0,
            __('For example, if this is a Layout then update the permissions on all Regions, Playlists and Widgets.'),
            'r');

        Theme::Set('form_fields', $formFields);

        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#PermissionsForm").submit()');

    }

    /**
     * Permissions form
     * Can be called from anywhere, must provide an entity name to set the permissions against.
     */
    public function permissions()
    {
        $response = $this->getState();


        $entity = \Xibo\Helper\Sanitize::getString('entity');
        if ($entity == '')
            throw new InvalidArgumentException(__('Permissions form requested without an entity'));

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        $objectId = \Xibo\Helper\Sanitize::getInt('objectId');
        if ($objectId == 0)
            throw new InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->user->checkPermissionsModifyable($object))
            throw new Exception(__('You do not have permission to edit these permissions.'));

        // Get all current permissions
        $permissions = \Xibo\Factory\PermissionFactory::getAllByObjectId(get_class($object), $objectId);

        // Get the provided permissions
        $groupIds = \Kit::GetParam('groupids', _POST, _ARRAY);
        $newPermissions = array();
        array_map(function ($string) use (&$newPermissions) {
            $array = explode('_', $string);
            return $newPermissions[$array[0]][$array[1]] = 1;
        }, $groupIds);

        Log::Audit(var_export($newPermissions, true));

        // List of groupIds with view, edit and del assignments
        foreach ($permissions as $row) {
            /* @var \Xibo\Entity\Permission $row */

            // Check and see what permissions we have been provided for this selection
            if (array_key_exists($row->groupId, $newPermissions)) {
                $row->view = (array_key_exists('view', $newPermissions[$row->groupId]) ? 1 : 0);
                $row->edit = (array_key_exists('edit', $newPermissions[$row->groupId]) ? 1 : 0);
                $row->delete = (array_key_exists('del', $newPermissions[$row->groupId]) ? 1 : 0);
                $row->save();
            } else {
                $row->delete();
            }
        }

        $cascade = \Xibo\Helper\Sanitize::getCheckbox('cascade');

        if ($cascade) {
            Log::Audit('Permissions to push down: ' . var_export($newPermissions, true));

            // TODO: Cascade permissions
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));

    }
}
