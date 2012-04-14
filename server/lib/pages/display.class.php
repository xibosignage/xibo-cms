<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner and James Packer
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class displayDAO
{
	private $db;
	private $user;
	private $has_permissions = true;

	//display table fields
	private $displayid;
	private $display;
	private $layoutid;
	private $license;
	private $licensed;
	private $inc_schedule;
	private $auditing;
    private $email_alert;
    private $alert_timeout;
	private $ajax;
        private $mediaInventoryStatus;
        private $mediaInventoryXml;
        private $macAddress;
        private $wakeOnLan;
        private $wakeOnLanTime;
        private $broadCastAddress;
        private $secureOn;
        private $cidr;
        private $clientIpAddress;

	function __construct(database $db, user $user)
	{
		$this->db 	=& $db;
		$this->user =& $user;

		include_once('lib/data/display.data.class.php');

		$this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
		$this->ajax		= Kit::GetParam('ajax', _REQUEST, _WORD, 'false');
		$displayid 		= Kit::GetParam('displayid', _REQUEST, _INT, 0);

		// validate displays so we get a realistic view of the table
		$this->validateDisplays();

		if(isset($_GET['modify']) || $displayid != 0)
		{
			$this->sub_page = 'edit';

			if (!$this->has_permissions && $this->ajax == 'true')
			{
				trigger_error(__("You do not have permissions to edit this display"), E_USER_ERROR);
			}

			$SQL = <<<SQL
    SELECT display.displayid,
            display.display,
            display.defaultlayoutid,
            display.license,
            display.licensed,
            display.inc_schedule,
            display.isAuditing,
            display.email_alert,
            display.alert_timeout,
            display.MediaInventoryStatus,
            display.MediaInventoryXml,
            display.MacAddress,
            display.WakeOnLan,
            display.WakeOnLanTime,
            display.BroadCastAddress,
            display.SecureOn,
            display.Cidr,
            display.ClientAddress
     FROM display
    WHERE display.displayid = %d
SQL;

			$SQL = sprintf($SQL, $displayid);

			Debug::LogEntry($db, 'audit', $SQL);

			if(!$results = $db->query($SQL))
			{
				trigger_error($db->error());
				trigger_error(__("Can not get the display information for display") . '[$this->displayid]', E_USER_ERROR);
			}

			while($row = $db->get_row($results))
			{
				$this->displayid 		= Kit::ValidateParam($row[0], _INT);
				$this->display 			= Kit::ValidateParam($row[1], _STRING);
				$this->layoutid 		= Kit::ValidateParam($row[2], _INT);
				$this->license 			= Kit::ValidateParam($row[3], _STRING);
				$this->licensed		 	= Kit::ValidateParam($row[4], _INT);
				$this->inc_schedule 	= Kit::ValidateParam($row[5], _INT);
				$this->auditing			= Kit::ValidateParam($row[6], _INT);
                            $this->email_alert      = Kit::ValidateParam($row[7], _INT);
                            $this->alert_timeout    = Kit::ValidateParam($row[8], _INT);
                            $this->mediaInventoryStatus = Kit::ValidateParam($row[9], _INT);
                            $this->mediaInventoryXml = Kit::ValidateParam($row[10], _HTMLSTRING);
                            $this->macAddress = Kit::ValidateParam($row[11], _STRING);
                            $this->wakeOnLan = Kit::ValidateParam($row[12], _INT);
                            $this->wakeOnLanTime = Kit::ValidateParam($row[13], _STRING);
                            $this->broadCastAddress = Kit::ValidateParam($row[14], _STRING);
                            $this->secureOn = Kit::ValidateParam($row[15], _STRING);
                            $this->cidr = Kit::ValidateParam($row[16], _INT);
                            $this->clientIpAddress = Kit::ValidateParam($row[17], _STRING);

                            // Make cidr null if its a 0
                            $this->cidr = ($this->cidr == 0) ? '' : $this->cidr;
			}
		}

		return true;
	}

	function on_page_load()
	{
		return "";
	}

	function echo_page_heading()
	{
		echo __("Display Administration");
		return true;
	}

	/**
	 * Modifies the selected display record
	 * @return
	 */
	function modify()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$displayid 		= Kit::GetParam('displayid', _POST, _INT);
		$display 		= Kit::GetParam('display', _POST, _STRING);
		$layoutid 		= Kit::GetParam('defaultlayoutid', _POST, _INT);
		$inc_schedule 	= Kit::GetParam('inc_schedule', _POST, _INT);
		$auditing 		= Kit::GetParam('auditing', _POST, _INT);
        $email_alert    = Kit::GetParam('email_alert', _POST, _INT);
        $alert_timeout  = Kit::GetParam('alert_timeout', _POST, _INT);
        $wakeOnLanEnabled = Kit::GetParam('wakeOnLanEnabled', _POST, _CHECKBOX);
        $wakeOnLanTime = Kit::GetParam('wakeOnLanTime', _POST, _STRING);
        $broadCastAddress = Kit::GetParam('broadCastAddress', _POST, _STRING);
        $secureOn = Kit::GetParam('secureOn', _POST, _STRING);
        $cidr = Kit::GetParam('cidr', _POST, _INT);

		// Do we take, or revoke a license
		if (isset($_POST['takeLicense']))
		{
			$licensed = Kit::GetParam('takeLicense', _POST, _INT);
		}
		if (isset($_POST['revokeLicense']))
		{
			$licensed = Kit::GetParam('revokeLicense', _POST, _INT);
		}

		//Validation
		if ($display == "")
		{
			trigger_error(__("Can not have a display without a name"), E_USER_ERROR);
		}

                if ($wakeOnLanEnabled == 1 && $wakeOnLanTime == '')
                    trigger_error(__('Wake on Lan is enabled, but you have not specified a time to wake the display'), E_USER_ERROR);

		$displayObject 	= new Display($db);

		if (!$displayObject->Edit($displayid, $display, $auditing, $layoutid, $licensed, $inc_schedule, $email_alert, $alert_timeout, $wakeOnLanEnabled, $wakeOnLanTime, $broadCastAddress, $secureOn, $cidr))
		{
			trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);
		}

		$response->SetFormSubmitResponse(__('Display Saved.'));
		$response->Respond();
	}

	/**
	 * Modify Display form
	 * @return
	 */
	function displayForm()
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();

		$helpManager	= new HelpManager($db, $user);

		//get some vars
		$displayid 			= $this->displayid;
		$display 			= $this->display;
		$layoutid 			= $this->layoutid;
		$license 			= $this->license;
		$licensed		 	= $this->licensed;
		$inc_schedule		= $this->inc_schedule;
		$auditing			= $this->auditing;
        $email_alert        = $this->email_alert;
        $alert_timeout      = $this->alert_timeout;

		// Help UI
		$nameHelp		= $helpManager->HelpIcon(__("The Name of the Display - (1 - 50 characters)."), true);
		$defaultHelp	= $helpManager->HelpIcon(__("The Default Layout to Display where there is no other content."), true);
		$interleveHelp	= $helpManager->HelpIcon(__("Whether to always put the default into the cycle."), true);
		$licenseHelp	= $helpManager->HelpIcon(__("Control the licensing on this display."), true);
		$auditHelp		= $helpManager->HelpIcon(__("Collect auditing from this client. Should only be used if there is a problem with the display."), true);
        $emailHelp      = $helpManager->HelpIcon(__("Do you want to be notified by email if there is a problem with this display?"), true);
        $alertHelp      = $helpManager->HelpIcon(__("How long in minutes after the display last connected to the webservice should we send an alert. Set this value higher than the collection interval on the client. Set to 0 to use global default."), true);
        $wolHelp = $helpManager->HelpIcon(__('Wake on Lan requires the correct network configuration to route the magic packet to the display PC'), true);
        $wolTimeHelp = $helpManager->HelpIcon(_('The time this display should receive the WOL command, using the 24hr clock - e.g. 19:00. Maintenance must be enabled.'), true);
        
                $layoutList = Kit::SelectList('defaultlayoutid', $this->user->LayoutList(), 'layoutid', 'layout', $layoutid);

                $inc_schedule_list = listcontent("1|Yes,0|No","inc_schedule",$inc_schedule);
		$auditing_list = listcontent("1|Yes,0|No","auditing",$auditing);
        $email_alert_list = listcontent("1|Yes,0|No","email_alert",$email_alert);

        // Is the wake on lan field checked?
        $wakeOnLanChecked = ($this->wakeOnLan == 1) ? ' checked' : '';

		$license_list = "";

		//Are we licensed
		if ($licensed == 0)
		{
			//There are licenses to take, shall we take them?
			$license_list = '<td><label for="takeLicense" title="' . __('Will use one of the available licenses for this display') . '">' . __('License Display') . '</label></td>';
			$license_list .= "<td>" . listcontent("1|Yes,0|No", "takeLicense", "1") . "</td>";
		}
		else
		{
			// Give an option to revoke
			$license_list = '<td><label for="revokeLicense" title="' . __('Revoke License') . '. ' . __('Make the license available for another display.') . '">' . __('Revoke License') . '</label></td>';
			$license_list .= "<td>" . listcontent("0|Yes,1|No", "revokeLicense", "1") . "</td>";
		}

		// Messages
		$msgDisplay	= __('Display');
		$msgDefault	= __('Default Layout');
		$msgInterL	= __('Interleave Default');
		$msgAudit	= __('Auditing');
		$msgLicense	= __('License');
        $msgAlert   = __('Email Alerts');
        $msgTimeout = __('Alert Timeout');
        $msgWakeOnLan = __('Enable Wake On LAN');
        $msgWakeOnLanTime = __('Wake On LAN Time');
        $msgBroadCastAddress = __('BroadCast Address');
        $msgSecureOn = __('Wake On LAN Secure On');
        $msgCidr = __('Wake On LAN CIDR');

        $helpBroadCastAddress = $helpManager->HelpIcon(__('The IP address of the remote host\'s broadcast address (or gateway)'), true);
        $helpSecureOn = $helpManager->HelpIcon(__('Enter a hexidecimal password of a SecureOn enabled Network Interface Card (NIC) of the remote host. Enter a value in this pattern: \'xx-xx-xx-xx-xx-xx\'. Leave the following field empty, if SecureOn is not used (for example, because the NIC of the remote host does not support SecureOn).'), true);
        $helpCidr = $helpManager->HelpIcon(__('Enter a number within the range of 0 to 32 in the following field. Leave the following field empty, if no subnet mask should be used (CIDR = 0). If the remote host\'s broadcast address is unkown: Enter the host name or IP address of the remote host in Broad Cast Address and enter the CIDR subnet mask of the remote host in this field.'), true);

        // If the broadcast address has not been set, then default to the client ip address
        $broadCastAddress = ($this->broadCastAddress == '') ? $this->clientIpAddress : $this->broadCastAddress;

		$form = <<<END
		<form id="DisplayEditForm" class="XiboForm" method="post" action="index.php?p=display&q=modify&id=$displayid">
			<input type="hidden" name="displayid" value="$displayid">
			<table>
				<tr>
					<td>$msgDisplay<span class="required">*</span></td>
					<td>$nameHelp <input name="display" type="text" value="$display"></td>
					<td>$msgDefault<span class="required">*</span></td>
					<td>$defaultHelp $layoutList</td>
				</tr>
				<tr>
					<td>$msgInterL<span class="required">*</span></td>
					<td>$interleveHelp $inc_schedule_list</td>
				</tr>
				<tr>
					<td>$msgAudit?<span class="required">*</span></td>
					<td>$auditHelp $auditing_list</td>
				</tr>
                <tr>
                    <td>$msgAlert<span class="required">*</span></td>
                    <td>$emailHelp $email_alert_list</td>
                    <td>$msgTimeout<span class="required">*</span></td>
                    <td>$alertHelp <input name="alert_timeout" type="text" value="$alert_timeout"></td>
                </tr>
                <tr>
                    <td colspan="2"><label for="wakeOnLanEnabled">$msgWakeOnLan</label>$wolHelp<input type="checkbox" id="wakeOnLanEnabled" name="wakeOnLanEnabled" $wakeOnLanChecked></td>
                    <td>$msgWakeOnLanTime</td>
                    <td>$wolTimeHelp<input name="wakeOnLanTime" type="text" value="$this->wakeOnLanTime"></td>
                </tr>
                <tr>
                    <td><label for="broadCastAddress">$msgBroadCastAddress</label></td>
                    <td>$helpBroadCastAddress<input type="text" id="broadCastAddress" name="broadCastAddress" value="$broadCastAddress"></td>
                </tr>
                <tr>
                    <td><label for="secureOn">$msgSecureOn</label></td>
                    <td>$helpSecureOn<input id="secureOn" name="secureOn" type="text" value="$this->secureOn"></td>
                    <td><label for="cidr">$msgCidr</label></td>
                    <td>$helpCidr<input id="cidr" name="cidr" type="text" value="$this->cidr" class="number"></td>
                </tr>
				<tr>
					<td>$msgLicense</td>
					<td>$licenseHelp <input type="text" readonly value="$license"></td>
					$license_list
				</tr>
			</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Edit a Display'), '650px', '300px');
                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Display', 'Edit') . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#DisplayEditForm").submit()');
		$response->Respond();
	}

	public function DisplayFilter()
	{
		$filterForm = <<<END
		<div class="FilterDiv" id="LayoutFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="display">
				<input type="hidden" name="q" value="DisplayGrid">
			</form>
		</div>
END;

		$id = uniqid();

		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$filterForm
			</div>
			<div class="XiboData">

			</div>
		</div>
HTML;
		echo $xiboGrid;
	}

	/**
	 * Grid of Displays
	 * @return
	 */
	function DisplayGrid()
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();

                $displayGroupAuth = $user->DisplayGroupAuth();

		//display the display table
		$SQL = <<<SQL
		SELECT display.displayid,
                        display.display,
                        layout.layout,
                        CASE WHEN display.loggedin = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS loggedin,
                        display.lastaccessed,
                        CASE WHEN display.inc_schedule = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS inc_schedule,
                        CASE WHEN display.licensed = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS licensed,
                        CASE WHEN display.email_alert = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS email_alert,
                        displaygroup.DisplayGroupID,
                        display.ClientAddress,
                        CASE WHEN display.MediaInventoryStatus = 1 THEN '<img src="img/act.gif">'
                             WHEN display.MediaInventoryStatus = 2 THEN '<img src="img/warn.gif">'
                             ELSE '<img src="img/disact.gif">'
                        END AS MediaInventoryStatus,
                        display.MediaInventoryXml,
                        display.MacAddress
		  FROM display
                    INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayID = display.DisplayID
                    INNER JOIN displaygroup ON displaygroup.DisplayGroupID = lkdisplaydg.DisplayGroupID
                    LEFT OUTER JOIN layout ON layout.layoutid = display.defaultlayoutid
		 WHERE displaygroup.IsDisplaySpecific = 1
		ORDER BY display.displayid
SQL;

		if(!($results = $db->query($SQL)))
		{
			trigger_error($db->error());
			trigger_error(__("Can not list displays"), E_USER_ERROR);
		}

		// Messages
		$msgDisplay	= __('Display');
		$msgDefault	= __('Default Layout');
		$msgInterL	= __('Interleave Default');
		$msgAudit	= __('Auditing');
		$msgLicense	= __('License');
                $msgAlert       = __('Email Alert');
		$msgSave	= __('Save');
		$msgCancel	= __('Cancel');
		$msgAction	= __('Action');
		$msgLastA	= __('Last Accessed');
		$msgLogIn	= __('Logged In');
		$msgEdit	= __('Edit');
		$msgDelete	= __('Delete');
		$msgGroupSecurity = __('Group Security');
                $msgClientAddress = __('IP Address');
                $msgDefault = __('Default Layout');
                $msgStatus = __('Status');
                $msgMediaInventory = __('Media Inventory');
                $msgMacAddress = __('Mac Address');
                $msgWakeOnLan = __('Wake on LAN');

		$output = <<<END
		<div class="info_table">
		<table style="width:100%">
                    <thead>
                    <tr>
                        <th>$msgDisplay ID</th>
                        <th>$msgLicense</th>
                        <th>$msgDisplay</th>
                        <th>$msgDefault</th>
                        <th>$msgInterL</th>
                        <th>$msgAlert</th>
                        <th>$msgLogIn</th>
                        <th>$msgLastA</th>
                        <th>$msgClientAddress</th>
                        <th>$msgMacAddress</th>
                        <th>$msgStatus</th>
                        <th>$msgAction</th>
                    </tr>
                    </thead>
                    <tbody>
END;

		while($aRow = $db->get_row($results))
		{
                    // Check that we have permission to access this display record
                    $displayGroupID = Kit::ValidateParam($aRow[8], _INT);

                    if (!in_array($displayGroupID, $displayGroupAuth) && $this->user->usertypeid != 1)
                        continue;

                    $displayid 	= $aRow[0];
                    $display 	= $aRow[1];
                    $defaultlayoutid = $aRow[2];
                    $loggedin 	= $aRow[3];
                    $lastaccessed 	= date('Y-m-d H:i:s', $aRow[4]);
                    $inc_schedule 	= $aRow[5];
                    $licensed 	= $aRow[6];
            $email_alert    = $aRow[7];
			
			$clientAddress  = Kit::ValidateParam($aRow[9], _STRING);
                        $displayName    = $display;

                        // Do we want to make a VNC link out of the display name?
                        $vncTemplate = Config::GetSetting($db, 'SHOW_DISPLAY_AS_VNCLINK');
                        $linkTarget = Kit::ValidateParam(Config::GetSetting($db, 'SHOW_DISPLAY_AS_VNC_TGT'), _STRING);
                        $mediaInventoryStatusLight = Kit::ValidateParam($aRow[10], _STRING);
                        $macAddress = Kit::ValidateParam($aRow[12], _STRING);

                        if ($vncTemplate != '' && $clientAddress != '')
                        {
                            if ($linkTarget == '')
                            {
                                $linkTarget = '_top';
                            }

                            $display = sprintf('<a href="' . $vncTemplate . '" title="VNC to ' . $display . '" target="' . $linkTarget . '">' . $display . '</a>', $clientAddress);
                        }

                        $buttons = '';

                        if ($user->usertypeid == 1)
                        {
                            $buttons = <<<END
                        <button class='XiboFormButton' href='index.php?p=display&q=displayForm&displayid=$displayid'><span>$msgEdit</span></button>
                        <button class='XiboFormButton' href='index.php?p=display&q=DeleteForm&displayid=$displayid'><span>$msgDelete</span></button>
                        <button class="XiboFormButton" href="index.php?p=displaygroup&q=GroupSecurityForm&DisplayGroupID=$displayGroupID&DisplayGroup=$displayName"><span>$msgGroupSecurity</span></button>
                        <button class="XiboFormButton" href="index.php?p=display&q=MediaInventory&DisplayId=$displayid"><span>$msgMediaInventory</span></button>
                        <button class="XiboFormButton" href="index.php?p=display&q=WakeOnLanForm&DisplayId=$displayid"><span>$msgWakeOnLan</span></button>
END;
                        }

                        $buttons .= '<button class="XiboFormButton" href="index.php?p=display&q=DefaultLayoutForm&DisplayId=' . $displayid . '"><span>' . $msgDefault . '</span></button>';
                        $buttons .= '<button class="XiboFormButton" href="index.php?p=schedule&q=ScheduleNowForm&displayGroupId=' . $displayGroupID . '"><span>' . __('Schedule Now') . '</span></button>';

			$output .= <<<END

			<tr>
			<td>$displayid</td>
			<td>$licensed</td>
			<td>$display</td>
			<td>$defaultlayoutid</td>
			<td>$inc_schedule</td>
                        <td>$email_alert</td>
			<td>$loggedin</td>
			<td>$lastaccessed</td>
			<td>$clientAddress</td>
                        <td>$macAddress</td>
                        <td>$mediaInventoryStatusLight</td>
			<td>$buttons</td>
END;
		}
		$output .= "</tbody></table></div>";

		$response->SetGridResponse($output);
		$response->Respond();
	}

	/**
	 * Include display page template page based on sub page selected
	 * @return
	 */
	function displayPage()
	{
		$db =& $this->db;

		if (!$this->has_permissions)
		{
			trigger_error(__("You do not have permissions to access this page"), E_USER_ERROR);
			return false;
		}

		switch ($this->sub_page)
		{

			case 'view':
				require("template/pages/display_view.php");
				break;

			default:
				break;
		}

		return false;
	}

	/**
	 * Output some display tabs based on displays that are licensed
	 * @return
	 * @param $defaulted_displayid Object
	 * @param $link Object
	 * @param $currently_playing Object[optional]
	 */
	function display_tabs($defaulted_displayid, $link, $currently_playing = true)
	{
		$db =& $this->db;
		$output = "";


		//get the number of displays allowed in the license
		$SQL  = "SELECT display.displayid, ";
		$SQL .= "       display.display ";
		$SQL .= "  FROM display ";
		$SQL .= " WHERE display.licensed = 1 ";

		if(!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__("Can not list displays"), E_USER_ERROR);
		}

		$output .= "<div class='buttons'>";

		while ($row = $db->get_row($results))
		{
			$displayid = $row[0];
			$display = substr($row[1], 0, 8);

			if ($displayid == $defaulted_displayid)
			{
				$output .= "<a class='defaulted_tab' href='$link&displayid=$displayid'><div class='button_text'>$display</div></a>";
			}
			else
			{
				$output .= "<a class='normal_tab' href='$link&displayid=$displayid'><div class='button_text'>$display</div></a>";
			}
		}

		$output .= "</div>";

		return $output;
	}

	/**
	 * Display what is currently playing on this display
	 * @return
	 * @param $displayid Object
	 */
	function currently_playing($displayid)
	{
		$db =& $this->db;
		$currentdate = date("Y-m-d H:i:s");
		$return = "<div class='display_info'>" . __('Currently Playing'); //return string
		/*
		  * Generates the currently playing list, defaulted to the display ID given
		  */
		#now i know what display i am find out what i am ment to be playing
		$SQL  = "";
		$SQL .= "SELECT  layoutdisplay.layoutDisplayID, ";
		$SQL .= "        layout.Name, ";
		$SQL .= "		 layout.layoutID, ";
		$SQL .= "		 layoutdisplay.starttime ";
		$SQL .= "FROM    display, ";
		$SQL .= "        layoutdisplay, ";
		$SQL .= "        layout ";
		$SQL .= "WHERE   display.displayid            = layoutdisplay.displayid ";
		$SQL .= "        AND layoutdisplay.layoutID = layout.layoutID ";
		$SQL .= "        AND display.displayid          = " . $displayid;
		$SQL .= "        AND layoutdisplay.starttime  < '" . $currentdate . "'";
		$SQL .= "        AND layoutdisplay.endtime    > '" . $currentdate . "'";

		if(!$results = $db->query($SQL))  trigger_error($db->error(), E_USER_ERROR);

		if($db->num_rows($results)==0)
		{
			//check to see if there is a default layout assigned instead
			$SQL  = "";
			$SQL .= "SELECT  1, layout.Name, ";
			$SQL .= "		 layout.layoutID, 1 ";
			$SQL .= "FROM    display, ";
			$SQL .= "        layout ";
			$SQL .= "WHERE   layout.layoutID 			= display.defaultlayoutid ";
			$SQL .= "        AND display.displayid          = " . $displayid;

			if(!$results = $db->query($SQL))  trigger_error($db->error(), E_USER_ERROR);

			if($db->num_rows($results)==0)
			{
				$return .= __('Nothing') . "</div>";
				return $return;
			}
		}

		$count = 1;

		while ($row = $db->get_row($results))
		{
			$name = $row[1];

			$return .= "$count. $name.   ";
			$count++;
		}
		$return .= "</div>";

		return $return;
	}

    /**
     * Assess each Display to correctly set the logged in flag based on last accessed time
     * @return
     */
    function validateDisplays()
    {
        $db =& $this->db;

        // Get the global timeout (overrides the alert timeout on the display if 0
        $globalTimeout = Config::GetSetting($db, 'MAINTENANCE_ALERT_TOUT');

        // Get a list of all displays and there last accessed / alert timeout value
        $SQL  = "";
        $SQL .= "SELECT displayid, lastaccessed, alert_timeout FROM display ";

        if (!$result =$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to access displays'), E_USER_ERROR);
        }

        // Look through each display
        while($row = $db->get_assoc_row($result))
        {
            $displayid    = Kit::ValidateParam($row['displayid'], _INT);
            $lastAccessed = Kit::ValidateParam($row['lastaccessed'], _INT);
            $alertTimeout = Kit::ValidateParam($row['alert_timeout'], _INT);

            // Do we need to update the logged in light?
            $timeoutToTestAgainst = ($alertTimeout == 0) ? $globalTimeout : $alertTimeout;

            // If the last time we accessed is less than now minus the timeout
            if ($lastAccessed < time() - ($timeoutToTestAgainst * 60))
            {
                // Update the display and set it as logged out
                $SQL = "UPDATE display SET loggedin = 0 WHERE displayid = " . $displayid;

                if ((!$db->query($SQL)))
                    trigger_error($db->error());

                Debug::LogEntry($db, 'audit', sprintf('LastAccessed = %d, Timeout = %d for displayId %d', $lastAccessed, $timeoutToTestAgainst, $displayid));
            }
        }
    }

	function DeleteForm()
	{
		$db 		=& $this->db;
                $user           =& $this->user;
                
		$response 	= new ResponseManager();
		$displayid 	= Kit::GetParam('displayid', _REQUEST, _INT);
                $helpManager    = new HelpManager($db, $user);

		// Output the delete form
		$msgInfo	= __('Deleting a display cannot be undone.');
		$msgWarn	= __('Are you sure you want to delete this display?');
		$msgYes		= __('Yes');
		$msgNo		= __('No');

		$form = <<<END
		<form id="DisplayDeleteForm" class="XiboForm" method="post" action="index.php?p=display&q=Delete">
			<input type="hidden" name="displayid" value="$displayid">
			<p>$msgInfo<br />
			<p>$msgWarn</p>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Delete this Display?'), '350px', '210');
                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Display', 'Delete') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#DisplayDeleteForm").submit()');
		$response->Respond();
	}

	function Delete()
	{
		$db 		=& $this->db;
		$response	= new ResponseManager();
		$displayid 	= Kit::GetParam('displayid', _POST, _INT, 0);

		if ($displayid == 0)
		{
			$response->SetError(__("No Display selected for Deletion."));
			$response->Respond();
		}

		$displayObject = new Display($db);

		if (!$displayObject->Delete($displayid))
		{
			trigger_error(__("Cannot delete this display. You may unlicense it to hide it from view."), E_USER_ERROR);
		}

		$response->SetFormSubmitResponse(__("The Display has been Deleted"));
		$response->Respond();
	}

    /**
     * Form for editing the default layout of a display
     */
    public function DefaultLayoutForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        if (!$defaultLayoutId = $this->db->GetSingleValue(sprintf("SELECT defaultlayoutid FROM display WHERE displayid = %d", $displayId),
                'defaultlayoutid', _INT))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the default layout'), E_USER_ERROR);
        }

        $msgDefault = __('Default Layout');
	$layoutList = Kit::SelectList('defaultlayoutid', $this->user->LayoutList(), 'layoutid', 'layout', $defaultLayoutId);

        $form = <<<END
            <form id="DefaultLayoutForm" class="XiboForm" method="post" action="index.php?p=display&q=DefaultLayout&DisplayId=$displayId">
                <input type="hidden" name="DisplayId" value="$displayId">
                <table>
                    <tr>
                        <td>$msgDefault<span class="required">*</span></td>
                        <td>$layoutList</td>
                    </tr>
                </table>
            </form>
END;

        $response->SetFormRequestResponse($form, __('Edit Default Layout'), '300px', '150px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DefaultLayoutForm").submit()');
        $response->Respond();
    }

    /**
     * Edit the default layout for a display
     */
    public function DefaultLayout()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $displayObject 	= new Display($db);

        $displayId = Kit::GetParam('DisplayId', _POST, _INT);
        $defaultLayoutId = Kit::GetParam('defaultlayoutid', _POST, _INT);

        if (!$displayObject->EditDefaultLayout($displayId, $defaultLayoutId))
        {
            trigger_error(__('Cannot Edit this Display'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Display Saved.'));
        $response->Respond();
    }

    /**
     * Shows the inventory XML for the display
     */
    public function MediaInventory()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        if ($displayId == 0)
            trigger_error(__('No DisplayId Given'));

        // Get the media inventory xml for this display
        $SQL = "SELECT MediaInventoryXml FROM display WHERE DisplayId = %d";
        $SQL = sprintf($SQL, $displayId);

        if (!$mediaInventoryXml = $db->GetSingleValue($SQL, 'MediaInventoryXml', _HTMLSTRING))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the Inventory for this Display'), E_USER_ERROR);
        }

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($mediaInventoryXml))
            trigger_error(__('Invalid Media Inventory'), E_USER_ERROR);

        // Output a table
        $table = '<table><tr><th>Type</th><th>Id</th><th>Complete</th><th>Last Checked</th><th>MD5</th></tr>';

        $xpath = new DOMXPath($document);
	$fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node)
        {
            $type = $node->getAttribute('type');
            $id = $node->getAttribute('id');
            $complete = $node->getAttribute('complete');
            $lastChecked = $node->getAttribute('lastChecked');
            $md5 = $node->getAttribute('md5');

            if ($complete == 0)
                $complete = __('No');
            else
                $complete = __('Yes');

            $table .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>', $type, $id, $complete, $lastChecked, $md5);
        }

        $table .= '</table>';

        $response->SetFormRequestResponse($table, __('Media Inventory'), '550px', '350px');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * Form for wake on Lan
     */
    public function WakeOnLanForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        $msg = __('Are you sure you want to send a Wake On Lan message to this display?');

        // Get the MAC Address
        $macAddress = $db->GetSingleValue(sprintf("SELECT MacAddress FROM `display` WHERE DisplayID = %d", $displayId), 'MacAddress', _STRING);

        if (!$macAddress || $macAddress == '')
                trigger_error(__('This display has no mac address recorded against it yet. Make sure the display is running.'), E_USER_ERROR);

        $form = <<<END
            <form id="WakeOnLanForm" class="XiboForm" method="post" action="index.php?p=display&q=WakeOnLan">
                <input type="hidden" name="DisplayId" value="$displayId">
                <inpput type="hidden" name="MacAddress" value="$macAddress">
                <table>
                    <tr>
                        <td>$msg</td>
                    </tr>
                </table>
            </form>
END;

        $response->SetFormRequestResponse($form, __('Wake On Lan'), '300px', '150px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Send'), '$("#WakeOnLanForm").submit()');
        $response->Respond();
    }

    /**
     * Wake on LAN
     */
    public function WakeOnLan()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $displayObject 	= new Display($db);

        $displayId = Kit::GetParam('DisplayId', _POST, _INT);

        if (!$displayObject->WakeOnLan($displayId))
        {
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Wake on Lan command sent.'));
        $response->Respond();
    }
}
?>
