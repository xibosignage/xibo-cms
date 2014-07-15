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

class timelineDAO {

    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        include_once("lib/data/layout.data.class.php");
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
		
		include_once("lib/data/region.data.class.php");

		$region = new region($db);
		
		if (!$region->AddRegion($layoutid, $user->userid))
		{
			//there was an ERROR
			trigger_error($region->GetErrorMessage(), E_USER_ERROR);
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
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
        if (!$regionAuth->del)
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);

        // Remove the permissions
        Kit::ClassLoader('layoutregiongroupsecurity');
        $security = new LayoutRegionGroupSecurity($db);
        $security->UnlinkAll($layoutid, $regionid);

        $db->query(sprintf("DELETE FROM lklayoutmediagroup WHERE layoutid = %d AND RegionID = '%s'", $layoutid, $regionid));

            if (!$region->DeleteRegion($layoutid, $regionid))
            {
                    //there was an ERROR
                    $response->SetError($region->GetErrorMessage());
                    $response->Respond();
            }

            $response->SetFormSubmitResponse(__('Region Deleted.'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $layoutid));
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
        $top 	= Kit::GetParam('top', _GET, _DOUBLE);
        $left 	= Kit::GetParam('left', _GET, _DOUBLE);
        $width 	= Kit::GetParam('width', _GET, _DOUBLE);
        $height 	= Kit::GetParam('height', _GET, _DOUBLE);
        $layoutWidth = Kit::GetParam('layoutWidth', _GET, _INT);
        $layoutHeight = Kit::GetParam('layoutHeight', _GET, _INT);
        $scale = Kit::GetParam('scale', _GET, _DOUBLE);

        Kit::ClassLoader('region');
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);
        $regionName = $region->GetRegionName($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Scale the layout width/height
        $layoutWidth = round($layoutWidth * $scale, 0);
        $layoutHeight = round($layoutHeight * $scale, 0);

        // Set some information about the form
        Theme::Set('form_id', 'RegionProperties');
        Theme::Set('form_action', 'index.php?p=timeline&q=ManualRegionPosition');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid .'"><input type="hidden" name="regionid" value="' . $regionid . '"><input id="layoutWidth" type="hidden" name="layoutWidth" value="' . $layoutWidth . '"><input id="layoutHeight" type="hidden" name="layoutHeight" value="' . $layoutHeight . '"><input type="hidden" name="scale" value="' . $scale .'">');
        
        // Theme Variables
        Theme::Set('regionName', $regionName);
        Theme::Set('width', round($width * $scale, 0));
        Theme::Set('height', round($height * $scale, 0));
        Theme::Set('top', round($top * $scale, 0));
        Theme::Set('left', round($left * $scale, 0));
        Theme::Set('transition', $region->GetOption($layoutid, $regionid, 'transOut', ''));
        Theme::Set('duration', $region->GetOption($layoutid, $regionid, 'transOutDuration', 0));
        Theme::Set('direction', $region->GetOption($layoutid, $regionid, 'transOutDirection', ''));
        
        // Add none to the list
        $transitions = $this->user->TransitionAuth('out');
        $transitions[] = array('code' => '', 'transition' => 'None', 'class' => '');

        Theme::Set('transition_field_list', $transitions);
        
        // Compass points for direction
        $compassPoints = array(
            array('id' => 'N', 'name' => __('North')), 
            array('id' => 'NE', 'name' => __('North East')), 
            array('id' => 'E', 'name' => __('East')), 
            array('id' => 'SE', 'name' => __('South East')), 
            array('id' => 'S', 'name' => __('South')), 
            array('id' => 'SW', 'name' => __('South West')), 
            array('id' => 'W', 'name' => __('West')),
            array('id' => 'NW', 'name' => __('North West'))
        );

        Theme::Set('direction_field_list', $compassPoints);

        if (count($this->user->TransitionAuth('out')) > 0) {
            $form = Theme::RenderReturn('region_form_options');
        }
        else {
            $form = Theme::RenderReturn('region_form_options_no_transition');
        }

        
        $response->SetFormRequestResponse($form, __('Region Options'), '350px', '275px', 'transitionFormLoad');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#RegionProperties").submit()');
        $response->AddButton(__('Set Full Screen'), 'setFullScreenLayout()');
        $response->Respond();
    }

    function ManualRegionPosition()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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
        $scale = Kit::GetParam('scale', _POST, _DOUBLE);

        // Adjust the dimensions
        $top = $top / $scale;
        $left = $left / $scale;
        $width = $width / $scale;
        $height = $height / $scale;
        
        // Transitions?
        $transitionType = Kit::GetParam('transitionType', _POST, _WORD);
        $duration = Kit::GetParam('transitionDuration', _POST, _INT, 0);
        $direction = Kit::GetParam('transitionDirection', _POST, _WORD, '');

        Kit::ClassLoader('region');
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        Debug::LogEntry('audit', sprintf('Layoutid [%d] Regionid [%s]', $layoutid, $regionid), 'layout', 'ManualRegionPosition');

        // Remove the "px" from them
        $width  = str_replace('px', '', $width);
        $height = str_replace('px', '', $height);
        $top    = str_replace('px', '', $top);
        $left   = str_replace('px', '', $left);
        
        // Create some options
        $options = array(
            array('name' => 'transOut', 'value' => $transitionType), 
            array('name' => 'transOutDuration', 'value' => $duration),
            array('name' => 'transOutDirection', 'value' => $direction)
        );

        // Edit the region 
        if (!$region->EditRegion($layoutid, $regionid, $width, $height, $top, $left, $regionName, $options))
            trigger_error($region->GetErrorMessage(), E_USER_ERROR);

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
		
		// Vars
		$layoutid = Kit::GetParam('layoutid', _REQUEST, _INT, 0);
        $regions = Kit::GetParam('regions', _POST, _HTMLSTRING);

        if ($regions == '')
            trigger_error(__('No regions present'));

        $regions = json_decode($regions);

        foreach ($regions as $region) {

            $regionid = Kit::ValidateParam($region->regionid, _STRING);
            $top = Kit::ValidateParam($region->top, _DOUBLE);
            $left = Kit::ValidateParam($region->left, _DOUBLE);
            $width = Kit::ValidateParam($region->width, _DOUBLE);
            $height = Kit::ValidateParam($region->height, _DOUBLE);

            Debug::LogEntry('audit', 'Editing Region ' . $regionid);

            // Remove the "px" from them
            $width  = str_replace("px", '', $width);
            $height = str_replace("px", '', $height);
            $top    = str_replace("px", '', $top);
            $left   = str_replace("px", '', $left);
            
            Kit::ClassLoader('region');
            $regionObject = new region($db);
            $ownerId = $regionObject->GetOwnerId($layoutid, $regionid);

            $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
            if (!$regionAuth->del)
                trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);
    		
    		if (!$regionObject->EditRegion($layoutid, $regionid, $width, $height, $top, $left))
    			trigger_error($regionObject->GetErrorMessage(), E_USER_ERROR);
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
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
        if (!$regionAuth->del)
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);
		
        // Translate messages
        $msgDelete		= __('Are you sure you want to remove this region?');
        $msgDelete2		= __('All media files will be unassigned and any context saved to the region itself (such as Text, Tickers) will be lost permanently.');
        $msgYes			= __('Yes');
        $msgNo			= __('No');

        //we can delete
        $form = <<<END
        <form id="RegionDeleteForm" class="XiboForm" method="post" action="index.php?p=timeline&q=DeleteRegion">
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
        $regionId = Kit::GetParam('regionid', _REQUEST, _STRING);
        $mediaList = Kit::GetParam('MediaID', _POST, _ARRAY, array());

        // Make sure we have permission to edit this region
        Kit::ClassLoader('region');
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        if (!$region->AddFromLibrary($user, $layoutId, $regionId, $mediaList))
            trigger_error($region->GetErrorMessage(), E_USER_ERROR);

        // We want to load a new form
        $response->SetFormSubmitResponse(sprintf(__('%d Media Items Assigned'), count($mediaList)));
        $response->loadForm = true;
        $response->loadFormUri = "index.php?p=timeline&layoutid=$layoutId&regionid=$regionId&q=Timeline";
        $response->Respond();
    }

    /**
	 * Called by AJAX
	 * @return 
	 */
	public function RegionPreview()
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		
		include_once("lib/data/region.data.class.php");
		
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
		$region 	= new region($db);
		
		if (!$xmlString = $region->GetLayoutXml($layoutid))
		{
            trigger_error($region->GetErrorMessage(), E_USER_ERROR);
		}
		
		$xml->loadXML($xmlString);
		
		// This will be all the media nodes in the region provided
		$xpath 		= new DOMXPath($xml);
		$nodeList 	= $xpath->query("//region[@id='$regionid']/media");
        $return = '';
		
		if ($nodeList->length == 0)
		{
			// No media to preview
			$response->extra['text']  = __('Empty Region');
			$response->html = '';
			$response->Respond();
		}
		
		$node = $nodeList->item($seq);
			
		// We have our node.
		$type = (string) $node->getAttribute("type");
		$mediaDurationText = (string) $node->getAttribute("duration");
        $mediaid = (string) $node->getAttribute("id");

        // Create a module to deal with this
        if (!file_exists('modules/' . $type . '.module.php'))
        {
            $return .= 'Unknow module type';
        }

        require_once("modules/$type.module.php");

        if (!$moduleObject = new $type($db, $user, $mediaid, $layoutid, $regionid))
            trigger_error($moduleObject->GetErrorMessage(), E_USER_ERROR);

        $return .= '<div class="regionPreviewOverlay"></div>';
        $return .= $moduleObject->Preview($width, $height);

		$response->html = $return;
		$response->extra['type'] = $type;
        $response->extra['duration'] = $mediaDurationText;
        $response->extra['number_items'] = $nodeList->length;
        $response->extra['text'] = $seqGiven . ' / ' . $nodeList->length . ' ' . $moduleObject->displayType . ' lasting ' . $mediaDurationText . ' seconds';
        $response->extra['current_item'] = $seqGiven;

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
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutid, $regionid);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutid, $regionid, true);
        if (!$regionAuth->modifyPermissions)
            trigger_error(__("You do not have permissions to edit this regions permissions"), E_USER_ERROR);

        // Form content
        $form = '<form id="RegionPermissionsForm" class="XiboForm" method="post" action="index.php?p=timeline&q=RegionPermissions">';
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
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
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
        $region = new region($db);
        $ownerId = $region->GetOwnerId($layoutId, $regionId);

        $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $layoutId, $regionId, true);
        if (!$regionAuth->edit)
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Library location
        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION');

        // Present a canvas with 2 columns, left column for the media icons
        $buttons = array();

        // Always output a Library assignment button
        $buttons[] = array(
                'id' => 'media_button_library',
                'url' => 'index.php?p=content&q=LibraryAssignForm&layoutid=' . $layoutId . '&regionid=' . $regionId,
                'text' => __('Library')
            );

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

            
            $buttons[] = array(
                'id' => 'media_button_' . $mod,
                'url' => 'index.php?p=module&q=Exec&mod=' . $mod . '&method=AddForm&layoutid=' . $layoutId . '&regionid=' . $regionId,
                'text' => __($caption)
            );
        }

        Theme::Set('media_buttons', $buttons);
        
        $response->html .= '<div class="container-fluid">';
        $response->html .= '<div class="row-fluid">';
        $response->html .= Theme::RenderReturn('layout_designer_form_timeline');

        // Load the XML for this layout and region, we need to get the media nodes.
        // These form the timeline and go in the right column

        // Generate an ID for the list (this is passed into the reorder function)
        $timeListMediaListId = uniqid('timelineMediaList_');

        $response->html .= '<div class="span10">';
        $response->html .= '<div id="timelineControl" class="timelineColumn" layoutid="' . $layoutId . '" regionid="' . $regionId . '">';
        $response->html .= '    <div class="timelineMediaVerticalList">';
        $response->html .= '        <ul id="' . $timeListMediaListId . '" class="timelineSortableListOfMedia">';

        // How are we going to colour the bars, my media type or my permissions
        $timeBarColouring = Config::GetSetting('REGION_OPTIONS_COLOURING');

        // Create a layout object
        $region = new Region($db);

        foreach($region->GetMediaNodeList($layoutId, $regionId) as $mediaNode)
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

            Debug::LogEntry('audit', sprintf('Permission Granted to View MediaID: %s', $mediaId), 'layout', 'TimeLine');

            // Create a media module to handle all the complex stuff
            require_once("modules/$mediaType.module.php");
            $tmpModule = new $mediaType($db, $user, $mediaId, $layoutId, $regionId, $lkId);
            $mediaName = $tmpModule->GetName();
            $transitionIn = $tmpModule->GetTransition('in');
            $transitionOut = $tmpModule->GetTransition('out');
            
            // Colouring for the media block
            if ($timeBarColouring == 'Permissions')
                $mediaBlockColouringClass = 'timelineMediaItemColouring_' . (($auth->edit) ? 'enabled' : 'disabled');
            else
                $mediaBlockColouringClass = 'timelineMediaItemColouring_' . $mediaType;
            
            // Create the list item
            $response->html .= '<li class="timelineMediaListItem" mediaid="' . $mediaId . '" lkid="' . $lkId . '">';
            
            // In transition
            $response->html .= '    <div class="timelineMediaInTransition">';
            
            if ($transitionIn != 'None')
                $response->html .= '<span>' . $transitionIn . '</span>';
            
            $response->html .= '    </div>';
            
            // Media Bar
            $response->html .= '    <div class="timelineMediaItem">';
            $response->html .= '        <ul class="timelineMediaItemLinks">';

            // Create some links
            if ($auth->edit)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=EditForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to edit this media') . '">' . __('Edit') . '</a></li>';

            if ($auth->del)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=DeleteForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to delete this media') . '">' . __('Delete') . '</a></li>';

            if ($auth->modifyPermissions)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=PermissionsForm&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="Click to change permissions for this media">' . __('Permissions') . '</a></li>';

            if (count($this->user->TransitionAuth('in')) > 0)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=TransitionEditForm&type=in&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to edit this transition') . '">' . __('In Transition') . '</a></li>';
            
            if (count($this->user->TransitionAuth('out')) > 0)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=TransitionEditForm&type=out&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '" title="' . __('Click to edit this transition') . '">' . __('Out Transition') . '</a></li>';
            
            $response->html .= '        </ul>';

            // Put the media name in
            $response->html .= '        <div class="timelineMediaDetails ' . $mediaBlockColouringClass . '">';
            $response->html .= '            <h3>' . sprintf('%s (%d seconds)', (($mediaName == '') ? __($tmpModule->displayType) : $mediaName), $mediaDuration) . '</h3>';
            $response->html .= '        </div>';

            // Put the media hover preview in
            $mediaHoverPreview = $tmpModule->HoverPreview();
            $response->html .= '        <div class="timelineMediaPreview">' . $mediaHoverPreview . '</div>';

            // End the time line media item
            $response->html .= '    </div>';
            
            // Out transition
            $response->html .= '    <div class="timelineMediaOutTransition">';
            
            if ($transitionOut != 'None')
                $response->html .= '<span>' . $transitionOut . '</span>';
            
            $response->html .= '    </div>';
            
            // End of this media item
            $response->html .= '</li>';
        }

        $response->html .= '        </ul>';
        $response->html .= '    </div>';

        // Output a div to contain the preview for this media item
        $response->html .= '    <div id="timelinePreview"></div>';

        $response->html .= '    </div>';
        $response->html .= '</div>';
        $response->html .= '</div>';
        $response->html .= '</div>';

        // Finish constructing the response
        $response->callBack = 'LoadTimeLineCallback';
        $response->dialogClass = 'modal-big';
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
        $region = new region($db);
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
            $mediaNode = explode(',', $mediaNode);

            $resolvedMedia[] = array('mediaid' => $mediaNode[0], 'lkid' => $mediaNode[1]);
        }

        // Hand off to the region object to do the actual reorder
        if (!$region->ReorderTimeline($layoutId, $regionId, $resolvedMedia))
            trigger_error($region->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Order Changed'));
        $response->keepOpen = true;
        $response->Respond();
    }
}
?>