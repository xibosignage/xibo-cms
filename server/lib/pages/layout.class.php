<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
                Debug::LogEntry('audit', 'Loading permissions for layoutid ' . $this->layoutid);

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

	/**
	 * Displays the Layout Page
	 */
	function displayPage() 
	{
		$db =& $this->db;
		
		switch ($this->sub_page) 
		{	
			case 'view':

				// Default options
		        if (Kit::IsFilterPinned('layout', 'LayoutFilter')) {
		            Theme::Set('filter_pinned', 'checked');
		            Theme::Set('layout', Session::Get('layout', 'filter_layout'));
		            Theme::Set('retired', Session::Get('layout', 'filter_retired'));
		            Theme::Set('filter_userid', Session::Get('layout', 'filter_userid'));
		            Theme::Set('filter_tags', Session::Get('layout', 'filter_tags'));
		        }
		        else {
					Theme::Set('retired', 0);
		        }
				
	        	Theme::Set('layout_form_add_url', 'index.php?p=layout&q=displayForm');

				$id = uniqid();
				Theme::Set('id', $id);
				Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
				Theme::Set('pager', ResponseManager::Pager($id));
				Theme::Set('form_meta', '<input type="hidden" name="p" value="layout"><input type="hidden" name="q" value="LayoutGrid">');
				
				// Field list for a "retired" dropdown list
		        Theme::Set('retired_field_list', array(array('retiredid' => 1, 'retired' => 'Yes'), array('retiredid' => 0, 'retired' => 'No')));
				
				// Field list for a "owner" dropdown list
				Theme::Set('owner_field_list', $db->GetArray("SELECT 0 AS UserID, 'All' AS UserName UNION SELECT DISTINCT user.UserID, user.UserName FROM `layout` INNER JOIN `user` ON layout.UserID = user.UserID "));

				// Call to render the template
				Theme::Render('layout_page');
				break;
				
			case 'edit':
				
   				Theme::Set('layout_form_edit_url', 'index.php?p=layout&q=displayForm&modify=true&layoutid=' . $this->layoutid);
   				Theme::Set('layout_form_edit_background_url', 'index.php?p=layout&q=BackgroundForm&modify=true&layoutid=' . $this->layoutid);
   				Theme::Set('layout_form_savetemplate_url', 'index.php?p=template&q=TemplateForm&layoutid=' . $this->layoutid);
   				Theme::Set('layout_form_addregion_url', 'index.php?p=timeline&q=AddRegion&layoutid=' . $this->layoutid);
                Theme::Set('layout_form_preview_url', 'index.php?p=preview&q=render&ajax=true&layoutid=' . $this->layoutid);
				Theme::Set('layout', $this->layout);

				Kit::ClassLoader('campaign');
		        $campaign = new Campaign($db);
		        $campaignId = $campaign->GetCampaignId($this->layoutid);
   				Theme::Set('layout_form_schedulenow_url', 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $campaignId);
   				Theme::Set('layout_designer_editor', $this->RenderDesigner());

   				// Set up the theme variables for the Layout Jump List
   				$this->LayoutJumpListFilter();

   				// Set up any JavaScript translations
   				Theme::Set('translations', json_encode(array('save_position_button' => __('Save Position'))));

   				// Call the render the template
   				Theme::Render('layout_designer');

				break;
				
			default:
				break;
		}
		
		return false;
	}	

	/**
	 * Adds a layout record to the db
	 * @return 
	 */
	function add() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db             =& $this->db;
        $response       = new ResponseManager();

        $layout         = Kit::GetParam('layout', _POST, _STRING);
        $description    = Kit::GetParam('description', _POST, _STRING);
        $tags           = Kit::GetParam('tags', _POST, _STRING);
        $templateId     = Kit::GetParam('templateid', _POST, _INT, 0);
        $userid         = Kit::GetParam('userid', _SESSION, _INT);

        // Add this layout
        $layoutObject = new Layout($db);

        if (!$id = $layoutObject->Add($layout, $description, $tags, $userid, $templateId))
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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$layoutid 		= Kit::GetParam('layoutid', _POST, _INT);
		$layout 		= Kit::GetParam('layout', _POST, _STRING);
		$description 	= Kit::GetParam('description', _POST, _STRING);
		$tags		 	= Kit::GetParam('tags', _POST, _STRING);
		$retired 		= Kit::GetParam('retired', _POST, _INT, 0);
		$userid 		= Kit::GetParam('userid', _SESSION, _INT);
		
		// Add this layout
        $layoutObject = new Layout($db);

        if (!$layoutObject->Edit($layoutid, $layout, $description, $tags, $userid, $retired))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

		$response->SetFormSubmitResponse(__('Layout Details Changed.'));
		$response->Respond();
	}
	
	function DeleteLayoutForm() 
	{
        $db =& $this->db;
        $response = new ResponseManager();
        
        $layoutid = $this->layoutid;

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);
		
		// Are we going to be able to delete this?
        Kit::ClassLoader('campaign');
        $campaign = new Campaign($db);
        $campaignId = $campaign->GetCampaignId($layoutid);

		// Has it been scheduled?
		$SQL = sprintf("SELECT CampaignID FROM schedule WHERE CampaignID = %d", $campaignId);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not get layout information"), E_USER_ERROR);
		}

		Theme::Set('form_id', 'LayoutDeleteForm');
		Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');
		
		if ($db->num_rows($results) == 0) 
		{
			// Delete the layout
			$themeFile = 'layout_form_delete';
			Theme::Set('form_action', 'index.php?p=layout&q=delete');
		}
		else 
		{
			// Retire the layout
			$themeFile = 'layout_form_retire';
			Theme::Set('form_action', 'index.php?p=layout&q=retire');
		}

		$form = Theme::RenderReturn($themeFile);
		
        $response->SetFormRequestResponse($form, __('Delete Layout'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutDeleteForm").submit()');
        $response->Respond();
    }

	/**
	 * Deletes a layout record from the DB
	 */
	function delete() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _POST, _INT, 0);

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);

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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _POST, _INT, 0);

        // Permission to retire?
        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);

        // Action the retire
        $layoutObject = new Layout($db);

        if (!$layoutObject->Retire($layoutId))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Layout has been Retired'));
        $response->Respond();
	}
	
	/**
	 * Shows the Layout Grid
	 * @return 
	 */
	function LayoutGrid() 
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();
		
		// Filter by Name
		$name = Kit::GetParam('filter_layout', _POST, _STRING);
		setSession('layout', 'filter_layout', $name);
		
		// User ID
		$filter_userid = Kit::GetParam('filter_userid', _POST, _INT);
		setSession('layout', 'filter_userid', $filter_userid);
		
		// Show retired
		$filter_retired = Kit::GetParam('filter_retired', _POST, _INT);
		setSession('layout', 'filter_retired', $filter_retired);
		
		// Tags list
		$filter_tags = Kit::GetParam("filter_tags", _POST, _STRING);
		setSession('layout', 'filter_tags', $filter_tags);
        
        // Pinned option?        
        setSession('layout', 'LayoutFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		
		// Get all layouts
		$layouts = $user->LayoutList($name, $filter_userid, $filter_retired, $filter_tags);

		if (!is_array($layouts))
			trigger_error(__('Unable to get layouts for user'), E_USER_ERROR);

        $rows = array();

    	foreach ($layouts as $layout) {
    		// Construct an object containing all the layouts, and pass to the theme
    		$row = array();

    		$row['layoutid'] = $layout['layoutid'];
    		$row['layout'] = $layout['layout'];
    		$row['description'] = $layout['description'];
    		$row['owner'] = $user->getNameFromID($layout['ownerid']);
    		$row['permissions'] = $this->GroupsForLayout($layout['layoutid']);

    		switch ($layout['status']) {

				case 1:
					$row['status'] = '<span title="' . __('This Layout is ready to play') . '" class="icon-ok-circle"></span>';
					break;

				case 2:
					$row['status'] = '<span title="' . __('There are items on this Layout that can only be assessed by the client') . '" class="icon-question-sign"></span>';
					break;

				case 3:
					$row['status'] = '<span title="' . __('This Layout is invalid and should not be scheduled') . '" class="icon-remove-sign"></span>';
					break;

				default:
					$row['status'] = '<span title="' . __('The Status of this Layout is not known') . '" class="icon-warning-sign"></span>';
    		}

    		
    		$row['layout_form_edit_url'] = 'index.php?p=layout&q=displayForm&layoutid=' . $layout['layoutid'];

    		// Add some buttons for this row
    		// Schedule Now
    		$row['buttons'][] = array(
    				'id' => 'layout_button_schedulenow',
    				'url' => 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $layout['campaignid'],
    				'text' => __('Schedule Now')
    			);

            $row['buttons'][] = array(
    				'id' => 'layout_button_preview',
    				'url' => 'index.php?p=preview&q=render&ajax=true&layoutid=' . $layout['layoutid'],
    				'text' => __('Preview Layout')
    			);

    		// Only proceed if we have edit permissions
    		if ($layout['edit']) {

				// Design Button
	    		$row['buttons'][] = array(
	    				'id' => 'layout_button_design',
						'url' => 'index.php?p=layout&modify=true&layoutid=' . $layout['layoutid'],
						'text' => __('Design')
	    			);

	    		// Edit Button
				$row['buttons'][] = array(
						'id' => 'layout_button_edit',
						'url' => 'index.php?p=layout&q=displayForm&modify=true&layoutid=' . $layout['layoutid'],
						'text' => __('Edit')
	    			);

				// Copy Button
				$row['buttons'][] = array(
						'id' => 'layout_button_copy',
						'url' => 'index.php?p=layout&q=CopyForm&layoutid=' . $layout['layoutid'] . '&oldlayout=' . $layout['layout'],
						'text' => __('Copy')
	    			);

				// Extra buttons if have delete permissions
				if ($layout['del']) {
					// Copy Button
					$row['buttons'][] = array(
							'id' => 'layout_button_delete',
							'url' => 'index.php?p=layout&q=DeleteLayoutForm&layoutid=' . $layout['layoutid'],
							'text' => __('Delete')
		    			);				
				}

				// Extra buttons if we have modify permissions
				if ($layout['modifyPermissions']) {
					// Permissions button
					$row['buttons'][] = array(
							'id' => 'layout_button_permissions',
							'url' => 'index.php?p=campaign&q=PermissionsForm&CampaignID=' . $layout['campaignid'],
							'text' => __('Permissions')
		    			);	
				}
    		}

			// Add the row
			$rows[] = $row;
    	}

    	// Store the table rows
    	Theme::Set('table_rows', $rows);

    	// Initialise the theme and capture the output
    	$output = Theme::RenderReturn('layout_page_grid');
		
		$response->SetGridResponse($output);
		$response->initialSortColumn = 2;
		$response->Respond();
	}

	/**
	 * Displays an Add/Edit form
	 */
	function displayForm () 
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();
		
		Theme::Set('layoutid', $this->layoutid); 
		Theme::Set('layout', $this->layout);
		Theme::Set('description', $this->description);
		Theme::Set('retired', $this->retired);
		Theme::Set('tags', $this->tags);
		Theme::Set('form_id', 'LayoutForm');

		if ($this->layoutid != '')
		{
			// We are editing
			$theme = 'layout_form_edit';
			Theme::Set('form_action', 'index.php?p=layout&q=modify');
			Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '">');
			
			// build the retired option
			Theme::Set('retired_field_list', array(array('retiredid' => '1', 'retired' => 'Yes'), array('retiredid' => '0', 'retired' => 'No')));
		}
		else
		{
			// We are adding
			$theme = 'layout_form_add';
			Theme::Set('form_action', 'index.php?p=layout&q=add');

            $templates = $user->TemplateList();
            array_unshift($templates, array('templateid' => '0', 'template' => 'None'));
                    
            Theme::Set('template_field_list', $templates);
		}

		// Initialise the template and capture the output
		$form = Theme::RenderReturn($theme);

		$dialogTitle = ($this->layoutid == 0) ? __('Add Layout') : __('Edit Layout');

		$response->SetFormRequestResponse($form, $dialogTitle, '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . (($this->layoutid != '') ? HelpManager::Link('Layout', 'Edit') : HelpManager::Link('Layout', 'Add')) . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#LayoutForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Generates a form for the background edit
	 */
	function BackgroundForm() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;

		$response	= new ResponseManager();

		// Permission to retire?
        if (!$this->auth->edit)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);

		// Load the XML into a SimpleXML OBJECT
		$xml                = simplexml_load_string($this->xml);

		$backgroundImage    = (string) $xml['background'];
		$backgroundColor    = (string) $xml['bgcolor'];
		$width              = (string) $xml['width'];
		$height             = (string) $xml['height'];
		$resolutionid = (int)$xml['resolutionid'];
        $bgImageId          = 0;

        // Do we need to override the background with one passed in?
        $bgOveride          = Kit::GetParam('backgroundOveride', _GET, _STRING);

        if ($bgOveride != '')
            $backgroundImage = $bgOveride;
		
		// Manipulate the images slightly
		if ($backgroundImage != '')
		{
            // Get the ID for the background image
            $bgImageInfo = explode('.', $backgroundImage);
            $bgImageId = $bgImageInfo[0];

            $thumbBgImage = "index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=$bgImageId&width=80&height=80&dynamic";
		}
		else
		{
            $thumbBgImage = "theme/default/img/forms/filenotfound.gif";
		}

		// Configure some template variables.
		Theme::Set('form_id', 'LayoutBackgroundForm');
		Theme::Set('form_action', 'index.php?p=layout&q=EditBackground');
		Theme::Set('form_meta', '<input type="hidden" id="layoutid" name="layoutid" value="' . $this->layoutid . '">');
		Theme::Set('background_thumbnail_url', $thumbBgImage);

		// A list of available backgrounds
        $backgrounds = $user->MediaList('image');
        array_unshift($backgrounds, array('mediaid' => '0', 'media' => 'None'));

        Theme::Set('background_image_list', $backgrounds);
        Theme::Set('background_id', $bgImageId);
        
		// A list of web safe colors
		// Strip the # from the currently set color
		Theme::Set('background_color', trim($backgroundColor,'#'));
		Theme::Set('background_color_list', gwsc());
		
		// Get the ID of the current resolution
		if ($resolutionid == 0) {
			$SQL = sprintf("SELECT resolutionID FROM resolution WHERE width = %d AND height = %d", $width, $height);
			
			if (!$resolutionid = $db->GetSingleValue($SQL, 'resolutionID', _INT)) 
			{
				trigger_error($db->error());
				trigger_error(__("Unable to get the Resolution information"), E_USER_ERROR);
			}
		}
		
		Theme::Set('resolutionid', $resolutionid);
		Theme::Set('resolution_field_list', $db->GetArray('SELECT resolutionid, resolution FROM resolution ORDER BY resolution'));
		
		// Begin the form output
		$form = Theme::RenderReturn('layout_form_background');

		$response->SetFormRequestResponse($form, __('Change the Background Properties'), '550px', '240px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Background') . '")');
        $response->AddButton(__('Add Image'), 'XiboFormRender("index.php?p=module&q=Exec&mod=image&method=AddForm&backgroundImage=true&layoutid=' . $this->layoutid . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#LayoutBackgroundForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Edits the background of the layout
	 */
	function EditBackground()
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db 			=& $this->db;
		$user 			=& $this->user;
		$response		= new ResponseManager();

		$layoutid 		= Kit::GetParam('layoutid', _POST, _INT);
		$bg_color 		= Kit::GetParam('bg_color', _POST, _STRING);
		$mediaID 		= Kit::GetParam('bg_image', _POST, _INT);
		$resolutionid	= Kit::GetParam('resolutionid', _POST, _INT);

		// Permission to retire?
        if (!$this->auth->edit)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);

		Kit::ClassLoader('Layout');
        $layoutObject = new Layout($db);

        if (!$layoutObject->SetBackground($layoutid, $resolutionid, $bg_color, $mediaID))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);
		
		$response->SetFormSubmitResponse(__('Layout Background Changed'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $layoutid));
		$response->Respond();
	}
	
	/**
	 * Render the designer
	 */
	function RenderDesigner() 
	{
		$db =& $this->db;
		
		// Assume we have the xml in memory already
		// Make a DOM from the XML
		$xml = new DOMDocument();
		$xml->loadXML($this->xml);
		
		// get the width and the height
		$resolutionid = (int)$xml->documentElement->getAttribute('resolutionid');
		$width 	= $xml->documentElement->getAttribute('width');
		$height = $xml->documentElement->getAttribute('height');

		// Get the display width / height
		if ($resolutionid != 0) {
			$SQL = sprintf("SELECT intended_width, intended_height FROM `resolution` WHERE resolutionid = %d", $resolutionid);
		}
		else {
			$SQL = sprintf("SELECT intended_width, intended_height FROM `resolution`  WHERE width = %d AND height = %d", $width, $height);
		}

		if (!$resolution = $db->GetSingleRow($SQL)) {
			trigger_error(__('Unable to determine display resolution'));

			$intended_width = $width;
			$intended_height = $height;
		}
		else {
			$intended_width = $resolution['intended_width'];
			$intended_height = $resolution['intended_height'];
		}

		// Work out the scaling factor for the tip
		// _scaleFactor = Math.Min(_clientSize.Width / _layoutWidth, _clientSize.Height / _layoutHeight);
		$scaleFactor = min($intended_width / $width, $intended_height / $height);
		
		// do we have a background? Or a background color (or both)
		$bgImage = $xml->documentElement->getAttribute('background');
		$bgColor = $xml->documentElement->getAttribute('bgcolor');

		// Library location
		$libraryLocation = Config::GetSetting("LIBRARY_LOCATION");
		
		// Fix up the background css
		if ($bgImage == '')
		{
            $background_css = $bgColor;
		}
        else
		{
            // Get the ID for the background image
            $bgImageInfo = explode('.', $bgImage);
            $bgImageId = $bgImageInfo[0];

            $background_css = "url('index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=$bgImageId&width=$width&height=$height&dynamic&proportional=0') top center no-repeat; background-color:$bgColor";
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
            $tipWidth       = round($region->getAttribute('width') * $scaleFactor, 0);
            $tipHeight      = round($region->getAttribute('height') * $scaleFactor, 0);
            $tipLeft        = round($region->getAttribute('left') * $scaleFactor, 0);
            $tipTop         = round($region->getAttribute('top') * $scaleFactor, 0);

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

			$regionHtml .= "<div id='region_$regionid' regionEnabled='$regionAuth->edit' regionid='$regionid' layoutid='$this->layoutid' scale='$scaleFactor' width='$regionWidth' height='$regionHeight' href='index.php?p=timeline&layoutid=$this->layoutid&regionid=$regionid&q=Timeline' ondblclick=\"$doubleClickLink\"' class='$regionDisabledClass $regionPreviewClass' style=\"position:absolute; width:$regionWidth; height:$regionHeight; top: $regionTop; left: $regionLeft;\">
					  $regionTransparency";
                                          
			if ($regionAuth->edit) {

				$regionHtml .= '<div class="btn-group regionInfo pull-right">';
				$regionHtml .= '	<button class="btn dropdown-toggle" data-toggle="dropdown">';
				$regionHtml .= '<span class="region-tip">' . $tipWidth . ' x ' . $tipHeight . ' (' . $tipLeft . ',' . $tipTop . ')' . '</span>';
				$regionHtml .= '		<span class="caret"></span>';
				$regionHtml .= '	</button>';
				$regionHtml .= '	<ul class="dropdown-menu">';
				$regionHtml .= '    	<li><a class="XiboFormButton" href="index.php?p=timeline&q=Timeline&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Timeline') . '">' . __('Edit Timeline') . '</a></li>';
				$regionHtml .= '    	<li><a class="RegionOptionsMenuItem" href="#" title="' . __('Options') . '">' . __('Options') . '</a></li>';
				$regionHtml .= '    	<li><a class="XiboFormButton" href="index.php?p=timeline&q=DeleteRegionForm&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Delete') . '">' . __('Delete') . '</a></li>';
				$regionHtml .= '    	<li><a class="XiboFormButton" href="index.php?p=timeline&q=RegionPermissionsForm&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Permissions') . '">' . __('Permissions') . '</a></li>';
				$regionHtml .= '	</ul>';
				$regionHtml .= '</div>';
				
			}
			else if ($regionAuth->view)
			{
				$regionHtml .= '<div class="regionInfo">';
				$regionHtml .= '<span class="region-tip">' . $tipWidth . ' x ' . $tipHeight . ' (' . $tipLeft . ',' . $tipTop . ')' . '</span>';
                $regionHtml .= '</div>';
			}

			$regionHtml .= '	<div class="preview">';
			$regionHtml .= '		<div class="previewContent"></div>';
			$regionHtml .= '		<div class="previewNav label label-info"></div>';
			$regionHtml .= '	</div>';
			$regionHtml .= '</div>';
		}
		
		//render the view pane
		$surface = <<<HTML

		<div id="layout" class="layout" layoutid="$this->layoutid" style="position:relative; width:$width; height:$height; border: 1px solid #000; background:$background_css;">
		$regionHtml
		</div>
HTML;
		
		return $surface;
	}

    /**
     * Copy layout form
     */
    public function CopyForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);
        $oldLayout = Kit::GetParam('oldlayout', _REQUEST, _STRING);

        $copyMediaChecked = (Config::GetSetting('LAYOUT_COPY_MEDIA_CHECKB') == 'Checked') ? 'checked' : '';

        Theme::Set('form_id', 'LayoutCopyForm');
        Theme::Set('form_action', 'index.php?p=layout&q=Copy');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');
        Theme::Set('copy_media_checked', $copyMediaChecked);
        Theme::Set('new_layout_default', $oldLayout . ' 2');

        $form = Theme::RenderReturn('layout_form_copy');

        $response->SetFormRequestResponse($form, __('Copy a Layout.'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Copy') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Copy'), '$("#LayoutCopyForm").submit()');
        $response->Respond();
    }

    /**
     * Copys a layout
     */
    public function Copy()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $layoutid = Kit::GetParam('layoutid', _POST, _INT);
        $layout = Kit::GetParam('layout', _POST, _STRING);
        $copyMedia = Kit::GetParam('copyMediaFiles', _POST, _CHECKBOX);

        Kit::ClassLoader('Layout');

        $layoutObject = new Layout($db);

        if (!$layoutObject->Copy($layoutid, $layout, $user->userid, (bool)$copyMedia))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Layout Copied'));
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
        // Default values?
        if (Kit::IsFilterPinned('layoutDesigner', 'JumpList'))
        {
            $filterPinned = 'checked';
            $listPinned = 'block';
            $arrowDirection = 'v';
            $filterName = Session::Get('layoutDesigner', 'Name');
        }
        else 
        {
            $filterPinned = '';
            $listPinned = 'none';
            $arrowDirection = '^';
            $filterName = '';
        }

        $id = uniqid();

        Theme::Set('jumplist_id', $id);
        Theme::Set('jumplist_pager', ResponseManager::Pager($id));
        Theme::Set('jumplist_form_meta', '<input type="hidden" name="p" value="layout"><input type="hidden" name="q" value="LayoutJumpList">');
        Theme::Set('jumplist_filter_pinned', $filterPinned);
        Theme::Set('jumplist_list_pinned', $listPinned);
        Theme::Set('jumplist_arrow_direction', $arrowDirection);
        Theme::Set('jumplist_filter_name', $filterName);
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
        setSession('layoutDesigner', 'JumpList', Kit::GetParam('XiboJumpListPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('layoutDesigner', 'Name', $layoutName);       

        // Get a layout list
        $layoutList = $user->LayoutList($layoutName);

        $rows = array();

        foreach ($layoutList as $layout)
        {
            if (!$layout['edit'] == 1)
                continue;

            // We have permission to edit this layout
            $row = array();
            $row['layoutid'] = $layout['layoutid'];
            $row['layout'] = $layout['layout'];
            $row['jump_to_url'] = 'index.php?p=layout&modify=true&layoutid=' . $layout['layoutid'];

            $rows[] = $row;
        }

        // Store the table rows
    	Theme::Set('table_rows', $rows);

    	$output = Theme::RenderReturn('layout_jumplist_grid');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function LayoutStatus() {

    	$db =& $this->db;
    	$response = new ResponseManager();
    	$layoutId = Kit::GetParam('layoutId', _GET, _INT);

    	Kit::ClassLoader('Layout');
    	$layout = new Layout($db);

    	$status = "";

    	switch ($layout->IsValid($layoutId)) {

			case 1:
				$status = '<span title="' . __('This Layout is ready to play') . '" class="icon-ok-circle"></span>';
				break;

			case 2:
				$status = '<span title="' . __('There are items on this Layout that can only be assessed by the client') . '" class="icon-question-sign"></span>';
				break;

			case 3:
				$status = '<span title="' . __('This Layout is invalid and should not be scheduled') . '" class="icon-remove-sign"></span>';
				break;

			default:
				$status = '<span title="' . __('The Status of this Layout is not known') . '" class="icon-warning-sign"></span>';
		}

		$response->html = $status;
		$response->success = true;
		$response->Respond();
    }
}
?>
