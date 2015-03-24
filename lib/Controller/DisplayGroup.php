<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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

class DisplayGroup extends Base
{
    /**
     * Display Group Page Render
     */
    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displaygroup"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Display Groups'));
        Theme::Set('form_fields', array());
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

        return array(
            array('title' => __('Add Display Group'),
                'class' => 'XiboFormButton',
                'selected' => false,
                'link' => 'index.php?p=displaygroup&q=AddForm',
                'help' => __('Add a new Display Group'),
                'onclick' => ''
            )
        );
    }

    /**
     * Shows the Display groups
     * @return
     */
    public function Grid()
    {

        $user = $this->getUser();
        $response = new ApplicationState();

        $displayGroups = $this->user->DisplayGroupList();

        if (!is_array($displayGroups))
            trigger_error(__('Cannot get list of display groups.'), E_USER_ERROR);

        $cols = array(
            array('name' => 'displaygroup', 'title' => __('Name')),
            array('name' => 'description', 'title' => __('Description'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($displayGroups as $row) {
            if ($row['isdisplayspecific'] != 0)
                continue;

            if ($row['edit'] == 1) {
                // Show the edit button, members button

                // Group Members
                $row['buttons'][] = array(
                    'id' => 'displaygroup_button_group_members',
                    'url' => 'index.php?p=displaygroup&q=MembersForm&DisplayGroupID=' . $row['displaygroupid'] . '&DisplayGroup=' . $row['displaygroup'],
                    'text' => __('Group Members')
                );

                // Edit
                $row['buttons'][] = array(
                    'id' => 'displaygroup_button_edit',
                    'url' => 'index.php?p=displaygroup&q=EditForm&DisplayGroupID=' . $row['displaygroupid'],
                    'text' => __('Edit')
                );

                // File Associations
                $row['buttons'][] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => 'index.php?p=displaygroup&q=FileAssociations&DisplayGroupID=' . $row['displaygroupid'],
                    'text' => __('Assign Files')
                );
            }

            if ($row['del'] == 1) {
                // Show the delete button
                $row['buttons'][] = array(
                    'id' => 'displaygroup_button_delete',
                    'url' => 'index.php?p=displaygroup&q=DeleteForm&DisplayGroupID=' . $row['displaygroupid'],
                    'text' => __('Delete')
                );
            }

            if ($row['modifypermissions'] == 1) {
                // Show the modify permissions button
                $row['buttons'][] = array(
                    'id' => 'displaygroup_button_permissions',
                    'url' => 'index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $row['displaygroupid'],
                    'text' => __('Permissions')
                );

                // Version Information
                $row['buttons'][] = array(
                    'id' => 'display_button_version_instructions',
                    'url' => 'index.php?p=displaygroup&q=VersionInstructionsForm&displaygroupid=' . $row['displaygroupid'],
                    'text' => __('Version Information')
                );
            }

            // Assign this to the table row
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);

    }

    /**
     * Shows an add form for a display group
     */
    public function AddForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        Theme::Set('form_id', 'DisplayGroupAddForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Add');

        $formFields[] = FormManager::AddText('group', __('Name'), NULL,
            __('The Name for this Group'), 'n', 'required');

        $formFields[] = FormManager::AddText('desc', __('Description'), NULL,
            __('A short description of this Group'), 'd', 'maxlength="254"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Display Group'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayGroupAddForm").submit()');

    }

    /**
     * Shows an edit form for a display group
     */
    public function EditForm()
    {

        $user =& $this->user;
        $response = new ApplicationState();
        $helpManager = new Help($db, $user);

        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Pull the currently known info from the DB
        $SQL = "SELECT DisplayGroupID, DisplayGroup, Description FROM displaygroup WHERE DisplayGroupID = %d AND IsDisplaySpecific = 0";
        $SQL = sprintf($SQL, $displayGroupID);

        if (!$row = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Group'), E_USER_ERROR);
        }

        // Pull out these columns
        if (count($row) <= 0)
            trigger_error(__('No display group found.'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayGroupEditForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayGroupID" value="' . $displayGroupID . '" />');

        $formFields[] = FormManager::AddText('group', __('Name'), \Xibo\Helper\Sanitize::string($row['DisplayGroup']),
            __('The Name for this Group'), 'n', 'required');

        $formFields[] = FormManager::AddText('desc', __('Description'), \Xibo\Helper\Sanitize::string($row['Description']),
            __('A short description of this Group'), 'd', 'maxlength="254"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Display Group'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayGroupEditForm").submit()');

    }

    /**
     * Shows the Delete Group Form
     */
    function DeleteForm()
    {

        $response = new ApplicationState();
        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayGroupDeleteForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayGroupID" value="' . $displayGroupID . '" />');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete this display? This cannot be undone.'))));

        $response->SetFormRequestResponse(NULL, __('Delete Display Group'), '350px', '175px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DisplayGroupDeleteForm").submit()');

    }

    /**
     * Display Group Members form
     */
    public function MembersForm()
    {

        $response = new ApplicationState();
        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // There needs to be two lists here.
        // One of which is the Displays currently assigned to this group
        // The other is a list of displays that are available to be assigned (i.e. the opposite of the first list)

        // Set some information about the form
        Theme::Set('displays_assigned_id', 'displaysIn');
        Theme::Set('displays_available_id', 'displaysOut');
        Theme::Set('displays_assigned_url', 'index.php?p=displaygroup&q=SetMembers&DisplayGroupID=' . $displayGroupID);

        // Displays in group
        $SQL = "";
        $SQL .= "SELECT display.DisplayID, ";
        $SQL .= "       display.Display, ";
        $SQL .= "       CONCAT('DisplayID_', display.DisplayID) AS list_id ";
        $SQL .= "FROM   display ";
        $SQL .= "       INNER JOIN lkdisplaydg ";
        $SQL .= "       ON     lkdisplaydg.DisplayID = display.DisplayID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);
        $SQL .= " ORDER BY display.Display ";

        $displays_assigned = $this->user->DisplayList(array('display'), array('displaygroupid' => $displayGroupID), 'edit');

        if (!is_array($displays_assigned))
            trigger_error(__('Error getting Displays'), E_USER_ERROR);

        // Build a new available array, based on the view permissions.
        $displaysAssigned = array();

        foreach ($displays_assigned as $display) {

            // Go through each and set the appropriate fields
            $displaysAssigned[] = array(
                'Display' => $display['display'],
                'list_id' => 'DisplayID_' . $display['displayid']
            );
        }

        Theme::Set('displays_assigned', $displaysAssigned);

        // All Displays
        $displays = $this->user->DisplayList(array('display'), array('exclude_displaygroupid' => $displayGroupID), 'edit');

        if (!is_array($displays))
            trigger_error(__('Error getting Displays'), E_USER_ERROR);

        // Build a new available array, based on the view permissions.
        $displaysAvailable = array();

        foreach ($displays as $display) {
            // Go through each and set the appropriate fields
            $displaysAvailable[] = array(
                'Display' => $display['display'],
                'list_id' => 'DisplayID_' . $display['displayid']
            );
        }

        Theme::Set('displays_available', $displaysAvailable);


        $form = Theme::RenderReturn('displaygroup_form_display_assign');

        $response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'DisplayGroupManageMembersCallBack');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Members') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'DisplayGroupMembersSubmit()');

    }

    /**
     * Adds a Display Group
     * @return
     */
    public function Add()
    {



        $response = $this->getState();

        $displayGroup = \Xibo\Helper\Sanitize::getString('group');
        $description = \Xibo\Helper\Sanitize::getString('desc');

        $displayGroupObject = new DisplayGroup($db);

        if (!$displayGroupId = $displayGroupObject->Add($displayGroup, 0, $description)) {
            trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
        }

        // Add full permissions for this user to this group
        $security = new DisplayGroupSecurity($db);

        if (!$security->Link($displayGroupId, $this->user->getGroupFromID($this->user->userId, true), 1, 1, 1))
            trigger_error(__('Unable to set permissions'));

        $response->SetFormSubmitResponse(__('Display Group Added'), false);

    }

    /**
     * Edits a Display Group
     * @return
     */
    public function Edit()
    {



        $response = new ApplicationState();

        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');
        $displayGroup = \Xibo\Helper\Sanitize::getString('group');
        $description = \Xibo\Helper\Sanitize::getString('desc');

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Deal with the Edit
        $displayGroupObject = new DisplayGroup($db);

        if (!$displayGroupObject->Edit($displayGroupID, $displayGroup, $description)) {
            trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Display Group Edited'), false);

    }

    /**
     * Deletes a Group
     * @return
     */
    function Delete()
    {



        $response = new ApplicationState();

        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Deal with the Delete
        $displayGroupObject = new DisplayGroup($db);

        if (!$displayGroupObject->Delete($displayGroupID)) {
            trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Display Group Deleted'), false);

    }

    /**
     * Sets the Members of a group
     * @return
     */
    public function SetMembers()
    {

        $response = new ApplicationState();
        $displayGroupObject = new DisplayGroup($db);

        $displayGroupID = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');
        $displays = \Kit::GetParam('DisplayID', _POST, _ARRAY, array());
        $members = array();

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupID, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Get a list of current members
        $SQL = "";
        $SQL .= "SELECT display.DisplayID ";
        $SQL .= "FROM   display ";
        $SQL .= "       INNER JOIN lkdisplaydg ";
        $SQL .= "       ON     lkdisplaydg.DisplayID = display.DisplayID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayGroupID   = %d", $displayGroupID);

        if (!$resultIn = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Displays'), E_USER_ERROR);
        }

        while ($row = $db->get_assoc_row($resultIn)) {
            // Test whether this ID is in the array or not
            $displayID = \Xibo\Helper\Sanitize::int($row['DisplayID']);

            if (!in_array($displayID, $displays)) {
                // Its currently assigned but not in the $displays array
                //  so we unassign
                if (!$displayGroupObject->Unlink($displayGroupID, $displayID)) {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            } else {
                $members[] = $displayID;
            }
        }

        foreach ($displays as $displayID) {
            // Add any that are missing
            if (!in_array($displayID, $members)) {
                if (!$displayGroupObject->Link($displayGroupID, $displayID)) {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);

    }

    /**
     * Show the Permissions for this Display Group
     */
    public function PermissionsForm()
    {

        $user = $this->getUser();
        $response = $this->getState();
        $helpManager = new Help($db, $user);

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this display group'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayGroupPermissionsForm');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="displayGroupId" value="' . $displayGroupId . '" />');

        // List of all Groups with a view / edit / delete check box
        $permissions = new UserGroup();
        if (!$result = $permissions->GetPermissionsForObject('lkdisplaygroupgroup', 'DisplayGroupID', $displayGroupId))
            trigger_error($permissions->GetErrorMessage(), E_USER_ERROR);

        if (count($result) <= 0)
            trigger_error(__('Unable to get permissions for this Display Group'), E_USER_ERROR);

        $checkboxes = array();

        foreach ($result as $row) {
            $groupId = $row['groupid'];
            $rowClass = ($row['isuserspecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                'id' => $groupId,
                'name' => \Xibo\Helper\Sanitize::string($row['group']),
                'class' => $rowClass,
                'value_view' => $groupId . '_view',
                'value_view_checked' => (($row['view'] == 1) ? 'checked' : ''),
                'value_edit' => $groupId . '_edit',
                'value_edit_checked' => (($row['edit'] == 1) ? 'checked' : ''),
                'value_del' => $groupId . '_del',
                'value_del_checked' => (($row['del'] == 1) ? 'checked' : ''),
            );

            $checkboxes[] = $checkbox;
        }

        $formFields = array();
        $formFields[] = FormManager::AddPermissions('groupids[]', $checkboxes);

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayGroupPermissionsForm").submit()');

    }

    /**
     * Add/Modify Permissions
     */
    public function Permissions()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('displayGroupId');
        $groupIds = \Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this display group'), E_USER_ERROR);

        // Unlink all
        $security = new DisplayGroupSecurity($db);
        if (!$security->UnlinkAll($displayGroupId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach ($groupIds as $groupPermission) {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first) {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId) {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($displayGroupId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1]) {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first) {
            if (!$security->Link($displayGroupId, $lastGroupId, $view, $edit, $del))
                trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));

    }

    public function FileAssociations()
    {

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displaygroup"><input type="hidden" name="q" value="FileAssociationsView"><input type="hidden" name="displaygroupid" value="' . $displayGroupId . '">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Module types filter
        $modules = $this->user->ModuleAuth(0, '', -1);
        $types = array();

        foreach ($modules as $module) {
            $type['moduleid'] = $module['Module'];
            $type['module'] = $module['Name'];

            $types[] = $type;
        }

        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        Theme::Set('module_field_list', $types);

        // Get the currently associated media items and put them in the top bar
        $existing = array();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('
                SELECT media.MediaID, media.Name
                  FROM `media`
                    INNER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.mediaid
                 WHERE lkmediadisplaygroup.displaygroupid = :displaygroupid
            ');

            $sth->execute(array('displaygroupid' => $displayGroupId));

            $existing = $sth->fetchAll();
        } catch (Exception $e) {

            Log::error($e->getMessage(), get_class(), __FUNCTION__);

            trigger_error(__('Unable to get existing assignments.'), E_USER_ERROR);
        }

        Theme::Set('existing_associations', $existing);

        // Call to render the template
        $output = Theme::RenderReturn('displaygroup_fileassociations_form_assign');

        // Construct the Response
        $response = $this->getState();
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogClass = 'modal-big';
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Associate an item from the Library');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'FileAssociations') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Assign'), 'FileAssociationsSubmit(' . $displayGroupId . ')');

    }

    public function FileAssociationsView()
    {
        $user = $this->getUser();

        //Input vars
        $mediatype = \Xibo\Helper\Sanitize::getString('filter_type');
        $name = \Xibo\Helper\Sanitize::getString('filter_name');
        $displaygroupid = \Xibo\Helper\Sanitize::getInt('displaygroupid');

        // Get the currently associated media items and put them in the top bar
        $existing = array();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('
                SELECT mediaid
                  FROM `lkmediadisplaygroup`
                 WHERE displaygroupid = :displaygroupid
            ');

            $sth->execute(array('displaygroupid' => $displaygroupid));

            while ($existing[] = $sth->fetchColumn()) ;
        } catch (Exception $e) {

            Log::error($e->getMessage(), get_class(), __FUNCTION__);

            trigger_error(__('Unable to get existing assignments.'), E_USER_ERROR);
        }

        // Get a list of media
        $mediaList = $user->MediaList(NULL, array('type' => $mediatype, 'name' => $name));

        $rows = array();

        // Add some extra information
        foreach ($mediaList as $row) {

            if (in_array($row['mediaid'], $existing))
                continue;

            $row['list_id'] = 'MediaID_' . $row['mediaid'];

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response = $this->getState();
        $response->SetGridResponse(Theme::RenderReturn('displaygroup_fileassociations_form_assign_list'));
        $response->callBack = 'FileAssociationsCallback';
        $response->pageSize = 5;

    }

    public function SetFileAssociations()
    {
        $user =& $this->user;
        $response = new ApplicationState();

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('displaygroupid');
        $mediaList = \Kit::GetParam('MediaID', _POST, _ARRAY_INT, NULL, false);

        if ($displayGroupId == 0)
            trigger_error(__('Display Group not selected'), E_USER_ERROR);

        // Auth
        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);


        $displayGroup = new DisplayGroup($this->db);

        if (!$displayGroup->AssociateFiles($this->user, $displayGroupId, $mediaList))
            trigger_error($displayGroup->GetErrorMessage(), E_USER_ERROR);

        // Success
        $response->SetFormSubmitResponse(sprintf(__('%d Media Items Assigned'), count($mediaList)));

    }

    public function VersionInstructionsForm()
    {
        $response = $this->getState();

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('displaygroupid');
        $displayId = \Xibo\Helper\Sanitize::getInt('displayid');
        Theme::Set('installer_file_id', 0);

        // List of effected displays
        $rows = array();

        if ($displayId != 0) {
            // Get some version information about this display.
            if (!$displays = $this->user->DisplayList(array('display'), array('displayid' => $displayId)))
                trigger_error(__('Unknown Display'), E_USER_ERROR);
        } else {
            // Get a list of displays with their version information?
            if (!$displays = $this->user->DisplayList(array('display'), array('displaygroupid' => $displayGroupId)))
                trigger_error(__('No displays in this group'), E_USER_ERROR);
        }

        foreach ($displays as $display) {
            $rows[] = array(
                'display' => Theme::Prepare($display['display']),
                'client_type' => Theme::Prepare($display['client_type']),
                'client_version' => Theme::Prepare($display['client_version']),
                'client_code' => Theme::Prepare($display['client_code'])
            );
        }

        // Store this for use in the theme
        Theme::Set('displays', $displays);

        // Present a list of possible files to choose from (generic file module)
        $mediaList = $this->user->MediaList(NULL, array('type' => 'genericfile'));
        array_unshift($mediaList, array('mediaid' => 0, 'media' => ''));
        Theme::Set('media_field_list', $mediaList);

        // Set some information about the form
        Theme::Set('form_id', 'VersionInstructions');
        Theme::Set('form_action', 'index.php?p=displaygroup&q=VersionInstructions');
        Theme::Set('form_meta', '<input type="hidden" name="displaygroupid" value="' . $displayGroupId . '">');

        $form = Theme::RenderReturn('display_form_version_instructions');

        $response->SetFormRequestResponse($form, __('Set Instructions for Upgrading this client'), '300px', '250px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#VersionInstructions").submit()');

    }

    public function VersionInstructions()
    {
        $response = $this->getState();


        $displayGroupId = \Xibo\Helper\Sanitize::getInt('displaygroupid');
        $mediaId = \Xibo\Helper\Sanitize::getInt('mediaid');

        // Make sure we have permission to do this to this display
        $auth = $this->user->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Make sure we have permission to use this file
        $mediaAuth = $this->user->MediaAuth($mediaId, true);

        if (!$mediaAuth->view)
            trigger_error(__('You have selected media that you no longer have permission to use. Please reload the form.'), E_USER_ERROR);

        // Make sure this file is assigned to this display group
        $link = new LkMediaDisplayGroup($this->db);
        if (!$link->Link($displayGroupId, $mediaId))
            trigger_error($display->GetErrorMessage(), E_USER_ERROR);

        // Get the "StoredAs" for this media item
        $media = new Media($this->db);
        $storedAs = $media->GetStoredAs($mediaId);

        // Get a list of displays for this group
        $displays = $this->user->DisplayList(array('displayid'), array('displaygroupid' => $displayGroupId));

        foreach ($displays as $display) {
            // Update the Display with the new instructions
            $displayObject = new Display($this->db);
            if (!$displayObject->SetVersionInstructions($display['displayid'], $mediaId, $storedAs))
                trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Version Instructions Set'));

    }
}

?>