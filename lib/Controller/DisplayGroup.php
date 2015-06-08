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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;


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

        $displays = Sanitize::getIntArray('displayId');

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

    /**
     * Media Form (media linked to displays)
     * @param int $displayGroupId
     */
    public function mediaForm($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->load();

        $this->getState()->template = 'displaygroup-form-media';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'modules' => ModuleFactory::query(null, ['regionSpecific' => 0]),
            'media' => MediaFactory::getByDisplayGroupId($displayGroup->displayGroupId),
            'help' => Help::Link('DisplayGroup', 'FileAssociations')
        ]);
    }

    /**
     * Assign Media
     * @param int $displayGroupId
     */
    public function media($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->load();

        $mediaIds = Sanitize::getIntArray('mediaIds');

        // Loop through all the media
        foreach ($mediaIds as $mediaId) {

            $media = MediaFactory::getById($mediaId);

            if (!$this->getUser()->checkViewable($media))
                throw new AccessDeniedException(__('You have selected media that you no longer have permission to use. Please reload the form.'));

            $displayGroup->assignMedia($mediaId);
        }

        $displayGroup->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Files assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Version Form
     * @param int $displayGroupId
     */
    public function versionForm($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // List of effected displays
        $displays = DisplayFactory::getByDisplayGroupId($displayGroupId);

        // Possible media files to assign
        $media = MediaFactory::query(['name'], ['type' => 'genericfile']);

        $this->getState()->template = 'displaygroup-form-version';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'displays' => $displays,
            'media' => $media,
            'help' => Help::Link('DisplayGroup', 'Version')
        ]);
    }

    /**
     * Version Update
     * @param int $displayGroupId
     */
    public function version($displayGroupId)
    {
        $displayGroup = DisplayGroupFactory::getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $media = MediaFactory::getById(Sanitize::getInt('mediaId'));

        if (!$this->getUser()->checkViewable($media))
            throw new AccessDeniedException();

        // Assign the media file
        $displayGroup->assignMedia($media->mediaId);

        // Update each display in the group with the new version
        foreach (DisplayFactory::getByDisplayGroupId($displayGroupId) as $display) {
            /* @var Display $display */
            $display->versionInstructions = json_encode(['id' => $media->mediaId, 'file' => $media->storedAs]);
            $display->save(false);
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Version set for %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }
}
