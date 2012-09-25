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
 
class layoutDAO 
{
	private $db;
	private $user;
    private $auth;
	private $has_permissions = true;
	
	private $sub_page = "";
	
	private $layoutid;
	private $layout;
	private $retired;
	private $description;
	private $tags;
	
	private $xml;
	
	/**
	 * Layout Page Logic
	 * @return 
	 * @param $db Object
	 */
	function __construct(database $db, user $user)
	{
            $this->db 	=& $db;
            $this->user =& $user;
		
            $this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
            $this->layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);

            // Include the layout data class
            include_once("lib/data/layout.data.class.php");

            //if we have modify selected then we need to get some info
            if ($this->layoutid != '')
            {
                // get the permissions
                Debug::LogEntry($db, 'audit', 'Loading permissions for layoutid ' . $this->layoutid);

                $this->auth = $user->LayoutAuth($this->layoutid, true);

                if (!$this->auth->edit)
                    trigger_error(__("You do not have permissions to edit this layout"), E_USER_ERROR);

                $this->sub_page = "edit";

                $sql  = " SELECT layout, description, userid, retired, tags, xml FROM layout ";
                $sql .= sprintf(" WHERE layoutID = %d ", $this->layoutid);

                if(!$results = $db->query($sql))
                {
                        trigger_error($db->error());
                        trigger_error(__("Cannot retrieve the Information relating to this layout. The layout may be corrupt."), E_USER_ERROR);
                }

                if ($db->num_rows($results) == 0)
                    $this->has_permissions = false;

                while($aRow = $db->get_row($results))
                {
                    $this->layout = Kit::ValidateParam($aRow[0], _STRING);
                    $this->description 	= Kit::ValidateParam($aRow[1], _STRING);
                    $this->retired = Kit::ValidateParam($aRow[3], _INT);
                    $this->tags = Kit::ValidateParam($aRow[4], _STRING);
                    $this->xml = $aRow[5];
                }
            }
	}

	function on_page_load() 
	{
		return "";
	}

	function echo_page_heading() 
	{
		echo __("Layouts");
		return true;
	}
	
	function displayPage() 
	{
		$db =& $this->db;
		
		switch ($this->sub_page) 
		{	
			case 'view':
				require("template/pages/layout_view.php");
				break;
				
			case 'edit':
				require("template/pages/layout_edit.php");
				break;
				
			default:
				break;
		}
		
		return false;
	}
	
	function LayoutFilter() 
	{
		$db 	=& $this->db;
		
		$layout = ""; //3
		if (isset($_SESSION['layout']['filter_layout'])) $layout = $_SESSION['layout']['filter_layout'];
		
		//retired list
		$retired = "0";
		if(isset($_SESSION['layout']['filter_retired'])) $retired = $_SESSION['layout']['retired'];
		$retired_list = listcontent("all|All,1|Yes,0|No","filter_retired",$retired);
		
		//owner list
		$filter_userid = "";
		if(isset($_SESSION['layout']['filter_userid'])) $filter_userid = $_SESSION['layout']['filter_userid'];
		$user_list = listcontent("all|All,".userlist("SELECT DISTINCT userid FROM layout"),"filter_userid", $filter_userid);
		
		//tags list
		$filter_tags = "";
		if(isset($_SESSION['layout']['filter_tags'])) $filter_tags = $_SESSION['layout']['filter_tags'];

		$msgName	= __('Name');
		$msgOwner	= __('Owner');
		$msgTags	= __('Tags');
		$msgRetired	= __('Retired');
                $msgKeepFilterOpen = __('Keep filter open');
                $filterPinned = (Kit::IsFilterPinned('layout', 'LayoutFilter')) ? 'checked' : '';
                $filterId = uniqid('filter');

		$filterForm = <<<END
		<div class="FilterDiv" id="LayoutFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="layout">
				<input type="hidden" name="q" value="LayoutGrid">
		
			<table class="layout_filterform">
				<tr>
					<td>$msgName</td>
					<td><input type="text" name="filter_layout"></td>
					<td>$msgOwner</td>
					<td>$user_list</td>
                                        <td><label for="XiboFilterPinned$filterId">$msgKeepFilterOpen</label></td>
                                        <td><input type="checkbox" id="XiboFilterPinned$filterId" name="XiboFilterPinned" class="XiboFilterPinned" $filterPinned /></td>
				</tr>
				<tr>
					<td>$msgTags</td>
					<td><input type="text" name="filter_tags" value="$filter_tags" /></td>
					<td>$msgRetired</td>
					<td>$retired_list</td>
				</tr>
			</table>
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
	 * Adds a layout record to the db
	 * @return 
	 */
	function add() 
	{
            $db             =& $this->db;
            $response       = new ResponseManager();

            $layout         = Kit::GetParam('layout', _POST, _STRING);
            $description    = Kit::GetParam('description', _POST, _STRING);
            $tags           = Kit::GetParam('tags', _POST, _STRING);
            $templateId     = Kit::GetParam('templateid', _POST, _INT, 0);
            $userid         = Kit::GetParam('userid', _SESSION, _INT);

            // Add this layout
            $layoutObject = new Layout($db);

            if(!$id = $layoutObject->Add($layout, $description, $tags, $userid, $templateId))
                trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

            // Successful layout creation
            $response->SetFormSubmitResponse(__('Layout Details Changed.'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $id));
            $response->Respond();
	}

	/**
	 * Modifies a layout record
	 *
	 * @param int $id
	 */
	function modify ()
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$layout 		= Kit::GetParam('layout', _POST, _STRING);
		$description 	= Kit::GetParam('description', _POST, _STRING);
		$tags		 	= Kit::GetParam('tags', _POST, _STRING);
		$retired 		= Kit::GetParam('retired', _POST, _INT, 0);
		
		$userid 		= Kit::GetParam('userid', _SESSION, _INT);
		$currentdate 	= date("Y-m-d H:i:s");

		//validation
		if (strlen($layout) > 50 || strlen($layout) < 1) 
		{
			$response->SetError(__("Layout Name must be between 1 and 50 characters"));
			$response->Respond();
		}
		
		if (strlen($description) > 254) 
		{
			$response->SetError(__("Description can not be longer than 254 characters"));
			$response->Respond();
		}
		
		if (strlen($tags) > 254) 
		{
			$response->SetError(__("Tags can not be longer than 254 characters"));
			$response->Respond();
		}
		
		$check = sprintf("SELECT layout FROM layout WHERE layout = '%s' AND userID = %d AND layoutid <> %d ", $db->escape_string($layout), $userid, $this->layoutid);
		$result = $db->query($check) or trigger_error($db->error());
		
		//Layouts with the same name?
		if($db->num_rows($result) != 0) 
		{
			$response->SetError(sprintf(__("You already own a layout called '%s'. Please choose another."), $layout));
			$response->Respond();
		}
		//end validation

		$SQL = <<<END

		UPDATE layout SET
			layout = '%s',
			description = '%s',
			modifiedDT = '%s',
			retired = %d,
			tags = '%s'
		
		WHERE layoutID = %s;		
END;

		$SQL = sprintf($SQL, 
						$db->escape_string($layout),
						$db->escape_string($description), 
						$db->escape_string($currentdate), $retired, 
						$db->escape_string($tags), $this->layoutid);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if(!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$response->SetError(sprintf(__("Unknown error editing %s"), $layout));
			$response->Respond();
		}
		
		// Create an array out of the tags
		$tagsArray = explode(' ', $tags);
		
		// Add the tags XML to the layout
		$layoutObject = new Layout($db);
		
		if (!$layoutObject->EditTags($this->layoutid, $tagsArray))
		{
			//there was an ERROR
			trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);
		}

                // Maintain the name on the campaign
                Kit::ClassLoader('campaign');
                $campaign = new Campaign($db);
                $campaignId = $campaign->GetCampaignId($this->layoutid);
                $campaign->Edit($campaignId, $layout);

                // Notify (dont error)
                Kit::ClassLoader('display');
                $displayObject = new Display($db);
                $displayObject->NotifyDisplays($this->layoutid);


		$response->SetFormSubmitResponse(__('Layout Details Changed.'));
		$response->Respond();
	}
	
	function delete_form() 
	{
            $db 		=& $this->db;
            $response 	= new ResponseManager();
            $helpManager = new HelpManager($db, $this->user);

            //expect the $layoutid to be set
            $layoutid = $this->layoutid;

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);
		
		//Are we going to be able to delete this?
                Kit::ClassLoader('campaign');
                $campaign = new Campaign($db);
                $campaignId = $campaign->GetCampaignId($layoutid);

		// - Has it been scheduled
		$SQL = sprintf("SELECT CampaignID FROM schedule WHERE CampaignID = %d", $campaignId);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not get layout information"), E_USER_ERROR);
		}
		
		if ($db->num_rows($results) == 0) 
		{
			//we can delete
			$msgWarn	= __('Are you sure you want to delete this layout? All media will be unassigned. Any layout specific media such as text/rss will be lost.');
			
			$form = <<<END
			<form id="LayoutDeleteForm" class="XiboForm" method="post" action="index.php?p=layout&q=delete">
				<input type="hidden" name="layoutid" value="$layoutid">
				<p>$msgWarn</p>
			</form>
END;
		}
		else 
		{
			//we can only retire
			$msgWarn	= __('Sorry, unable to delete this layout.');
			$msgWarn2	= __('Retire this layout instead?');
			
			$form = <<<END
			<form id="LayoutDeleteForm" class="XiboForm" method="post" action="index.php?p=layout&q=retire">
				<input type="hidden" name="layoutid" value="$layoutid">
				<p>$msgWarn</p>
				<p>$msgWarn2</p>
			</form>
END;
		}
		
        $response->SetFormRequestResponse($form, __('Delete this layout?'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Layout', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutDeleteForm").submit()');
        $response->Respond();
    }

	/**
	 * Deletes a layout record from the DB
	 */
	function delete() 
	{
            $db 	=& $this->db;
            $response	= new ResponseManager();
            $layoutId 	= Kit::GetParam('layoutid', _POST, _INT, 0);

            if ($layoutId == 0)
                trigger_error(__('No Layout selected'), E_USER_ERROR);

            $layoutObject = new Layout($db);

            if (!$layoutObject->Delete($layoutId))
                trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('The Layout has been Deleted'));
            $response->Respond();
	}
	
	/**
	 * Retire a Layout
	 * @return 
	 */
	function retire() 
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();
		$layoutid 		= Kit::GetParam('layoutid', _POST, _INT, 0);
		
		if ($layoutid == 0) 
		{
			$response->SetError(__("No Layout selected"));
			$response->Respond();
		}
		
		$SQL = sprintf("UPDATE layout SET retired = 1 WHERE layoutID = %d", $layoutid);
	
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			
			$response->SetError(__("Failed to retire, Unknown Error."));
			$response->Respond();
		}

		$response->SetFormSubmitResponse(__('Layout Retired.'));
		$response->Respond();
	}
	
	/**
	 * Shows the Layout Grid
	 * @return 
	 */
	function LayoutGrid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$name = Kit::GetParam('filter_layout', _POST, _STRING, '');
		setSession('layout', 'filter_layout', $name);
		
		// User ID
		$filter_userid = Kit::GetParam('filter_userid', _POST, _STRING, 'all');
		setSession('layout', 'filter_userid', $filter_userid);
		
		// Show retired
		$filter_retired = $_REQUEST['filter_retired'];
		setSession('layout', 'filter_userid', $filter_userid);
		
		// Tags list
		$filter_tags = Kit::GetParam("filter_tags", _POST, _STRING);
		setSession('layout', 'filter_tags', $filter_tags);
                
                setSession('layout', 'LayoutFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		
		$SQL = "";
		$SQL .= "SELECT  layout.layoutID, ";
		$SQL .= "        layout.layout, ";
		$SQL .= "        layout.description, ";
		$SQL .= "        layout.userID, ";
		$SQL .= "        campaign.CampaignID ";
		$SQL .= "  FROM layout ";
                $SQL .= "  INNER JOIN `lkcampaignlayout` ";
                $SQL .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
                $SQL .= "   INNER JOIN `campaign` ";
                $SQL .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
                $SQL .= "       AND campaign.IsLayoutSpecific = 1";
		$SQL .= " WHERE 1= 1";
		//name filter
		if ($name != "") 
		{
			$SQL.= " AND  (layout.layout LIKE '%" . sprintf('%s', $name) . "%') ";
		}
		//owner filter
		if ($filter_userid != "all") 
		{
			$SQL .= sprintf(" AND layout.userid = %d ", $filter_userid);
		}
		//retired options
		if ($filter_retired == "1") 
		{
			$SQL .= " AND layout.retired = 1 ";
		}
		elseif ($filter_retired == "0") 
		{
			$SQL .= " AND layout.retired = 0 ";			
		}
		if ($filter_tags != "")
		{
			$SQL .= " AND layout.tags LIKE '%" . sprintf('%s', $filter_tags) . "%' ";
		}

		if(!$results = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__("An Unknown error occured when listing the layouts."), E_USER_ERROR);			
		}

                $msgCopy = __('Copy');
                $msgPermissions = __('Permissions');
                $msgDelete = __('Delete');

		$output = <<<END
		<div class="info_table">
		<table style="width:100%">
			<thead>
				<tr>
				<th>Name</th>
				<th>Description</th>
				<th>Owner</th>
				<th>$msgPermissions</th>
				<th>Action</th>	
				</tr>
			</thead>
			<tbody>
END;

		while($aRow = $db->get_row($results)) 
		{
			//get the query results
			$layout 		= Kit::ValidateParam($aRow[1], _STRING);
			$description 	= Kit::ValidateParam($aRow[2], _STRING);
			$layoutid 		= Kit::ValidateParam($aRow[0], _INT);
			$userid 		= Kit::ValidateParam($aRow[3], _INT);
                        $campaignId = Kit::ValidateParam($aRow[4], _INT);
			
			//get the username from the userID using the user module
			$username 		= $user->getNameFromID($userid);

        $group = $this->GroupsForLayout($layoutid);
			
        // Permissions
        $auth = $this->user->LayoutAuth($layoutid, true);
			
			if ($auth->view)
			{
				if ($auth->edit)
				{			
					$title = <<<END
					<tr ondblclick="return XiboFormRender('index.php?p=layout&q=displayForm&layoutid=$layoutid')">
END;
				}
				else 
				{
					$msgNoPermission = __('You do not have permission to design this layout');
					
					$title = <<<END
					<tr ondblclick="alert('$msgNoPermission')">
END;
				}
				
				$output .= <<<END
				$title
				<td>$layout</td>
				<td>$description</td>
				<td>$username</td>
				<td>$group</td>
END;

                                $output .= '<td class="nobr">';
                                $output .= '<button class="XiboFormButton" href="index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $campaignId . '"><span>' . __('Schedule Now') . '</span></button>';

				if ($auth->edit)
				{
                                    $output .= '<button href="index.php?p=layout&modify=true&layoutid=' . $layoutid . '" onclick="window.location = $(this).attr(\'href\')"><span>Design</span></button>';
                                    $output .= '<button class="XiboFormButton" href="index.php?p=layout&q=displayForm&modify=true&layoutid=' . $layoutid . '"><span>Edit</span></button>';
                                    $output .= '<button class="XiboFormButton" href="index.php?p=layout&q=CopyForm&layoutid=' . $layoutid . '&oldlayout=' . $layout . '"><span>' . $msgCopy . '</span></button>';
                                    if ($auth->del)
                                        $output .= '<button class="XiboFormButton" href="index.php?p=layout&q=delete_form&layoutid=' . $layoutid . '"><span>' . $msgDelete . '</span></button>';
                                        
                                    if ($auth->modifyPermissions)
                                        $output .= '<button class="XiboFormButton" href="index.php?p=campaign&q=PermissionsForm&CampaignID=' . $campaignId . '"><span>' . $msgPermissions . '</span></button>';

				}
				
                                $output .= '</td>';
				$output .= '</tr>';
			}
		}
		$output .= '</tbody></table></div>';
		
		$response->SetGridResponse($output);
		$response->Respond();
	}

	function displayForm () 
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();
		
		$helpManager            = new HelpManager($db, $user);

		$action 		= "index.php?p=layout&q=add";
		
		$layoutid 		= $this->layoutid; 
		$layout 		= $this->layout;
		$description            = $this->description;
		$retired		= $this->retired;
		$tags			= $this->tags;
		
		// Help icons for the form
		$nameHelp	= $helpManager->HelpIcon(__("The Name of the Layout - (1 - 50 characters)"), true);
		$descHelp	= $helpManager->HelpIcon(__("An optional description of the Layout. (1 - 250 characters)"), true);
		$tagsHelp	= $helpManager->HelpIcon(__("Tags for this layout - used when searching for it. Space delimited. (1 - 250 characters)"), true);
		$retireHelp	= $helpManager->HelpIcon(__("Retire this layout or not? It will no longer be visible in lists"), true);
		$templateHelp	= $helpManager->HelpIcon(__("Template to create this layout with."), true);
		
		//init the retired option
		$retired_option 	= '';
		$template_option 	= '';
		
		if ($this->layoutid != '')
		{ 
                        // assume an edit
			$action = "index.php?p=layout&q=modify";
			
			// build the retired option
			$retired_list = listcontent("1|Yes,0|No","retired",$retired);
			$retired_option = <<<END
			<tr>
				<td><label for='retired'>Retired<span class="required">*</span></label></td>
				<td>$retireHelp $retired_list</td>
			</tr>
END;
		}
		else
		{
                    $templates = $user->TemplateList();
                    array_unshift($templates, array('templateid' => '0', 'template' => 'None'));
                    
                    $templateList = Kit::SelectList('templateid', $templates, 'templateid', 'template');
                        
                    $template_option = <<<END
                    <tr>
                            <td><label for='templateid'>Template<span class="required">*</span></label></td>
                            <td>$templateHelp $templateList</td>
                    </tr>
END;
		}
		
		$msgName	= __('Name');
		$msgName2	= __('The Name of the Layout - (1 - 50 characters)');
		$msgDesc	= __('Description');
		$msgDesc2	= __('An optional description of the Layout. (1 - 250 characters)');
		$msgTags	= __('Tags');
		$msgTags2	= __('Tags for this layout - used when searching for it. Space delimited. (1 - 250 characters)');
		
                $form = <<<END
		<form id="LayoutForm" class="XiboForm" method="post" action="$action">
			<input type="hidden" name="layoutid" value="$this->layoutid">
		<table>
			<tr>
				<td><label for="layout" accesskey="n" title="$msgName2">$msgName<span class="required">*</span></label></td>
				<td>$nameHelp <input name="layout" type="text" id="layout" value="$layout" tabindex="1" /></td>
			</tr>
			<tr>
				<td><label for="description" accesskey="d" title="$msgDesc2">$msgDesc</label></td>
				<td>$descHelp <input name="description" type="text" id="description" value="$description" tabindex="2" /></td>
			</tr>
			<tr>
				<td><label for="tags" accesskey="d" title="$msgTags2">$msgTags</label></td>
				<td>$tagsHelp <input name="tags" type="text" id="tags" value="$tags" tabindex="3" /></td>
			</tr>
			$retired_option
			$template_option
		</table>
		</form>
END;

		$response->SetFormRequestResponse($form, __('Add/Edit a Layout.'), '350px', '275px');
                $response->AddButton(__('Help'), 'XiboHelpRender("' . (($this->layoutid != '') ? $helpManager->Link('Layout', 'Edit') : $helpManager->Link('Layout', 'Add')) . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#LayoutForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Generates a form for the background edit
	 * @return 
	 */
	function BackgroundForm() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;

		$helpManager	= new HelpManager($db, $user);
		$response	= new ResponseManager();


		//load the XML into a SimpleXML OBJECT
		$xml                = simplexml_load_string($this->xml);

		$backgroundImage    = (string) $xml['background'];
		$backgroundColor    = (string) $xml['bgcolor'];
		$width              = (string) $xml['width'];
		$height             = (string) $xml['height'];
                $bgImageId          = 0;

                // Do we need to override the background with one passed in?
                $bgOveride          = Kit::GetParam('backgroundOveride', _GET, _STRING);

                if ($bgOveride != '')
                    $backgroundImage = $bgOveride;
		
		// Manipulate the images slightly
		if ($backgroundImage != "")
		{
                    // Get the ID for the background image
                    $bgImageInfo = explode('.', $backgroundImage);
                    $bgImageId = $bgImageInfo[0];

                    $thumbBgImage = "index.php?p=module&q=GetImage&id=$bgImageId&width=80&height=80&dynamic";
		}
		else
		{
                    $thumbBgImage = "img/forms/filenotfound.png";
		}

		// A list of available backgrounds
                $backgrounds = $user->MediaList('image');
                array_unshift($backgrounds, array('mediaid' => '0', 'media' => 'None'));
                $backgroundList = Kit::SelectList('bg_image', $backgrounds, 'mediaid', 'media', $bgImageId, "onchange=\"background_button_callback()\"");
		
		//A list of web safe colors
		//Strip the # from the currently set color
		$backgroundColor = trim($backgroundColor,'#');
		
		$webSafeColors = gwsc("bg_color", $backgroundColor);
		
		//Get the ID of the current resolution
		$SQL = sprintf("SELECT resolutionID FROM resolution WHERE width = %d AND height = %d", $width, $height);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Unable to get the Resolution information"), E_USER_ERROR);
		}
		
		$row 		= $db->get_row($results) ;
		$resolutionid 	=  Kit::ValidateParam($row[0], _INT);
		
		//Make up the list
		$resolution_list = dropdownlist("SELECT resolutionID, resolution FROM resolution ORDER BY width", "resolutionid", $resolutionid);
		
		// Help text for fields
		$resolutionHelp = $helpManager->HelpIcon(__("Pick the resolution"), true);
		$bgImageHelp	= $helpManager->HelpIcon(__("Select the background image from the library."), true);
		$bgColorHelp	= $helpManager->HelpIcon(__("Use the color picker to select the background color."), true);
		
		$helpButton 	= $helpManager->HelpButton("content/layout/layouteditor", true);
		
		$msgBg				= __('Background Color');
		$msgBgTitle			= __('Use the color picker to select the background color');
		$msgBgImage			= __('Background Image');
		$msgBgImageTitle	= __('Select the background image from the library');
		$msgRes				= __('Resolution');
		$msgResTitle		= __('Pick the resolution');
		
		// Begin the form output
		$form = <<<FORM
		<form id="LayoutBackgroundForm" class="XiboForm" method="post" action="index.php?p=layout&q=EditBackground">
			<input type="hidden" id="layoutid" name="layoutid" value="$this->layoutid">
			<table>
				<tr>
					<td><label for="bg_color" title="$msgBgTitle">$msgBg</label></td>
					<td>$bgColorHelp $webSafeColors</td>
				</tr>
				<tr>
					<td><label for="bg_image" title="$msgBgImageTitle">$msgBgImage</label></td>
					<td>$bgImageHelp $backgroundList</td>
					<td rowspan="3"><img id="bg_image_image" src="$thumbBgImage" alt="Thumb" />
				</tr>
				<tr>
					<td><label for="resolutionid" title="$msgResTitle">$msgRes<span class="required">*</span></label></td>
					<td>$resolutionHelp $resolution_list</td>
				</tr>
				<tr>
					<td></td>
				</tr>
			</table>
		</form>
FORM;
		
		$response->SetFormRequestResponse($form, __('Change the Background Properties'), '550px', '240px');
                $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Layout', 'Background') . '")');
                $response->AddButton(__('Add Image'), 'XiboFormRender("index.php?p=module&q=Exec&mod=image&method=AddForm&backgroundImage=true&layoutid=' . $this->layoutid . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#LayoutBackgroundForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Edits the background of the layout
	 * @return 
	 */
	function EditBackground()
	{
		$db 			=& $this->db;
		$user 			=& $this->user;
		$response		= new ResponseManager();

		$layoutid 		= Kit::GetParam('layoutid', _POST, _INT);
		$bg_color 		= '#'.Kit::GetParam('bg_color', _POST, _STRING);
		$mediaID 		= Kit::GetParam('bg_image', _POST, _INT);
		$resolutionid		= Kit::GetParam('resolutionid', _POST, _INT);

                // Get the file URI
                $SQL = sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $mediaID);

                // Allow for the 0 media idea (no background image)
                if ($mediaID == 0)
                {
                    $bg_image = '';
                }
                else
                {
                    // Look up the bg image from the media id given
                    if (!$bg_image = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
                        trigger_error('No media found for that media ID', E_USER_ERROR);
                }

		// Look up the width and the height
		$SQL = sprintf("SELECT width, height FROM resolution WHERE resolutionID = %d ", $resolutionid);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$response->SetError(__("Unable to get the Resolution information"));
			$response->Respond();
		}
		
		$row 	= $db->get_row($results) ;
		$width  =  Kit::ValidateParam($row[0], _INT);
		$height =  Kit::ValidateParam($row[1], _INT);
		
		include_once("lib/pages/region.class.php");
		
		$region = new region($db, $user);
		
		if (!$region->EditBackground($layoutid, $bg_color, $bg_image, $width, $height))
		{
			//there was an ERROR
			$response->SetError($region->errorMsg);
			$response->Respond();
		}
		
		// Update the layout record with the new background
		$SQL = sprintf("UPDATE layout SET background = '%s' WHERE layoutid = %d ", $bg_image, $layoutid);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$response->SetError(__("Unable to update background information"));
			$response->Respond();
		}
		
		$response->SetFormSubmitResponse(__('Layout Details Changed.'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $this->layoutid));
		$response->Respond();
	}
	
	/**
	 * Adds a new region for a layout
	 * @return 
	 */
	function AddRegion()
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		//ajax request handler
		$response = new ResponseManager();
		
		$layoutid = Kit::GetParam('layoutid', _REQUEST, _INT, 0);
		
		if ($layoutid == 0)
		{
			trigger_error(__("No layout information available, please refresh the page."), E_USER_ERROR);
		}
		
		include_once("lib/pages/region.class.php");
		
		$region = new region($db, $user);
		
		if (!$region->AddRegion($this->layoutid))
		{
			//there was an ERROR
			trigger_error($region->errorMsg, E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('Region Added.'), true, "index.php?p=layout&modify=true&layoutid=$layoutid");
		$response->Respond();
	}
	
	/**
	 * Deletes a region and all its media
	 * @return 
	 */
	function DeleteRegion()
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		$response 	= new ResponseManager();
		
		$layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT, 0);
		$regionid 	= Kit::GetParam('regionid', _REQUEST, _STRING);
		
		if ($layoutid == 0 || $regionid == '')
		{
			$response->SetError(__("No layout/region information available, please refresh the page and try again."));
			$response->Respond();
		}

        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->del)
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);

        // Remove the permissions
        Kit::ClassLoader('layoutregiongroupsecurity');
        $security = new LayoutRegionGroupSecurity($db);
        $security->UnlinkAll($layoutid, $regionid);

        $db->query(sprintf("DELETE FROM lklayoutmediagroup WHERE layoutid = %d AND RegionID = '%s'", $this->layoutid, $regionid));

            if (!$region->DeleteRegion($this->layoutid, $regionid))
            {
                    //there was an ERROR
                    $response->SetError($region->errorMsg);
                    $response->Respond();
            }

            $response->SetFormSubmitResponse(__('Region Deleted.'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $this->layoutid));
            $response->Respond();
	}

        /*
         * Form called by the layout which shows a manual positioning/sizing form.
         */
        function ManualRegionPositionForm()
        {
            $db 	=& $this->db;
            $user 	=& $this->user;
            $response = new ResponseManager();

            $regionid 	= Kit::GetParam('regionid', _GET, _STRING);
            $layoutid 	= Kit::GetParam('layoutid', _GET, _INT);
            $top 	= Kit::GetParam('top', _GET, _INT);
            $left 	= Kit::GetParam('left', _GET, _INT);
            $width 	= Kit::GetParam('width', _GET, _INT);
            $height 	= Kit::GetParam('height', _GET, _INT);
            $layoutWidth = Kit::GetParam('layoutWidth', _GET, _INT);
            $layoutHeight = Kit::GetParam('layoutHeight', _GET, _INT);

        Kit::ClassLoader('region');
        $region = new region($db, $this->user);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);
        $regionName = $region->GetRegionName($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

            $form = <<<END
		<form id="RegionProperties" class="XiboForm" method="post" action="index.php?p=layout&q=ManualRegionPosition">
                    <input type="hidden" name="layoutid" value="$layoutid">
                    <input type="hidden" name="regionid" value="$regionid">
                    <input id="layoutWidth" type="hidden" name="layoutWidth" value="$layoutWidth">
                    <input id="layoutHeight" type="hidden" name="layoutHeight" value="$layoutHeight">
                    <table>
			<tr>
                            <td><label for="name" title="Name of the Region">Name</label></td>
                            <td><input name="name" type="text" id="name" value="$regionName" tabindex="1" /></td>
			</tr>
			<tr>
                            <td><label for="top" title="Offset from the Top Corner">Top Offset</label></td>
                            <td><input name="top" type="text" id="top" value="$top" tabindex="2" /></td>
			</tr>
			<tr>
                            <td><label for="left" title="Offset from the Left Corner">Left Offset</label></td>
                            <td><input name="left" type="text" id="left" value="$left" tabindex="3" /></td>
			</tr>
			<tr>
                            <td><label for="width" title="Width of the Region">Width</label></td>
                            <td><input name="width" type="text" id="width" value="$width" tabindex="4" /></td>
			</tr>
			<tr>
                            <td><label for="height" title="Height of the Region">Height</label></td>
                            <td><input name="height" type="text" id="height" value="$height" tabindex="5" /></td>
			</tr>
                        <tr>
                            <td></td>
                            <td>
                                <input id="btnFullScreen" type='button' value="Full Screen" / >
                            </td>
                        </tr>
                    </table>
		</form>
END;

            $response->SetFormRequestResponse($form, 'Manual Region Positioning', '350px', '275px', 'manualPositionCallback');
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');
            $response->AddButton(__('Save'), '$("#RegionProperties").submit()');
            $response->Respond();
        }

        function ManualRegionPosition()
        {
            $db 	=& $this->db;
            $user 	=& $this->user;
            $response   = new ResponseManager();

            $layoutid   = Kit::GetParam('layoutid', _POST, _INT);
            $regionid   = Kit::GetParam('regionid', _POST, _STRING);
            $regionName = Kit::GetParam('name', _POST, _STRING);
            $top        = Kit::GetParam('top', _POST, _INT);
            $left       = Kit::GetParam('left', _POST, _INT);
            $width      = Kit::GetParam('width', _POST, _INT);
            $height 	= Kit::GetParam('height', _POST, _INT);

        Kit::ClassLoader('region');
        $region = new region($db, $this->user);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

            Debug::LogEntry($db, 'audit', sprintf('Layoutid [%d] Regionid [%s]', $layoutid, $regionid), 'layout', 'ManualRegionPosition');

            // Remove the "px" from them
            $width  = str_replace('px', '', $width);
            $height = str_replace('px', '', $height);
            $top    = str_replace('px', '', $top);
            $left   = str_replace('px', '', $left);

            include_once("lib/pages/region.class.php");

            $region = new region($db, $user);

            if (!$region->EditRegion($layoutid, $regionid, $width, $height, $top, $left, $regionName))
                trigger_error($region->errorMsg, E_USER_ERROR);

            $response->SetFormSubmitResponse('Region Resized', true, "index.php?p=layout&modify=true&layoutid=$layoutid");
            $response->Respond();
        }
	
	/**
	 * Edits the region information
	 * @return 
	 */
	function RegionChange()
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		// ajax request handler
		$response = new ResponseManager();
		
		//Vars
		$regionid 	= Kit::GetParam('regionid', _REQUEST, _STRING);
		$top            = Kit::GetParam('top', _POST, _INT);
                $left           = Kit::GetParam('left', _POST, _INT);
                $width          = Kit::GetParam('width', _POST, _INT);
                $height 	= Kit::GetParam('height', _POST, _INT);

		// Remove the "px" from them
		$width 	= str_replace("px", '', $width);
		$height = str_replace("px", '', $height);
		$top 	= str_replace("px", '', $top);
		$left 	= str_replace("px", '', $left);
		
        Kit::ClassLoader('region');
        $region = new region($db, $this->user);
        $ownerId = $region->GetOwnerId($this->layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->del)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);
		
		if (!$region->EditRegion($this->layoutid, $regionid, $width, $height, $top, $left))
		{
			//there was an ERROR
			trigger_error($region->errorMsg, E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse('');
		$response->hideMessage = true;
		$response->Respond();
	}
	
    /**
     * Return the Delete Form as HTML
     * @return
     */
    public function DeleteRegionForm()
    {
        $db 		=& $this->db;
        $response	= new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);
        $layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT, 0);
        $regionid 	= Kit::GetParam('regionid', _REQUEST, _STRING);

        Kit::ClassLoader('region');
        $region = new region($db, $this->user);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->del)
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);
		
        // Translate messages
        $msgDelete		= __('Are you sure you want to remove this region?');
        $msgDelete2		= __('All media files will be unassigned and any context saved to the region itself (such as Text, Tickers) will be lost permanently.');
        $msgYes			= __('Yes');
        $msgNo			= __('No');

        //we can delete
        $form = <<<END
        <form id="RegionDeleteForm" class="XiboForm" method="post" action="index.php?p=layout&q=DeleteRegion">
                <input type="hidden" name="layoutid" value="$layoutid">
                <input type="hidden" name="regionid" value="$regionid">
                <p>$msgDelete $msgDelete2</p>
        </form>
END;
		
        $response->SetFormRequestResponse($form, __('Delete this region?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Region', 'Delete') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Delete'), '$("#RegionDeleteForm").submit()');
        $response->Respond();
    }
	
	function RenderDesigner() 
	{
		$db =& $this->db;
		
		// Assume we have the xml in memory already
		// Make a DOM from the XML
		$xml = new DOMDocument();
		$xml->loadXML($this->xml);
		
		// get the width and the height
		$width 	= $xml->documentElement->getAttribute('width');
		$height = $xml->documentElement->getAttribute('height');
		
		//do we have a background? Or a background color (or both)
		$bgImage = $xml->documentElement->getAttribute('background');
		$bgColor = $xml->documentElement->getAttribute('bgcolor');

		//Library location
		$libraryLocation = Config::GetSetting($db, "LIBRARY_LOCATION");
		
		//Fix up the background css
		if ($bgImage == '')
		{
                    $background_css = $bgColor;
		}
                else
		{
                    // Get the ID for the background image
                    $bgImageInfo = explode('.', $bgImage);
                    $bgImageId = $bgImageInfo[0];

                    $background_css = "url('index.php?p=module&q=GetImage&id=$bgImageId&width=$width&height=$height&dynamic&proportional=0') top center no-repeat; background-color:$bgColor";
		}
		
		$width 	= $width . "px";
		$height = $height . "px";
		
		// Get all the regions and draw them on
		$regionHtml 	= "";
		$regionNodeList = $xml->getElementsByTagName('region');

		//get the regions
		foreach ($regionNodeList as $region)
		{
			// get dimensions
                        $tipWidth       = $region->getAttribute('width');
                        $tipHeight      = $region->getAttribute('height');
                        $tipTop         = $region->getAttribute('top');
                        $tipLeft        = $region->getAttribute('left');

			$regionWidth 	= $region->getAttribute('width') . "px";
			$regionHeight 	= $region->getAttribute('height') . "px";
			$regionLeft	= $region->getAttribute('left') . "px";
			$regionTop	= $region->getAttribute('top') . "px";
			$regionid	= $region->getAttribute('id');
                        $ownerId = $region->getAttribute('userId');

                        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);

			$paddingTop	= $regionHeight / 2 - 16;
			$paddingTop	= $paddingTop . "px";

                        $regionAuthTransparency = ($regionAuth->edit) ? '' : ' regionDisabled';
                        $regionDisabledClass = ($regionAuth->edit) ? 'region' : 'regionDis';
                        $regionPreviewClass = ($regionAuth->view) ? 'regionPreview' : '';

			$regionTransparency  = '<div class="regionTransparency ' . $regionAuthTransparency . '" style="width:100%; height:100%;"></div>';
			$doubleClickLink = ($regionAuth->edit) ? "XiboFormRender($(this).attr('href'))" : '';

			$regionHtml .= "<div id='region_$regionid' regionEnabled='$regionAuth->edit' regionid='$regionid' layoutid='$this->layoutid' href='index.php?p=layout&layoutid=$this->layoutid&regionid=$regionid&q=RegionOptions' ondblclick=\"$doubleClickLink\"' class='$regionDisabledClass $regionPreviewClass' style=\"position:absolute; width:$regionWidth; height:$regionHeight; top: $regionTop; left: $regionLeft;\">
					  $regionTransparency";
                                          
                      if ($regionAuth->view)
                      {
                        $regionHtml .= "<div class='regionInfo'>
                                                $tipWidth x $tipHeight ($tipLeft,$tipTop)
                                           </div>
								<div class='preview'>
									<div class='previewContent'></div>
									<div class='previewNav'></div>
								</div>";
                      }

                 if ($regionAuth->edit)
                 {
                    $regionHtml .= '<div class="timelineLink">';
                    $regionHtml .= '    <a class="XiboFormButton" href="index.php?p=layout&q=Timeline&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Timeline') . '">' . __('Edit Timeline') . '</a>';
                    $regionHtml .= '</div>';
                 }

                      $regionHtml .= '</div>';
		}
		
		// Translate messages
		$msgTimeLine			= __('Timeline');
		$msgOptions			= __('Options');
		$msgDelete			= __('Delete');
		$msgSetAsHome		= __('Permissions');
		
		$msgAddRegion		= __('Add Region');
		$msgEditBg			= __('Edit Background');
		$msgProperties		= __('Properties');
		$msgSaveTemplate	= __('Save Template');
		
		//render the view pane
		$surface = <<<HTML
                <!--<div id="aspectRatioOption">
                    <input id="lockAspectRatio" type="checkbox" /><label for="lockAspectRatio">Lock Aspect Ratio?</label>
                </div>-->
		<div id="layout" layoutid="$this->layoutid" style="position:relative; width:$width; height:$height; border: 1px solid #000; background:$background_css;">
		$regionHtml
		</div>
                <div id="LayoutJumpList">
HTML;
                echo $surface;

                // Output the layout jump list filter form 
                $this->LayoutJumpListFilter();
                
                $surface = <<<HTML
                </div>
		<div class="contextMenu" id="regionMenu">
			<ul>
                                <li id="btnTimeline">$msgTimeLine</li>
				<li id="options">$msgOptions</li>
				<li id="deleteRegion">$msgDelete</li>
				<li id="setAsHomepage">$msgSetAsHome</li>
			</ul>
		</div>
		<div class="contextMenu" id="layoutMenu">
			<ul>
				<li id="addRegion">$msgAddRegion</li>
				<li id="editBackground">$msgEditBg</li>
				<li id="layoutProperties">$msgProperties</li>
				<li id="templateSave">$msgSaveTemplate</li>
			</ul>
		</div>
HTML;
		echo $surface;
		
		return true;
	}
	
    /**
     * Shows the Timeline for this region
     * Also shows any Add/Edit options
     * @return
     */
    function RegionOptions()
    {
        $this->Timeline();
        exit();
    }
	
    /**
     * Adds the media into the region provided
     * @return
     */
    function AddFromLibrary()
    {
        $db 		=& $this->db;
        $user 		=& $this->user;
        $response 	= new ResponseManager();

        $layoutId = Kit::GetParam('layoutid', _GET, _INT);
        $regionId = Kit::GetParam('regionid', _POST, _STRING);
        $mediaList = Kit::GetParam('MediaID', _POST, _ARRAY, array());

        // Make sure we have permission to edit this region
        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Check that some media assignments have been made
        if (count($mediaList) == 0)
            trigger_error(__('No media to assign'), E_USER_ERROR);

        // Loop through all the media
        foreach ($mediaList as $mediaId)
        {
            $mediaId = Kit::ValidateParam($mediaId, _INT);

            // Check we have permissions to use this media (we will use this to copy the media later)
            $mediaAuth = $this->user->MediaAuth($mediaId, true);

            if (!$mediaAuth->view)
            {
                $response->SetError(__('You have selected media that you no longer have permission to use. Please reload Library form.'));
                $response->keepOpen = true;
                return $response;
            }

            // Get the type from this media
            $SQL = sprintf("SELECT type FROM media WHERE mediaID = %d", $mediaId);

            if (!$mod = $db->GetSingleValue($SQL, 'type', _STRING))
            {
                trigger_error($db->error());
                $response->SetError(__('Error getting type from a media item.'));
                $response->keepOpen = false;
                return $response;
            }

            require_once("modules/$mod.module.php");

            // Create the media object without any region and layout information
            $this->module = new $mod($db, $user, $mediaId);

            if ($this->module->SetRegionInformation($layoutId, $regionId))
                $this->module->UpdateRegion();
            else
            {
                $response->SetError(__('Cannot set region information.'));
                $response->keepOpen = true;
                return $response;
            }

            // Need to copy over the permissions from this media item & also the delete permission
            Kit::ClassLoader('layoutmediagroupsecurity');
            $security = new LayoutMediaGroupSecurity($db);
            $security->Link($layoutId, $regionId, $mediaId, $this->user->getGroupFromID($this->user->userid, true), $mediaAuth->view, $mediaAuth->edit, 1);
        }

        // We want to load a new form
        $response->SetFormSubmitResponse(sprintf(__('%d Media Items Assigned'), count($mediaList)));
        $response->loadForm = true;
        $response->loadFormUri = "index.php?p=layout&layoutid=$layoutId&regionid=$regionId&q=RegionOptions";
        $response->Respond();
    }

	/**
	 * Properties Edit
	 * @return 
	 */
	function EditPropertiesHref() 
	{		
		//output the button
		echo "index.php?p=layout&q=displayForm&modify=true&layoutid=$this->layoutid";
	}

	function EditBackgroundHref() 
	{		
		//output the button
		echo "index.php?p=layout&q=BackgroundForm&modify=true&layoutid=$this->layoutid";
	}

    function ScheduleNowHref()
    {
        // Get the Campaign ID
        $SQL  = "SELECT campaign.CampaignID ";
        $SQL .= "  FROM `lkcampaignlayout` ";
        $SQL .= "   INNER JOIN `campaign` ";
        $SQL .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $SQL .= " WHERE lkcampaignlayout.LayoutID = %d ";
        $SQL .= "   AND campaign.IsLayoutSpecific = 1";

        if (!$campaignId = $this->db->GetSingleValue(sprintf($SQL, $this->layoutid), 'CampaignID', _INT))
            trigger_error(__('Layout has no associated Campaign, corrupted Layout'), E_USER_ERROR);

        echo 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $campaignId;
    }
	
	/**
	 * Called by AJAX
	 * @return 
	 */
	public function RegionPreview()
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		
		include_once("lib/pages/region.class.php");
		
		//ajax request handler
		$response	= new ResponseManager();
		
		//Expect
		$layoutid 	= Kit::GetParam('layoutid', _POST, _INT, 0);
		$regionid 	= Kit::GetParam('regionid', _POST, _STRING);
		
		$seqGiven 	= Kit::GetParam('seq', _POST, _INT, 0);
		$seq	 	= Kit::GetParam('seq', _POST, _INT, 0);
		$width	 	= Kit::GetParam('width', _POST, _INT, 0);
		$height	 	= Kit::GetParam('height', _POST, _INT, 0);
		
		// The sequence will not be zero based, so adjust it
		$seq--;
		
		// Get some region imformation
		$return		= "";
		$xml		= new DOMDocument("1.0");
		$region 	= new region($db, $user);
		
		if (!$xmlString = $region->GetLayoutXml($layoutid))
		{
                    trigger_error($region->errorMsg, E_USER_ERROR);
		}
		
		$xml->loadXML($xmlString);
		
		// This will be all the media nodes in the region provided
		$xpath 		= new DOMXPath($xml);
		$nodeList 	= $xpath->query("//region[@id='$regionid']/media");
		
		$return = "<input type='hidden' id='maxSeq' value='{$nodeList->length}' />";
		$return .= "<div class='seqInfo' style='position:absolute; right:15px; top:31px; color:#FFF; background-color:#000; z-index:50; padding: 5px;'>
                                <span style='font-family: Verdana;'>$seqGiven / {$nodeList->length}</span>
                            </div>";
                $return .= '<div class="regionPreviewOverlay"></div>';
		
		if ($nodeList->length == 0)
		{
			// No media to preview
			$return .= "<h1>" . __('Empty Region') . "</h1>";
			
			$response->html = $return;
			$response->Respond();
		}
		
		$node = $nodeList->item($seq);
			
		// We have our node.
		$type 			= (string) $node->getAttribute("type");
		$mediaDurationText 	= (string) $node->getAttribute("duration");
                $mediaid                = (string) $node->getAttribute("id");

		$return .= "
                   <div class='previewInfo' style='position:absolute; right:15px; top:61px; color:#FFF; background-color:#000; z-index:50; padding: 5px; font-family: Verdana;'>
                        <span style='font-family: Verdana;'>Type: $type <br />
                        Duration: $mediaDurationText (s)</span>
                    </div>";

		// Create a module to deal with this
                if (!file_exists('modules/' . $type . '.module.php'))
                {
                    $return .= 'Unknow module type';
                }

                require_once("modules/$type.module.php");

                $moduleObject = new $type($db, $user, $mediaid, $layoutid, $regionid);

                $return .= $moduleObject->Preview($width, $height);

		$response->html = $return;
		$response->Respond();
	}

    /**
     * Copy layout form
     */
    public function CopyForm()
    {
        $db             =& $this->db;
        $user		=& $this->user;
        $response	= new ResponseManager();

        $helpManager    = new HelpManager($db, $user);

        $layoutid       = Kit::GetParam('layoutid', _REQUEST, _INT);
        $oldLayout      = Kit::GetParam('oldlayout', _REQUEST, _STRING);

        $msgName        = __('New Name');
        $msgName2       = __('The name for the new layout');
        $msgCopyMedia = __('Make new copies of all media on this layout?');

        $copyMediaChecked = (Config::GetSetting($db, 'LAYOUT_COPY_MEDIA_CHECKB') == 'Checked') ? 'checked' : '';

        $form = <<<END
        <form id="LayoutCopyForm" class="XiboForm" method="post" action="index.php?p=layout&q=Copy">
            <input type="hidden" name="layoutid" value="$layoutid">
            <table>
                <tr>
                    <td><label for="layout" accesskey="n" title="$msgName2">$msgName<span class="required">*</span></label></td>
                    <td><input name="layout" class="required" type="text" id="layout" value="$oldLayout 2" tabindex="1" /></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="checkbox" id="copyMediaFiles" name="copyMediaFiles" $copyMediaChecked />
                        <label for="copyMediaFiles" accesskey="c" title="$msgCopyMedia">$msgCopyMedia</label>
                    </td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Copy a Layout.'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Layout', 'Copy') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Copy'), '$("#LayoutCopyForm").submit()');
        $response->Respond();
    }

    /**
     * Copys a layout
     */
    public function Copy()
    {
        $db             =& $this->db;
        $user		=& $this->user;
        $response	= new ResponseManager();

        $layoutid       = Kit::GetParam('layoutid', _POST, _INT);
        $layout         = Kit::GetParam('layout', _POST, _STRING);
        $copyMedia = Kit::GetParam('copyMediaFiles', _POST, _CHECKBOX);

        Kit::ClassLoader('Layout');

        $layoutObject = new Layout($db);

        if (!$layoutObject->Copy($layoutid, $layout, $user->userid, (bool)$copyMedia))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Layout Copied'));
        $response->Respond();
    }

    public function RegionPermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $layoutid = Kit::GetParam('layoutid', _GET, _INT);
        $regionid = Kit::GetParam('regionid', _GET, _STRING);

        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);
        if (!$regionAuth->modifyPermissions)
            trigger_error(__("You do not have permissions to edit this regions permissions"), E_USER_ERROR);

        // Form content
        $form = '<form id="RegionPermissionsForm" class="XiboForm" method="post" action="index.php?p=layout&q=RegionPermissions">';
	$form .= '<input type="hidden" name="layoutid" value="' . $layoutid . '" />';
	$form .= '<input type="hidden" name="regionid" value="' . $regionid . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lklayoutregiongroup ';
        $SQL .= '   ON lklayoutregiongroup.GroupID = group.GroupID ';
        $SQL .= '       AND lklayoutregiongroup.LayoutID = %d ';
        $SQL .= "       AND lklayoutregiongroup.RegionID = '%s' ";
        $SQL .= ' WHERE `group`.GroupID <> %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $layoutid, $regionid, $user->getGroupFromId($user->userid, true));

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this layout region'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Region', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#RegionPermissionsForm").submit()');
        $response->Respond();
    }

    public function RegionPermissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        Kit::ClassLoader('layoutregiongroupsecurity');

        $layoutId = Kit::GetParam('layoutid', _POST, _INT);
        $regionId = Kit::GetParam('regionid', _POST, _STRING);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionId, true);
        if (!$regionAuth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this regions permissions'), E_USER_ERROR);

        // Unlink all
        $layoutSecurity = new LayoutRegionGroupSecurity($db);
        if (!$layoutSecurity->UnlinkAll($layoutId, $regionId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$layoutSecurity->Link($layoutId, $regionId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
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
        if (!$first)
        {
            if (!$layoutSecurity->Link($layoutId, $regionId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForLayout($layoutId)
    {
        $db =& $this->db;

        Kit::ClassLoader('campaign');
        $campaign = new Campaign($db);
        $campaignId = $campaign->GetCampaignId($layoutId);

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkcampaigngroup ';
        $SQL .= '   ON `group`.GroupID = lkcampaigngroup.GroupID ';
        $SQL .= ' WHERE lkcampaigngroup.CampaignID = %d ';

        $SQL = sprintf($SQL, $campaignId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for layout'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results))
        {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }
    
    /**
     * Filter form for the layout jump list
     */
    public function LayoutJumpListFilter()
    {
        $msgName = __('Layout');
        $msgJumpList = __('Layout Jump List');
        $filterPinned = (Kit::IsFilterPinned('layout', 'JumpList')) ? 'checked' : '';
        $listPinned = (Kit::IsFilterPinned('layout', 'JumpList')) ? 'block' : 'none';
        
        $form = <<<HTML
        <div class="XiboFilterInner">     
        <form>
            <input type="hidden" name="p" value="layout">
            <input type="hidden" name="q" value="LayoutJumpList">
            <input type="checkbox" class="XiboFilterPinned" style="display:none" checked />
            <table>
                <tr>
                    <td>$msgName</td>
                    <td><input type="text" name="name"></td>
                    <td><label for="XiboJumpListPinned">Pin?</label><input id="XiboJumpListPinned" name="XiboJumpListPinned" type="checkbox" class="XiboJumpListPinned" $filterPinned /></td>
                </tr>
            </table>
        </form>
        </div>
HTML;
		
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div id="JumpListHeader" JumpListGridId="$id">
            <center>$msgJumpList<span id="JumpListOpenClose">_</span></center>
        </div>
        <div class="XiboGrid" id="$id" style="display:$listPinned;">
            <div class="XiboFilter">
                $form
            </div>
            <div class="XiboData"></div>
        </div>
HTML;
		
        echo $xiboGrid;
    }

    /**
     * A List of Layouts we have permission to design
     */
    public function LayoutJumpList()
    {
        $user =& $this->user;
        $response = new ResponseManager();
        
        // Layout filter?
        $layoutName = Kit::GetParam('name', _POST, _STRING, '');
        setSession('layout', 'JumpList', Kit::GetParam('XiboJumpListPinned', _REQUEST, _CHECKBOX, 'off'));

        // Show a list of layouts we have permission to jump to
        $output = '<div class="info_table">';
        $output .= '<table style="width:100%">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Layout') . '</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        // Get a layout list
        $layoutList = $user->LayoutList($layoutName);

        foreach($layoutList as $layout)
        {
            if (!$layout['edit'] == 1)
                continue;

            // We have permission to edit this layout
            $output .= '<tr>';
            $output .= '    <td><a href="index.php?p=layout&modify=true&layoutid=' . $layout['layoutid'] . '">' . $layout['layout'] . '</a></td>';
            $output .= '</tr>';
        }

        $output .= '    </tbody>';
        $output .= '</table>';
        $output .= '</div>';

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Shows the TimeLine
     */
    public function Timeline()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $response->html = '';

        $layoutId = Kit::GetParam('layoutid', _GET, _INT);
        $regionId = Kit::GetParam('regionid', _REQUEST, _STRING);

        // Make sure we have permission to edit this region
        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Library location
        $libraryLocation = Config::GetSetting($db, 'LIBRARY_LOCATION');

        // Present a canvas with 2 columns, left column for the media icons
        $response->html .= '<div class="timelineLeftColumn">';
        $response->html .= '    <ul class="timelineModuleButtons">';

        // Always output a Library assignment button
        $response->html .= '<li class="timelineModuleListItem">';
        $response->html .= '    <a class="XiboFormButton timelineModuleButtonAnchor" title="' . __('Assign from Library') . '" href="index.php?p=content&q=LibraryAssignForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '">';
        $response->html .= '        <img class="timelineModuleButtonImage" src="img/forms/library.gif" alt="' . __('Library Image') . '" />';
        $response->html .= '        <span class="timelineModuleButtonText">' . __('Library') . '</span>';
        $response->html .= '    </a>';
        $response->html .= '</li>';
        
        // Get a list of the enabled modules and then create buttons for them
        if (!$enabledModules = new ModuleManager($db, $user))
            trigger_error($enabledModules->message, E_USER_ERROR);

        // Loop through the buttons we have and output each one
        while ($modulesItem = $enabledModules->GetNextModule())
        {
            $mod = Kit::ValidateParam($modulesItem['Module'], _STRING);
            $caption = Kit::ValidateParam($modulesItem['Name'], _STRING);
            $mod = strtolower($mod);
            $title = Kit::ValidateParam($modulesItem['Description'], _STRING);
            $img = Kit::ValidateParam($modulesItem['ImageUri'], _STRING);

            $uri = 'index.php?p=module&q=Exec&mod=' . $mod . '&method=AddForm&layoutid=' . $layoutId . '&regionid=' . $regionId;

            $response->html .= '<li class="timelineModuleListItem">';
            $response->html .= '    <a class="XiboFormButton timelineModuleButtonAnchor" title="' . $title . '" href="' . $uri . '">';
            $response->html .= '        <img class="timelineModuleButtonImage" src="' . $img . '" alt="' . __('Module Image') . '" />';
            $response->html .= '        <span class="timelineModuleButtonText">' . $caption . '</span>';
            $response->html .= '    </a>';
            $response->html .= '</li>';
        }
        
        $response->html .= '    </ul>';
        $response->html .= '</div>';

        // Load the XML for this layout and region, we need to get the media nodes.
        // These form the timeline and go in the right column

        // Generate an ID for the list (this is passed into the reorder function)
        $timeListMediaListId = uniqid('timelineMediaList_');

        $response->html .= '<div id="timelineControl" class="timelineRightColumn" layoutid="' . $layoutId . '" regionid="' . $regionId . '">';
        $response->html .= '    <div class="timelineMediaVerticalList">';
        $response->html .= '        <ul id="' . $timeListMediaListId . '" class="timelineSortableListOfMedia">';

        // How are we going to colour the bars, my media type or my permissions
        $timeBarColouring = Config::GetSetting($db, 'REGION_OPTIONS_COLOURING');

        // Create a layout object
        $layout = new Layout($db);

        foreach($layout->GetMediaNodeList($layoutId, $regionId) as $mediaNode)
        {
            // Put this node vertically in the region timeline
            $mediaId = $mediaNode->getAttribute('id');
            $lkId = $mediaNode->getAttribute('lkid');
            $mediaType = $mediaNode->getAttribute('type');
            $mediaDuration = $mediaNode->getAttribute('duration');
            $ownerId = $mediaNode->getAttribute('userId');

            // Permissions for this assignment
            $auth = $user->MediaAssignmentAuth($ownerId, $layoutId, $regionId, $mediaId, true);

            // Skip over media assignments that we do not have permission to see
            if (!$auth->view)
                continue;

            Debug::LogEntry($db, 'audit', sprintf('Permission Granted to View MediaID: %s', $mediaId), 'layout', 'TimeLine');

            // Create a media module to handle all the complex stuff
            require_once("modules/$mediaType.module.php");
            $tmpModule = new $mediaType($db, $user, $mediaId, $layoutId, $regionId, $lkId);
            $mediaName = $tmpModule->GetName();
            
            // Colouring for the media block
            if ($timeBarColouring == 'Media Colouring')
                $mediaBlockColouringClass = 'timelineMediaItemColouring_' . $mediaType;
            else
                $mediaBlockColouringClass = 'timelineMediaItemColouring_' . (($auth->edit) ? 'enabled' : 'disabled');
            
            // Create the list item
            $response->html .= '<li class="timelineMediaListItem" mediaid="' . $mediaId . '" lkid="' . $lkId . '">';
            $response->html .= '    <div class="timelineMediaItem">';
            $response->html .= '        <ul class="timelineMediaItemLinks">';

            // Create some links
            if ($auth->edit)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=EditForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to edit this media') . '">' . __('Edit') . '</a></li>';

            if ($auth->del)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=DeleteForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to delete this media') . '">' . __('Delete') . '</a></li>';

            if ($auth->modifyPermissions)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=PermissionsForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="Click to change permissions for this media">' . __('Permissions') . '</a></li>';

            $response->html .= '        </ul>';

            // Put the media name in
            $response->html .= '        <div class="timelineMediaDetails ' . $mediaBlockColouringClass . '">';
            $response->html .= '            <h3>' . (($mediaName == '') ? $tmpModule->displayType : $mediaName) . ' (' . $mediaDuration . ' seconds)</h3>';
            $response->html .= '            <div class="timelineMediaImageThumbnail">' . $tmpModule->ImageThumbnail() . '</div>';
            $response->html .= '        </div>';

            // Put the media hover preview in
            $mediaHoverPreview = $tmpModule->HoverPreview();
            $response->html .= '        <div class="timelineMediaPreview">' . $mediaHoverPreview . '</div>';

            // End the time line media item and list
            $response->html .= '    </div>';
            $response->html .= '</li>';
        }

        $response->html .= '        </ul>';
        $response->html .= '    </div>';

        // Output a div to contain the preview for this media item
        $response->html .= '    <div id="timelinePreview"></div>';

        $response->html .= '</div>';

        // Finish constructing the response
        $response->callBack = 'LoadTimeLineCallback';
        $response->dialogTitle 	= __('Region Timeline');
        $response->dialogSize 	= true;
        $response->dialogWidth 	= '1000px';
        $response->dialogHeight = '550px';
        $response->focusInFirstInput = false;

        // Add some buttons
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'RegionOptions') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Save Order'), 'XiboTimelineSaveOrder("' . $timeListMediaListId . '","' . $layoutId . '","' . $regionId . '")');

        $response->Respond();
    }

    /**
     * Re-orders a medias regions
     * @return
     */
    function TimelineReorder()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        // Vars
        $layoutId = Kit::GetParam('layoutid', _REQUEST, _INT);
        $regionId = Kit::GetParam('regionid', _POST, _STRING);
        $mediaList = Kit::GetParam('medialist', _POST, _STRING);

        // Check the user has permission
        Kit::ClassLoader('region');
        $region = new region($db, $user);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Create a list of media
        if ($mediaList == '')
            trigger_error(__('No media to reorder'));

        // Trim the last | if there is one
        $mediaList = rtrim($mediaList, '|');

        // Explode into an array
        $mediaList = explode('|', $mediaList);

        // Store in an array
        $resolvedMedia = array();

        foreach($mediaList as $mediaNode)
        {
            // Explode the second part of the array
            $mediaNode = explode('&', $mediaNode);

            $resolvedMedia[] = array('mediaid' => $mediaNode[0], 'lkid' => $mediaNode[1]);
        }

        // Hand off to the region object to do the actual reorder
        if (!$region->ReorderTimeline($layoutId, $regionId, $resolvedMedia))
            trigger_error($region->errorMsg, E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Order Changed'));
        $response->keepOpen = true;
        $response->Respond();
    }
}
?>
