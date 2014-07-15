<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner and James Packer
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
class ticker extends Module
{
	public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
	{
		// Must set the type of the class
		$this->type = 'ticker';
                $this->displayType = 'Ticker';
	
		// Must call the parent class	
		parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
	}
	
	/**
	 * Return the Add Form as HTML
	 * @return 
	 */
	public function AddForm()
	{
		$db 		=& $this->db;
		$user		=& $this->user;
				
		// Would like to get the regions width / height 
		$layoutid	= $this->layoutid;
		$regionid	= $this->regionid;
		$rWidth		= Kit::GetParam('rWidth', _REQUEST, _STRING);
		$rHeight	= Kit::GetParam('rHeight', _REQUEST, _STRING);

    	Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        // Source list
        Theme::Set('source_field_list', array(array('sourceid' => '1', 'source' => 'Feed'),array('sourceid' => '2', 'source' => 'DataSet')));

		// Data set list
		$datasets = $user->DataSetList();
		array_unshift($datasets, array('datasetid' => '0', 'dataset' => 'None'));
        Theme::Set('dataset_field_list', $datasets);
		        
		// Return
		$this->response->html = Theme::RenderReturn('media_form_ticker_add');
		$this->response->dialogTitle = __('Add New Ticker');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

		return $this->response;
	}
	
	/**
	 * Return the Edit Form as HTML
	 * @return 
	 */
	public function EditForm()
	{
		$db =& $this->db;
		
		$layoutid = $this->layoutid;
		$regionid = $this->regionid;
		$mediaid = $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        
		// What is the source for this ticker?
        $sourceId = $this->GetOption('sourceId');
        $dataSetId = $this->GetOption('datasetid');
        Theme::Set('sourceId', $sourceId);

        // Data Set Source
        if ($sourceId == 2) {
        	// Extra Fields for the DataSet
        	Theme::Set('columns', $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d ", $dataSetId)));
        	Theme::Set('upperLimit', $this->GetOption('upperLimit'));
	        Theme::Set('lowerLimit', $this->GetOption('lowerLimit'));
	        Theme::Set('filter', $this->GetOption('filter'));
	        Theme::Set('ordering', $this->GetOption('ordering'));
        }
        else {
        	// Extra Fields for the Ticker
        	$subs = array(
        			array('Substitute' => 'Title'),
        			array('Substitute' => 'Description'),
        			array('Substitute' => 'Content'),
        			array('Substitute' => 'Copyright'),
        			array('Substitute' => 'Link'),
        			array('Substitute' => 'PermaLink'),
        			array('Substitute' => 'Tag|Namespace')
        		);
        	Theme::Set('substitutions', $subs);
        }

        // Direction Options
        $directionOptions = array(
            array('directionid' => 'none', 'direction' => __('None')), 
            array('directionid' => 'left', 'direction' => __('Left')), 
            array('directionid' => 'right', 'direction' => __('Right')), 
            array('directionid' => 'up', 'direction' => __('Up')), 
            array('directionid' => 'down', 'direction' => __('Down')),
            array('directionid' => 'single', 'direction' => __('Single'))
        );
        Theme::Set('direction_field_list', $directionOptions);

    	// "Take from" Options
    	$takeItemsFrom = array(
    		array('takeitemsfromid' => 'start', 'takeitemsfrom' => __('Start of the Feed')),
    		array('takeitemsfromid' => 'end', 'takeitemsfrom' => __('End of the Feed'))
		);
        Theme::Set('takeitemsfrom_field_list', $takeItemsFrom);

        // Set up the variables we already have
		Theme::Set('name', $this->GetOption('name'));
		Theme::Set('direction', $this->GetOption('direction'));
		Theme::Set('copyright', $this->GetOption('copyright'));
		Theme::Set('scrollSpeed', $this->GetOption('scrollSpeed'));
		Theme::Set('updateInterval', $this->GetOption('updateInterval'));
		Theme::Set('uri', urldecode($this->GetOption('uri')));
		Theme::Set('numItems', $this->GetOption('numItems'));
		Theme::Set('takeItemsFrom', $this->GetOption('takeItemsFrom'));
		Theme::Set('itemsPerPage', $this->GetOption('itemsPerPage'));
		Theme::Set('datasetid', $this->GetOption('datasetid'));

		// Checkboxes
		Theme::Set('fitTextChecked', ($this->GetOption('fitText', 0) == 0) ? '' : ' checked');
		Theme::Set('itemsSideBySideChecked', ($this->GetOption('itemsSideBySide', 0) == 0) ? '' : ' checked');
        Theme::Set('durationIsPerItemChecked', ($this->GetOption('durationIsPerItem') == 1) ? ' checked' : '');
        
		// Get the text out of RAW
		$rawXml = new DOMDocument();
		$rawXml->loadXML($this->GetRaw());
		
		Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());
		
		// Get the Text Node out of this
		$textNodes = $rawXml->getElementsByTagName('template');
		$textNode = $textNodes->item(0);
		Theme::Set('text', $textNode->nodeValue);

		// Get the CSS node
		$cssNodes = $rawXml->getElementsByTagName('css');
		if ($cssNodes->length > 0) {
			$cssNode = $cssNodes->item(0);
			Theme::Set('css', $cssNode->nodeValue);
		}
		else {
			Theme::Set('css', '');
		}
                
        // Duration
        Theme::Set('duration', $this->duration);
        Theme::Set('is_duration_enabled', ($this->auth->modifyPermissions) ? '' : ' readonly');
        
        // Output the form
        if ($sourceId == 2) {
        	$this->response->html = Theme::RenderReturn('media_form_ticker_dataset_edit');
        }
        else {
        	$this->response->html = Theme::RenderReturn('media_form_ticker_edit');
        }

        // Generate the Response
        $this->response->callBack 	= 'text_callback';
        $this->response->dialogTitle = __('Edit Ticker');
    	$this->response->dialogClass = 'modal-big';

    	if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
	}
	
	/**
	 * Add Media to the Database
	 * @return 
	 */
	public function AddMedia()
	{
		$db =& $this->db;
		
		$layoutid = $this->layoutid;
		$regionid = $this->regionid;
		$mediaid = $this->mediaid;
		
		// Other properties
		$sourceId = Kit::GetParam('sourceid', _POST, _INT);
		$uri = Kit::GetParam('uri', _POST, _URI);
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);
		$duration = Kit::GetParam('duration', _POST, _INT, 0);
		$template = '';
		
		// Must have a duration
		if ($duration == 0)
			trigger_error(__('Please enter a duration'), E_USER_ERROR);

		// Required Attributes
		$this->mediaid	= md5(uniqid());
		$this->duration = $duration;

		// Data Source
		if ($sourceId == 1) {
			// Feed
			
			// Validate the URL
			if ($uri == "" || $uri == "http://")
				trigger_error(__('Please enter a Link for this Ticker'), E_USER_ERROR);

			$template = '<p><span style="font-size:22px;"><span style="color:#FFFFFF;">[Title]</span></span></p>';
		}
		else if ($sourceId == 2) {
			// DataSet
			
			// Validate Data Set Selected
			if ($dataSetId == 0)
				trigger_error(__('Please select a DataSet'), E_USER_ERROR);

			// Check we have permission to use this DataSetId
	        if (!$this->user->DataSetAuth($dataSetId))
	            trigger_error(__('You do not have permission to use that dataset'), E_USER_ERROR);

	        // Link
	        Kit::ClassLoader('dataset');
	        $dataSet = new DataSet($db);
        	$dataSet->LinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);
		}
		else {
			// Only supported two source types at the moment
			trigger_error(__('Unknown Source Type'));
		}
		
		// Any Options
		$this->SetOption('xmds', true);
		$this->SetOption('sourceId', $sourceId);
		$this->SetOption('uri', $uri);
		$this->SetOption('datasetid', $dataSetId);
		$this->SetOption('updateInterval', 120);
		$this->SetOption('scrollSpeed', 2);

		$this->SetRaw('<template><![CDATA[' . $template . ']]></template><css><![CDATA[]]></css>');
		
		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'ticker');
		
		if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=module&mod=ticker&q=Exec&method=EditForm&layoutid=$this->layoutid&regionid=$regionid&mediaid=$this->mediaid";
        }
		
		return $this->response;
	}
	
	/**
	 * Edit Media in the Database
	 * @return 
	 */
	public function EditMedia()
	{
		$db 		=& $this->db;
		
		$layoutid 	= $this->layoutid;
		$regionid 	= $this->regionid;
		$mediaid	= $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        $sourceId = $this->GetOption('sourceId', 1);
		
		// Other properties
		$uri		  = Kit::GetParam('uri', _POST, _URI);
		$name = Kit::GetParam('name', _POST, _STRING);
		$direction	  = Kit::GetParam('direction', _POST, _WORD, 'none');
		$text		  = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
		$css = Kit::GetParam('ta_css', _POST, _HTMLSTRING);
		$scrollSpeed  = Kit::GetParam('scrollSpeed', _POST, _INT, 2);
		$updateInterval = Kit::GetParam('updateInterval', _POST, _INT, 360);
		$copyright	  = Kit::GetParam('copyright', _POST, _STRING);
		$numItems = Kit::GetParam('numItems', _POST, _STRING);
		$takeItemsFrom = Kit::GetParam('takeItemsFrom', _POST, _STRING);
		$durationIsPerItem = Kit::GetParam('durationIsPerItem', _POST, _CHECKBOX);
        $fitText = Kit::GetParam('fitText', _POST, _CHECKBOX);
        $itemsSideBySide = Kit::GetParam('itemsSideBySide', _POST, _CHECKBOX);
        
        // DataSet Specific Options
		$itemsPerPage = Kit::GetParam('itemsPerPage', _POST, _INT);
        $upperLimit = Kit::GetParam('upperLimit', _POST, _INT);
        $lowerLimit = Kit::GetParam('lowerLimit', _POST, _INT);
        $filter = Kit::GetParam('filter', _POST, _STRINGSPECIAL);
        $ordering = Kit::GetParam('ordering', _POST, _STRING);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);
		
		// Validation
		if ($text == '')
		{
			$this->response->SetError('Please enter some text');
			$this->response->keepOpen = true;
			return $this->response;
		}

		if ($sourceId == 1) {
			// Feed
			
			// Validate the URL
			if ($uri == "" || $uri == "http://")
				trigger_error(__('Please enter a Link for this Ticker'), E_USER_ERROR);
		}
		else if ($sourceId == 2) {
			// Make sure we havent entered a silly value in the filter
			if (strstr($filter, 'DESC'))
				trigger_error(__('Cannot user ordering criteria in the Filter Clause'), E_USER_ERROR);

			if (!is_numeric($upperLimit) || !is_numeric($lowerLimit))
	            trigger_error(__('Limits must be numbers'), E_USER_ERROR);

	        if ($upperLimit < 0 || $lowerLimit < 0)
	            trigger_error(__('Limits cannot be lower than 0'), E_USER_ERROR);

	        // Check the bounds of the limits
	        if ($upperLimit < $lowerLimit)
	        	trigger_error(__('Upper limit must be higher than lower limit'), E_USER_ERROR);
		}
		
		if ($this->duration == 0)
		{
			$this->response->SetError('You must enter a duration.');
			$this->response->keepOpen = true;
			return $this->response;
		}

        if ($numItems != '')
        {
            // Make sure we have a number in here
            if (!is_numeric($numItems))
            {
                $this->response->SetError(__('The value in Number of Items must be numeric.'));
				$this->response->keepOpen = true;
				return $this->response;
            }
        }

		if ($updateInterval < 0)
            trigger_error(__('Update Interval must be greater than or equal to 0'), E_USER_ERROR);
		
		// Any Options
		$this->SetOption('xmds', true);
		$this->SetOption('name', $name);
		$this->SetOption('direction', $direction);
		$this->SetOption('copyright', $copyright);
		$this->SetOption('scrollSpeed', $scrollSpeed);
		$this->SetOption('updateInterval', $updateInterval);
		$this->SetOption('uri', $uri);
        $this->SetOption('numItems', $numItems);
        $this->SetOption('takeItemsFrom', $takeItemsFrom);
		$this->SetOption('durationIsPerItem', $durationIsPerItem);
        $this->SetOption('fitText', $fitText);
        $this->SetOption('itemsSideBySide', $itemsSideBySide);
        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('itemsPerPage', $itemsPerPage);
        
        // Text Template
		$this->SetRaw('<template><![CDATA[' . $text . ']]></template><css><![CDATA[' . $css . ']]></css>');
		
		// Should have built the media object entirely by this time
		// This saves the Media Object to the Region
		$this->UpdateRegion();
		
		//Set this as the session information
		setSession('content', 'type', 'ticker');
		
		if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }
		
		return $this->response;	
	}

	public function DeleteMedia() {

		$dataSetId = $this->GetOption('datasetid');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($this->db);
        $dataSet->UnlinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        return parent::DeleteMedia();
    }

	public function GetName() {
		return $this->GetOption('name');
	}

    public function HoverPreview()
    {
        $msgName = __('Name');
        $msgType = __('Type');
        $msgUrl = __('Source');
        $msgDuration = __('Duration');

        $name = $this->GetOption('name');
        $url = urldecode($this->GetOption('uri'));
        $sourceId = $this->GetOption('sourceId', 1);

        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="thumbnail"><img alt="' . $this->displayType . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif"></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . $msgType . ': ' . $this->displayType . '</li>';
        $output .= '    <li>' . $msgName . ': ' . $name . '</li>';

        if ($sourceId == 2)
        	$output .= '    <li>' . $msgUrl . ': DataSet</li>';
        else
        	$output .= '    <li>' . $msgUrl . ': <a href="' . $url . '" target="_blank" title="' . $msgUrl . '">' . $url . '</a></li>';


        $output .= '    <li>' . $msgDuration . ': ' . $this->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Preview
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        $layoutId = $this->layoutid;
        $regionId = $this->regionid;

        $mediaId = $this->mediaid;
        $lkId = $this->lkid;
        $mediaType = $this->type;
        $mediaDuration = $this->duration;

        $widthPx	= $width.'px';
        $heightPx	= $height.'px';

        return '<iframe scrolling="no" src="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=GetResource&raw=true&preview=true&scale_override=1&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    /**
     * Get Resource
     */
    public function GetResource($displayId = 0)
    {
    	// Load the HtmlTemplate
		$template = file_get_contents('modules/preview/HtmlTemplateForGetResource.html');

        // What is the data source for this ticker?
        $sourceId = $this->GetOption('sourceId', 1);

        // Information from the Module
        $direction = $this->GetOption('direction');
        $scrollSpeed = $this->GetOption('scrollSpeed');
        $fitText = $this->GetOption('fitText', 0);
        $itemsSideBySide = $this->GetOption('itemsSideBySide', 0);
        $duration = $this->duration;
        $durationIsPerItem = $this->GetOption('durationIsPerItem', 0);
        $numItems = $this->GetOption('numItems', 0);
        $takeItemsFrom = $this->GetOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->GetOption('itemsPerPage', 0);

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node
        $textNodes = $rawXml->getElementsByTagName('template');
        $textNode = $textNodes->item(0);
        $text = $textNode->nodeValue;

        // Get the CSS Node
        $cssNodes = $rawXml->getElementsByTagName('css');

        if ($cssNodes->length > 0) {
        	$cssNode = $cssNodes->item(0);
        	$css = $cssNode->nodeValue;
        }
        else {
        	$css = '';
        }

        $options = array('type' => 'ticker',
        	'sourceid' => $sourceId,
        	'direction' => $direction,
        	'duration' => $duration,
        	'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
        	'numItems' => $numItems,
        	'takeItemsFrom' => $takeItemsFrom,
        	'itemsPerPage' => $itemsPerPage,
        	'scrollSpeed' => $scrollSpeed,
        	'scaleMode' => (($fitText == 0) ? 'scale' : 'fit'),
        	'originalWidth' => $this->width,
        	'originalHeight' => $this->height,
        	'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
        	'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
    	);

        // Generate a JSON string of substituted items.
        if ($sourceId == 2) {
			$items = $this->GetDataSetItems($displayId, $text);
        }
        else {
        	$items = $this->GetRssItems($text);
        }

        // Return empty string if there are no items to show.
        if (count($items) == 0)
        	return '';

        // Work out how many pages we will be showing.
        $pages = $numItems;

        if ($numItems > count($items) || $numItems == 0)
        	$pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        $controlMeta = array('numItems' => $pages, 'totalDuration' => $totalDuration);

        // Replace and Control Meta options
        $template = str_replace('<!--[[[CONTROLMETA]]]-->', '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->', $template);

        // Replace the head content
        $headContent  = '<script type="text/javascript">';
        $headContent .= '   function init() { ';
        $headContent .= '       $("body").xiboRender(options, items);';
        $headContent .= '   } ';
        $headContent .= '	var options = ' . json_encode($options) . ';';
        $headContent .= '	var items = ' . json_encode($items) . ';';
        $headContent .= '</script>';

        if ($itemsSideBySide == 1) {
	        $headContent .= '<style type="text/css">';
	        $headContent .= ' .item, .page { float: left; }';
	        $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
        	$headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Replace the View Port Width?
    	if (isset($_GET['preview']))
        	$template = str_replace('[[ViewPortWidth]]', $this->width . 'px', $template);

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    private function GetRssItems($text) {

    	// Make sure we have the cache location configured
    	Kit::ClassLoader('file');
    	$file = new File($this->db);
    	$file->EnsureLibraryExists();

    	// Parse the text template
    	$matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        Debug::LogEntry('audit', 'Loading SimplePie to handle RSS parsing');
    	
    	// Use SimplePie to get the feed
    	include_once('3rdparty/simplepie/autoloader.php');

    	$feed = new SimplePie();
    	$feed->set_cache_location($file->GetLibraryCacheUri());
    	$feed->set_feed_url(urldecode($this->GetOption('uri')));
    	$feed->set_cache_duration(($this->GetOption('updateInterval', 3600) * 60));
    	$feed->handle_content_type();
    	$feed->init();

    	if ($feed->error()) {
        	Debug::LogEntry('audit', 'Feed Error: ' . $feed->error());
        	return array();
        }

    	// Store our formatted items
    	$items = array();

    	foreach ($feed->get_items() as $item) {

    		// Substitute for all matches in the template
			$rowString = $text;
        	
        	// Substitite
        	foreach ($matches[0] as $sub) {
        		$replace = '';

    			// Pick the appropriate column out
    			if (strstr($sub, '|') !== false) {
    				// Use the provided namespace to extract a tag
    				list($tag, $namespace) = explode('|', $sub);

    				$tags = $item->get_item_tags(str_replace(']', '', $namespace), str_replace('[', '', $tag));
        			$replace = (is_array($tags)) ? $tags[0]['data'] : '';
    			}
    			else {
    				
    				// Use the pool of standard tags
    				switch ($sub) {
    					case '[Title]':
    						$replace = $item->get_title();
    						break;

    					case '[Description]':
							$replace = $item->get_description();
    						break;

    					case '[Content]':
    						$replace = $item->get_content();
    						break;

						case '[Copyright]':
							$replace = $item->get_copyright();
    						break;

    					case '[Date]':
    						$replace = $item->get_local_date();
    						break;

    					case '[PermaLink]':
    						$replace = $item->get_permalink();
    						break;

    					case '[Link]':
    						$replace = $item->get_link();
    						break;
    				}
    			}

    			// Substitute the replacement we have found (it might be '')
				$rowString = str_replace($sub, $replace, $rowString);
        	}

        	$items[] = $rowString;
    	}

    	// Return the formatted items
    	return $items;
    }

    private function GetDataSetItems($displayId, $text) {

    	$db =& $this->db;

    	// Extra fields for data sets
    	$dataSetId = $this->GetOption('datasetid');
    	$upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $filter = $this->GetOption('filter');
        $ordering = $this->GetOption('ordering');

        Debug::LogEntry('audit', 'Then template for each row is: ' . $text);

        // Combine the column id's with the dataset data
        $matches = '';
        preg_match_all('/\[(.*?)\]/', $text, $matches);

        $columnIds = array();
        
        foreach ($matches[1] as $match) {
        	// Get the column id's we are interested in
        	Debug::LogEntry('audit', 'Matched column: ' . $match);

        	$col = explode('|', $match);
        	$columnIds[] = $col[1];
        }

        // Get the dataset results
        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($db);
        $dataSetResults = $dataSet->DataSetResults($dataSetId, implode(',', $columnIds), $filter, $ordering, $lowerLimit, $upperLimit, $displayId, true /* Associative */);

        $items = array();

        foreach ($dataSetResults['Rows'] as $row) {
        	// For each row, substitute into our template
        	$rowString = $text;

        	foreach ($matches[1] as $sub) {
    			// Pick the appropriate column out
    			$subs = explode('|', $sub);

        		$rowString = str_replace('[' . $sub . ']', $row[$subs[0]], $rowString);
        	}

        	$items[] = $rowString;
        }

        return $items;
    }
	
    public function IsValid() {
    	// Can't be sure because the client does the rendering
    	return ($this->GetOption('xmds')) ? 1 : 2;
    }
}
?>