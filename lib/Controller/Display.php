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
use Xibo\Helper\ApplicationState;
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
        if (\Kit::IsFilterPinned('display', 'DisplayFilter')) {
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
     * Modifies the selected display record
     * @return
     */
    function modify()
    {


        $response = $this->getState();

        $displayObject = new Display();
        $displayObject->displayId = Sanitize::getInt('displayid');

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayObject->displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$displayObject->Load())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        // Update properties
        $displayObject->display = Sanitize::getString('display');
        $displayObject->description = Sanitize::getString('description');
        $displayObject->isAuditing = Sanitize::getInt('auditing');
        $displayObject->defaultLayoutId = Sanitize::getInt('defaultlayoutid');
        $displayObject->licensed = Sanitize::getInt('licensed');
        $displayObject->incSchedule = Sanitize::getInt('inc_schedule');
        $displayObject->emailAlert = Sanitize::getInt('email_alert');
        $displayObject->alertTimeout = Sanitize::getCheckbox('alert_timeout');
        $displayObject->wakeOnLanEnabled = Sanitize::getCheckbox('wakeOnLanEnabled');
        $displayObject->wakeOnLanTime = Sanitize::getString('wakeOnLanTime');
        $displayObject->broadCastAddress = Sanitize::getString('broadCastAddress');
        $displayObject->secureOn = Sanitize::getString('secureOn');
        $displayObject->cidr = Sanitize::getString('cidr');
        $displayObject->latitude = \Kit::GetParam('latitude', _POST, _DOUBLE);
        $displayObject->longitude = \Kit::GetParam('longitude', _POST, _DOUBLE);
        $displayObject->displayProfileId = Sanitize::getInt('displayprofileid');

        if (!$displayObject->Edit())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Saved.'));

    }

    /**
     * Modify Display form
     */
    function displayForm()
    {
        $response = $this->getState();

        // Get the display Id
        $displayObject = new Display();
        $displayObject->displayId = Sanitize::getInt('displayid');

        $auth = $this->getUser()->DisplayGroupAuth($this->GetDisplayGroupId($displayObject->displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        // Load this display
        if (!$displayObject->Load())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayEditForm');
        Theme::Set('form_action', 'index.php?p=display&q=modify');
        Theme::Set('form_meta', '<input type="hidden" name="displayid" value="' . $displayObject->displayId . '" />');

        // Column 1
        $formFields = array();
        $formFields[] = Form::AddText('display', __('Display'), $displayObject->display,
            __('The Name of the Display - (1 - 50 characters).'), 'd', 'required');

        $formFields[] = Form::AddText('hardwareKey', __('Display\'s Hardware Key'), $displayObject->license,
            __('A unique identifier for this display.'), 'h', 'required', NULL, false);

        $formFields[] = Form::AddText('description', __('Description'), $displayObject->description,
            __('A description - (1 - 254 characters).'), 'p', 'maxlength="50"');

        $formFields[] = Form::AddCombo(
            'licensed',
            __('Licence Display?'),
            $displayObject->licensed,
            array(array('licensedid' => '1', 'licensed' => 'Yes'), array('licensedid' => '0', 'licensed' => 'No')),
            'licensedid',
            'licensed',
            __('Use one of the available licenses for this display?'),
            'l');

        $formFields[] = Form::AddCombo(
            'defaultlayoutid',
            __('Default Layout'),
            $displayObject->defaultLayoutId,
            $this->getUser()->LayoutList(),
            'layoutid',
            'layout',
            __('The Default Layout to Display where there is no other content.'),
            't');

        Theme::Set('form_fields_general', $formFields);

        // Maintenance
        $formFields = array();
        $formFields[] = Form::AddCombo(
            'email_alert',
            __('Email Alerts'),
            $displayObject->emailAlert,
            array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
            'id',
            'value',
            __('Do you want to be notified by email if there is a problem with this display?'),
            'a');

        $formFields[] = Form::AddCheckbox('alert_timeout', __('Use the Global Timeout?'), $displayObject->alertTimeout,
            __('Should this display be tested against the global time out or the client collection interval?'),
            'o');

        Theme::Set('form_fields_maintenance', $formFields);

        // Location
        $formFields = array();

        $formFields[] = Form::AddNumber('latitude', __('Latitude'), $displayObject->latitude,
            __('The Latitude of this display'), 'g');

        $formFields[] = Form::AddNumber('longitude', __('Longitude'), $displayObject->longitude,
            __('The Longitude of this Display'), 'g');

        Theme::Set('form_fields_location', $formFields);

        // Wake on LAN
        $formFields = array();

        $formFields[] = Form::AddCheckbox('wakeOnLanEnabled', __('Enable Wake on LAN'),
            $displayObject->wakeOnLanEnabled, __('Wake on Lan requires the correct network configuration to route the magic packet to the display PC'),
            'w');

        $formFields[] = Form::AddText('broadCastAddress', __('BroadCast Address'), (($displayObject->broadCastAddress == '') ? $displayObject->clientAddress : $displayObject->broadCastAddress),
            __('The IP address of the remote host\'s broadcast address (or gateway)'), 'b');

        $formFields[] = Form::AddText('secureOn', __('Wake on LAN SecureOn'), $displayObject->secureOn,
            __('Enter a hexadecimal password of a SecureOn enabled Network Interface Card (NIC) of the remote host. Enter a value in this pattern: \'xx-xx-xx-xx-xx-xx\'. Leave the following field empty, if SecureOn is not used (for example, because the NIC of the remote host does not support SecureOn).'), 's');

        $formFields[] = Form::AddText('wakeOnLanTime', __('Wake on LAN Time'), $displayObject->wakeOnLanTime,
            __('The time this display should receive the WOL command, using the 24hr clock - e.g. 19:00. Maintenance must be enabled.'), 't');

        $formFields[] = Form::AddText('cidr', __('Wake on LAN CIDR'), $displayObject->cidr,
            __('Enter a number within the range of 0 to 32 in the following field. Leave the following field empty, if no subnet mask should be used (CIDR = 0). If the remote host\'s broadcast address is unknown: Enter the host name or IP address of the remote host in Broad Cast Address and enter the CIDR subnet mask of the remote host in this field.'), 'c');

        Theme::Set('form_fields_wol', $formFields);

        // Advanced
        $formFields = array();

        $displayProfileList = $this->getUser()->DisplayProfileList(NULL, array('type' => $displayObject->clientType));
        array_unshift($displayProfileList, array('displayprofileid' => 0, 'name' => ''));

        $formFields[] = Form::AddCombo(
            'displayprofileid',
            __('Settings Profile?'),
            $displayObject->displayProfileId,
            $displayProfileList,
            'displayprofileid',
            'name',
            __('What display profile should this display use?'),
            'p');

        $formFields[] = Form::AddCombo(
            'inc_schedule',
            __('Interleave Default'),
            $displayObject->incSchedule,
            array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
            'id',
            'value',
            __('Whether to always put the default layout into the cycle.'),
            'i');

        $formFields[] = Form::AddCombo(
            'auditing',
            __('Auditing'),
            $displayObject->isAuditing,
            array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
            'id',
            'value',
            __('Collect auditing from this client. Should only be used if there is a problem with the display.'),
            'a');

        // Show the resolved settings for this display.
        $formFields[] = Form::AddMessage(__('The settings for this display are shown below. They are taken from the active Display Profile for this Display, which can be changed in Display Settings. If you have altered the Settings Profile above, you will need to save and re-show the form.'));

        // Build a table for the settings to be shown in
        $cols = array(
            array('name' => 'title', 'title' => __('Setting')),
            array('name' => 'valueString', 'title' => __('Value'))
        );

        // Get the settings from the profile
        $profile = $displayObject->getSettingsProfile();

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

        Theme::Set('table_cols', $cols);
        Theme::Set('table_rows', $profile);
        $formFields[] = Form::AddRaw(Theme::RenderReturn('table_render'));

        Theme::Set('form_fields_advanced', $formFields);

        // Two tabs
        $tabs = array();
        $tabs[] = Form::AddTab('general', __('General'));
        $tabs[] = Form::AddTab('location', __('Location'));
        $tabs[] = Form::AddTab('maintenance', __('Maintenance'));
        $tabs[] = Form::AddTab('wol', __('Wake on LAN'));
        $tabs[] = Form::AddTab('advanced', __('Advanced'));

        Theme::Set('form_tabs', $tabs);

        $response->SetFormRequestResponse(NULL, __('Edit a Display'), '650px', '350px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Display', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayEditForm").submit()');

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
        Session::Set('display', 'DisplayFilter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        $displays = $user->DisplayList(array('displayid'), array(
            'displaygroupid' => $filter_displaygroupid,
            'display' => $filter_display,
            'macAddress' => $filterMacAddress,
            'clientVersion' => $filterVersion)
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
            if (file_exists(Config::GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $display->displayid . '_screenshot.jpg')) {
                $display->thumbnail = 'index.php?p=display&q=ScreenShot&DisplayId=' . $display->displayid;
            }

            // Format the storage available / total space
            $display->storagePercentage = ($display->storageTotalSpace == 0) ? 100 : round($display->storageAvailableSpace / $display->storageTotalSpace * 100.0, 2);

            // Edit and Delete buttons first
            if ($this->getUser()->checkEditable($display)) {
                // Edit
                $display->buttons[] = array(
                    'id' => 'display_button_edit',
                    'url' => 'index.php?p=display&q=displayForm&displayid=' . $display->displayid,
                    'text' => __('Edit')
                );
            }

            // Delete
            if ($this->getUser()->checkDeleteable($display)) {
                $display->buttons[] = array(
                    'id' => 'display_button_delete',
                    'url' => 'index.php?p=display&q=DeleteForm&displayid=' . $display->displayid,
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
                    'url' => 'index.php?p=schedule&q=ScheduleNowForm&displayGroupId=' . $display->displaygroupid,
                    'text' => __('Schedule Now')
                );
            }

            if ($this->getUser()->checkEditable($display)) {

                // Default Layout
                $display->buttons[] = array(
                    'id' => 'display_button_defaultlayout',
                    'url' => 'index.php?p=display&q=DefaultLayoutForm&DisplayId=' . $display->displayid,
                    'text' => __('Default Layout')
                );

                // File Associations
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => 'index.php?p=displaygroup&q=FileAssociations&DisplayGroupID=' . $display->displaygroupid,
                    'text' => __('Assign Files')
                );

                // Screen Shot
                $display->buttons[] = array(
                    'id' => 'display_button_requestScreenShot',
                    'url' => 'index.php?p=display&q=RequestScreenShotForm&displayId=' . $display->displayid,
                    'text' => __('Request Screen Shot'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'multiselectlink', 'value' => 'index.php?p=display&q=RequestScreenShot'),
                        array('name' => 'rowtitle', 'value' => $display->display),
                        array('name' => 'displayId', 'value' => $display->displayid)
                    )
                );

                $display->buttons[] = ['divider' => true];
            }

            // Media Inventory
            $display->buttons[] = array(
                'id' => 'display_button_mediainventory',
                'url' => 'index.php?p=display&q=MediaInventory&DisplayId=' . $display->displayid,
                'text' => __('Media Inventory')
            );

            if ($this->getUser()->checkEditable($display)) {

                // Logs
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_logs',
                    'url' => 'index.php?p=log&q=LastHundredForDisplay&displayid=' . $display->displayid,
                    'text' => __('Recent Log')
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkPermissionsModifyable($display)) {

                // Display Groups
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => 'index.php?p=display&q=MemberOfForm&DisplayID=' . $display->displayid,
                    'text' => __('Display Groups')
                );

                // Permissions
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => 'index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $display->displaygroupid,
                    'text' => __('Permissions')
                );

                // Version Information
                $display->buttons[] = array(
                    'id' => 'display_button_version_instructions',
                    'url' => 'index.php?p=displaygroup&q=VersionInstructionsForm&displaygroupid=' . $display->displaygroupid . '&displayid=' . $display->displayid,
                    'text' => __('Version Information')
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkEditable($display)) {
                // Wake On LAN
                $display->buttons[] = array(
                    'id' => 'display_button_wol',
                    'url' => 'index.php?p=display&q=WakeOnLanForm&DisplayId=' . $display->displayid,
                    'text' => __('Wake on LAN')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($displays);
    }

    /**
     * Delete form
     */
    function DeleteForm()
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

    /**
     * Wake on LAN
     */
    public function WakeOnLan()
    {



        $response = $this->getState();
        $displayObject = new Display($db);

        $displayId = Sanitize::getInt('DisplayId');

        if (!$displayObject->WakeOnLan($displayId))
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Wake on Lan command sent.'));

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
}

?>
