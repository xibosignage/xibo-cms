<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
	private $ajax;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		include_once('lib/data/display.data.class.php');
		
		$this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
		$this->ajax		= Kit::GetParam('ajax', _REQUEST, _WORD, 'false');
		$displayid 		= Kit::GetParam('displayid', _REQUEST, _INT, 0);

		if ($this->sub_page == 'view') 
		{
			// validate displays so we get a realistic view of the table
			$this->validateDisplays();
		}
				
		if(isset($_GET['modify']) || $displayid != 0) 
		{
			$this->sub_page = 'edit';
			
			if (!$this->has_permissions && $this->ajax == 'true') 
			{
				trigger_error(__("You do not have permissions to edit this display"), E_USER_ERROR);
			}

			$SQL = <<<SQL
		SELECT  display.displayid,
				display.display,
				display.defaultlayoutid,
				display.license,
				display.licensed,
				display.inc_schedule,
				display.isAuditing
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
		
		$displayObject 	= new Display($db);
		
		if (!$displayObject->Edit($displayid, $display, $auditing, $layoutid, $licensed, $inc_schedule))
		{
			trigger_error(__('Cannot Edit this Display'), E_USER_ERROR);
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
		
		// Help UI
		$nameHelp		= $helpManager->HelpIcon(__("The Name of the Display - (1 - 50 characters)."), true);
		$defaultHelp	= $helpManager->HelpIcon(__("The Default Layout to Display where there is no other content."), true);
		$interleveHelp	= $helpManager->HelpIcon(__("Whether to always put the default into the cycle."), true);
		$licenseHelp	= $helpManager->HelpIcon(__("Control the licensing on this display."), true);
		$auditHelp		= $helpManager->HelpIcon(__("Collect auditing from this client. Should only be used if there is a problem with the display."), true);
		
		$layout_list = dropdownlist("SELECT layoutid, layout FROM layout WHERE retired = 0 ORDER by layout", "defaultlayoutid", $layoutid);
		$inc_schedule_list = listcontent("1|Yes,0|No","inc_schedule",$inc_schedule);
		$auditing_list = listcontent("1|Yes,0|No","auditing",$auditing);

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
		$msgSave	= __('Save');
		$msgCancel	= __('Cancel');
		
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=display&q=modify&id=$displayid">
			<input type="hidden" name="displayid" value="$displayid">
			<table>
				<tr>
					<td>$msgDisplay<span class="required">*</span></td>
					<td>$nameHelp <input name="display" type="text" value="$display"></td>
					<td>$msgDefault<span class="required">*</span></td>
					<td>$defaultHelp $layout_list</td>
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
					<td>$msgLicense</td>
					<td>$licenseHelp <input type="text" readonly value="$license"></td>
					$license_list
				</tr>
				<tr>
					<td></td>
					<td>
						<input type='submit' value="$msgSave" / >
						<input id="btnCancel" type="button" title="No / Cancel" onclick="$('#div_dialog').dialog('close');return false; " value="$msgCancel" />	
					</td>
				</tr>
			</table>
		</form>		
END;
		
		$response->SetFormRequestResponse($form, __('Edit a Display'), '650px', '250px');
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
					
		//display the display table
		$SQL = <<<SQL
		SELECT  display.displayid, 
				display.display, 
				layout.layout, 
				CASE WHEN display.loggedin = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS loggedin, 
				display.lastaccessed, 
				CASE WHEN display.inc_schedule = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS loggedin,
				CASE WHEN display.licensed = 1 THEN '<img src="img/act.gif">' ELSE '<img src="img/disact.gif">' END AS licensed
		FROM display
		LEFT OUTER JOIN layout ON layout.layoutid = display.defaultlayoutid
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
		$msgSave	= __('Save');
		$msgCancel	= __('Cancel');
		$msgAction	= __('Action');
		$msgLastA	= __('Last Accessed');
		$msgLogIn	= __('Logged In');
		$msgEdit	= __('Edit');
		$msgDelete	= __('Delete');

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
				<th>$msgLogIn</th>
				<th>$msgLastA</th>
				<th>$msgAction</th>
			</tr>
			</thead>
			<tbody>
END;

		while($aRow = $db->get_row($results)) 
		{
			$displayid 		= $aRow[0];
			$display 		= $aRow[1];
			$defaultlayoutid = $aRow[2];
			$loggedin 		= $aRow[3];
			$lastaccessed 	= $aRow[4];
			$inc_schedule 	= $aRow[5];
			$licensed 		= $aRow[6];
			
			$output .= <<<END
			
			<tr>
			<td>$displayid</td>
			<td>$licensed</td>
			<td>$display</td>
			<td>$defaultlayoutid</td>
			<td>$inc_schedule</td>
			<td>$loggedin</td>
			<td>$lastaccessed</td>
			<td>
				<button class='XiboFormButton' href='index.php?p=display&q=displayForm&displayid=$displayid'><span>$msgEdit</span></button>
				<button class='XiboFormButton' href='index.php?p=display&q=DeleteForm&displayid=$displayid'><span>$msgDelete</span></button>
			</td>
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
		
		// timeout after 10 minutes
		$timeout = date("Y-m-d H:i:s", time() - (10 * 60) );

        $SQL  = "";
        $SQL .= "SELECT displayid FROM display WHERE loggedin = 1 ";
        $SQL .= "AND lastaccessed < '$timeout' ";

        $result = $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);

        while($row = $db->get_row($result)) 
		{
            $displayid = $row[0];

            $SQL = "UPDATE display SET loggedin = 0 WHERE displayid = " . $displayid;
            $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);
        }
    }
	
	function DeleteForm()
	{
		$db 		=& $this->db;
		$response 	= new ResponseManager();
		$displayid 	= Kit::GetParam('displayid', _REQUEST, _INT);
		
		// Output the delete form
		$msgInfo	= __('You will only be able to delete this display if there is no associated information contained in Xibo.');
		$msgWarn	= __('Are you sure you want to delete this display?');
		$msgYes		= __('Yes');
		$msgNo		= __('No');
		
		$form = <<<END
		<form class="XiboForm" method="post" action="index.php?p=display&q=Delete">
			<input type="hidden" name="displayid" value="$displayid">
			<p>$msgInfo<br />
			<p>$msgWarn</p>
			<input type="submit" value="$msgYes">
			<input type="submit" value="$msgNo" onclick="$('#div_dialog').dialog('close');return false; ">
		</form>
END;
		
		$response->SetFormRequestResponse($form, __('Delete this Display?'), '350px', '180px');
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
}
?>
