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

use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\PageFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserTypeFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;

class User extends Base
{
    /**
     * Controls which pages are to be displayed
     */
    function displayPage()
    {
        // Configure the theme
        if (\Kit::IsFilterPinned('user_admin', 'Filter')) {
            $filter_pinned = 1;
            $filter_username = Session::Get('user_admin', 'filter_username');
            $filter_usertypeid = Session::Get('user_admin', 'filter_usertypeid');
        } else {
            $filter_pinned = 0;
            $filter_username = NULL;
            $filter_usertypeid = NULL;
        }

        $userTypes = PDOConnect::select("SELECT userTypeId, userType FROM usertype ORDER BY usertype", []);
        array_unshift($userTypes, array('userTypeId' => 0, 'userType' => 'All'));

        $data = [
            'defaults' => [
                'filterPinned' => $filter_pinned,
                'userName' => $filter_username,
                'userType' => $filter_usertypeid
            ],
            'options' => [
                'userTypes' => $userTypes
            ]
        ];

        $this->getState()->template = 'user-page';
        $this->getState()->setData($data);
    }

    /**
     * Prints the user information in a table based on a check box selection
     *
     */
    function grid()
    {
        // Capture the filter options
        // User ID
        $filter_username = Sanitize::getString('filter_username');
        Session::Set('user_admin', 'filter_username', $filter_username);

        // User Type ID
        $filter_usertypeid = Sanitize::getInt('filter_usertypeid');
        Session::Set('user_admin', 'filter_usertypeid', $filter_usertypeid);

        // Pinned option?
        Session::Set('user_admin', 'Filter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        // Filter our users?
        $filterBy = array();

        // Filter on User Type
        if ($filter_usertypeid != 0)
            $filterBy['userTypeId'] = $filter_usertypeid;

        // Filter on User Name
        if ($filter_username != '')
            $filterBy['userName'] = $filter_username;

        // Load results into an array
        $users = $this->getUser()->userList(array('userName'), $filterBy);

        foreach ($users as $user) {
            /* @var \Xibo\Entity\User $user */

            // Super admins have some buttons
            if ($this->getUser()->userTypeId == 1) {

                // Edit
                $user->buttons[] = array(
                    'id' => 'user_button_edit',
                    'url' => $this->getApp()->urlFor('user.edit.form', ['id' => $user->userId]),
                    'text' => __('Edit')
                );

                // Delete
                $user->buttons[] = array(
                    'id' => 'user_button_delete',
                    'url' => $this->getApp()->urlFor('user.delete.form', ['id' => $user->userId]),
                    'text' => __('Delete')
                );

                // Page Security
                $user->buttons[] = array(
                    'id' => 'user_button_page_security',
                    'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $user->groupId,
                    'text' => __('Page Security')
                );

                // Menu Security
                $user->buttons[] = array(
                    'id' => 'user_button_menu_security',
                    'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $user->groupId,
                    'text' => __('Menu Security')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($users);
    }

    /**
     * Adds a user
     */
    public function add()
    {
        // Build a user entity and save it
        $user = new \Xibo\Entity\User();
        $user->userName = Sanitize::getString('userName');
        $user->email = Sanitize::getString('email');
        $user->userTypeId = Sanitize::getInt('userTypeId');
        $user->homePage = Sanitize::getString('homePageId');
        $user->libraryQuota = Sanitize::getInt('libraryQuota');
        $user->groupId = Sanitize::getInt('groupId');
        $user->setNewPassword(Sanitize::getString('password'));

        // Save the user
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $user->userName),
            'id' => $user->userId,
            'data' => [$user]
        ]);
    }

    /**
     * Edits a user
     * @param int $userId
     */
    public function edit($userId)
    {
        $user = UserFactory::getById($userId);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        // Build a user entity and save it
        $user->userName = Sanitize::getString('userName');
        $user->email = Sanitize::getString('email');
        $user->userTypeId = Sanitize::getInt('userTypeId');
        $user->homePage = Sanitize::getString('homePageId');
        $user->libraryQuota = Sanitize::getInt('libraryQuota');
        $user->retired = Sanitize::getCheckbox('retired');

        // If we are a super admin
        if ($this->getUser()->userTypeId == 1) {
            $newPassword = Sanitize::getString('newPassword');
            $retypeNewPassword = Sanitize::getString('retypeNewPassword');

            if ($newPassword != $retypeNewPassword)
                throw new \InvalidArgumentException(__('Passwords do not match'));

            // Set the new password
            $user->setNewPassword($newPassword);
        }

        // Save the user
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $user->userName),
            'id' => $user->userId,
            'data' => [$user]
        ]);
    }

    /**
     * Delete User
     * @param int $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete($userId)
    {
        $user = UserFactory::getById($userId);

        if (!$this->getUser()->checkDeleteable($user))
            throw new AccessDeniedException();

        // Child objects?
        $children = $user->countChildren();

        if (Sanitize::getCheckbox('deleteAllItems') != 1 && $children > 0) {
            // Check to see if we have any child data that would prevent us from deleting
            throw new \InvalidArgumentException(sprintf(__('This user cannot be deleted as it has %d child items'), $children));
        }

        $user->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $user->userName),
            'id' => $user->userId
        ]);
    }

    /**
     * User Add Form
     */
    public function addForm()
    {
        $this->getState()->template = 'user-form-add';
        $this->getState()->setData([
            'options' => [
                'homepage' => PageFactory::query(),
                'groups' => UserGroupFactory::query(),
                'userTypes' => UserTypeFactory::query()
            ],
            'help' => [
                'add' => Help::Link('User', 'Add')
            ]
        ]);
    }

    /**
     * User Edit Form
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($userId)
    {
        $user = UserFactory::getById($userId);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        $this->getState()->template = 'user-form-edit';
        $this->getState()->setData([
            'user' => $user,
            'options' => [
                'homepage' => PageFactory::query(),
                'userTypes' => UserTypeFactory::query()
            ],
            'help' => [
                'edit' => Help::Link('User', 'Edit')
            ]
        ]);
    }

    /**
     * User Delete Form
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function deleteForm($userId)
    {
        $user = UserFactory::getById($userId);

        if (!$this->getUser()->checkDeleteable($user))
            throw new AccessDeniedException();

        $this->getState()->template = 'user-form-delete';
        $this->getState()->setData([
            'user' => $user,
            'help' => [
                'delete' => Help::Link('User', 'Delete')
            ]
        ]);
    }

    /**
     * Change my password form
     */
    public function changePasswordForm()
    {
        $this->getState()->template = 'user-form-change-password';
        $this->getState()->setData([
            'help' => [
                'changePassword' => Help::Link('User', 'ChangePassword')
            ]
        ]);
    }

    /**
     * Change my Password
     */
    public function changePassword()
    {
        // Save the user
        $user = $this->getUser();
        $oldPassword = Sanitize::getString('password');
        $newPassword = Sanitize::getString('newPassword');
        $retypeNewPassword = Sanitize::getString('retypeNewPassword');

        if ($newPassword != $retypeNewPassword)
            throw new \InvalidArgumentException(__('Passwords do not match'));

        $user->setNewPassword($newPassword, $oldPassword);
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $user->userName),
            'id' => $user->userId,
            'data' => [$user]
        ]);
    }

    /**
     * Show the Permissions for this Campaign
     */
    public function permissionsForm()
    {
        $response = $this->getState();

        $entity = Sanitize::getString('entity');
        if ($entity == '')
            throw new \InvalidArgumentException(__('Permissions form requested without an entity'));

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new \InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        $objectId = Sanitize::getInt('objectId');
        if ($objectId == 0)
            throw new \InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

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
        $formFields[] = Form::AddPermissions('groupids[]', $checkboxes);
        $formFields[] = Form::AddCheckbox('cascade',
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


        $entity = Sanitize::getString('entity');
        if ($entity == '')
            throw new InvalidArgumentException(__('Permissions form requested without an entity'));

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        $objectId = Sanitize::getInt('objectId');
        if ($objectId == 0)
            throw new InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
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

        Log::debug(var_export($newPermissions, true));

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

        $cascade = Sanitize::getCheckbox('cascade');

        if ($cascade) {
            Log::debug('Permissions to push down: ' . var_export($newPermissions, true));

            // TODO: Cascade permissions
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));

    }
}
