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
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserTypeFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Storage\PDOConnect;

class User extends Base
{
    /**
     * Controls which pages are to be displayed
     */
    function displayPage()
    {
        // Configure the theme
        if (Session::Get('user_admin', 'Filter') == 1) {
            $pinned = 1;
            $userName = Session::Get('user_admin', 'userName');
            $userTypeId = Session::Get('user_admin', 'userTypeId');
        } else {
            $pinned = 0;
            $userName = NULL;
            $userTypeId = NULL;
        }

        $userTypes = PDOConnect::select("SELECT userTypeId, userType FROM usertype ORDER BY usertype", []);
        array_unshift($userTypes, array('userTypeId' => 0, 'userType' => 'All'));

        $data = [
            'defaults' => [
                'filterPinned' => $pinned,
                'userName' => $userName,
                'userType' => $userTypeId
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
        Session::Set('user_admin', 'Filter', Sanitize::getCheckbox('XiboFilterPinned', 0));

        // Filter our users?
        $filterBy = [
            'userTypeId' => Session::Set('user_admin', 'userTypeId', Sanitize::getInt('userTypeId')),
            'userName' => Session::Set('user_admin', 'userName', Sanitize::getString('userName'))
        ];

        // Load results into an array
        $users = $this->getUser()->userList($this->gridRenderSort(), $this->gridRenderFilter($filterBy));

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
                    'url' => $this->urlFor('group.acl.form', ['entity' => 'Page', 'id' => $user->groupId]),
                    'text' => __('Page Security')
                );

                // Menu Security
                $user->buttons[] = array(
                    'id' => 'user_button_menu_security',
                    'url' => $this->urlFor('group.acl.form', ['entity' => 'Menu', 'id' => $user->groupId]),
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
        $user->homePageId = Sanitize::getInt('homePageId');
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
        $user->load();
        $user->userName = Sanitize::getString('userName');
        $user->email = Sanitize::getString('email');
        $user->userTypeId = Sanitize::getInt('userTypeId');
        $user->homePageId = Sanitize::getInt('homePageId');
        $user->libraryQuota = Sanitize::getInt('libraryQuota');
        $user->retired = Sanitize::getCheckbox('retired');

        // If we are a super admin
        if ($this->getUser()->userTypeId == 1) {
            $newPassword = Sanitize::getString('newPassword');
            $retypeNewPassword = Sanitize::getString('retypeNewPassword');

            if ($newPassword != null && $newPassword != '') {
                // Make sure they are the same
                if ($newPassword != $retypeNewPassword)
                    throw new \InvalidArgumentException(__('Passwords do not match'));

                // Set the new password
                $user->setNewPassword($newPassword);
            }
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
            'message' => __('Password Changed'),
            'id' => $user->userId,
            'data' => [$user]
        ]);
    }

    /**
     * Permissions to users for the provided entity
     * @param $entity
     * @param $objectId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function permissionsForm($entity, $objectId)
    {
        if ($entity == '')
            throw new \InvalidArgumentException(__('Permissions form requested without an entity'));

        $requestEntity = $entity;

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new \InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        if ($objectId == 0)
            throw new \InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // List of all Groups with a view / edit / delete check box
        $permissions = PermissionFactory::getAllByObjectId(get_class($object), $objectId);

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

        $data = [
            'entity' => $requestEntity,
            'objectId' => $objectId,
            'permissions' => $checkboxes,
            'help' => [
                'permissions' => Help::Link('Campaign', 'Permissions')
            ]
        ];

        $this->getState()->template = 'user-form-permissions';
        $this->getState()->setData($data);
    }

    /**
     * Set Permissions to users for the provided entity
     * @param string $entity
     * @param int $objectId
     */
    public function permissions($entity, $objectId)
    {
        if ($entity == '')
            throw new \InvalidArgumentException(__('Permissions requested without an entity'));

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new \InvalidArgumentException(__('Permissions form requested with an invalid entity'));

        // Get the object
        if ($objectId == 0)
            throw new \InvalidArgumentException(__('Permissions form requested without an object'));

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // Get all current permissions
        $permissions = PermissionFactory::getAllByObjectId(get_class($object), $objectId);

        // Get the provided permissions
        $groupIds = Sanitize::getStringArray('groupIds');
        $newPermissions = array();
        array_map(function ($string) use (&$newPermissions) {
            $array = explode('_', $string);
            return $newPermissions[$array[0]][$array[1]] = 1;
        }, $groupIds);

        Log::debug('New Permissions: %s', var_export($newPermissions, true));

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

        // Return
        $this->getState()->hydrate([
            'message' => __('Permissions Updated')
        ]);
    }
}
