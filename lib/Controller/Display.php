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
use baseDAO;
use database;
use DisplayGroup;
use DOMDocument;
use DOMXPath;
use finfo;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class Display extends Base
{
    /**
     * Include display page template page based on sub page selected
     */
    function displayPage()
    {
        // Default options
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_displaygroup = Session::Get('display', 'filter_displaygroup');
            $filter_display = Session::Get('display', 'filter_display');
            $filterMacAddress = Session::Get('display', 'filterMacAddress');
            $filter_showView = Session::Get('display', 'filter_showView');
            $filterVersion = Session::Get('display', 'filterVersion');
            $filter_autoRefresh = Session::Get('display', 'filter_autoRefresh');
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

        $displayGroups = $this->getUser()->DisplayGroupList();
        array_unshift($displayGroups, array('displaygroupid' => '0', 'displaygroup' => 'All'));
        $data['displayGroup'] = $displayGroups;

        // Call to render the template
        $this->getState()->template = 'display-page';
        $this->getState()->setData($data);
    }

    /**
     * Grid of Displays
     */
    function grid()
    {
        // validate displays so we get a realistic view of the table
        //Display::ValidateDisplays();

        $user = $this->getUser();

        // Filter by Name
        $filter_display = Sanitize::getString('filter_display');
        Session::Set('display', 'filter_display', $filter_display);

        // Filter by Name
        $filterMacAddress = Sanitize::getString('filterMacAddress');
        Session::Set('display', 'filterMacAddress', $filterMacAddress);

        // Display Group
        $filter_displaygroupid = Sanitize::getInt('filter_displaygroup');
        Session::Set('display', 'filter_displaygroup', $filter_displaygroupid);

        // Thumbnail?
        $filter_showView = Sanitize::getInt('filter_showView');
        Session::Set('display', 'filter_showView', $filter_showView);

        $filterVersion = Sanitize::getString('filterVersion');
        Session::Set('display', 'filterVersion', $filterVersion);

        // filter_autoRefresh?
        $filter_autoRefresh = Sanitize::getCheckbox('filter_autoRefresh', 0);
        Session::Set('display', 'filter_autoRefresh', $filter_autoRefresh);

        // Pinned option?
        Session::Set('display', 'DisplayFilter', Sanitize::getCheckbox('XiboFilterPinned'));

        $displays = $user->DisplayList($this->gridRenderSort(), $this->gridRenderFilter(array(
            'displaygroupid' => $filter_displaygroupid,
            'display' => $filter_display,
            'macAddress' => $filterMacAddress,
            'clientVersion' => $filterVersion))
        );

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
                    'url' => $this->urlFor('schedule.now.form', ['id' => $display->displayGroupId]),
                    'text' => __('Schedule Now')
                );
            }

            if ($this->getUser()->checkEditable($display)) {

                // File Associations
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => 'index.php?p=displaygroup&q=FileAssociations&DisplayGroupID=' . $display->displayGroupId,
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

            // Media Inventory
            $display->buttons[] = array(
                'id' => 'display_button_mediainventory',
                'url' => 'index.php?p=display&q=MediaInventory&DisplayId=' . $display->displayId,
                'text' => __('Media Inventory')
            );

            if ($this->getUser()->checkEditable($display)) {

                // Logs
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_logs',
                    'url' => 'index.php?p=log&q=LastHundredForDisplay&displayid=' . $display->displayId,
                    'text' => __('Recent Log')
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkPermissionsModifyable($display)) {

                // Display Groups
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => 'index.php?p=display&q=MemberOfForm&DisplayID=' . $display->displayId,
                    'text' => __('Display Groups')
                );

                // Permissions
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => 'index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $display->displayGroupId,
                    'text' => __('Permissions')
                );

                // Version Information
                $display->buttons[] = array(
                    'id' => 'display_button_version_instructions',
                    'url' => 'index.php?p=displaygroup&q=VersionInstructionsForm&displaygroupid=' . $display->displayGroupId . '&displayid=' . $display->displayId,
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
            'settings' => $profile,
            'help' => Help::Link('Display', 'Edit')
        ]);
    }

    /**
     * Delete form
     */
    function deleteForm()
    {
        $user = $this->getUser();
        $response = $this->getState();
        $displayid = Sanitize::getInt('displayid');

        // Auth
        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayid), true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        Theme::Set('form_id', 'DisplayDeleteForm');
        Theme::Set('form_action', 'index.php?p=display&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="displayid" value="' . $displayid . '">');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to delete this display? This cannot be undone.'))));

        $response->SetFormRequestResponse(NULL, __('Delete this Display?'), '350px', '210');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Display', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DisplayDeleteForm").submit()');

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
            'data' => [$display]
        ]);
    }

    /**
     * Delete a display
     */
    function Delete()
    {



        $response = $this->getState();
        $displayid = \Kit::GetParam('displayid', _POST, _INT, 0);

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayid), true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if ($displayid == 0)
            trigger_error(__("No Display selected for Deletion."));

        $displayObject = new Display($db);

        if (!$displayObject->Delete($displayid))
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__("The Display has been Deleted"));

    }

    /**
     * Form for editing the default layout of a display
     */
    public function DefaultLayoutForm()
    {

        $response = $this->getState();

        $displayId = Sanitize::getInt('DisplayId');

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$defaultLayoutId = $this->db->GetSingleValue(sprintf("SELECT defaultlayoutid FROM display WHERE displayid = %d", $displayId), 'defaultlayoutid', _INT)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get the default layout'), E_USER_ERROR);
        }

        Theme::Set('form_id', 'DefaultLayoutForm');
        Theme::Set('form_action', 'index.php?p=display&q=DefaultLayout');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayId" value="' . $displayId . '">');

        $formFields = array();
        $formFields[] = Form::AddCombo(
            'defaultlayoutid',
            __('Default Layout'),
            $defaultLayoutId,
            $this->getUser()->LayoutList(),
            'layoutid',
            'layout',
            __('The Default Layout will be shown there are no other scheduled Layouts. It is usually a full screen logo or holding image.'),
            'd');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Default Layout'), '300px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Display', 'DefaultLayout') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DefaultLayoutForm").submit()');

    }

    /**
     * Edit the default layout for a display
     */
    public function DefaultLayout()
    {



        $response = $this->getState();
        $displayObject = new Display($db);

        $displayId = Sanitize::getInt('DisplayId');
        $defaultLayoutId = Sanitize::getInt('defaultlayoutid');

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$displayObject->EditDefaultLayout($displayId, $defaultLayoutId))
            trigger_error(__('Cannot Edit this Display'), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Saved.'));

    }

    /**
     * Shows the inventory XML for the display
     */
    public function MediaInventory()
    {

        $response = $this->getState();
        $displayId = Sanitize::getInt('DisplayId');

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->view)
            trigger_error(__('You do not have permission to view this display'), E_USER_ERROR);

        if ($displayId == 0)
            trigger_error(__('No DisplayId Given'));

        // Get the media inventory xml for this display
        $SQL = "SELECT IFNULL(MediaInventoryXml, '<xml></xml>') AS MediaInventoryXml FROM display WHERE DisplayId = %d";
        $SQL = sprintf($SQL, $displayId);

        if (!$mediaInventoryXml = $db->GetSingleValue($SQL, 'MediaInventoryXml', _HTMLSTRING)) {
            trigger_error($db->error());
            trigger_error(__('Unable to get the Inventory for this Display'), E_USER_ERROR);
        }

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($mediaInventoryXml))
            trigger_error(__('Invalid Media Inventory'), E_USER_ERROR);

        $cols = array(
            array('name' => 'id', 'title' => __('ID')),
            array('name' => 'type', 'title' => __('Type')),
            array('name' => 'complete', 'title' => __('Complete')),
            array('name' => 'last_checked', 'title' => __('Last Checked'))
        );
        Theme::Set('table_cols', $cols);

        // Need to parse the XML and return a set of rows
        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        $rows = array();

        foreach ($fileNodes as $node) {
            $row = array();
            $row['type'] = $node->getAttribute('type');
            $row['id'] = $node->getAttribute('id');
            $row['complete'] = ($node->getAttribute('complete') == 0) ? __('No') : __('Yes');
            $row['last_checked'] = $node->getAttribute('lastChecked');
            $row['md5'] = $node->getAttribute('md5');

            $rows[] = $row;
        }

        // Store the table rows
        Theme::Set('table_rows', $rows);

        $response->SetFormRequestResponse(Theme::RenderReturn('table_render'), __('Media Inventory'), '550px', '350px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Display', 'MediaInventory') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    /**
     * Get DisplayGroupID
     * @param <type> $displayId
     */
    private function GetDisplayGroupId($displayId)
    {
        $sql = "SELECT displaygroup.DisplayGroupID ";
        $sql .= "  FROM `displaygroup` ";
        $sql .= "   INNER JOIN `lkdisplaydg` ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $sql .= " WHERE displaygroup.IsDisplaySpecific = 1 AND lkdisplaydg.DisplayID = %d";

        if (!$id = $this->db->GetSingleValue(sprintf($sql, $displayId), 'DisplayGroupID', _INT)) {
            trigger_error($this->db->error());
            trigger_error(__('Unable to determine permissions'), E_USER_ERROR);
        }

        return $id;
    }

    /**
     * Member of Display Groups Form
     */
    public function MemberOfForm()
    {

        $response = $this->getState();
        $displayID = Sanitize::getInt('DisplayID');

        // Auth
        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayID), true);
        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permission to change Display Groups on this display'), E_USER_ERROR);

        // There needs to be two lists here.
        //  - DisplayGroups this Display is already assigned to
        //  - DisplayGroups this Display could be assigned to

        // Set some information about the form
        Theme::Set('displaygroups_assigned_id', 'displaysIn');
        Theme::Set('displaygroups_available_id', 'displaysOut');
        Theme::Set('displaygroups_assigned_url', 'index.php?p=display&q=SetMemberOf&DisplayID=' . $displayID);

        // Display Groups Assigned
        $SQL = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID, ";
        $SQL .= "       displaygroup.DisplayGroup, ";
        $SQL .= "       CONCAT('DisplayGroupID_', displaygroup.DisplayGroupID) AS list_id ";
        $SQL .= "FROM   displaygroup ";
        $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= " AND displaygroup.IsDisplaySpecific = 0 ";
        $SQL .= " ORDER BY displaygroup.DisplayGroup ";

        $displaygroupsAssigned = $db->GetArray($SQL);

        if (!is_array($displaygroupsAssigned)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }

        Theme::Set('displaygroups_assigned', $displaygroupsAssigned);

        // Display Groups not assigned
        $SQL = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID, ";
        $SQL .= "       displaygroup.DisplayGroup, ";
        $SQL .= "       CONCAT('DisplayGroupID_', displaygroup.DisplayGroupID) AS list_id ";
        $SQL .= "  FROM displaygroup ";
        $SQL .= " WHERE displaygroup.IsDisplaySpecific = 0 ";
        $SQL .= " AND displaygroup.DisplayGroupID NOT IN ";
        $SQL .= "       (SELECT lkdisplaydg.DisplayGroupID ";
        $SQL .= "          FROM lkdisplaydg ";
        $SQL .= sprintf(" WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= "       )";
        $SQL .= " ORDER BY displaygroup.DisplayGroup ";

        Log::notice($SQL);

        $displaygroups_available = $db->GetArray($SQL);

        if (!is_array($displaygroups_available)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }

        Theme::Set('displaygroups_available', $displaygroups_available);

        // Render the theme
        $form = Theme::RenderReturn('display_form_group_assign');

        $response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'DisplayGroupManageMembersCallBack');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DisplayGroup', 'Members') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'DisplayGroupMembersSubmit()');

    }

    /**
     * Sets the Members of a group
     * @return
     */
    public function SetMemberOf()
    {

        $response = $this->getState();


        $displayGroupObject = new DisplayGroup($db);

        $displayID = Sanitize::getInt('DisplayID');
        $displayGroups = \Kit::GetParam('DisplayGroupID', _POST, _ARRAY, array());
        $members = array();

        // Get a list of current members
        $SQL = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID ";
        $SQL .= "FROM   displaygroup ";
        $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= " AND displaygroup.IsDisplaySpecific = 0 ";

        if (!$resultIn = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }

        while ($row = $db->get_assoc_row($resultIn)) {
            // Test whether this ID is in the array or not
            $displayGroupID = Sanitize::int($row['DisplayGroupID']);

            if (!in_array($displayGroupID, $displayGroups)) {
                // Its currently assigned but not in the $displays array
                //  so we unassign
                if (!$displayGroupObject->Unlink($displayGroupID, $displayID)) {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            } else {
                $members[] = $displayGroupID;
            }
        }

        foreach ($displayGroups as $displayGroupID) {
            // Add any that are missing
            if (!in_array($displayGroupID, $members)) {
                if (!$displayGroupObject->Link($displayGroupID, $displayID)) {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);

    }

    /**
     * Form for wake on Lan
     */
    public function WakeOnLanForm()
    {

        $response = $this->getState();

        $displayId = Sanitize::getInt('DisplayId');

        // Get the MAC Address
        $macAddress = $db->GetSingleValue(sprintf("SELECT MacAddress FROM `display` WHERE DisplayID = %d", $displayId), 'MacAddress', _STRING);

        if (!$macAddress || $macAddress == '')
            trigger_error(__('This display has no mac address recorded against it yet. Make sure the display is running.'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'WakeOnLanForm');
        Theme::Set('form_action', 'index.php?p=display&q=WakeOnLan');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayId" value="' . $displayId . '"><input type="hidden" name="MacAddress" value="' . $macAddress . '">');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to send a Wake On Lan message to this display?'))));

        $response->SetFormRequestResponse(NULL, __('Wake On Lan'), '300px', '250px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Send'), '$("#WakeOnLanForm").submit()');

    }

    public function ScreenShot()
    {
        $displayId = Sanitize::getInt('DisplayId');

        // Output an image if present, otherwise not found image.
        $file = 'screenshots/' . $displayId . '_screenshot.jpg';

        // File upload directory.. get this from the settings object
        $library = Config::GetSetting("LIBRARY_LOCATION");
        $fileName = $library . $file;

        if (!file_exists($fileName)) {
            $fileName = Theme::ImageUrl('forms/filenotfound.gif');
        }

        $size = filesize($fileName);

        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($fileName);
        header("Content-Type: {$mime}");

        //Output a header
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . $size);

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        @ob_end_clean();
        @ob_end_flush();
        readfile($fileName);
    }

    public function RequestScreenShotForm()
    {

        $response = $this->getState();

        $displayId = Sanitize::getInt('displayId');

        // Set some information about the form
        Theme::Set('form_id', 'RequestScreenShotForm');
        Theme::Set('form_action', 'index.php?p=display&q=RequestScreenShot');
        Theme::Set('form_meta', '<input type="hidden" name="displayId" value="' . $displayId . '">');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to request a screen shot? The next time the client connects to the CMS the screen shot will be sent.'))));

        $response->SetFormRequestResponse(NULL, __('Request Screen Shot'), '300px', '250px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Request'), '$("#RequestScreenShotForm").submit()');

    }

    public function RequestScreenShot()
    {



        $response = $this->getState();
        $displayObject = new Display($db);

        $displayId = Sanitize::getInt('displayId');

        if (!$displayObject->RequestScreenShot($displayId))
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Request Sent.'));

    }

    public function validateDisplays()
    {
        $statObject = new Stat();

        // Get a list of all displays and there last accessed / alert time out value
        $sth = $dbh->prepare('SELECT displayid, display, lastaccessed, alert_timeout, client_type, displayprofileid, email_alert, loggedin FROM display');
        $sthUpdate = $dbh->prepare('UPDATE display SET loggedin = 0 WHERE displayid = :displayid');

        $sth->execute(array());

        // Get the global time out (overrides the alert time out on the display if 0)
        $globalTimeout = Config::GetSetting('MAINTENANCE_ALERT_TOUT') * 60;

        $displays = $sth->fetchAll();

        foreach ($displays as $row) {
            $displayid = \Xibo\Helper\Sanitize::int($row['displayid']);
            $lastAccessed = \Xibo\Helper\Sanitize::int($row['lastaccessed']);
            $alertTimeout = \Xibo\Helper\Sanitize::int($row['alert_timeout']);
            $clientType = \Kit::ValidateParam($row['client_type'], _WORD);
            $loggedIn = \Xibo\Helper\Sanitize::int($row['loggedin']);

            // Get the config object
            if ($alertTimeout == 0 && $clientType != '') {
                $displayProfileId = (empty($row['displayprofileid']) ? 0 : \Xibo\Helper\Sanitize::int($row['displayprofileid']));

                $display = new Display();
                $display->displayId = $displayid;
                $display->displayProfileId = $displayProfileId;
                $display->clientType = $clientType;
                $timeoutToTestAgainst = $display->GetSetting('collectInterval', $globalTimeout);
            }
            else {
                $timeoutToTestAgainst = $globalTimeout;
            }

            // Store the time out to test against
            $row['timeout'] = $timeoutToTestAgainst;
            $timeOut = $lastAccessed + $timeoutToTestAgainst;

            // If the last time we accessed is less than now minus the time out
            if ($timeOut < time()) {
                Log::debug('Timed out display. Last Accessed: ' . date('Y-m-d h:i:s', $lastAccessed) . '. Time out: ' . date('Y-m-d h:i:s', $timeOut));

                // If this is the first switch (i.e. the row was logged in before)
                if ($loggedIn == 1) {

                    // Update the display and set it as logged out
                    $sthUpdate->execute(array('displayid' => $displayid));

                    // Log the down event
                    $statObject->displayDown($displayid, $lastAccessed);
                }

                // Store this row
                $timedOutDisplays[] = $row;
            }
        }

        return $timedOutDisplays;
    }

    /**
     * Wake this display using a WOL command
     * @param <int> $displayId
     * @return <bool>
     */
    public function WakeOnLan($displayId)
    {
        Log::notice('IN', get_class(), __FUNCTION__);

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Get the Client Address and the Mac Address
            $sth = $dbh->prepare('SELECT MacAddress, BroadCastAddress, SecureOn, Cidr FROM `display` WHERE DisplayID = :displayid');
            $sth->execute(array(
                'displayid' => $displayId
            ));

            if (!$row = $sth->fetch())
                $this->ThrowError(25013, __('Unable to get the Mac or Client Address'));

            // Check they are populated
            if ($row['MacAddress'] == '' || $row['BroadCastAddress'] == '')
                $this->SetError(25014, __('This display has no mac address recorded against it yet. Make sure the display is running.'));

            Log::notice('About to send WOL packet to ' . $row['BroadCastAddress'] . ' with Mac Address ' . $row['MacAddress'], 'display', 'WakeOnLan');

            if (!$this->TransmitWakeOnLan($row['MacAddress'], $row['SecureOn'], $row['BroadCastAddress'], $row['Cidr'], "9"))
                throw new Exception('Error in TransmitWakeOnLan');

            // If we succeeded then update this display with the last WOL time
            $sth = $dbh->prepare('UPDATE `display` SET LastWakeOnLanCommandSent = :lastaccessed WHERE DisplayID = :displayid');
            $sth->execute(array(
                'displayid' => $displayId,
                'lastaccessed' => time()
            ));

            return true;
        }
        catch (Exception $e) {
            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(25012, __('Unknown Error.'));

            return false;
        }
    }

    /**
     * Get a list of users that have permission for the provided display
     * @param  int $displayId The Display
     * @param  string $authLevel The Auth Level (view|edit|delete)
     * @return array Users Array
     */
    public static function getUsers($displayId, $authLevel = 'view')
    {
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('
                    SELECT DISTINCT user.userId, user.userName, user.email
                      FROM `user`
                        INNER JOIN `lkusergroup`
                        ON lkusergroup.userId = user.userId
                        INNER JOIN `group`
                        ON group.groupId = lkusergroup.groupId
                        INNER JOIN `lkdisplaygroupgroup`
                        ON lkdisplaygroupgroup.groupId = group.groupId
                        INNER JOIN `displaygroup`
                        ON displaygroup.displayGroupId = lkdisplaygroupgroup.displayGroupId
                        INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.displayGroupId = lkdisplaygroupgroup.displayGroupId
                     WHERE lkdisplaydg.displayId = :displayId
                ');

            $sth->execute(array(
                'displayId' => $displayId
            ));

            // Return this list of users
            return $sth->fetchAll();
        }
        catch (Exception $e) {
            Log::error($e->getMessage(), get_class(), __FUNCTION__);
            return false;
        }
    }

    /**
     * Wake On Lan Script
     *  // Version: 2
     *  // Author of this application:
     *  //  DS508_customer (http://www.synology.com/enu/forum/memberlist.php?mode=viewprofile&u=12636)
     *  //  Please inform the author of any suggestions on (the functionality, graphical design, ... of) this application.
     *  //  More info: http://wolviaphp.sourceforge.net
     *  // License: GPLv2.0
     *
     * Modified for use with the Xibo project by Dan Garner.
     */
    function TransmitWakeOnLan($mac_address, $secureon, $addr, $cidr, $port) {
        Log::notice('IN', get_class(), __FUNCTION__);

        // Prepare magic packet: part 1/3 (defined constant)
        $buf = "";

        // the defined constant as represented in hexadecimal: FF FF FF FF FF FF (i.e., 6 bytes of hexadecimal FF)
        for ($a=0; $a<6; $a++) $buf .= chr(255);

        // Check whether $mac_address is valid
        $mac_address = strtoupper($mac_address);
        $mac_address = str_replace(":", "-", $mac_address);

        if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/",$mac_address)) || (strlen($mac_address) != 17))
        {
            return $this->SetError(25015, __('Pattern of MAC-address is not "xx-xx-xx-xx-xx-xx" (x = digit or letter)'));
        }
        else
        {
            // Prepare magic packet: part 2/3 (16 times MAC-address)
            // Split MAC-address into an array of (six) bytes
            $addr_byte = explode('-', $mac_address);
            $hw_addr = "";

            // Convert MAC-address from bytes to hexadecimal to decimal
            for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));

            $hw_addr_string = "";

            for ($a=0; $a<16; $a++) $hw_addr_string .= $hw_addr;
            $buf .= $hw_addr_string;
        }

        if ($secureon != "")
        {
            // Check whether $secureon is valid
            $secureon = strtoupper($secureon);
            $secureon = str_replace(":", "-", $secureon);

            if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $secureon)) || (strlen($secureon) != 17))
            {
                return $this->SetError(25015, __('Pattern of SecureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
            }
            else
            {
                // Prepare magic packet: part 3/3 (Secureon password)
                // Split MAC-address into an array of (six) bytes
                $addr_byte = explode('-', $secureon);
                $hw_addr = "";

                // Convert MAC address from hexadecimal to decimal
                for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
                $buf .= $hw_addr;
            }
        }

        // Fill $addr with client's IP address, if $addr is empty
        if ($addr == "")
            return $this->SetError(25000, __('No IP Address Specified'));

        // Resolve broadcast address
        if (filter_var ($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) // same as (but easier than):  preg_match("/\b(([01]?\d?\d|2[0-4]\d|25[0-5])\.){3}([01]?\d?\d|2[0-4]\d|25[0-5])\b/",$addr)
        {
            // $addr has an IP-adres format
        }
        else
        {
            return $this->SetError(25000, __('IP Address Incorrectly Formed'));
        }

        // If $cidr is set, replace $addr for its broadcast address
        if ($cidr != "")
        {
            // Check whether $cidr is valid
            if ((!ctype_digit($cidr)) || ($cidr < 0) || ($cidr > 32))
            {
                return $this->SetError(25015, __('CIDR subnet mask is not a number within the range of 0 till 32.'));
            }

            // Convert $cidr from one decimal to one inverted binary array
            $inverted_binary_cidr = "";

            // Build $inverted_binary_cidr by $cidr * zeros (this is the mask)
            for ($a=0; $a<$cidr; $a++) $inverted_binary_cidr .= "0";

            // Invert the mask (by postfixing ones to $inverted_binary_cidr untill 32 bits are filled/ complete)
            $inverted_binary_cidr = $inverted_binary_cidr.substr("11111111111111111111111111111111", 0, 32 - strlen($inverted_binary_cidr));

            // Convert $inverted_binary_cidr to an array of bits
            $inverted_binary_cidr_array = str_split($inverted_binary_cidr);

            // Convert IP address from four decimals to one binary array
            // Split IP address into an array of (four) decimals
            $addr_byte = explode('.', $addr);
            $binary_addr = "";

            for ($a=0; $a<4; $a++)
            {
                // Prefix zeros
                $pre = substr("00000000",0,8-strlen(decbin($addr_byte[$a])));

                // Postfix binary decimal
                $post = decbin($addr_byte[$a]);
                $binary_addr .= $pre.$post;
            }

            // Convert $binary_addr to an array of bits
            $binary_addr_array = str_split($binary_addr);

            // Perform a bitwise OR operation on arrays ($binary_addr_array & $inverted_binary_cidr_array)
            $binary_broadcast_addr_array="";

            // binary array of 32 bit variables ('|' = logical operator 'or')
            for ($a=0; $a<32; $a++) $binary_broadcast_addr_array[$a] = ($binary_addr_array[$a] | $inverted_binary_cidr_array[$a]);

            // build binary address of four bundles of 8 bits (= 1 byte)
            $binary_broadcast_addr = chunk_split(implode("", $binary_broadcast_addr_array), 8, ".");

            // chop off last dot ('.')
            $binary_broadcast_addr = substr($binary_broadcast_addr,0,strlen($binary_broadcast_addr)-1);

            // binary array of 4 byte variables
            $binary_broadcast_addr_array = explode(".", $binary_broadcast_addr);
            $broadcast_addr_array = "";

            // decimal array of 4 byte variables
            for ($a=0; $a<4; $a++) $broadcast_addr_array[$a] = bindec($binary_broadcast_addr_array[$a]);

            // broadcast address
            $addr = implode(".", $broadcast_addr_array);
        }

        // Check whether $port is valid
        if ((!ctype_digit($port)) || ($port < 0) || ($port > 65536))
            return $this->SetError(25000, __('Port is not a number within the range of 0 till 65536. Port Provided: ' . $port));

        // Check whether UDP is supported
        if (!array_search('udp', stream_get_transports()))
            return $this->SetError(25000, __('No magic packet can been sent, since UDP is unsupported (not a registered socket transport)'));

        // Ready to send the packet
        if (function_exists('fsockopen'))
        {
            // Try fsockopen function - To do: handle error 'Permission denied'
            $socket = fsockopen("udp://" . $addr, $port, $errno, $errstr);

            if ($socket)
            {
                $socket_data = fwrite($socket, $buf);

                if ($socket_data)
                {
                    $function = "fwrite";
                    $sent_fsockopen = "A magic packet of ".$socket_data." bytes has been sent via UDP to IP address: ".$addr.":".$port.", using the '".$function."()' function.";
                    $content = bin2hex($buf);

                    $sent_fsockopen = $sent_fsockopen."Contents of magic packet:".strlen($content)." ".$content;
                    fclose($socket);

                    unset($socket);

                    Log::notice($sent_fsockopen, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    unset($socket);

                    return $this->SetError(25015, __('Using "fwrite()" failed, due to error: ' . $errstr.  ' ("' . $errno . '")'));
                }
            }
            else
            {
                unset($socket);

                Log::notice(__('Using fsockopen() failed, due to denied permission'));
            }
        }

        // Try socket_create function
        if (function_exists('socket_create'))
        {
            // create socket based on IPv4, datagram and UDP
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($socket)
            {
                // to enable manipulation of options at the socket level (you may have to change this to 1)
                $level = SOL_SOCKET;

                // to enable permission to transmit broadcast datagrams on the socket (you may have to change this to 6)
                $optname = SO_BROADCAST;

                $optval = true;
                $opt_returnvalue = socket_set_option($socket, $level, $optname, $optval);

                if ($opt_returnvalue < 0)
                {
                    return $this->SetError(25015, __('Using "socket_set_option()" failed, due to error: ' . socket_strerror($opt_returnvalue)));
                }

                $flags = 0;

                // To do: handle error 'Operation not permitted'
                $socket_data = socket_sendto($socket, $buf, strlen($buf), $flags, $addr, $port);

                if ($socket_data)
                {
                    $function = "socket_sendto";
                    $socket_create = "A magic packet of ". $socket_data . " bytes has been sent via UDP to IP address: ".$addr.":".$port.", using the '".$function."()' function.<br>";

                    $content = bin2hex($buf);
                    $socket_create = $socket_create . "Contents of magic packet:" . strlen($content) ." " . $content;

                    socket_close($socket);
                    unset($socket);

                    Log::notice($socket_create, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    $error = __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')');
                    socket_close($socket);
                    unset($socket);

                    return $this->SetError(25015, $error);
                }
            }
            else
            {
                return $this->SetError(25015, __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')'));
            }
        }
        else
        {
            return $this->SetError(25015, __('Wake On Lan Failed as there are no functions available to transmit it'));
        }
    }

    /**
     * Notify displays of this campaign change
     * @param <type> $layoutId
     */
    public function NotifyDisplays($campaignId)
    {
        Log::notice(sprintf('Checking for Displays to refresh on Layout %d', $campaignId), 'display', 'NotifyDisplays');

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $currentdate = time();
            $rfLookahead = \Xibo\Helper\Sanitize::int(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'));
            $rfLookahead = $currentdate + $rfLookahead;

            // Which displays does a change to this layout effect?
            $SQL  = " SELECT DISTINCT display.DisplayID ";
            $SQL .= "   FROM schedule ";
            $SQL .= "   INNER JOIN schedule_detail ";
            $SQL .= "   ON schedule_detail.eventid = schedule.eventid ";
            $SQL .= "   INNER JOIN lkdisplaydg ";
            $SQL .= "   ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= "   INNER JOIN display ";
            $SQL .= "   ON lkdisplaydg.DisplayID = display.displayID ";
            $SQL .= " WHERE schedule.CampaignID = :campaignid ";
            $SQL .= " AND schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt ";
            $SQL .= " UNION ";
            $SQL .= " SELECT DISTINCT display.DisplayID ";
            $SQL .= "   FROM display ";
            $SQL .= "       INNER JOIN lkcampaignlayout ";
            $SQL .= "       ON lkcampaignlayout.LayoutID = display.DefaultLayoutID ";
            $SQL .= " WHERE lkcampaignlayout.CampaignID = :campaignid";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                'campaignid' => $campaignId,
                'fromdt' => $rfLookahead,
                'todt' => $currentdate - 3600
            ));

            while ($row = $sth->fetch()) {
                // Notify each display in turn
                $displayId = \Xibo\Helper\Sanitize::int($row['DisplayID']);
                $this->FlagIncomplete($displayId);
            }
        }
        catch (Exception $e) {
            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(25004, 'Unable to Flag Display as incomplete');

            return false;
        }
    }
}
