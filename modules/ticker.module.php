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
class ticker extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'ticker';
    
        // Must call the parent class   
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/moment.js');
        $media->addModuleFile('modules/preview/vendor/jquery.marquee.min.js');
        $media->addModuleFile('modules/preview/vendor/jquery-cycle-2.1.6.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFile('modules/preview/xibo-text-render.js');
    }

    /** 
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/theme/ticker/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        Debug::Audit(count($this->settings['templates']));
    }
    
    /**
     * Return the Add Form as HTML
     * @return 
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();
        $db         =& $this->db;
        $user       =& $this->user;
                
        // Would like to get the regions width / height 
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // Augment settings with templates
        $this->loadTemplates();

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        $formFields = array();
        $formFields[] = FormManager::AddCombo(
                    'sourceid', 
                    __('Source Type'), 
                    NULL,
                    array(array('sourceid' => '1', 'source' => __('Feed')), array('sourceid' => '2', 'source' => __('DataSet'))),
                    'sourceid',
                    'source',
                    __('The source for this Ticker'), 
                    's');

        $formFields[] = FormManager::AddText('uri', __('Feed URL'), NULL, 
            __('The Link for the RSS feed'), 'f', '', 'feed-fields');

        $datasets = $user->DataSetList();
        array_unshift($datasets, array('datasetid' => '0', 'dataset' => 'None'));
        Theme::Set('dataset_field_list', $datasets);

        $formFields[] = FormManager::AddCombo(
                    'datasetid', 
                    __('DataSet'), 
                    NULL,
                    $datasets,
                    'datasetid',
                    'dataset',
                    __('Please select the DataSet to use as a source of data for this ticker.'), 
                    'd', 'dataset-fields');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this should be displayed'), 'd', 'required');

        Theme::Set('form_fields', $formFields);

        // Field dependencies
        $sourceFieldDepencies_1 = array(
                '.feed-fields' => array('display' => 'block'),
                '.dataset-fields' => array('display' => 'none'),
            );

        $sourceFieldDepencies_2 = array(
                '.feed-fields' => array('display' => 'none'),
                '.dataset-fields' => array('display' => 'block'),
            );

        $this->response->AddFieldAction('sourceid', 'init', 1, $sourceFieldDepencies_1);
        $this->response->AddFieldAction('sourceid', 'change', 1, $sourceFieldDepencies_1);
        $this->response->AddFieldAction('sourceid', 'init', 2, $sourceFieldDepencies_2);
        $this->response->AddFieldAction('sourceid', 'change', 2, $sourceFieldDepencies_2);
                
        // Return
        $this->response->html = Theme::RenderReturn('form_render');
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
        $this->response = new ResponseManager();
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

        // Augment settings with templates
        $this->loadTemplates();

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        
        $formFields = array();

        // What is the source for this ticker?
        $sourceId = $this->GetOption('sourceId');
        $dataSetId = $this->GetOption('datasetid');

        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('template', __('Appearance'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('format', __('Format'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $field_name = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        $field_duration = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        // Common fields
        $oldDirection = $this->GetOption('direction');
        
        if ($oldDirection == 'single')
            $oldDirection = 'fade';
        else if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $fieldFx = FormManager::AddCombo(
                'effect', 
                __('Effect'), 
                $this->GetOption('effect', $oldDirection),
                array(
                    array('effectid' => 'none', 'effect' => __('None')), 
                    array('effectid' => 'fade', 'effect' => __('Fade')),
                    array('effectid' => 'fadeout', 'effect' => __('Fade Out')),
                    array('effectid' => 'scrollHorz', 'effect' => __('Scroll Horizontal')),
                    array('effectid' => 'scrollVert', 'effect' => __('Scroll Vertical')),
                    array('effectid' => 'flipHorz', 'effect' => __('Flip Horizontal')),
                    array('effectid' => 'flipVert', 'effect' => __('Flip Vertical')),
                    array('effectid' => 'shuffle', 'effect' => __('Shuffle')),
                    array('effectid' => 'tileSlide', 'effect' => __('Tile Slide')),
                    array('effectid' => 'tileBlind', 'effect' => __('Tile Blinds')),
                    array('effectid' => 'marqueeLeft', 'effect' => __('Marquee Left')),
                    array('effectid' => 'marqueeRight', 'effect' => __('Marquee Right')),
                    array('effectid' => 'marqueeUp', 'effect' => __('Marquee Up')),
                    array('effectid' => 'marqueeDown', 'effect' => __('Marquee Down')),
                ),
                'effectid',
                'effect',
                __('Please select the effect that will be used to transition between items. If all items should be output, select None. Marquee effects are CPU intensive and may not be suitable for lower power displays.'), 
                'e');

        $fieldScrollSpeed = FormManager::AddNumber('speed', __('Speed'), $this->GetOption('speed', $this->GetOption('scrollSpeed')), 
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        $fieldBackgroundColor = FormManager::AddText('backgroundColor', __('Background Colour'), $this->GetOption('backgroundColor'), 
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'background-color-group');

        $field_itemsPerPage = FormManager::AddNumber('itemsPerPage', __('Items per page'), $this->GetOption('itemsPerPage'), 
            __('When in single mode how many items per page should be shown.'), 'p');

        $field_updateInterval = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 5), 
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

        $field_durationIsPerItem = FormManager::AddCheckbox('durationIsPerItem', __('Duration is per item'), 
            $this->GetOption('durationIsPerItem'), __('The duration specified is per item otherwise it is per feed.'), 
            'i');

        $field_itemsSideBySide = FormManager::AddCheckbox('itemsSideBySide', __('Show items side by side?'), 
            $this->GetOption('itemsSideBySide'), __('Should items be shown side by side?'), 
            's');

        // Data Set Source
        if ($sourceId == 2) {

            $formFields['general'][] = $field_name;
            $formFields['general'][] = $field_duration;
            $formFields['general'][] = $fieldFx;
            $formFields['general'][] = $fieldScrollSpeed;
            $formFields['advanced'][] = $fieldBackgroundColor;
            $formFields['advanced'][] = $field_durationIsPerItem;
            $formFields['advanced'][] = $field_updateInterval;

            // Extra Fields for the DataSet
            $formFields['general'][] = FormManager::AddText('ordering', __('Order'), $this->GetOption('ordering'),
                __('Please enter a SQL clause for how this dataset should be ordered'), 'o');

            $formFields['general'][] = FormManager::AddText('filter', __('Filter'), $this->GetOption('filter'), 
                __('Please enter a SQL clause to filter this DataSet.'), 'f');

            $formFields['advanced'][] = FormManager::AddNumber('lowerLimit', __('Lower Row Limit'), $this->GetOption('lowerLimit'), 
                __('Please enter the Lower Row Limit for this DataSet (enter 0 for no limit)'), 'l');

            $formFields['advanced'][] = FormManager::AddNumber('upperLimit', __('Upper Row Limit'), $this->GetOption('upperLimit'), 
                __('Please enter the Upper Row Limit for this DataSet (enter 0 for no limit)'), 'u');

            $formFields['format'][] = $field_itemsPerPage;
            $formFields['format'][] = $field_itemsSideBySide;

            Theme::Set('columns', $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d ", $dataSetId)));

            $formFields['template'][] = FormManager::AddRaw(Theme::RenderReturn('media_form_ticker_dataset_edit'));
        }
        else {
            // Extra Fields for the Ticker
            $formFields['general'][] = FormManager::AddText('uri', __('Feed URL'), urldecode($this->GetOption('uri')), 
                __('The Link for the RSS feed'), 'f');

            $formFields['general'][] = $field_name;
            $formFields['general'][] = $field_duration;
            $formFields['general'][] = $fieldFx;
            $formFields['format'][] = $fieldScrollSpeed;

            // Add a field for RTL tickers
            $formFields['format'][] = FormManager::AddCombo(
                    'textDirection', 
                    __('Text direction'), 
                    $this->GetOption('textDirection'),
                    array(
                        array('textdirectionid' => 'ltr', 'textdirection' => __('Left to Right (LTR)')),
                        array('textdirectionid' => 'rtl', 'textdirection' => __('Right to Left (RTL)'))
                    ),
                    'textdirectionid',
                    'textdirection',
                    __('Which direction does the text in the feed use? (left to right or right to left)'), 
                    'd');

            $formFields['advanced'][] = $fieldBackgroundColor;
            
            $formFields['format'][] = FormManager::AddNumber('numItems', __('Number of Items'), $this->GetOption('numItems'), 
                __('The Number of RSS items you want to display'), 'o');

            $formFields['format'][] = $field_itemsPerPage;

            $formFields['advanced'][] = FormManager::AddText('copyright', __('Copyright'), $this->GetOption('copyright'), 
                __('Copyright information to display as the last item in this feed. This can be styled with the #copyright CSS selector.'), 'f');

            $formFields['advanced'][] = $field_updateInterval;

            $formFields['format'][] = FormManager::AddCombo(
                    'takeItemsFrom', 
                    __('Take items from the '), 
                    $this->GetOption('takeItemsFrom'),
                    array(
                        array('takeitemsfromid' => 'start', 'takeitemsfrom' => __('Start of the Feed')),
                        array('takeitemsfromid' => 'end', 'takeitemsfrom' => __('End of the Feed'))
                    ),
                    'takeitemsfromid',
                    'takeitemsfrom',
                    __('Take the items from the beginning or the end of the list'), 
                    't');

            $formFields['format'][] = $field_durationIsPerItem;
            $formFields['advanced'][] = $field_itemsSideBySide;

            $formFields['advanced'][] = FormManager::AddText('dateFormat', __('Date Format'), $this->GetOption('dateFormat'), 
                __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

            $subs = array(
                    array('Substitute' => 'Name'),
                    array('Substitute' => 'Title'),
                    array('Substitute' => 'Description'),
                    array('Substitute' => 'Date'),
                    array('Substitute' => 'Content'),
                    array('Substitute' => 'Copyright'),
                    array('Substitute' => 'Link'),
                    array('Substitute' => 'PermaLink'),
                    array('Substitute' => 'Tag|Namespace')
                );
            Theme::Set('substitutions', $subs);

            $formFieldSubs = FormManager::AddRaw(Theme::RenderReturn('media_form_ticker_edit'));

            $formFields['advanced'][] = FormManager::AddText('allowedAttributes', __('Allowable Attributes'), $this->GetOption('allowedAttributes'), 
                __('A comma separated list of attributes that should not be stripped from the incoming feed.'), '');

            $formFields['advanced'][] = FormManager::AddText('stripTags', __('Strip Tags'), $this->GetOption('stripTags'), 
                __('A comma separated list of HTML tags that should be stripped from the feed in addition to the default ones.'), '');

            $formFields['advanced'][] = FormManager::AddCheckbox('disableDateSort', __('Disable Date Sort'), $this->GetOption('disableDateSort'),
                __('Should the date sort applied to the feed be disabled?'), '');

            // Encode up the template
            //$formFields['advanced'][] = FormManager::AddMessage('<pre>' . htmlentities(json_encode(array('id' => 'media-rss-with-title', 'value' => 'Image overlaid with the Title', 'template' => '<div class="image">[Link|image]<div class="cycle-overlay"><p style="font-family: Arial, Verdana, sans-serif; font-size:48px;">[Title]</p></div></div>', 'css' => '.image img { width:100%;}.cycle-overlay {color: white;background: black;opacity: .6;filter: alpha(opacity=60);position: absolute;bottom: 0;width: 100%;padding: 15px;text-align:center;}'))) . '</pre>');
        }

        // Get the CSS node
        $formFields['template'][] = FormManager::AddMultiText('ta_css', NULL, $this->GetRawNode('css'), 
            __('Optional Style sheet'), 's', 10, NULL, 'template-override-controls');

        // Get the Text Node out of this
        $formFields['template'][] = FormManager::AddMultiText('ta_text', NULL, $this->GetRawNode('template'),
            __('Enter the template. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10, NULL, 'template-override-controls');

        // RSS
        if ($this->GetOption('sourceId') == 1) {

            // Append the templates to the response
            $this->response->extra = $this->settings['templates'];
            
            $formFields['template'][] = $formFieldSubs;

            // Add a field for whether to override the template or not.
            // Default to 1 so that it will work correctly with old items (that didn't have a template selected at all)
            $formFields['template'][] = FormManager::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate', 1), 
            __('Tick if you would like to override the template.'), 'o');

            // Template - for standard stuff
            $formFields['template'][] = FormManager::AddCombo('templateId', __('Template'), $this->GetOption('templateId', 'title-only'), 
                $this->settings['templates'], 
                'id', 
                'value', 
                __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

            // Add some field dependencies
            // When the override template check box is ticked, we want to expose the advanced controls and we want to hide the template selector
            $this->response->AddFieldAction('overrideTemplate', 'init', false, 
                array(
                    '.template-override-controls' => array('display' => 'none'),
                    '.template-selector-control' => array('display' => 'block')
                ), 'is:checked');
            $this->response->AddFieldAction('overrideTemplate', 'change', false, 
                array(
                    '.template-override-controls' => array('display' => 'none'),
                    '.template-selector-control' => array('display' => 'block')
                ), 'is:checked');
            $this->response->AddFieldAction('overrideTemplate', 'init', true, 
                array(
                    '.template-override-controls' => array('display' => 'block'),
                    '.template-selector-control' => array('display' => 'none')
                ), 'is:checked');
            $this->response->AddFieldAction('overrideTemplate', 'change', true, 
                array(
                    '.template-override-controls' => array('display' => 'block'),
                    '.template-selector-control' => array('display' => 'none')
                ), 'is:checked');
        }

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', array_reverse($formFields['template']));
        Theme::Set('form_fields_format', $formFields['format']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Generate the Response
        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->callBack   = 'text_callback';
        $this->response->dialogTitle = __('Edit Ticker');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }
    
    /**
     * Add Media to the Database
     * @return 
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;
        
        // Other properties
        $sourceId = Kit::GetParam('sourceid', _POST, _INT);
        $uri = Kit::GetParam('uri', _POST, _URI);
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);
        $duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        
        // Must have a duration
        if ($duration == 0)
            trigger_error(__('Please enter a duration'), E_USER_ERROR);

        if ($sourceId == 1) {
            // Feed
            
            // Validate the URL
            if ($uri == "" || $uri == "http://")
                trigger_error(__('Please enter a Link for this Ticker'), E_USER_ERROR);
        }
        else if ($sourceId == 2) {
            // DataSet
            
            // Validate Data Set Selected
            if ($dataSetId == 0)
                trigger_error(__('Please select a DataSet'), E_USER_ERROR);

            // Check we have permission to use this DataSetId
            if (!$this->user->DataSetAuth($dataSetId))
                trigger_error(__('You do not have permission to use that dataset'), E_USER_ERROR);
        }
        else {
            // Only supported two source types at the moment
            trigger_error(__('Unknown Source Type'));
        }
        
        // Required Attributes
        $this->mediaid  = md5(uniqid());
        $this->duration = $duration;
        
        // Any Options
        $this->SetOption('xmds', true);
        $this->SetOption('sourceId', $sourceId);
        $this->SetOption('uri', $uri);
        $this->SetOption('datasetid', $dataSetId);
        $this->SetOption('updateInterval', 120);
        $this->SetOption('speed', 2);

        // New tickers have template override set to 0 by add.
        // the edit form can then default to 1 when the element doesn't exist (for legacy)
        $this->SetOption('overrideTemplate', 0);

        $this->SetRaw('<template><![CDATA[]]></template><css><![CDATA[]]></css>');
        
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
        $this->response = new ResponseManager();
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        $sourceId = $this->GetOption('sourceId', 1);
        
        // Other properties
        $uri          = Kit::GetParam('uri', _POST, _URI);
		$name = Kit::GetParam('name', _POST, _STRING);
        $text         = Kit::GetParam('ta_text', _POST, _HTMLSTRING);
        $css = Kit::GetParam('ta_css', _POST, _HTMLSTRING);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT, 360);
        $copyright    = Kit::GetParam('copyright', _POST, _STRING);
        $numItems = Kit::GetParam('numItems', _POST, _STRING);
        $takeItemsFrom = Kit::GetParam('takeItemsFrom', _POST, _STRING);
        $durationIsPerItem = Kit::GetParam('durationIsPerItem', _POST, _CHECKBOX);
        $itemsSideBySide = Kit::GetParam('itemsSideBySide', _POST, _CHECKBOX);
        
        // DataSet Specific Options
        $itemsPerPage = Kit::GetParam('itemsPerPage', _POST, _INT);
        $upperLimit = Kit::GetParam('upperLimit', _POST, _INT);
        $lowerLimit = Kit::GetParam('lowerLimit', _POST, _INT);
        $filter = Kit::GetParam('filter', _POST, _STRINGSPECIAL);
        $ordering = Kit::GetParam('ordering', _POST, _STRING);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        
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
        $this->SetOption('effect', Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('copyright', $copyright);
        $this->SetOption('speed', Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('uri', $uri);
        $this->SetOption('numItems', $numItems);
        $this->SetOption('takeItemsFrom', $takeItemsFrom);
        $this->SetOption('durationIsPerItem', $durationIsPerItem);
        $this->SetOption('itemsSideBySide', $itemsSideBySide);
        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('itemsPerPage', $itemsPerPage);
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('allowedAttributes', Kit::GetParam('allowedAttributes', _POST, _STRING));
        $this->SetOption('stripTags', Kit::GetParam('stripTags', _POST, _STRING));
        $this->SetOption('backgroundColor', Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('disableDateSort', Kit::GetParam('disableDateSort', _POST, _CHECKBOX));
        $this->SetOption('textDirection', Kit::GetParam('textDirection', _POST, _WORD));
        $this->SetOption('overrideTemplate', Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('templateId', Kit::GetParam('templateId', _POST, _WORD));
        
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
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
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
     * Get Resource
     */
    public function GetResource($displayId = 0)
    {
        // Load in the template
        if ($this->layoutSchemaVersion == 1)
            $template = file_get_contents('modules/preview/Html4TransitionalTemplate.html');
        else
            $template = file_get_contents('modules/preview/HtmlTemplate.html');

        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // What is the data source for this ticker?
        $sourceId = $this->GetOption('sourceId', 1);

        // Information from the Module
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

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->GetOption('direction', 'none');
        
        if ($oldDirection == 'single')
            $oldDirection = 'fade';
        else if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->GetOption('effect', $oldDirection);

        $options = array(
            'type' => $this->type,
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
            'numItems' => $numItems,
            'takeItemsFrom' => $takeItemsFrom,
            'itemsPerPage' => $itemsPerPage,
            'speed' => $this->GetOption('speed'),
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // Generate a JSON string of substituted items.
        if ($sourceId == 2) {
            $items = $this->GetDataSetItems($displayId, $isPreview, $text);
        }
        else {
            $items = $this->GetRssItems($isPreview, $text);
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

        // Replace and Control Meta options
        $template = str_replace('<!--[[[CONTROLMETA]]]-->', '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->', $template);

        // Replace the head content
        $headContent  = '';

        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        if ($this->GetOption('textDirection') == 'rtl') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' #content { direction: rtl; }';
            $headContent .= '</style>';   
        }

        if ($this->GetOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->GetOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Add some scripts to the JavaScript Content
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    private function GetRssItems($isPreview, $text) {

        // Make sure we have the cache location configured
        $file = new File($this->db);
        File::EnsureLibraryExists();

        // Make sure we have a $media/$layout object to use
        $media = new Media();
        $layout = new Layout();

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        Debug::LogEntry('audit', 'Loading SimplePie to handle RSS parsing.' . urldecode($this->GetOption('uri')));
        
        // Use SimplePie to get the feed
        include_once('3rdparty/simplepie/autoloader.php');

        $feed = new SimplePie();
        $feed->set_cache_location($file->GetLibraryCacheUri());
        $feed->set_feed_url(urldecode($this->GetOption('uri')));
        $feed->force_feed(true);
        $feed->set_cache_duration(($this->GetOption('updateInterval', 3600) * 60));
        $feed->handle_content_type();

        // Get a list of allowed attributes
        if ($this->GetOption('allowedAttributes') != '') {
            $attrsStrip = array_diff($feed->strip_attributes, explode(',', $this->GetOption('allowedAttributes')));
            //Debug::Audit(var_export($attrsStrip, true));
            $feed->strip_attributes($attrsStrip);
        }

        // Disable date sorting?
        if ($this->GetOption('disableDateSort') == 1) {
            $feed->enable_order_by_date(false);
        }

        // Init
        $feed->init();

        $dateFormat = $this->GetOption('dateFormat');

        if ($feed->error()) {
            Debug::LogEntry('audit', 'Feed Error: ' . $feed->error());
            return array();
        }

        // Set an expiry time for the media
        $expires = time() + ($this->GetOption('updateInterval', 3600) * 60);

        // Store our formatted items
        $items = array();

        foreach ($feed->get_items() as $item) {
            /* @var SimplePie_Item $item */

            // Substitute for all matches in the template
            $rowString = $text;
            
            // Substitute
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Pick the appropriate column out
                if (strstr($sub, '|') !== false) {
                    // Use the provided name space to extract a tag
                    $attribs = NULL;
                    if (substr_count($sub, '|') > 1)
                        list($tag, $namespace, $attribs) = explode('|', $sub);
                    else
                        list($tag, $namespace) = explode('|', $sub);

                    // What are we looking at
                    Debug::Audit('Namespace: ' . str_replace(']', '', $namespace) . '. Tag: ' . str_replace('[', '', $tag) . '. ');

                    // Are we an image place holder?
                    if (strstr($namespace, 'image') != false) {
                        // Try to get a link for the image
                        $link = null;

                        switch (str_replace('[', '', $tag)) {
                            case 'Link':
                                if ($enclosure = $item->get_enclosure()) {
                                    // Use the link to get the image
                                    $link = $enclosure->get_link();
                                }
                                break;

                            default:
                                // Default behaviour just tries to get the content from the tag provided (without a name space).
                                $tags = $item->get_item_tags('', str_replace('[', '', $tag));

                                if ($tags != null) {
                                    $link = (is_array($tags)) ? $tags[0]['data'] : '';
                                }
                        }

			if ($link == NULL) {
				$dom = new DOMDocument;
				$dom->loadHTML($item->get_content()); // Full
				$images = $dom->getElementsByTagName('img');
				foreach ($images as $key => $value) {
				    if($key == 0) { $link = html_entity_decode($images->item($key)->getAttribute('src')); }
				}
			}

                        if ($link == NULL) {
                                $dom = new DOMDocument;
                                $dom->loadHTML($item->get_description()); //Summary
                                $images = $dom->getElementsByTagName('img');
                                foreach ($images as $key => $value) {
                                    if($key == 0) { $link = html_entity_decode($images->item($key)->getAttribute('src')); }
                                }
                        }

                        // If we have managed to resolve a link, download it and replace the tag with the downloaded
                        // image url
                        if ($link != NULL) {
                            // Grab the profile image
                            $file = $media->addModuleFileFromUrl($link, 'ticker_' . md5($this->GetOption('url') . $link), $expires);

                            // Tag this layout with this file
                            $layout->AddLk($this->layoutid, 'module', $file['mediaId']);

                            $replace = ($isPreview) ? '<img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $file['mediaId'] . '" ' . $attribs . '/>' : '<img src="' . $file['storedAs'] . '" ' . $attribs . ' />';
                        }
                    }
                    else {
                        $tags = $item->get_item_tags(str_replace(']', '', $namespace), str_replace('[', '', $tag));
                        
                        Debug::LogEntry('audit', 'Tags:' . var_export($tags, true));

                        // If we find some tags then do the business with them
                        if ($tags != NULL) {
                            if ($attribs != NULL)
                                $replace = (is_array($tags)) ? $tags[0]['attribs'][''][str_replace(']', '', $attribs)] : '';
                            else
                                $replace = (is_array($tags)) ? $tags[0]['data'] : '';
                        }
                    }
                }
                else {
                    
                    // Use the pool of standard tags
                    switch ($sub) {
                        case '[Name]':
                            $replace = $this->GetOption('name');
                            break;

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
                            $replace = DateManager::getLocalDate($item->get_date('U'), $dateFormat);
                            break;

                        case '[PermaLink]':
                            $replace = $item->get_permalink();
                            break;

                        case '[Link]':
                            $replace = $item->get_link();
                            break;
                    }
                

			if ($this->GetOption('stripTags') != '') {
				require_once '3rdparty/htmlpurifier/library/HTMLPurifier.auto.php';

				$config = HTMLPurifier_Config::createDefault();
				$config->set('HTML.ForbiddenElements', array_merge($feed->strip_htmltags, explode(',', $this->GetOption('stripTags'))));
				$purifier = new HTMLPurifier($config);
				$replace = $purifier->purify($replace);
			}

		}
                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);
            }

            $items[] = $rowString;
        }

        // Copyright information?
        if ($this->GetOption('copyright', '') != '') {
            $items[] = '<span id="copyright">' . $this->GetOption('copyright') . '</span>';
        }

        // Return the formatted items
        return $items;
    }

    private function GetDataSetItems($displayId, $isPreview, $text) {

        $db =& $this->db;

        // Extra fields for data sets
        $dataSetId = $this->GetOption('datasetid');
        $upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $filter = $this->GetOption('filter');
        $ordering = $this->GetOption('ordering');

        Debug::LogEntry('audit', 'Then template for each row is: ' . $text);

        // Set an expiry time for the media
        $media = new Media();
        $layout = new Layout();
        $expires = time() + ($this->GetOption('updateInterval', 3600) * 60);

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
        $dataSet = new DataSet($db);
        if (!$dataSetResults = $dataSet->DataSetResults($dataSetId, implode(',', $columnIds), $filter, $ordering, $lowerLimit, $upperLimit, $displayId)) {
            return;
        }

        // Create an array of header|datatypeid pairs
        $columnMap = array();
        foreach ($dataSetResults['Columns'] as $col) {
            $columnMap[$col['Text']] = $col;
        }

        Debug::Audit(var_export($columnMap, true));

        $items = array();

        foreach ($dataSetResults['Rows'] as $row) {
            // For each row, substitute into our template
            $rowString = $text;

            foreach ($matches[1] as $sub) {
                // Pick the appropriate column out
                $subs = explode('|', $sub);

                // The column header
                $header = $subs[0];
                $replace = $row[$header];

                // Check in the columns array to see if this is a special one
                if ($columnMap[$header]['DataTypeID'] == 4) {
                    // Download the image, alter the replace to wrap in an image tag
                    $file = $media->addModuleFileFromUrl(str_replace(' ', '%20', htmlspecialchars_decode($replace)), 'ticker_dataset_' . md5($dataSetId . $columnMap[$header]['DataSetColumnID'] . $replace), $expires);

                    // Tag this layout with this file
                    $layout->AddLk($this->layoutid, 'module', $file['mediaId']);

                    $replace = ($isPreview) ? '<img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';
                }
                
                $rowString = str_replace('[' . $sub . ']', $replace, $rowString);
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
