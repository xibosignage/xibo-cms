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
use finfo;
use Xibo\Entity\DisplayGroup;
use Xibo\Entity\Stat;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Helper\WakeOnLan;
use Xibo\Storage\PDOConnect;


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

        $data['displayGroups'] = DisplayGroupFactory::query();

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

        // Errors in the last 24 hours
        $errors = LogFactory::query(null, [
            'displayId' => $display->displayId,
            'type' => 'ERROR',
            'fromDt' => Date::getLocalDate(Date::parse()->subHours(24), 'U'),
            'toDt' => Date::getLocalDate(null, 'U')
        ]);

        // Widget for file status
        $status = PDOConnect::select('
            SELECT IFNULL(SUM(size), 0) AS sizeTotal,
                SUM(CASE WHEN complete = 1 THEN size ELSE 0 END) AS sizeComplete,
                COUNT(*) AS countTotal,
                IFNULL(SUM(complete), 0) AS countComplete
              FROM `requiredfile`
             WHERE `requiredfile`.displayId = :displayId
        ', [
            'displayId' => $display->displayId
        ]);

        // Decide what our units are going to be, based on the size
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');
        $base = (int)floor(log($status[0]['sizeTotal']) / log(1024));

        if ($base < 0)
            $base = 0;

        $units = (isset($suffixes[$base]) ? $suffixes[$base] : '');
        Log::debug('Base for size is %d and suffix is %s', $base, $units);

        // Show 3 widgets
        $layouts = PDOConnect::select('
            SELECT `layout`.layout,
                `requiredfile`.*
              FROM `requiredfile`
                INNER JOIN `layout`
                ON layout.layoutId = `requiredfile`.layoutId
             WHERE `requiredfile`.displayId = :displayId
              AND IFNULL(`requiredfile`.mediaId, 0) = 0
            ORDER BY `layout`.layout
        ', [
            'displayId' => $display->displayId
        ]);

        // Media
        $media = PDOConnect::select('
            SELECT `media`.name,
                `media`.type,
                `requiredfile`.*
              FROM `requiredfile`
                INNER JOIN `media`
                ON media.mediaId = `requiredfile`.mediaId
             WHERE `requiredfile`.displayId = :displayId
              AND IFNULL(`requiredfile`.layoutId, 0) = 0
            ORDER BY `media`.name
        ', [
            'displayId' => $display->displayId
        ]);

        // Widgets
        $widgets = PDOConnect::select('
            SELECT `widget`.type,
                `widgetoption`.value AS widgetName,
                `requiredfile`.*
              FROM `requiredfile`
                INNER JOIN `layout`
                ON layout.layoutId = `requiredfile`.layoutId
                INNER JOIN `widget`
                ON widget.widgetId = `requiredfile`.mediaId
                LEFT OUTER JOIN `widgetoption`
                ON `widgetoption`.widgetId = `widget`.widgetId
                  AND `widgetoption`.option = \'name\'
             WHERE `requiredfile`.displayId = :displayId
              AND IFNULL(`requiredfile`.layoutId, 0) <> 0
              AND IFNULL(`requiredfile`.regionId, 0) <> 0
              AND IFNULL(`requiredfile`.mediaId, 0) <> 0
            ORDER BY IFNULL(`widgetoption`.value, `widget`.type)
        ', [
            'displayId' => $display->displayId
        ]);

        // Call to render the template
        $this->getState()->template = 'display-page-manage';
        $this->getState()->setData([
            'requiredFiles' => [],
            'display' => $display,
            'timeAgo' => Date::parse($display->lastAccessed, 'U')->diffForHumans(),
            'errors' => $errors,
            'inventory' => [
                'layouts' => $layouts,
                'media' => $media,
                'widgets' => $widgets
            ],
            'status' => [
                'units' => $units,
                'countComplete' => $status[0]['countComplete'],
                'countRemaining' => $status[0]['countTotal'] - $status[0]['countComplete'],
                'sizeComplete' => round((double)$status[0]['sizeComplete'] / (pow(1024, $base)), 2),
                'sizeRemaining' => round((double)($status[0]['sizeTotal'] - $status[0]['sizeComplete']) / (pow(1024, $base)), 2),
            ],
            'defaults' => [
                'fromDate' => Date::getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => Date::getLocalDate(time() - 86400),
                'toDate' => Date::getLocalDate()
            ]
        ]);
    }

    /**
     * Grid of Displays
     *
     * @SWG\Get(
     *  path="/display",
     *  operationId="displaySearch",
     *  tags={"display"},
     *  summary="Display Search",
     *  description="Search Displays for this User",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Display")
     *      )
     *  )
     * )
     */
    function grid()
    {
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

            if ($this->isApi())
                break;

            /* @var \Xibo\Entity\Display $display */

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
                        array('name' => 'commit-url', 'value' => $this->urlFor('display.requestscreenshot', ['id' => $display->displayId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'display_button_requestScreenShot'),
                        array('name' => 'text', 'value' => __('Request Screen Shot')),
                        array('name' => 'rowtitle', 'value' => $display->display)
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
     *
     * @SWG\Put(
     *  path="/display/{displayId}",
     *  operationId="displayEdit",
     *  tags={"display"},
     *  summary="Display Edit",
     *  description="Edit a Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="display",
     *      in="formData",
     *      description="The Display Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of the Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isAuditing",
     *      in="formData",
     *      description="Flag indicating whether this Display records auditing information.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="defaultLayoutId",
     *      in="formData",
     *      description="A Layout ID representing the Default Layout for this Display.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="licensed",
     *      in="formData",
     *      description="Flag indicating whether this display is licensed.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="license",
     *      in="formData",
     *      description="The hardwareKey to use as the licence key for this Display",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="incSchedule",
     *      in="formData",
     *      description="Flag indicating whether the Default Layout should be included in the Schedule",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="emailAlert",
     *      in="formData",
     *      description="Flag indicating whether the Display generates up/down email alerts.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="alertTimeout",
     *      in="formData",
     *      description="How long in seconds should this display wait before alerting when it hasn't connected. Override for the collection interval.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="wakeOnLanEnabled",
     *      in="formData",
     *      description="Flag indicating if Wake On LAN is enabled for this Display",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="wakeOnLanTime",
     *      in="formData",
     *      description="A h:i string representing the time that the Display should receive its Wake on LAN command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="broadCastAddress",
     *      in="formData",
     *      description="The BroadCast Address for this Display - used by Wake On LAN",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="secureOn",
     *      in="formData",
     *      description="The secure on configuration for this Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="cidr",
     *      in="formData",
     *      description="The CIDR configuration for this Display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="latitude",
     *      in="formData",
     *      description="The Latitude of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="longitude",
     *      in="formData",
     *      description="The Longitude of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="formData",
     *      description="The Display Settings Profile ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Display")
     *  )
     * )
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
        $display->license = Sanitize::getString('license');
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
     *
     * @SWG\Delete(
     *  path="/display/{displayId}",
     *  operationId="displayDelete",
     *  tags={"display"},
     *  summary="Display Delete",
     *  description="Delete a Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkDeleteable($display))
            throw new AccessDeniedException();

        $display->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
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

        if (!$this->getUser()->checkEditable($display))
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
     * Assign Display to Display Groups
     * @param int $displayId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function assignDisplayGroup($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Go through each ID to assign
        foreach (Sanitize::getIntArray('displayGroupId') as $displayGroupId) {
            $displayGroup = DisplayGroupFactory::getById($displayGroupId);

            if (!$this->getUser()->checkEditable($displayGroup))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->assignDisplay($display);
            $displayGroup->save(false);
        }

        // Have we been provided with unassign id's as well?
        foreach (Sanitize::getIntArray('unassignDisplayGroupId') as $displayGroupId) {
            $displayGroup = DisplayGroupFactory::getById($displayGroupId);

            if (!$this->getUser()->checkEditable($displayGroup))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->unassignDisplay($display);
            $displayGroup->save(false);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s assigned to Display Groups'), $display->display),
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
     *
     * @SWG\Put(
     *  path="/display/requestscreenshot/{displayId}",
     *  operationId="displayRequestScreenshot",
     *  tags={"display"},
     *  summary="Request Screen Shot",
     *  description="Notify the display that the CMS would like a screen shot to be sent.",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Display")
     *  )
     * )
     */
    public function requestScreenShot($displayId)
    {
        $display = DisplayFactory::getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        $display->screenShotRequested = 1;
        $display->save(['validate' => false]);

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
     *
     * @SWG\Get(
     *  path="/display/wol/{displayId}",
     *  operationId="displayWakeOnLan",
     *  tags={"display"},
     *  summary="Issue WOL",
     *  description="Send a Wake On LAN packet to this Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
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
        $display->save(['validate' => false]);

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
                    $display->save(['validate' => false, 'audit' => false]);

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
