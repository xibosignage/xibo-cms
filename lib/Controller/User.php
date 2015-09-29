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

use Xibo\Entity\Campaign;
use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PageFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserTypeFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class User extends Base
{
    /**
     * Controls which pages are to be displayed
     */
    function displayPage()
    {
        // Configure the theme
        if ($this->getSession()->get('user_admin', 'Filter') == 1) {
            $pinned = 1;
            $userName = $this->getSession()->get('user_admin', 'userName');
            $userTypeId = $this->getSession()->get('user_admin', 'userTypeId');
            $retired = $this->getSession()->get('user_admin', 'retired');
        } else {
            $pinned = 0;
            $userName = NULL;
            $userTypeId = NULL;
            $retired = 0;
        }

        $userTypes = PDOConnect::select("SELECT userTypeId, userType FROM usertype ORDER BY usertype", []);
        array_unshift($userTypes, array('userTypeId' => '', 'userType' => 'All'));

        $data = [
            'defaults' => [
                'filterPinned' => $pinned,
                'userName' => $userName,
                'userType' => $userTypeId,
                'retired' => $retired
            ],
            'options' => [
                'userTypes' => $userTypes
            ]
        ];

        $this->getState()->template = 'user-page';
        $this->getState()->setData($data);
    }

    /**
     * Me
     *
     * @SWG\Get(
     *  path="/user/me",
     *  operationId="userMe",
     *  tags={"user"},
     *  summary="Get Me",
     *  description="Get my details",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/User")
     *  )
     * )
     */
    public function myDetails()
    {
        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'data' => $this->getUser()
        ]);
    }

    /**
     * Prints the user information in a table based on a check box selection
     *
     * @SWG\Get(
     *  path="/user",
     *  operationId="userSearch",
     *  tags={"user"},
     *  summary="User Search",
     *  description="Search users",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/User")
     *      )
     *  )
     * )
     */
    function grid()
    {
        // Capture the filter options
        $this->getSession()->set('user_admin', 'Filter', Sanitize::getCheckbox('XiboFilterPinned', 0));

        // Filter our users?
        $filterBy = [
            'userTypeId' => $this->getSession()->set('user_admin', 'userTypeId', Sanitize::getInt('userTypeId')),
            'userName' => $this->getSession()->set('user_admin', 'userName', Sanitize::getString('userName')),
            'retired' => $this->getSession()->set('user_admin', 'retired', Sanitize::getInt('retired'))
        ];

        // Load results into an array
        $users = UserFactory::query($this->gridRenderSort(), $this->gridRenderFilter($filterBy));

        foreach ($users as $user) {
            /* @var \Xibo\Entity\User $user */

            if ($this->isApi())
                continue;

            $user->includeProperty('buttons');
            $user->homePage = __($user->homePage);

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
                    'url' => $this->urlFor('group.acl.form', ['id' => $user->groupId]),
                    'text' => __('Page Security')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = UserFactory::countLast();
        $this->getState()->setData($users);
    }

    /**
     * Adds a user
     *
     * @SWG\Post(
     *  path="/user",
     *  operationId="userAdd",
     *  tags={"user"},
     *  summary="Add User",
     *  description="Add a new User",
     *  @SWG\Parameter(
     *      name="userName",
     *      in="formData",
     *      description="The User Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="email",
     *      in="formData",
     *      description="The user email address",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userTypeId",
     *      in="formData",
     *      description="The user type ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="homePageId",
     *      in="formData",
     *      description="The homepage to use for this User",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="libraryQuota",
     *      in="formData",
     *      description="The users library quota in kilobytes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="password",
     *      in="formData",
     *      description="The users password",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="groupId",
     *      in="formData",
     *      description="The inital user group for this User",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/User"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
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
        $user->setNewPassword(Sanitize::getString('password'));

        // Initial user group
        $group = UserGroupFactory::getById(Sanitize::getInt('groupId'));

        // Save the user
        $user->save();

        // Assign the initial group
        $group->assignUser($user);
        $group->save(false);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
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

        // Make sure the user has permission to access this page.
        if (!$user->checkViewable(PageFactory::getById($user->homePageId)))
            throw new \InvalidArgumentException(__('User does not have permission for this homepage'));

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
            'data' => $user
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

        if (Sanitize::getCheckbox('deleteAllItems') != 1) {

            // Do we have a userId to reassign content to?
            if (Sanitize::getInt('reassignUserId') != null) {
                // Reassign all content owned by this user to the provided user
                Log::debug('Reassigning content to new userId: %d', Sanitize::getInt('reassignUserId'));

                $user->reassignAllTo(UserFactory::getById(Sanitize::getInt('reassignUserId')));
            } else {
                // Check to see if we have any child data that would prevent us from deleting
                $children = $user->countChildren();

                if ($children > 0)
                    throw new \InvalidArgumentException(sprintf(__('This user cannot be deleted as it has %d child items'), $children));
            }
        }

        // Delete the user
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
                'homepage' => PageFactory::query(null, ['asHome' => 1]),
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
            'users' => UserFactory::query(null, ['notUserId' => $userId]),
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
            'data' => $user
        ]);
    }

    /**
     * @SWG\Get(
     *  path="/user/permissions/{entity}/{objectId}",
     *  operationId="userPermissionsSearch",
     *  tags={"user"},
     *  summary="Permission Data",
     *  description="Permission data for the Entity and Object Provided.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="objectId",
     *      in="path",
     *      description="The ID of the Object to return permissions for",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Permission")
     *      )
     *  )
     * )
     *
     * @param string $entity
     * @param int $objectId
     */
    public function permissionsGrid($entity, $objectId)
    {
        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // List of all Groups with a view / edit / delete check box
        $permissions = PermissionFactory::getAllByObjectId(get_class($object), $objectId, $this->gridRenderSort(), $this->gridRenderFilter(['name' => Sanitize::getString('name')]));

        $this->getState()->template = 'grid';
        $this->getState()->setData($permissions);
        $this->getState()->recordsTotal = PermissionFactory::countLast();
    }

    /**
     * Permissions to users for the provided entity
     * @param $entity
     * @param $objectId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function permissionsForm($entity, $objectId)
    {
        $requestEntity = $entity;

        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        $currentPermissions = [];
        foreach (PermissionFactory::getAllByObjectId(get_class($object), $objectId) as $permission) {
            /* @var Permission $permission */
            $currentPermissions[$permission->groupId] = [
                'view' => ($permission->view == null) ? 0 : $permission->view,
                'edit' => ($permission->edit == null) ? 0 : $permission->edit,
                'delete' => ($permission->delete == null) ? 0 : $permission->delete
            ];
        }

        $data = [
            'entity' => $requestEntity,
            'objectId' => $objectId,
            'permissions' => $currentPermissions,
            'help' => [
                'permissions' => Help::Link('Campaign', 'Permissions')
            ]
        ];

        $this->getState()->template = 'user-form-permissions';
        $this->getState()->setData($data);
    }

    /**
     * @SWG\Post(
     *  path="/user/permissions/{entity}/{objectId}",
     *  operationId="userPermissionsSet",
     *  tags={"user"},
     *  summary="Permission Set",
     *  description="Set Permissions to users/groups for the provided entity.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="objectId",
     *      in="path",
     *      description="The ID of the Object to set permissions on",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="groupIds",
     *      in="formData",
     *      description="Array of permissions with groupId as the key",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param string $entity
     * @param int $objectId
     */
    public function permissions($entity, $objectId)
    {
        $requestEntity = $entity;
        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity::getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // Get all current permissions
        $permissions = PermissionFactory::getAllByObjectId(get_class($object), $objectId);

        // Get the provided permissions
        $groupIds = Sanitize::getStringArray('groupIds');

        // Run the update
        $this->updatePermissions($permissions, $groupIds);

        // Cascade permissions
        if ($requestEntity == 'Campaign' && Sanitize::getCheckbox('cascade') == 1) {
            /* @var Campaign $object */
            Log::debug('Cascade permissions down');

            $updatePermissionsOnLayout = function($layout) use ($object, $groupIds) {

                // Regions
                foreach ($layout->regions as $region) {
                    /* @var Region $region */
                    $this->updatePermissions(PermissionFactory::getAllByObjectId(get_class($region), $region->getId()), $groupIds);

                    // Playlists
                    foreach ($region->playlists as $playlist) {
                        /* @var Playlist $playlist */
                        $this->updatePermissions(PermissionFactory::getAllByObjectId(get_class($playlist), $playlist->getId()), $groupIds);

                        // Widgets
                        foreach ($playlist->widgets as $widget) {
                            /* @var Widget $widget */
                            $this->updatePermissions(PermissionFactory::getAllByObjectId(get_class($widget), $widget->getId()), $groupIds);
                        }
                    }
                }
            };

            foreach (LayoutFactory::getByCampaignId($object->campaignId) as $layout) {
                /* @var Layout $layout */
                // Assign the same permissions to the Layout
                $this->updatePermissions(PermissionFactory::getAllByObjectId(get_class($object), $layout->campaignId), $groupIds);

                // Load the layout
                $layout->load();

                $updatePermissionsOnLayout($layout);
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpCode' => 204,
            'message' => __('Permissions Updated')
        ]);
    }

    /**
     * Parse the Permissions Entity
     * @param string $entity
     * @param int $objectId
     * @return string
     */
    private function parsePermissionsEntity($entity, $objectId)
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

        return $entity;
    }

    /**
     * Updates a set of permissions from a set of groupIds
     * @param array[Permission] $permissions
     * @param array $groupIds
     */
    private function updatePermissions($permissions, $groupIds)
    {
        Log::debug('Received Permissions Array to update: %s', var_export($groupIds, true));

        // List of groupIds with view, edit and del assignments
        foreach ($permissions as $row) {
            /* @var \Xibo\Entity\Permission $row */

            // Check and see what permissions we have been provided for this selection
            // If all permissions are 0, then the record is deleted
            if (array_key_exists($row->groupId, $groupIds)) {
                $row->view = (array_key_exists('view', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['view'] : 0);
                $row->edit = (array_key_exists('edit', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['edit'] : 0);
                $row->delete = (array_key_exists('delete', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['delete'] : 0);
                $row->save();
            }
        }
    }

    /**
     * User Applications
     */
    public function myApplications()
    {
        $this->getState()->template = 'user-applications-form';
        $this->getState()->setData([
            'applications' => ApplicationFactory::getByUserId($this->getUser()->userId),
            'help' => Help::Link('User', 'Applications')
        ]);
    }
}
