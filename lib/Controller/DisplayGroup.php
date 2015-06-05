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

use Xibo\Entity\Display;
use Xibo\Entity\Permission;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;


class DisplayGroup extends Base
{
    /**
     * Display Group Page Render
     */
    public function displayPage()
    {
        $this->getState()->template = 'displaygroup-page';
    }

    /**
     * Shows the Display groups
     */
    public function grid()
    {
        $displayGroups = $this->getUser()->DisplayGroupList();

        foreach ($displayGroups as $group) {
            /* @var \Xibo\Entity\DisplayGroup $group */

            if ($this->isApi())
                continue;

            if ($this->getUser()->checkEditable($group)) {
                // Show the edit button, members button

                // Group Members
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_group_members',
                    'url' => $this->urlFor('displayGroup.members.form', ['id' => $group->displayGroupId]),
                    'text' => __('Group Members')
                );

                // Edit
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_edit',
                    'url' => $this->urlFor('displayGroup.edit.form', ['id' => $group->displayGroupId]),
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($group)) {
                // Show the delete button
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_delete',
                    'url' => $this->urlFor('displayGroup.delete.form', ['id' => $group->displayGroupId]),
                    'text' => __('Delete')
                );
            }

            $group->buttons[] = ['divider' => true];

            if ($this->getUser()->checkEditable($group)) {
                // File Associations
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => $this->urlFor('displayGroup.media.form', ['id' => $group->displayGroupId]),
                    'text' => __('Assign Files')
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($group)) {
                // Show the modify permissions button
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DisplayGroup', 'id' => $group->displayGroupId]),
                    'text' => __('Permissions')
                );

                // Version Information
                $group->buttons[] = array(
                    'id' => 'display_button_version_instructions',
                    'url' => $this->urlFor('displayGroup.version.form', ['id' => $group->displayGroupId]),
                    'text' => __('Version Information')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($displayGroups);
    }

    /**
     * Shows an add form for a display group
     */
    public function addForm()
    {
        $this->getState()->template = 'displaygroup-form-add';
        $this->getState()->setData([
            'help' => Help::Link('DisplayGroup', 'Add')
        ]);
    }

    /**
     * Shows an edit form for a display group
     * @param int $displayGroupId
     */
    public function editForm($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->getState()->template = 'displaygroup-form-edit';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'help' => Help::Link('DisplayGroup', 'Edit')
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $displayGroupId
     */
    function deleteForm($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkDeleteable($displayGroup))
            throw new AccessDeniedException();

        $this->getState()->template = 'displaygroup-form-delete';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'help' => Help::Link('DisplayGroup', 'Delete')
        ]);
    }

    /**
     * Display Group Members form
     * @param int $displayGroupId
     */
    public function membersForm($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Displays in Group
        $displaysAssigned = DisplayFactory::getByDisplayGroupId($displayGroup->displayGroupId);

        // All Displays
        $allDisplays = DisplayFactory::query();

        // The available users are all users except users already in assigned users
        $checkboxes = array();

        foreach ($allDisplays as $display) {
            /* @var Display $display */
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($displaysAssigned as $displayAssigned) {
                /* @var Display $displayAssigned */
                if ($displayAssigned->displayId == $display->displayId) {
                    $exists = true;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $display->displayId,
                'name' => $display->display,
                'value_checked' => (($exists) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }


        $this->getState()->template = 'displaygroup-form-members';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'checkboxes' => $checkboxes,
            'help' => Help::Link('DisplayGroup', 'Delete')
        ]);
    }

    /**
     * Adds a Display Group
     */
    public function add()
    {
        $displayGroup = new \Xibo\Entity\DisplayGroup();
        $displayGroup->displayGroup = Sanitize::getString('displayGroup');
        $displayGroup->description = Sanitize::getString('description');
        $displayGroup->save();

        // Add full permissions for this user to this group
        /* @var Permission $permission */
        $permission = PermissionFactory::create($this->getUser()->groupId, 'DisplayGroup', $displayGroup->displayGroupId, 0, 0, 0);
        $permission->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => [$displayGroup]
        ]);
    }

    /**
     * Edits a Display Group
     * @param int $displayGroupId
     */
    public function edit($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $displayGroup->displayGroup = Sanitize::getString('displayGroup');
        $displayGroup->description = Sanitize::getString('description');
        $displayGroup->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => [$displayGroup]
        ]);
    }

    /**
     * Deletes a Group
     * @param int $displayGroupId
     */
    function delete($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkDeleteable($displayGroup))
            throw new AccessDeniedException();

        $displayGroup->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $displayGroup->displayGroup)
        ]);
    }

    /**
     * Sets the Members of a group
     * @param int $displayGroupId
     */
    public function members($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->load();

        $displays = $this->getApp()->request()->params('displayId');

        // We will receive a list of users from the UI which are in the "assign column" at the time the form is
        // submitted.
        // We want to go through and unlink any users that are NOT in that list, but that the current user has access
        // to edit.
        // We want to add any users that are in that list (but aren't already assigned)

        // All users that this session has access to
        $allDisplays = DisplayFactory::query();

        // Convert to an array of ID's for convenience
        $allDisplayIds = array_map(function ($display) {
            return $display->displayId;
        }, $allDisplays);

        // Users in group
        $displaysAssigned = DisplayFactory::getByDisplayGroupId($displayGroupId);

        foreach ($displaysAssigned as $row) {
            /* @var Display $row */
            // Did this session have permission to do anything to this user?
            // If not, move on
            if (!in_array($row->displayId, $allDisplayIds))
                continue;

            // Is this user in the provided list of users?
            if (in_array($row->displayId, $displays)) {
                // This user is already assigned, so we remove it from the $users array
                unset($displays[$row->displayId]);
            } else {
                // It isn't therefore needs to be removed
                $displayGroup->unassignDisplay($row->displayId);
            }
        }

        // Add any users that are still missing after tha assignment process
        foreach ($displays as $displayId) {
            // Add any that are missing
            $displayGroup->assignDisplay($displayId);
        }

        $displayGroup->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Membership set for %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    public function FileAssociations()
    {

        $displayGroupId = \Xibo\Helper\Sanitize::getInt('DisplayGroupID');

        // Auth
        $auth = $this->getUser()->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="displaygroup"><input type="hidden" name="q" value="FileAssociationsView"><input type="hidden" name="displaygroupid" value="' . $displayGroupId . '">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Module types filter
        $modules = $this->getUser()->ModuleAuth(0, '', -1);
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

        } catch (\Exception $e) {

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
        $auth = $this->getUser()->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);


        $displayGroup = new DisplayGroup($this->db);

        if (!$displayGroup->AssociateFiles($this->user, $displayGroupId, $mediaList))
            trigger_error($displayGroup->GetErrorMessage(), E_USER_ERROR);

        // Loop through all the media
        foreach ($mediaList as $mediaId)
        {
            $mediaId = \Xibo\Helper\Sanitize::int($mediaId);

            // Check we have permissions to use this media (we will use this to copy the media later)
            $mediaAuth = $user->MediaAuth($mediaId, true);

            if (!$mediaAuth->view)
                $this->ThrowError(__('You have selected media that you no longer have permission to use. Please reload the form.'));

            // Create the link
            if (!$link->Link($displayGroupId, $mediaId))
                $this->ThrowError(__('Unable to make this assignment'));
        }

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
            if (!$displays = $this->getUser()->DisplayList(array('display'), array('displayid' => $displayId)))
                trigger_error(__('Unknown Display'), E_USER_ERROR);
        } else {
            // Get a list of displays with their version information?
            if (!$displays = $this->getUser()->DisplayList(array('display'), array('displaygroupid' => $displayGroupId)))
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
        $mediaList = $this->getUser()->MediaList(NULL, array('type' => 'genericfile'));
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
        $auth = $this->getUser()->DisplayGroupAuth($displayGroupId, true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display group'), E_USER_ERROR);

        // Make sure we have permission to use this file
        $mediaAuth = $this->getUser()->MediaAuth($mediaId, true);

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
        $displays = $this->getUser()->DisplayList(array('displayid'), array('displaygroupid' => $displayGroupId));

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