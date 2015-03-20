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

class timelineDAO extends baseDAO
{
    /**
	 * Adds a new region for a layout
	 */
	function AddRegion()
	{
		$response = new ResponseManager();
		
		$layout = \Xibo\Factory\LayoutFactory::loadById(Kit::GetParam('layoutid', _REQUEST, _INT));

        // Check Permissions
        if (!$this->user->checkEditable($layout))
            trigger_error(__('You do not have permission to edit this Layout'), E_USER_ERROR);

        // Create a new region
        $region = \Xibo\Factory\RegionFactory::create($this->user->userid, $layout->layout . '-' . (count($layout->regions) + 1), 250, 250, 50, 50);

        // Add the region to the layout
        $layout->regions[] = $region;

        // Save everything
        $layout->save();
		
		$response->SetFormSubmitResponse(__('Region Added.'), true, 'index.php?p=layout&modify=true&layoutid=' . $layout->layoutId);
		$response->Respond();
	}
	
	/**
	 * Deletes a region and all the assigned widgets
	 */
	function DeleteRegion()
	{
		$response = new ResponseManager();

        $region = \Xibo\Factory\RegionFactory::getById(Kit::GetParam('regionid', _REQUEST, _INT));

        // Do we have permission
        if (!$this->user->checkDeleteable($region))
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);

        // Delete the region
        $region->delete();

        $response->SetFormSubmitResponse(__('Region Deleted.'), true, sprintf('index.php?p=layout&layoutid=%d&modify=true', $region->layoutId));
        $response->Respond();
	}

    /*
     * Form called by the layout which shows a manual positioning/sizing form.
     */
    function ManualRegionPositionForm()
    {
        $response = new ResponseManager();

        $layoutId = \Kit::GetParam('layoutId', _GET, _INT);
        $scale = \Kit::GetParam('scale', _GET, _DOUBLE);
        $zoom = \Kit::GetParam('zoom', _GET, _DOUBLE);

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _GET, _INT));

        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        $top = round($region->top * $scale, 0);
        $left = round($region->left * $scale, 0);
        $width = round($region->width * $scale, 0);
        $height = round($region->height * $scale, 0);
        $zIndex = $region->zIndex;

        // Set some information about the form
        Theme::Set('form_id', 'RegionProperties');
        Theme::Set('form_action', 'index.php?p=timeline&q=ManualRegionPosition');
        Theme::Set('form_meta', '<input type="hidden" name="regionid" value="' . $region->regionId . '"><input type="hidden" name="scale" value="' . $scale .'"><input type="hidden" name="zoom" value="' . $zoom .'">');
        
        $formFields = array();
        $formFields[] = FormManager::AddText('name', __('Name'), $region->name,
            __('Name of the Region'), 'n', 'maxlength="50"');

        $formFields[] = FormManager::AddNumber('top', __('Top'), $top, 
            __('Offset from the Top Corner'), 't');

        $formFields[] = FormManager::AddNumber('left', __('Left'), $left, 
            __('Offset from the Left Corner'), 'l');

        $formFields[] = FormManager::AddNumber('width', __('Width'), $width, 
            __('Width of the Region'), 'w');

        $formFields[] = FormManager::AddNumber('height', __('Height'), $height, 
            __('Height of the Region'), 'h');

        // Transitions
        if (count($this->user->TransitionAuth('out')) > 0) {
            // Add none to the list
            $transitions = $this->user->TransitionAuth('out');
            $transitions[] = array('code' => '', 'transition' => 'None', 'class' => '');

            $formFields[] = FormManager::AddCombo(
                        'transitionType', 
                        __('Exit Transition'), 
                        $region->getOptionValue('transOut', ''),
                        $transitions,
                        'code',
                        'transition',
                        __('What transition should be applied when this region is finished?'), 
                        't');

            $formFields[] = FormManager::AddNumber('transitionDuration', __('Duration'), $region->getOptionValue('transOutDuration', 0),
                __('The duration for this transition, in milliseconds.'), 'l', '', 'transition-group');

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

            $formFields[] = FormManager::AddCombo(
                        'transitionDirection', 
                        __('Direction'), 
                        $region->getOptionValue('transOutDirection', ''),
                        $compassPoints,
                        'id',
                        'name',
                        __('The direction for this transition. Only appropriate for transitions that move, such as Fly.'),
                        'd',
                        'transition-group transition-direction');

            // Add some dependencies
            $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'none')));
            $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'block')), 'not');
            $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'none')));
            $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'block')), 'not');
        }

        $formFields[] = FormManager::AddCheckbox('loop', __('Loop?'), 
            $region->getOptionValue('loop', 0), __('If there is only one item in this region should it loop? Not currently available for Windows Players.'),
            'l');

        $formFields[] = FormManager::AddNumber('zindex', __('Layer'), ($zIndex == 0) ? NULL : $zIndex,
            __('The layering order of this region (z-index). Advanced use only. '), 'z');

        Theme::Set('form_fields', $formFields);

        // Add some information about the whole layout to this request.
        $layout = \Xibo\Factory\LayoutFactory::getById($layoutId);
        $response->extra['layoutInformation'] = array('width' => $layout->width, 'height' => $layout->height);
        
        $response->SetFormRequestResponse(NULL, __('Region Options'), '350px', '275px');
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
        
        $response = new ResponseManager();

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _POST, _INT));
        Debug::Audit($region);

        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Set the new values
        $region->name = \Kit::GetParam('name', _POST, _STRING);
        $region->zIndex = \Kit::GetParam('zindex', _POST, _INT, NULL);

        $top  = \Kit::GetParam('top', _POST, _INT);
        $left = \Kit::GetParam('left', _POST, _INT);
        $width = \Kit::GetParam('width', _POST, _INT);
        $height = \Kit::GetParam('height', _POST, _INT);
        $scale = \Kit::GetParam('scale', _POST, _DOUBLE);
        $zoom = \Kit::GetParam('zoom', _POST, _DOUBLE);

        // Remove the "px" from them
        $width  = str_replace('px', '', $width);
        $height = str_replace('px', '', $height);
        $top    = str_replace('px', '', $top);
        $left   = str_replace('px', '', $left);

        // Adjust the dimensions
        // For version 2 layouts and above, the scale will always be 1.
        // Version 1 layouts need to use scale because the values in the XLF should be scaled down
        $region->top = $top / $scale;
        $region->left = $left / $scale;
        $region->width = $width / $scale;
        $region->height = $height / $scale;

        // Loop
        $region->setOptionValue('loop', \Kit::GetParam('loop', _POST, _CHECKBOX));

        // Transitions
        $region->setOptionValue('transitionType', \Kit::GetParam('transitionType', _POST, _WORD));
        $region->setOptionValue('transitionDuration', \Kit::GetParam('transitionDuration', _POST, _INT));
        $region->setOptionValue('transitionDirection', \Kit::GetParam('transitionDirection', _POST, _WORD));

        // Save
        $region->save();

        $response->SetFormSubmitResponse('Region Resized', true, "index.php?p=layout&modify=true&layoutid=$region->layoutId&zoom=$zoom");
        $response->Respond();
    }
	
	/**
	 * Edits the region information
	 */
	function RegionChange()
	{
		$response = new ResponseManager();
		
		// Create the layout
		$layout = \Xibo\Factory\LayoutFactory::loadById(Kit::GetParam('layoutid', _REQUEST, _INT));

        // Pull in the regions and convert them to stdObjects
        $regions = \Kit::GetParam('regions', _POST, _HTMLSTRING);

        if ($regions == '')
            trigger_error(__('No regions present'), E_USER_ERROR);

        $regions = json_decode($regions);

        // Go through each region and update the region in the layout we have
        foreach ($regions as $newCoordinates) {
            $regionId = \Kit::ValidateParam($newCoordinates->regionid, _INT);

            // Load the region
            $region = $layout->getRegion($regionId);
            Debug::Audit('Editing Region ' . $region);

            // Check Permissions
            if (!$this->user->checkEditable($region))
                trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

            // New coordinates
            $region->top = \Kit::ValidateParam($newCoordinates->top, _DOUBLE);
            $region->left = \Kit::ValidateParam($newCoordinates->left, _DOUBLE);
            $region->width = \Kit::ValidateParam($newCoordinates->width, _DOUBLE);
            $region->height = \Kit::ValidateParam($newCoordinates->height, _DOUBLE);
            Debug::Audit('Set ' . $region);
        }

        // Mark the layout as having changed
        $layout->status = 0;
        $layout->save();

		$response->SetFormSubmitResponse('');
		$response->hideMessage = true;
		$response->Respond();
	}
	
    /**
     * Delete Region Form
     */
    public function DeleteRegionForm()
    {
        $response = new ResponseManager();

        // Load our region
        $region = \Xibo\Factory\RegionFactory::getById(Kit::GetParam('regionid', _REQUEST, _INT));

        // Do we have permission
        if (!$this->user->checkDeleteable($region))
            trigger_error(__('You do not have permissions to delete this region'), E_USER_ERROR);
		
		// Set some information about the form
        Theme::Set('form_id', 'RegionDeleteForm');
        Theme::Set('form_action', 'index.php?p=timeline&q=DeleteRegion');
        Theme::Set('form_meta', '<input type="hidden" name="regionid" value="' . $region->regionId . '" />');
        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to remove this region? All media files will be unassigned and any context saved to the region itself (such as Text, Tickers) will be lost permanently.'))));

        $response->SetFormRequestResponse(NULL, __('Delete this region?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Region', 'Delete') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Delete'), '$("#RegionDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Shows the Timeline for this region
     * Also shows any Add/Edit options
     */
    function RegionOptions()
    {
        $this->Timeline();
        exit();
    }
	
    /**
     * Adds the media into the region provided
     */
    function AddFromLibrary()
    {
        $response = new ResponseManager();

        // Load the region
        $region = \Xibo\Factory\RegionFactory::getById(Kit::GetParam('regionid', _REQUEST, _INT));

        Debug::Audit('Assigning files to ' . $region);

        // Make sure we have permission to edit this region
        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Media to assign
        $mediaList = \Kit::GetParam('MediaID', _POST, _ARRAY, array());

        if (count($mediaList) <= 0)
            throw new InvalidArgumentException(__('No media to assign'), 25006);

        // Get the playlist for this region
        // TODO: Playlist Implementation
        $playlists = \Xibo\Factory\PlaylistFactory::getByRegionId($region->regionId);
        $playlist = $playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        // Add each media item to the region and save
        // Loop through all the media
        foreach ($mediaList as $mediaId) {
            /* @var int $mediaId */
            $media = \Xibo\Factory\MediaFactory::getById($mediaId);

            if (!$this->user->checkViewable($media))
                trigger_error(__('You do not have permissions to use this media'), E_USER_ERROR);

            // Create a Widget and add it to our region
            $widget = \Xibo\Factory\WidgetFactory::create($this->user->userid, $playlist->playlistId, $media->mediaType, $media->duration);
            $widget->assignMedia($mediaId);

            $playlist->widgets[] = $widget;
        }

        // Save the playlist
        $playlist->save();

        // We want to load a new form
        $response->SetFormSubmitResponse(sprintf(__('%d Media Items Assigned'), count($mediaList)));
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=timeline&regionid=' . $region->regionId . '&q=Timeline';
        $response->Respond();
    }

    /**
	 * Represents the Preview inside the Layout Designer
	 */
	public function RegionPreview()
	{
		// Response Manager
		$response = new ResponseManager();
		
		// Keyed off the region id
		$regionId = \Kit::GetParam('regionid', _POST, _STRING);
		
		$seqGiven = \Kit::GetParam('seq', _POST, _INT, 0);
		$seq = \Kit::GetParam('seq', _POST, _INT, 0);
		$width = \Kit::GetParam('width', _POST, _INT, 0);
        $height = \Kit::GetParam('height', _POST, _INT, 0);
		$scaleOverride = \Kit::GetParam('scale_override', _POST, _DOUBLE, 0);
		
		// The sequence will not be zero based, so adjust it
		$seq--;
		
		// Load our region
        try {
            $region = \Xibo\Factory\RegionFactory::getById($regionId);
            $playlists = \Xibo\Factory\PlaylistFactory::getByRegionId($regionId);

            // Get the first playlist we can find
            // TODO: implement playlists
            $playlist = $playlists[0];
            /* @var \Xibo\Entity\Playlist $playlist */

            // Use the playlist to get the widgets (softly)
            $widgets = \Xibo\Factory\WidgetFactory::getByPlaylistId($playlist->playlistId);

            // We want to load the widget in the given sequence
            if (count($widgets) <= 0) {
                // No media to preview
                $response->extra['text']  = __('Empty Region');
                $response->html = '';
                $response->Respond();
            }

            // Select the widget at the required sequence
            $widget = $widgets[$seq];
            /* @var \Xibo\Entity\Widget $widget */
            $widget->load();

            // Otherwise, output a preview
            $module = \Xibo\Factory\ModuleFactory::createWithWidget($widget, $region);

            $return  = '<div class="regionPreviewOverlay"></div>';
            $return .= $module->Preview($width, $height, $scaleOverride);

            $response->html = $return;
            $response->extra['type'] = $widget->type;
            $response->extra['duration'] = $widget->duration;
            $response->extra['number_items'] = count($widgets);
            $response->extra['text'] = $seqGiven . ' / ' . count($widgets) . ' ' . $module->GetName() . ' lasting ' . $widget->duration . ' seconds';
            $response->extra['current_item'] = $seqGiven;

            $response->Respond();
        }
        catch (\Xibo\Exception\NotFoundException $e) {
            // Log it
            Debug::Error($e->getMessage());

            // No media to preview
            $response->extra['text']  = __('Region cannot be found.');
            $response->html = '';
            $response->Respond();
        }
	}

    /**
     * Set the Module Buttons for a Form
     * @param int $regionId
     * @param int $playlistId
     */
    private function setModuleButtons($regionId, $playlistId)
    {
        // Present a canvas with 2 columns, left column for the media icons
        $buttons = array();

        // Always output a Library assignment button
        $buttons[] = array(
                'id' => 'media_button_library',
                'url' => 'index.php?p=content&q=LibraryAssignForm&regionId=' . $regionId,
                'text' => __('Library')
            );

        // Get a list of the enabled modules and then create buttons for them
        foreach (\Xibo\Factory\ModuleFactory::getAssignableModules() as $module) {
            /* @var \Xibo\Entity\Module $module */
            $url = (($module->regionSpecific == 1) ? 'index.php?p=module&q=Exec&mod=' . $module->type . '&method=AddForm' : 'index.php?p=content&q=fileUploadForm') . '&regionId=' . $regionId . '&playlistId=' . $playlistId;

            $buttons[] = array(
                'id' => 'media_button_' . $module->type,
                'url' => $url,
                'text' => __($module->name)
            );
        }

        Theme::Set('media_buttons', $buttons);
    }

    /**
     * Timeline Form
     */
    public function TimeLine()
    {
        if ($this->user->GetPref('timeLineView') == 'grid')
            $this->TimeLineGrid();
        else
            $this->TimeLineList();
    }

    /**
     * Shows the TimeLine
     */
    public function TimelineList()
    {
        $user =& $this->user;
        $user->SetPref('timeLineView', 'list');
        $response = new ResponseManager();
        $response->html = '';

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _GET, _INT));

        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Start buildings the Timeline List
        $response->html .= '<div class="container-fluid">';
        $response->html .= '<div class="row">';
        // Set the theme module buttons
        $this->setModuleButtons($region->regionId, $region->playlists[0]->playlistId);
        $response->html .= Theme::RenderReturn('layout_designer_form_timeline');

        // Load the XML for this layout and region, we need to get the media nodes.
        // These form the time line and go in the right column

        // Generate an ID for the list (this is passed into the reorder function)
        $timeListMediaListId = uniqid('timelineMediaList_');

        $response->html .= '<div class="col-md-10">';
        $response->html .= '<div id="timelineControl" class="timelineColumn" layoutid="' . $region->layoutId . '" regionid="' . $region->regionId . '">';
        $response->html .= '    <div class="timelineMediaVerticalList">';
        $response->html .= '        <ul id="' . $timeListMediaListId . '" class="timelineSortableListOfMedia">';

        // How are we going to colour the bars, my media type or my permissions
        $timeBarColouring = Config::GetSetting('REGION_OPTIONS_COLOURING');

        // Get the Widgets on this Timeline
        // TODO: Playlist logic
        $playlist = $region->playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        foreach($playlist->widgets as $widget) {
            /* @var \Xibo\Entity\Widget $widget */
            // Put this node vertically in the region time line
            if (!$this->user->checkViewable($widget))
                // Skip over media assignments that we do not have permission to see
                continue;

            // Create a media module to handle all the complex stuff
            $tmpModule = null;
            try {
                $tmpModule = \Xibo\Factory\ModuleFactory::createWithWidget($widget, $region);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }

            $mediaName = $tmpModule->GetName();
            $transitionIn = $tmpModule->GetTransition('in');
            $transitionOut = $tmpModule->GetTransition('out');
            
            // Colouring for the media block
            if ($timeBarColouring == 'Permissions')
                $mediaBlockColouringClass = 'timelineMediaItemColouring_' . (($this->user->checkEditable($widget)) ? 'enabled' : 'disabled');
            else
                $mediaBlockColouringClass = 'timelineMediaItemColouringDefault timelineMediaItemColouring_' . $tmpModule->getModuleType();
            
            // Create the list item
            $response->html .= '<li class="timelineMediaListItem" widgetid="' . $widget->widgetId . '">';
            
            // In transition
            $response->html .= '    <div class="timelineMediaInTransition">';
            
            if ($transitionIn != 'None')
                $response->html .= '<span>' . $transitionIn . '</span>';
            
            $response->html .= '    </div>';
            
            // Media Bar
            $response->html .= '    <div class="timelineMediaItem">';
            $response->html .= '        <ul class="timelineMediaItemLinks">';

            // Create some links
            if ($this->user->checkEditable($widget))
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=EditForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '" title="' . __('Click to edit this media') . '">' . __('Edit') . '</a></li>';

            if ($this->user->checkDeleteable($widget))
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=DeleteForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '" title="' . __('Click to delete this media') . '">' . __('Delete') . '</a></li>';

            if ($this->user->checkPermissionsModifyable($widget))
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=user&q=permissionsForm&entity=Widget&objectId=' . $widget->widgetId . '" title="' . __('Click to change permissions for this media') . '">' . __('Permissions') . '</a></li>';

            if (count($this->user->TransitionAuth('in')) > 0)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=in&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '" title="' . __('Click to edit this transition') . '">' . __('In Transition') . '</a></li>';
            
            if (count($this->user->TransitionAuth('out')) > 0)
                $response->html .= '<li><a class="XiboFormButton timelineMediaBarLink" href="index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=out&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '" title="' . __('Click to edit this transition') . '">' . __('Out Transition') . '</a></li>';
            
            $response->html .= '        </ul>';

            // Put the media name in
            $response->html .= '        <div class="timelineMediaDetails ' . $mediaBlockColouringClass . '">';
            $response->html .= '            <h3>' . sprintf('%s (%d seconds)', $mediaName, $widget->duration) . '</h3>';
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
        $response->AddButton(__('Save Order'), 'XiboTimelineSaveOrder("' . $timeListMediaListId . '","' . $region->regionId . '")');
        $response->AddButton(__('Switch to Grid'), 'XiboSwapDialog("index.php?p=timeline&q=TimelineGrid&regionid=' . $region->regionId . '")');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'RegionOptions') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

        $response->Respond();
    }

    /**
     * Timeline in Grid mode
     */
    public function TimelineGrid() {
        $this->user->SetPref('timeLineView', 'grid');

        $response = new ResponseManager();
        $response->html = '';

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _GET, _INT));

        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Set the theme module buttons
        $this->setModuleButtons($region->regionId, $region->playlists[0]->playlistId);

        $id = uniqid();
        Theme::Set('prepend', '<div class="row">' . Theme::RenderReturn('layout_designer_form_timeline') . '<div class="col-md-10">');
        Theme::Set('append', '</div></div>');
        Theme::Set('header_text', __('Media'));
        Theme::Set('id', $id);
        Theme::Set('form_fields', array());
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="timeline">
            <input type="hidden" name="q" value="TimelineGridView">
            <input type="hidden" name="regionid" value="' . $region->regionId . '">');
        
        // Call to render the template
        $response->html = Theme::RenderReturn('grid_render');

        // Finish constructing the response
        $response->dialogClass = 'modal-big';
        $response->dialogTitle  = __('Region Timeline');
        $response->dialogSize   = true;
        $response->dialogWidth  = '1000px';
        $response->dialogHeight = '550px';
        $response->focusInFirstInput = false;

        // Add some buttons
        $response->AddButton(__('Switch to List'), 'XiboSwapDialog("index.php?p=timeline&q=TimelineList&regionid=' . $region->regionId . '")');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'RegionOptions') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * TimeLine Grid
     */
    public function TimelineGridView()
    {
        $user =& $this->user;
        $response = new ResponseManager();

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _POST, _INT));

        if (!$this->user->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Columns
        $cols = array(
                array('name' => 'order', 'title' => __('Order')),
                array('name' => 'name', 'title' => __('Name')),
                array('name' => 'type', 'title' => __('Type')),
                array('name' => 'duration', 'title' => __('Duration')),
                array('name' => 'transition', 'title' => __('Transition'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();
        $i = 0;

        // Get the Widgets on this Timeline
        // TODO: Playlist logic
        $playlist = $region->playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        Debug::Audit(count($playlist->widgets) . ' widgets on ' . $region);

        foreach($playlist->widgets as $widget) {
            /* @var \Xibo\Entity\Widget $widget */
            // Put this node vertically in the region time line
            if (!$this->user->checkViewable($widget))
                // Skip over media assignments that we do not have permission to see
                continue;

            // Construct an object containing all the layouts, and pass to the theme
            $row = array();

            $i++;

            // Create a media module to handle all the complex stuff
            $tmpModule = null;
            try {
                $tmpModule = \Xibo\Factory\ModuleFactory::createWithWidget($widget, $region);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }

            $mediaName = $tmpModule->GetName();

            $row['order'] = $i;
            $row['name'] = $mediaName;
            $row['type'] = __($tmpModule->getModuleName());
            $row['duration'] = sprintf('%d seconds', $widget->duration);
            $row['transition'] = sprintf('%s / %s', $tmpModule->GetTransition('in'), $tmpModule->GetTransition('out'));

            if ($this->user->checkEditable($widget)) {
                $row['buttons'][] = array(
                        'id' => 'timeline_button_edit',
                        'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=EditForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                        'text' => __('Edit')
                    );
            }

            if ($this->user->checkDeleteable($widget)) {
                $row['buttons'][] = array(
                        'id' => 'timeline_button_delete',
                        'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=DeleteForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                        'text' => __('Remove'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'multiselectlink', 'value' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=DeleteMedia'),
                            array('name' => 'rowtitle', 'value' => $row['name']),
                            array('name' => 'regionid', 'value' => $region->regionId),
                            array('name' => 'lkid', 'value' => $widget->widgetId),
                            array('name' => 'options', 'value' => 'unassign')
                        )
                    );
            }

            if ($this->user->checkPermissionsModifyable($widget)) {
                $row['buttons'][] = array(
                        'id' => 'timeline_button_permissions',
                        'url' => 'index.php?p=user&q=permissionsForm&entity=Widget&objectId=' . $widget->widgetId . '"',
                        'text' => __('Permissions')
                    );
            }

            if (count($this->user->TransitionAuth('in')) > 0) {
                $row['buttons'][] = array(
                        'id' => 'timeline_button_trans_in',
                        'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=in&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                        'text' => __('In Transition')
                    );
            }

            if (count($this->user->TransitionAuth('out')) > 0) {
                $row['buttons'][] = array(
                        'id' => 'timeline_button_trans_in',
                        'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=out&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                        'text' => __('Out Transition')
                    );
            }

            $rows[] = $row;
        }

        // Store the table rows
        Theme::Set('table_rows', $rows);
        Theme::Set('gridId', \Kit::GetParam('gridId', _REQUEST, _STRING));

        // Initialise the theme and capture the output
        $output = Theme::RenderReturn('table_render');
        
        $response->SetGridResponse($output);
        $response->initialSortColumn = 1;
        $response->Respond();
    }

    /**
     * Re-orders a medias regions
     */
    function TimelineReorder()
    {
        $response = new ResponseManager();

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $playlists = \Xibo\Factory\PlaylistFactory::getByRegionId(Kit::GetParam('regionId', _GET, _INT));
        $playlist = $playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        if (!$this->user->checkEditable($playlist))
            trigger_error(__('You do not have permissions to edit this playlist'), E_USER_ERROR);

        // Load the widgets
        $playlist->load();

        // Create a list of media
        $widgetList = \Kit::GetParam('widgetIds', _POST, _ARRAY_INT);
        if (count($widgetList) <= 0)
            trigger_error(__('No widgets to reorder'), E_USER_ERROR);

        Debug::Audit($playlist . ' reorder to ' . var_export($widgetList, true));

        // Go through each one and move it
        $i = 0;
        foreach ($widgetList as $widgetId) {
            $i++;
            // Find this item in the existing list and add it to our new order
            foreach ($playlist->widgets as $widget) {
                /* @var \Xibo\Entity\Widget $widget */
                Debug::Audit('Comparing ' . $widget . ' with ' . $widgetId);
                if ($widget->getId() == $widgetId) {
                    Debug::Audit('Setting Display Order ' . $i . ' on widgetId ' . $widgetId);
                    $widget->displayOrder = $i;
                    $widget->save();
                    break;
                }
            }
        }

        $response->SetFormSubmitResponse(__('Order Changed'));
        $response->keepOpen = true;
        $response->Respond();
    }
}
