<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
use DOMDocument;
use DOMXPath;
use finfo;
use Xibo\Entity\DisplayGroup;
use Xibo\Entity\Stat;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Helper\WakeOnLan;


class Display extends Base
{
    /**
     * Include display page template page based on sub page selected
     */
    function displayPage()
    {
        // Default options
        if ($this->getSession()->get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_displaygroup = $this->getSession()->get('display', 'filter_displaygroup');
            $filter_display = $this->getSession()->get('display', 'filter_display');
            $filterMacAddress = $this->getSession()->get('display', 'filterMacAddress');
            $filter_showView = $this->getSession()->get('display', 'filter_showView');
            $filterVersion = $this->getSession()->get('display', 'filterVersion');
            $filter_autoRefresh = $this->getSession()->get('display', 'filter_autoRefresh');
        } else {
            $filter_pinned = 0;
            $filter_displaygroup = NULL;
            $filter_display = NULL;
            $filterMacAddress = NULL;
            $filter_showView = 0;
            $filterVersion = NULL;
            $filter_autoRefresh = 0;
        }

        $data = [
            'defaults' => [
                'displayGroup' => $filter_displaygroup,
                'display' => $filter_display,
                'macAddress' => $filterMacAddress,
                'showView' => $filter_showView,
                'version' => $filterVersion,
                'filterAutoRefresh' => $filter_autoRefresh,
                'filterPinned' => $filter_pinned
            ]
        ];

        $data['displayGroup'] = DisplayGroupFactory::query();

        // Call to render the template
        $this->getState()->template = 'display-page';
        $this->getState()->setData($data);
    }

    /**
     * Display Management Page for an Individual Display
     * @param int $displayId
     * @throws \Xibo\Exception\NotFoundException
     */
    function displayManage($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($display->mediaInventoryXml))
            throw new \InvalidArgumentException(__('Invalid Media Inventory'));

        // Need to parse the XML and return a set of rows
        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        $rows = array();

        foreach ($fileNodes as $node) {
            /* @var \DOMElement $node */
            $row = array();
            $row['type'] = $node->getAttribute('type');
            $row['id'] = $node->getAttribute('id');
            $row['complete'] = ($node->getAttribute('complete') == 0) ? __('No') : __('Yes');
            $row['lastChecked'] = $node->getAttribute('lastChecked');
            $row['md5'] = $node->getAttribute('md5');

            $rows[] = $row;
        }

        // Call to render the template
        $this->getState()->template = 'display-page-manage';
        $this->getState()->setData([
            'inventory' => $rows,
            'display' => $display
        ]);
    }

    /**
     * Grid of Displays
     */
    function grid()
    {
        $user = $this->getUser();

        // Filter by Name
        $filter_display = Sanitize::getString('filter_display');
        $this->getSession()->set('display', 'filter_display', $filter_display);

        // Filter by Name
        $filterMacAddress = Sanitize::getString('filterMacAddress');
        $this->getSession()->set('display', 'filterMacAddress', $filterMacAddress);

        // Display Group
        $filter_displaygroupid = Sanitize::getInt('filter_displaygroup');
        $this->getSession()->set('display', 'filter_displaygroup', $filter_displaygroupid);

        // Thumbnail?
        $filter_showView = Sanitize::getInt('filter_showView');
        $this->getSession()->set('display', 'filter_showView', $filter_showView);

        $filterVersion = Sanitize::getString('filterVersion');
        $this->getSession()->set('display', 'filterVersion', $filterVersion);

        // filter_autoRefresh?
        $filter_autoRefresh = Sanitize::getCheckbox('filter_autoRefresh', 0);
        $this->getSession()->set('display', 'filter_autoRefresh', $filter_autoRefresh);

        // Pinned option?
        $this->getSession()->set('display', 'DisplayFilter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Get a list of displays
        $displays = DisplayFactory::query($this->gridRenderSort(), $this->gridRenderFilter(array(
            'displaygroupid' => $filter_displaygroupid,
            'display' => $filter_display,
            'macAddress' => $filterMacAddress,
            'clientVersion' => $filterVersion))
        );

        // validate displays so we get a realistic view of the table
        $this->validateDisplays($displays);

        foreach ($displays as $display) {

            /* @var \Xibo\Entity\Display $display */

            // Format last accessed
            $display->lastAccessed = Date::getLocalDate($display->lastAccessed);

            // Set some text for the display status
            switch ($display->mediaInventoryStatus) {
                case 1:
                    $display->statusDescription = __('Display is up to date');
                    break;

                case 2:
                    $display->statusDescription = __('Display is downloading new files');
                    break;

                case 3:
                    $display->statusDescription = __('Display is out of date but has not yet checked in with the server');
                    break;

                default:
                    $display->statusDescription = __('Unknown Display Status');
            }

            $display->status = ($display->mediaInventoryStatus == 1) ? 1 : (($display->mediaInventoryStatus == 2) ? 0 : -1);

            // Thumbnail
            $display->thumbnail = '';
            // If we aren't logged in, and we are showThumbnail == 2, then show a circle
            if (file_exists(Config::GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $display->displayId . '_screenshot.jpg')) {
                $display->thumbnail = 'index.php?p=display&q=ScreenShot&DisplayId=' . $display->displayId;
            }

            // Format the storage available / total space
            $display->storagePercentage = ($display->storageTotalSpace == 0) ? 100 : round($display->storageAvailableSpace / $display->storageTotalSpace * 100.0, 2);

            // Edit and Delete buttons first
            if ($this->getUser()->checkEditable($display)) {

                // Manage
                $display->buttons[] = array(
                    'id' => 'display_button_manage',
                    'url' => $this->urlFor('display.manage', ['id' => $display->displayId]),
                    'text' => __('Manage'),
                    'external' => true
                );

                $display->buttons[] = ['divider' => true];

                // Edit
                $display->buttons[] = array(
                    'id' => 'display_button_edit',
                    'url' => $this->urlFor('display.edit.form', ['id' => $display->displayId]),
                    'text' => __('Edit')
                );
            }

            // Delete
            if ($this->getUser()->checkDeleteable($display)) {
                $display->buttons[] = array(
                    'id' => 'display_button_delete',
                    'url' => $this->urlFor('display.delete.form', ['id' => $display->displayId]),
                    'text' => __('Delete')
                );
            }

            if ($this->getUser()->checkEditable($display) || $this->getUser()->checkDeleteable($display)) {
                $display->buttons[] = ['divider' => true];
            }

            // Schedule Now
            if ($this->getUser()->checkEditable($display) || Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes') {
                $display->buttons[] = array(
                    'id' => 'display_button_schedulenow',
                    'url' => $this->urlFor('schedule.now.form', ['id' => $display->displayGroupId, 'from' => 'DisplayGroup']),
                    'text' => __('Schedule Now')
                );
            }

            if ($this->getUser()->checkEditable($display)) {

                // File Associations
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => $this->urlFor('displayGroup.media.form', ['id' => $display->displayGroupId]),
                    'text' => __('Assign Files')
                );

                // Screen Shot
                $display->buttons[] = array(
                    'id' => 'display_button_requestScreenShot',
                    'url' => $this->urlFor('display.screenshot.form', ['id' => $display->displayId]),
                    'text' => __('Request Screen Shot'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'multiselectlink', 'value' => $this->urlFor('display.screenshot.form')),
                        array('name' => 'rowtitle', 'value' => $display->display),
                        array('name' => 'displayId', 'value' => $display->displayId)
                    )
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkPermissionsModifyable($display)) {

                // Display Groups
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => $this->urlFor('display.membership.form', ['id' => $display->displayId]),
                    'text' => __('Display Groups')
                );

                // Permissions
                $display->buttons[] = array(
                    'id' => 'display_button_group_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DisplayGroup', 'id' => $display->displayGroupId]),
                    'text' => __('Permissions')
                );

                // Version Information
                $display->buttons[] = array(
                    'id' => 'display_button_version_instructions',
                    'url' => $this->urlFor('displayGroup.version.form', ['id' => $display->displayGroupId]),
                    'text' => __('Version Information')
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkEditable($display)) {
                // Wake On LAN
                $display->buttons[] = array(
                    'id' => 'display_button_wol',
                    'url' => $this->urlFor('display.wol.form', ['id' => $display->displayId]),
                    'text' => __('Wake on LAN')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = DisplayFactory::countLast();
        $this->getState()->setData($displays);
    }

    /**
     * Edit Display Form
     * @param int $displayId
     */
    function editForm($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Get the settings from the profile
        $profile = $display->getSettings();

        // Go through each one, and see if it is a drop down
        for ($i = 0; $i < count($profile); $i++) {
            // Always update the value string with the source value
            $profile[$i]['valueString'] = $profile[$i]['value'];

            // Overwrite the value string when we are dealing with dropdowns
            if ($profile[$i]['fieldType'] == 'dropdown') {
                // Update our value
                foreach ($profile[$i]['options'] as $option) {
                    if ($option['id'] == $profile[$i]['value'])
                        $profile[$i]['valueString'] = $option['value'];
                }
            } else if ($profile[$i]['fieldType'] == 'timePicker') {
                $profile[$i]['valueString'] = Date::getSystemDate($profile[$i]['value'] / 1000, 'H:i');
            }
        }

        $this->getState()->template = 'display-form-edit';
        $this->getState()->setData([
            'display' => $display,
            'layouts' => LayoutFactory::query(),
            'profiles' => DisplayProfileFactory::query(NULL, array('type' => $display->clientType)),
            'settings' => $profile,
            'help' => Help::Link('Display', 'Edit')
        ]);
    }

    /**
     * Delete form
     * @param int $displayId
     */
    function deleteForm($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkDeleteable($display))
            throw new AccessDeniedException();

        $this->getState()->template = 'display-form-delete';
        $this->getState()->setData([
            'display' => $display,
            'help' => Help::Link('Display', 'Delete')
        ]);
    }

    /**
     * Display Edit
     * @param int $displayId
     */
    function edit($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Update properties
        $display->display = Sanitize::getString('display');
        $display->description = Sanitize::getString('description');
        $display->isAuditing = Sanitize::getInt('isAuditing');
        $display->defaultLayoutId = Sanitize::getInt('defaultLayoutId');
        $display->licensed = Sanitize::getInt('licensed');
        $display->incSchedule = Sanitize::getInt('incSchedule');
        $display->emailAlert = Sanitize::getInt('emailAlert');
        $display->alertTimeout = Sanitize::getCheckbox('alertTimeout');
        $display->wakeOnLanEnabled = Sanitize::getCheckbox('wakeOnLanEnabled');
        $display->wakeOnLanTime = Sanitize::getString('wakeOnLanTime');
        $display->broadCastAddress = Sanitize::getString('broadCastAddress');
        $display->secureOn = Sanitize::getString('secureOn');
        $display->cidr = Sanitize::getString('cidr');
        $display->latitude = Sanitize::getDouble('latitude');
        $display->longitude = Sanitize::getDouble('longitude');
        $display->displayProfileId = Sanitize::getInt('displayProfileId');

        $display->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);
    }

    /**
     * Delete a display
     * @param int $displayId
     */
    function delete($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkDeleteable($display))
            throw new AccessDeniedException();

        $display->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);
    }

    /**
     * Member of Display Groups Form
     * @param int $displayId
     */
    public function membershipForm($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkPermissionsModifyable($display))
            throw new AccessDeniedException();

        // Groups we are assigned to
        $groupsAssigned = DisplayGroupFactory::getByDisplayId($display->displayId);

        // All Groups
        $allGroups = DisplayGroupFactory::query(['displayGroup']);

        // The available users are all users except users already in assigned users
        $checkboxes = array();

        foreach ($allGroups as $group) {
            /* @var DisplayGroup $group */
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($groupsAssigned as $groupAssigned) {
                /* @var DisplayGroup $groupAssigned */
                if ($groupAssigned->displayGroupId == $group->displayGroupId) {
                    $exists = true;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $group->displayGroupId,
                'name' => $group->displayGroup,
                'value_checked' => (($exists) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $this->getState()->template = 'display-form-membership';
        $this->getState()->setData([
            'display' => $display,
            'checkboxes' => $checkboxes,
            'help' =>  Help::Link('Display', 'Members')
        ]);
    }

    /**
     * Sets the Members of a group
     * @param int $displayId
     */
    public function membership($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkPermissionsModifyable($display))
            throw new AccessDeniedException();

        // Load the groups details
        $display->load();

        $displayGroups = $this->getApp()->request()->params('displayGroupId');

        // We will receive a list of displayGroups from the UI which are in the "assign column" at the time the form is
        // submitted.
        // We want to go through and unlink any displayGroups that are NOT in that list, but that the current user has access
        // to edit.
        // We want to add any displayGroups that are in that list (but aren't already assigned)

        // All users that this session has access to
        $allGroups = DisplayGroupFactory::query(['displayGroup']);

        // Convert to an array of ID's for convenience
        $allGroupIds = array_map(function ($group) {
            return $group->displayGroupId;
        }, $allGroups);

        // Groups assigned to Display
        $groupsAssigned = DisplayGroupFactory::getByDisplayId($display->displayId);

        foreach ($groupsAssigned as $row) {
            /* @var DisplayGroup $row */
            // Did this session have permission to do anything to this displayGroup?
            // If not, move on
            if (!in_array($row->displayGroupId, $allGroupIds))
                continue;

            // Is this displayGroup in the provided list of displayGroups?
            if (in_array($row->displayGroupId, $displayGroups)) {
                // This displayGroup is already assigned, so we remove it from the $displayGroups array
                unset($displayGroups[$row->displayGroupId]);
            } else {
                // It isn't therefore needs to be removed
                $row->unassignDisplay($display->displayId);
                $row->save(false);
            }
        }

        // Add any displayGroups that are still missing after that assignment process
        foreach ($displayGroups as $displayGroupId) {
            // Add any that are missing
            $group = DisplayGroupFactory::getById($displayGroupId);
            $group->assignDisplay($display->displayId);
            $group->save(false);
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Membership set for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Output a screen shot
     * @param int $displayId
     */
    public function screenShot($displayId)
    {
        // Output an image if present, otherwise not found image.
        $file = 'screenshots/' . $displayId . '_screenshot.jpg';

        // File upload directory.. get this from the settings object
        $library = Config::GetSetting("LIBRARY_LOCATION");
        $fileName = $library . $file;

        if (!file_exists($fileName)) {
            $fileName = Theme::uri('forms/filenotfound.gif');
        }

        $size = filesize($fileName);

        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($fileName);
        header("Content-Type: {$mime}");

        // Output a header
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . $size);

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        @ob_end_clean();
        @ob_end_flush();
        readfile($fileName);
    }

    /**
     * Request ScreenShot form
     * @param int $displayId
     */
    public function requestScreenShotForm($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        $this->getState()->template = 'display-form-request-screenshot';
        $this->getState()->setData([
            'display' => $display,
            'help' =>  Help::Link('Display', 'ScreenShot')
        ]);
    }

    /**
     * Request ScreenShot
     * @param int $displayId
     */
    public function requestScreenShot($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        $display->screenShotRequested = 1;
        $display->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Form for wake on Lan
     * @param int $displayId
     */
    public function wakeOnLanForm($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        if ($display->macAddress == '')
            throw new \InvalidArgumentException(__('This display has no mac address recorded against it yet. Make sure the display is running.'));

        $this->getState()->template = 'display-form-wakeonlan';
        $this->getState()->setData([
            'display' => $display,
            'help' =>  Help::Link('Display', 'WakeOnLan')
        ]);
    }

    /**
     * Wake this display using a WOL command
     * @param int $displayId
     */
    public function wakeOnLan($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        if ($display->macAddress == '' || $display->broadCastAddress == '')
            throw new \InvalidArgumentException(__('This display has no mac address recorded against it yet. Make sure the display is running.'));

        Log::notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

        WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9');

        $display->lastWakeOnLanCommandSent = time();
        $display->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Wake on Lan sent for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Validate the display list
     * @param array[Display] $displays
     * @return array[Display]
     */
    public static function validateDisplays($displays)
    {
        $timedOutDisplays = [];

        // Get the global time out (overrides the alert time out on the display if 0)
        $globalTimeout = Config::GetSetting('MAINTENANCE_ALERT_TOUT') * 60;

        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */

            // Should we test against the collection interval or the preset alert timeout?
            if ($display->alertTimeout == 0 && $display->clientType != '') {
                $timeoutToTestAgainst = $display->GetSetting('collectInterval', $globalTimeout);
            }
            else {
                $timeoutToTestAgainst = $globalTimeout;
            }

            // Store the time out to test against
            $timeOut = $display->lastAccessed + $timeoutToTestAgainst;

            // If the last time we accessed is less than now minus the time out
            if ($timeOut < time()) {
                Log::debug('Timed out display. Last Accessed: ' . date('Y-m-d h:i:s', $display->lastAccessed) . '. Time out: ' . date('Y-m-d h:i:s', $timeOut));

                // If this is the first switch (i.e. the row was logged in before)
                if ($display->loggedIn == 1) {

                    // Update the display and set it as logged out
                    $display->loggedIn = 0;
                    $display->save(false, false);

                    // We put it back again (in memory only)
                    // this is then used to indicate whether or not this is the first time this display has gone
                    // offline (for anything that uses the timedOutDisplays return
                    $display->loggedIn = 1;

                    // Log the down event
                    $stat = new Stat();
                    $stat->type = 'displaydown';
                    $stat->displayId = $display->displayId;
                    $stat->fromDt = $display->lastAccessed;
                    $stat->save();
                }

                // Store this row
                $timedOutDisplays[] = $display;
            }
        }

        return $timedOutDisplays;
    }
}
