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
use Xibo\Entity\User;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class UserGroup extends Base
{
    //general fields
    private $groupid;
    private $group = "";


    //init
    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        $usertype = \Kit::GetParam('usertype', _SESSION, _INT, 0);
        $this->groupid = \Kit::GetParam('groupid', _REQUEST, _INT, 0);

        // Do we have a user group selected?
        if ($this->groupid != 0) {
            // If so then we will need to get some information about it
            $SQL = <<<END
			SELECT 	group.GroupID,
					group.Group
			FROM `group`
			WHERE groupID = %d
END;

            $SQL = sprintf($SQL, $this->groupid);

            if (!$results = $db->query($SQL)) {
                trigger_error($db->error());
                trigger_error(__("Can not get Group information."), E_USER_ERROR);
            }

            $aRow = $db->get_assoc_row($results);

            $this->group = $aRow['Group'];
        }

        // Include the group data classes
        include_once('lib/data/usergroup.data.class.php');
    }

    /**
     * Display page logic
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));

        // Default options
        if (\Kit::IsFilterPinned('usergroup', 'Filter')) {
            $filter_pinned = 1;
            $filter_name = Session::Get('usergroup', 'filter_name');
        } else {
            $filter_pinned = 0;
            $filter_name = NULL;
        }

        $formFields = array();
        $formFields[] = Form::AddText('filter_name', __('Name'), $filter_name, NULL, 'n');

        $formFields[] = Form::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $filter_pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('User Groups'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

        return array(
            array('title' => __('Add User Group'),
                'class' => 'XiboFormButton',
                'selected' => false,
                'link' => 'index.php?p=group&q=GroupForm',
                'help' => __('Add a new User Group'),
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
     * Group Grid
     * Called by AJAX
     * @return
     */
    function Grid()
    {

        $user = $this->getUser();

        $filter_name = \Xibo\Helper\Sanitize::getString('filter_name');

        \Xibo\Helper\Session::Set('usergroup', 'Filter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        \Xibo\Helper\Session::Set('usergroup', 'filter_name', $filter_name);

        $SQL = <<<END
		SELECT 	group.group,
				group.groupID
		FROM `group`
		WHERE IsUserSpecific = 0 AND IsEveryone = 0
END;

        if ($filter_name != '')
            $SQL .= sprintf(" AND group.group LIKE '%%%s%%' ", $db->escape_string($filter_name));

        $SQL .= " ORDER BY group.group ";

        //Log::debug($SQL);

        if (!$results = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__("Can not get group information."), E_USER_ERROR);
        }

        $cols = array(
            array('name' => 'usergroup', 'title' => __('User Group'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        while ($row = $db->get_assoc_row($results)) {
            $groupid = \Xibo\Helper\Sanitize::int($row['groupID']);
            $group = \Xibo\Helper\Sanitize::string($row['group']);

            $row['usergroup'] = $group;

            // we only want to show certain buttons, depending on the user logged in
            if ($user->getUserTypeId() == 1) {
                // Edit
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_edit',
                    'url' => 'index.php?p=group&q=GroupForm&groupid=' . $groupid,
                    'text' => __('Edit')
                );

                // Delete
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_delete',
                    'url' => 'index.php?p=group&q=DeleteForm&groupid=' . $groupid,
                    'text' => __('Delete')
                );

                // Members
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_members',
                    'url' => 'index.php?p=group&q=MembersForm&groupid=' . $groupid,
                    'text' => __('Members')
                );

                // Page Security
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_page_security',
                    'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $groupid,
                    'text' => __('Page Security')
                );

                // Menu Security
                $row['buttons'][] = array(
                    'id' => 'usergroup_button_menu_security',
                    'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $groupid,
                    'text' => __('Menu Security')
                );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response = $this->getState();
        $response->SetGridResponse($output);

    }

    /**
     * Add / Edit Group Form
     * @return
     */
    function GroupForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        Theme::Set('form_id', 'UserGroupForm');

        // alter the action variable depending on which form we are after
        if ($this->groupid == "") {
            Theme::Set('form_action', 'index.php?p=group&q=Add');

            $form_name = 'Add User Group';
            $form_help_link = Help::Link('UserGroup', 'Add');
        } else {
            Theme::Set('form_action', 'index.php?p=group&q=Edit');
            Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $this->groupid . '">');
            Theme::Set('group', $this->group);

            $form_name = 'Edit User Group';
            $form_help_link = Help::Link('UserGroup', 'Edit');
        }

        $formFields = array();
        $formFields[] = Form::AddText('group', __('Name'), $this->group,
            __('The Name for this User Group'), 'n', 'maxlength="50" required');

        Theme::Set('form_fields', $formFields);

        // Construct the Response
        $response->SetFormRequestResponse(NULL, $form_name, '400', '180');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $form_help_link . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#UserGroupForm").submit()');


        return true;
    }

    /**
     * Assign Page Security Filter form (will trigger grid)
     * @return boolean
     */
    function PageSecurityForm()
    {
        $response = new ApplicationState();

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('header_text', __('Please select your Page Security Assignments'));
        Theme::Set('pager', ApplicationState::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="PageSecurityFormGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');

        $formFields = array();
        $formFields[] = Form::AddText('filter_name', __('Name'), NULL, NULL, 'n');
        Theme::Set('form_fields', $formFields);

        // Call to render the template
        $xiboGrid = Theme::RenderReturn('grid_render');

        // Construct the Response
        $response->SetFormRequestResponse($xiboGrid, __('Page Security'), '500', '380');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('User', 'PageSecurity') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Assign / Unassign'), '$("#UserGroupForm").submit()');


        return true;
    }

    /**
     * Assign Page Security Grid
     */
    function PageSecurityFormGrid()
    {
        $db =& $this->db;
        $groupId = \Xibo\Helper\Sanitize::getInt('groupid');

        Theme::Set('form_id', 'UserGroupForm');
        Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupId . '">');
        Theme::Set('form_action', 'index.php?p=group&q=assign');

        $params = array();

        $SQL = <<<END
		SELECT 	pagegroup.pagegroup,
				pagegroup.pagegroupID,
				CASE WHEN pages_assigned.pagegroupID IS NULL
					THEN 0
		        	ELSE 1
		        END AS AssignedID
		FROM	pagegroup
		LEFT OUTER JOIN
				(SELECT DISTINCT pages.pagegroupID
				 FROM	lkpagegroup
				 INNER JOIN pages ON lkpagegroup.pageID = pages.pageID
				 WHERE  groupID = :groupId
				) pages_assigned
		ON pagegroup.pagegroupID = pages_assigned.pagegroupID
END;
        $params['groupId'] = $groupId;

        // Filter by Name?
        if (\Kit::GetParam('filter_name', _POST, _STRING) != '') {
            $SQL .= ' WHERE pagegroup.pagegroup LIKE :name ';
            $params['name'] = '%' . \Kit::GetParam('filter_name', _POST, _STRING) . '%';
        }

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // while loop
            $rows = array();

            foreach ($results as $row) {
                $row['name'] = $row['pagegroup'];
                $row['pageid'] = $row['pagegroupID'];
                $row['assigned'] = (($row['AssignedID'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove');
                $row['assignedid'] = $row['AssignedID'];
                $row['checkbox_value'] = $row['AssignedID'] . ',' . $row['pagegroupID'];
                $row['checkbox_ticked'] = '';

                $rows[] = $row;
            }

            Theme::Set('table_rows', $rows);

            $output = Theme::RenderReturn('usergroup_form_pagesecurity_grid');

            $response = $this->getState();
            $response->SetGridResponse($output);
            $response->initialSortColumn = 2;

        } catch (Exception $e) {
            Log::Error($e);
            trigger_error(__('Unable to process request'), E_USER_ERROR);
        }
    }

    /**
     * Shows the Delete Group Form
     */
    function DeleteForm()
    {
        $groupId = $this->groupid;
        $response = $this->getState();

        // Get the group name
        $group = __('Unknown');
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();
            $sth = $dbh->prepare('SELECT `group` FROM `group` WHERE groupId = :groupId');
            $sth->execute(array('groupId' => $groupId));

            if ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $group = \Xibo\Helper\Sanitize::string($row['group']);
            }
        } catch (Exception $e) {
            Log::Error($e->getMessage());
        }

        // Set some information about the form
        Theme::Set('form_id', 'UserGroupDeleteForm');
        Theme::Set('form_action', 'index.php?p=group&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupId . '">');

        Theme::Set('form_fields', array(Form::AddMessage(sprintf(__('Are you sure you want to delete %s?'), $group))));

        // Construct the Response
        $response->SetFormRequestResponse(NULL, sprintf(__('Delete %s'), $group), '400', '180');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('UserGroup', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#UserGroupDeleteForm").submit()');


        return true;
    }

    /**
     * Adds a group
     * @return
     */
    function Add()
    {


        $response = $this->getState();

        $group = \Xibo\Helper\Sanitize::getString('group');

        $userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Add($group, 0))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('User Group Added'), false);

    }

    /**
     * Edits the Group Information
     * @return
     */
    function Edit()
    {


        $groupid = \Xibo\Helper\Sanitize::getInt('groupid');
        $group = \Xibo\Helper\Sanitize::getString('group');

        $userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Edit($groupid, $group))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);

        $response = $this->getState();
        $response->SetFormSubmitResponse(__('User Group Edited'), false);

    }

    /**
     * Deletes a Group
     * @return
     */
    function Delete()
    {


        $groupid = \Xibo\Helper\Sanitize::getInt('groupid');

        $userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Delete($groupid))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);

        $response = $this->getState();
        $response->SetFormSubmitResponse(__('User Group Deleted'), false);

    }

    /**
     * Assigns and unassigns pages from groups
     * @return JSON object
     */
    function assign()
    {
        $db =& $this->db;
        $groupid = \Xibo\Helper\Sanitize::getInt('groupid');

        $pageids = $_POST['pageids'];

        foreach ($pageids as $pagegroupid) {
            $row = explode(",", $pagegroupid);

            // The page ID actually refers to the pagegroup ID - we have to look up all the page ID's for this
            // PageGroupID
            $SQL = "SELECT pageID FROM pages WHERE pagegroupID = " . \Xibo\Helper\Sanitize::int($row[1]);

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
     * Assign Menu Item Security Grid
     * @return
     */
    function MenuItemSecurityGrid()
    {

        $groupid = \Xibo\Helper\Sanitize::getInt('groupid');

        $filter_menu = \Xibo\Helper\Sanitize::getString('filter_menu');

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


        $groupid = \Xibo\Helper\Sanitize::getInt('groupid');

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
        $groupID = \Xibo\Helper\Sanitize::getInt('groupid');

        // There needs to be two lists here.

        // Set some information about the form
        Theme::Set('users_assigned_id', 'usersIn');
        Theme::Set('users_available_id', 'usersOut');
        Theme::Set('users_assigned_url', 'index.php?p=group&q=SetMembers&GroupID=' . $groupID);

        // Users in group
        $usersAssigned = $this->user->userList(null, array('groupIds' => array($groupID)));

        Theme::Set('users_assigned', $usersAssigned);

        // Users not in group
        if (!$allUsers = $this->user->userList())
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

        $groupId = \Xibo\Helper\Sanitize::getInt('GroupID');
        $users = \Kit::GetParam('UserID', _POST, _ARRAY, array());

        // We will receive a list of users from the UI which are in the "assign column" at the time the form is
        // submitted.
        // We want to go through and unlink any users that are NOT in that list, but that the current user has access
        // to edit.
        // We want to add any users that are in that list (but aren't already assigned)

        // All users that this session has access to
        if (!$allUsers = $this->user->userList())
            trigger_error(__('Error getting all users'), E_USER_ERROR);

        // Convert to an array of ID's for convenience
        $allUserIds = array_map(function ($array) {
            return $array['userid'];
        }, $allUsers);

        // Users in group
        $usersAssigned = $this->user->userList(null, array('groupIds' => array($groupId)));

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

?>
