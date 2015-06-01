<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008,2009 Daniel Garner and James Packer
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
use baseDAO;
use database;
use Exception;
use JSON;
use Kit;
use PDO;
use Xibo\Entity\Permission;
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class UserGroup extends Base
{
    /**
     * Display page logic
     */
    function displayPage()
    {
        // Default options
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_name = Session::Get(get_class(), 'filter_name');
        } else {
            $filter_pinned = 0;
            $filter_name = NULL;
        }

        $data = [
            'defaults' => [
                'filterPinned' => $filter_pinned,
                'name' => $filter_name
            ]
        ];

        $this->getState()->template = 'usergroup-page';
        $this->getState()->setData($data);
    }

    /**
     * Group Grid
     */
    function grid()
    {
        $user = $this->getUser();

        Session::Set(get_class(), 'Filter', Sanitize::getCheckbox('XiboFilterPinned', 0));

        $filterBy = [
            'group' => Session::Set(get_class(), 'filter_name', Sanitize::getString('filter_name'))
        ];

        $groups = UserGroupFactory::query($this->gridRenderSort(), $this->gridRenderFilter($filterBy));

        foreach ($groups as $group) {
            /* @var \Xibo\Entity\UserGroup $group */

            // we only want to show certain buttons, depending on the user logged in
            if ($user->getUserTypeId() == 1) {
                // Edit
                $group->buttons[] = array(
                    'id' => 'usergroup_button_edit',
                    'url' => $this->urlFor('group.edit.form', ['id' => $group->groupId]),
                    'text' => __('Edit')
                );

                // Delete
                $group->buttons[] = array(
                    'id' => 'usergroup_button_delete',
                    'url' => $this->urlFor('group.delete.form', ['id' => $group->groupId]),
                    'text' => __('Delete')
                );

                // Members
                $group->buttons[] = array(
                    'id' => 'usergroup_button_members',
                    'url' => 'index.php?p=group&q=MembersForm&groupid=' . $group->groupId,
                    'text' => __('Members')
                );

                // Page Security
                $group->buttons[] = array(
                    'id' => 'usergroup_button_page_security',
                    'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $group->groupId,
                    'text' => __('Page Security')
                );

                // Menu Security
                $group->buttons[] = array(
                    'id' => 'usergroup_button_menu_security',
                    'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $group->groupId,
                    'text' => __('Menu Security')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($groups);
    }

    /**
     * Form to Add a Group
     */
    function addForm()
    {
        $this->getState()->template = 'usergroup-form-add';
        $this->getState()->setData([
            'help' => [
                'add' => Help::Link('UserGroup', 'Add')
            ]
        ]);
    }

    /**
     * Form to Add a Group
     * @param int $groupId
     */
    function editForm($groupId)
    {
        $group = UserGroupFactory::getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $this->getState()->template = 'usergroup-form-edit';
        $this->getState()->setData([
            'group' => $group,
            'help' => [
                'add' => Help::Link('UserGroup', 'Edit')
            ]
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $groupId
     * @throws \Xibo\Exception\NotFoundException
     */
    function deleteForm($groupId)
    {
        $group = UserGroupFactory::getById($groupId);

        if (!$this->getUser()->checkDeleteable($group))
            throw new AccessDeniedException();

        $this->getState()->template = 'usergroup-form-delete';
        $this->getState()->setData([
            'group' => $group,
            'help' => [
                'delete' => Help::Link('UserGroup', 'Delete')
            ]
        ]);
    }

    /**
     * Adds a group
     */
    function add()
    {
        // Build a user entity and save it
        $group = new \Xibo\Entity\UserGroup();
        $group->group = Sanitize::getString('group');
        $group->libraryQuota = Sanitize::getInt('libraryQuota');

        // Save
        $group->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $group->group),
            'id' => $group->groupId,
            'data' => [$group]
        ]);
    }

    /**
     * Edits the Group Information
     * @param int $groupId
     */
    function edit($groupId)
    {
        $group = UserGroupFactory::getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $group->group = Sanitize::getString('group');
        $group->libraryQuota = Sanitize::getInt('libraryQuota');

        // Save
        $group->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $group->group),
            'id' => $group->groupId,
            'data' => [$group]
        ]);
    }

    /**
     * Deletes a Group
     * @param int $groupId
     * @throws \Xibo\Exception\NotFoundException
     */
    function delete($groupId)
    {
        $group = UserGroupFactory::getById($groupId);

        if (!$this->getUser()->checkDeleteable($group))
            throw new AccessDeniedException();

        $group->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * Assigns and unassigns pages from groups
     * @return JSON object
     */
    function assign()
    {
        $db =& $this->db;
        $groupid = Sanitize::getInt('groupid');

        $pageids = $_POST['pageids'];

        foreach ($pageids as $pagegroupid) {
            $row = explode(",", $pagegroupid);

            // The page ID actually refers to the pagegroup ID - we have to look up all the page ID's for this
            // PageGroupID
            $SQL = "SELECT pageID FROM pages WHERE pagegroupID = " . Sanitize::int($row[1]);

            if (!$results = $db->query($SQL)) {
                trigger_error($db->error());
                Kit::Redirect(array('success' => false, 'message' => __('Can\'t assign this page to this group') . ' [error getting pages]'));
            }

            while ($page_row = $db->get_row($results)) {
                $pageid = $page_row[0];

                if ($row[0] == "0") {
                    //it isnt assigned and we should assign it
                    $SQL = "INSERT INTO lkpagegroup (groupID, pageID) VALUES ($groupid, $pageid)";

                    if (!$db->query($SQL)) {
                        trigger_error($db->error());
                        Kit::Redirect(array('success' => false, 'message' => __('Can\'t assign this page to this group')));
                    }
                } else {
                    //it is already assigned and we should remove it
                    $SQL = "DELETE FROM lkpagegroup WHERE groupid = $groupid AND pageID = $pageid";

                    if (!$db->query($SQL)) {
                        trigger_error($db->error());
                        Kit::Redirect(array('success' => false, 'message' => __('Can\'t remove this page from this group')));
                    }
                }
            }
        }

        $response = $this->getState();
        $response->SetFormSubmitResponse(__('User Group Page Security Edited'));
        $response->keepOpen = true;

    }

    /**
     * Security for Menu Items
     * @return
     */
    function MenuItemSecurityForm()
    {

        $user = $this->getUser();

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('header_text', __('Select your Menu Assignments'));
        Theme::Set('pager', ApplicationState::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="MenuItemSecurityGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');

        $formFields = array();
        $formFields[] = Form::AddCombo(
            'filter_menu',
            __('Menu'),
            null,
            $db->GetArray("SELECT MenuID, Menu FROM menu"),
            'MenuID',
            'Menu',
            NULL,
            'r');

        Theme::Set('form_fields', $formFields);

        // Call to render the template
        $xiboGrid = Theme::RenderReturn('grid_render');

        // Construct the Response
        $response = $this->getState();
        $response->SetFormRequestResponse($xiboGrid, __('Menu Item Security'), '500', '380');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'MenuSecurity') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Assign / Unassign'), '$("#UserGroupMenuForm").submit()');


        return true;
    }

    /**
     * ACL Form for the provided Entity and GroupId
     * @param string $entity
     * @param int $groupId
     */
    public function aclForm($entity, $groupId)
    {
        // Check permissions to this function
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        // Show a form with a list of entities, checked or unchecked based on the current assignment
        if ($entity == '')
            throw new \InvalidArgumentException(__('ACL form requested without an entity'));

        $requestEntity = $entity;

        // Check to see that we can resolve the entity
        $entity = 'Xibo\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new \InvalidArgumentException(__('ACL form requested with an invalid entity'));

        // Use the factory to get all the entities
        $entities = $entity::query();

        // Load the Group we are working on
        // Get the object
        if ($groupId == 0)
            throw new \InvalidArgumentException(__('ACL form requested without a User Group'));

        $group = UserGroupFactory::getById($groupId);

        // Get all permissions for this user and this object
        $permissions = PermissionFactory::getByGroupId($requestEntity, $groupId);

        Log::debug('Entity: %s, GroupId: %d. ' . var_export($permissions, true), $requestEntity, $groupId);

        $checkboxes = array();

        foreach ($entities as $entity) {
            // Check to see if this entity is set or not
            $entityId = $entity->getId();
            $viewChecked = 0;

            foreach ($permissions as $permission) {
                /* @var Permission $permission */
                if ($permission->objectId == $entityId && $permission->view == 1) {
                    $viewChecked = 1;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $entityId,
                'name' => $entity->getName(),
                'value_view' => $entityId . '_view',
                'value_view_checked' => (($viewChecked == 1) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $data = [
            'entity' => $requestEntity,
            'title' => sprintf(__('ACL for %s'), $group->group),
            'groupId' => $groupId,
            'group' => $group->group,
            'permissions' => $checkboxes,
            'help' => Help::Link('User', 'Acl')
        ];

        $this->getState()->template = 'usergroup-form-acl';
        $this->getState()->setData($data);
    }

    /**
     * ACL update
     * @param string $entity
     * @param int $groupId
     */
    public function acl($entity, $groupId)
    {
        // Check permissions to this function
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        if ($entity == '')
            throw new \InvalidArgumentException(__('ACL form requested without an entity'));

        $requestEntity = $entity;

        // Check to see that we can resolve the entity
        $entity = 'Xibo\\Factory\\' . $entity . 'Factory';

        if (!class_exists($entity) || !method_exists($entity, 'getById'))
            throw new \InvalidArgumentException(__('ACL form requested with an invalid entity'));

        // Load the Group we are working on
        // Get the object
        if ($groupId == 0)
            throw new \InvalidArgumentException(__('ACL form requested without a User Group'));

        $group = UserGroupFactory::getById($groupId);

        // Use the factory to get all the entities
        $entities = $entity::query();

        // Get all permissions for this user and this object
        $permissions = PermissionFactory::getByGroupId($requestEntity, $groupId);
        $objectIds = $this->getApp()->request()->params('objectId');

        if (!is_array($objectIds))
            throw new \InvalidArgumentException(__('Missing New ACL'));

        $newAcl = array();
        array_map(function ($string) use (&$newAcl) {
            $array = explode('_', $string);
            return $newAcl[$array[0]][$array[1]] = 1;
        }, $objectIds);

        Log::debug(var_export($newAcl, true));

        foreach ($entities as $entity) {
            // Check to see if this entity is set or not
            $objectId = $entity->getId();
            $permission = null;
            $view = (array_key_exists($objectId, $newAcl));

            // Is the permission currently assigned?
            foreach ($permissions as $row) {
                /* @var \Xibo\Entity\Permission $row */
                if ($row->objectId == $objectId) {
                    $permission = $row;
                    break;
                }
            }

            if ($permission == null) {
                if ($view) {
                    // Not currently assigned and needs to be
                    $permission = PermissionFactory::create($groupId, $requestEntity, $objectId, 1, 0, 0);
                    $permission->save();
                }
            }
            else {
                Log::debug('Permission Exists for %s, and has been set to %d.', $entity->getName(), $view);
                // Currently assigned
                if ($view) {
                    $permission->view = 1;
                    $permission->save();
                }
                else {
                    $permission->delete();
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('ACL set for %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * Assign Menu Item Security Grid
     * @return
     */
    function MenuItemSecurityGrid()
    {

        $groupid = Sanitize::getInt('groupid');

        $filter_menu = Sanitize::getString('filter_menu');

        Theme::Set('form_id', 'UserGroupMenuForm');
        Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupid . '">');
        Theme::Set('form_action', 'index.php?p=group&q=MenuItemSecurityAssign');

        $SQL = <<<END
		SELECT 	menu.Menu,
				menuitem.Text,
				menuitem.MenuItemID,
				CASE WHEN menuitems_assigned.MenuItemID IS NULL
					THEN 0
		        	ELSE 1
		        END AS AssignedID
		FROM	menuitem
		INNER JOIN menu
		ON		menu.MenuID = menuitem.MenuID
		LEFT OUTER JOIN
				(SELECT DISTINCT lkmenuitemgroup.MenuItemID
				 FROM	lkmenuitemgroup
				 WHERE  GroupID = $groupid
				) menuitems_assigned
		ON menuitem.MenuItemID = menuitems_assigned.MenuItemID
		WHERE menuitem.MenuID = %d
END;

        $SQL = sprintf($SQL, $filter_menu);

        if (!$results = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Cannot get the menu items for this Group.'), E_USER_ERROR);
        }

        if ($db->num_rows($results) == 0) {
            trigger_error(__('Cannot get the menu items for this Group.'), E_USER_ERROR);
        }

        // while loop
        $rows = array();

        while ($row = $db->get_assoc_row($results)) {
            $row['name'] = $row['Text'];
            $row['pageid'] = $row['MenuItemID'];
            $row['assigned'] = (($row['AssignedID'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove');
            $row['assignedid'] = $row['AssignedID'];
            $row['checkbox_value'] = $row['AssignedID'] . ',' . $row['MenuItemID'];
            $row['checkbox_ticked'] = '';

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('usergroup_form_menusecurity_grid');

        $response = $this->getState();
        $response->SetGridResponse($output);
        $response->initialSortColumn = 2;

    }

    /**
     * Menu Item Security Assignment to Groups
     * @return
     */
    function MenuItemSecurityAssign()
    {


        $groupid = Sanitize::getInt('groupid');

        $pageids = $_POST['pageids'];

        foreach ($pageids as $menuItemId) {
            $row = explode(",", $menuItemId);

            $menuItemId = $row[1];

            // If the ID is 0 then this menu item is not currently assigned
            if ($row[0] == "0") {
                //it isnt assigned and we should assign it
                $SQL = sprintf("INSERT INTO lkmenuitemgroup (GroupID, MenuItemID) VALUES (%d, %d)", $groupid, $menuItemId);

                if (!$db->query($SQL)) {
                    trigger_error($db->error());
                    Kit::Redirect(array('success' => false, 'message' => __('Can\'t assign this menu item to this group')));
                }
            } else {
                //it is already assigned and we should remove it
                $SQL = sprintf("DELETE FROM lkmenuitemgroup WHERE groupid = %d AND MenuItemID = %d", $groupid, $menuItemId);

                if (!$db->query($SQL)) {
                    trigger_error($db->error());
                    Kit::Redirect(array('success' => false, 'message' => __('Can\'t remove this menu item from this group')));
                }
            }
        }

        // Response
        $response = $this->getState();
        $response->SetFormSubmitResponse(__('User Group Menu Security Edited'));
        $response->keepOpen = true;

    }

    /**
     * Shows the Members of a Group
     */
    public function MembersForm()
    {

        $response = $this->getState();
        $groupID = Sanitize::getInt('groupid');

        // There needs to be two lists here.

        // Set some information about the form
        Theme::Set('users_assigned_id', 'usersIn');
        Theme::Set('users_available_id', 'usersOut');
        Theme::Set('users_assigned_url', 'index.php?p=group&q=SetMembers&GroupID=' . $groupID);

        // Users in group
        $usersAssigned = $this->getUser()->userList(null, array('groupIds' => array($groupID)));

        Theme::Set('users_assigned', $usersAssigned);

        // Users not in group
        if (!$allUsers = $this->getUser()->userList())
            trigger_error(__('Error getting all users'), E_USER_ERROR);

        // The available users are all users except users already in assigned users
        $usersAvailable = array();

        foreach ($allUsers as $user) {
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($usersAssigned as $userAssigned) {
                if ($userAssigned['userid'] == $user['userid']) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists)
                $usersAvailable[] = $user;
        }

        Theme::Set('users_available', $usersAvailable);

        $form = Theme::RenderReturn('usergroup_form_user_assign');

        $response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'ManageMembersCallBack');
        $response->AddButton(__('Help'), "XiboHelpRender('" . Help::Link('UserGroup', 'Members') . "')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'MembersSubmit()');

    }

    /**
     * Sets the Members of a group
     */
    public function SetMembers()
    {
        $db =& $this->db;
        $response = new ApplicationState();
        $groupObject = new UserGroup($db);

        $groupId = Sanitize::getInt('GroupID');
        $users = \Kit::GetParam('UserID', _POST, _ARRAY, array());

        // We will receive a list of users from the UI which are in the "assign column" at the time the form is
        // submitted.
        // We want to go through and unlink any users that are NOT in that list, but that the current user has access
        // to edit.
        // We want to add any users that are in that list (but aren't already assigned)

        // All users that this session has access to
        if (!$allUsers = $this->getUser()->userList())
            trigger_error(__('Error getting all users'), E_USER_ERROR);

        // Convert to an array of ID's for convenience
        $allUserIds = array_map(function ($array) {
            return $array['userid'];
        }, $allUsers);

        // Users in group
        $usersAssigned = $this->getUser()->userList(null, array('groupIds' => array($groupId)));

        foreach ($usersAssigned as $row) {
            // Did this session have permission to do anything to this user?
            // If not, move on
            if (!in_array($row['userid'], $allUserIds))
                continue;

            // Is this user in the provided list of users?
            if (in_array($row['userid'], $users)) {
                // This user is already assigned, so we remove it from the $users array
                unset($users[$row['userid']]);
            } else {
                // It isn't therefore needs to be removed
                if (!$groupObject->Unlink($groupId, $row['userid']))
                    trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
            }
        }

        // Add any users that are still missing after tha assignment process
        foreach ($users as $userId) {
            // Add any that are missing
            if (!$groupObject->Link($groupId, $userId)) {
                trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
            }
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);

    }
}
