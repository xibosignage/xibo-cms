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
 *
 */ 
include_once('modules/3rdparty/emoji.php');

class Twitter extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '', $typeOverride = NULL) {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = ($typeOverride == NULL) ? 'twitter' : $typeOverride;

        // This is the code schema version, it should be 1 for a new module and should be incremented each time the 
        // module data structure changes.
        // It is used to install / update your module and to put updated modules down to the display clients.
        $this->codeSchemaVersion = 1;
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Install or Update this module
     */
    public function InstallOrUpdate()
    {
        // This function should update the `module` table with information about your module.
        // The current version of the module in the database can be obtained in $this->schemaVersion
        // The current version of this code can be obtained in $this->codeSchemaVersion
        
        // $settings will be made available to all instances of your module in $this->settings. These are global settings to your module, 
        // not instance specific (i.e. not settings specific to the layout you are adding the module to).
        // $settings will be collected from the Administration -> Modules CMS page.
        // 
        // Layout specific settings should be managed with $this->SetOption in your add / edit forms.
        
        if ($this->schemaVersion <= 1) {
            // Install
            $this->InstallModule('Twitter', 'Twitter Search Module', 'forms/library.gif', 1, 1, array());
        }
        else {
            // Update
            // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)" with the updated items
        }

        // Check we are all installed
        $this->InstallFiles();

        // After calling either Install or Update your code schema version will match the database schema version and this method will not be called
        // again. This means that if you want to change those fields in an update to your module, you will need to increment your codeSchemaVersion.
    }

    public function InstallFiles()
    {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/xibo-text-render.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFile('modules/theme/twitter/emoji.css');
        $media->addModuleFile('modules/theme/twitter/emoji.png');
    }

    /** 
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/theme/twitter/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        Debug::Audit(count($this->settings['templates']));
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm()
    {
        // API Key
        $formFields[] = FormManager::AddText('apiKey', __('API Key'), $this->GetSetting('apiKey'), 
            __('Enter your API Key from Twitter.'), 'a', 'required');

        // API Secret
        $formFields[] = FormManager::AddText('apiSecret', __('API Secret'), $this->GetSetting('apiSecret'), 
            __('Enter your API Secret from Twitter.'), 's', 'required');
        
        // Cache Period
        $formFields[] = FormManager::AddText('cachePeriod', __('Cache Period'), $this->GetSetting('cachePeriod', 300), 
            __('Enter the number of seconds you would like to cache twitter search results.'), 'c', 'required');
        
        // Cache Period Images
        $formFields[] = FormManager::AddText('cachePeriodImages', __('Cache Period for Images'), $this->GetSetting('cachePeriodImages', 24), 
            __('Enter the number of hours you would like to cache twitter images.'), 'i', 'required');

        // Present an error message if we don't have the required extension enabled. Don't prevent further configuration.
        if (!extension_loaded('curl')) {
            $formFields[] = FormManager::AddMessage(__('The php-curl extension is required for the Twitter Module and it does not appear to be enabled on this CMS. Please enable it before using this module.'), 'alert alert-danger');
        }
        
        return $formFields;
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings()
    {
        // Process any module settings you asked for.
        $apiKey = Kit::GetParam('apiKey', _POST, _STRING, '');

        if ($apiKey == '')
            $this->ThrowError(__('Missing API Key'));

        // Process any module settings you asked for.
        $apiSecret = Kit::GetParam('apiSecret', _POST, _STRING, '');

        if ($apiSecret == '')
            $this->ThrowError(__('Missing API Secret'));

        $this->settings['apiKey'] = $apiKey;
        $this->settings['apiSecret'] = $apiSecret;
        $this->settings['cachePeriod'] = Kit::GetParam('cachePeriod', _POST, _INT, 300);
        $this->settings['cachePeriodImages'] = Kit::GetParam('cachePeriodImages', _POST, _INT, 24);

        // Return an array of the processed settings.
        return $this->settings;
    }
    
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();

        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // The CMS provides the region width and height in case they are needed
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // Augment settings with templates
        $this->loadTemplates();

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('template', __('Template'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('effect', __('Effect'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields['general'][] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = FormManager::AddText('searchTerm', __('Search Term'), NULL, 
            __('Search term. You can test your search term in the twitter.com search box first.'), 's', 'required');

        // Type
        $formFields['general'][] = FormManager::AddCombo('resultType', __('Type'), 'mixed',
            array(
                array('typeid' => 'mixed', 'type' => __('Mixed')), 
                array('typeid' => 'recent', 'type' => __('Recent')),
                array('typeid' => 'popular', 'type' => __('Popular')),
            ),
            'typeid',
            'type', 
            __('Recent shows only the most recent tweets, Popular the most popular and Mixed includes both popular and recent results.'), 't', 'required');

        // Distance
        $formFields['general'][] = FormManager::AddNumber('tweetDistance', __('Distance'), NULL,
            __('Distance in miles that the tweets should be returned from. Set to 0 for no restrictions.'), 'd');

        // Distance
        $formFields['general'][] = FormManager::AddNumber('tweetCount', __('Count'), 15, 
            __('The number of Tweets to return.'), 'c');

        // Common fields
        $formFields['effect'][] = FormManager::AddCombo(
                'effect', 
                __('Effect'), 
                $this->GetOption('effect'),
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

        $formFields['effect'][] = FormManager::AddNumber('speed', __('Speed'), NULL, 
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['advanced'][] = FormManager::AddText('backgroundColor', __('Background Colour'), NULL, 
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'background-color-group');

        // Field empty
        $formFields['advanced'][] = FormManager::AddText('noTweetsMessage', __('No tweets'), NULL, 
            __('A message to display when there are no tweets returned by the search query'), 'n');

        // Date format
        $formFields['advanced'][] = FormManager::AddText('dateFormat', __('Date Format'), 'd M',
            __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), 60,
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');
        
        // Template - for standard stuff
        $formFields['template'][] = FormManager::AddCombo('templateId', __('Template'), $this->GetOption('templateId', 'tweet-only'), 
            $this->settings['templates'], 
            'id', 
            'value', 
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        // Add a field for whether to override the template or not.
        // Default to 1 so that it will work correctly with old items (that didn't have a template selected at all)
        $formFields['template'][] = FormManager::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate', 0), 
        __('Tick if you would like to override the template.'), 'o');
        
        // Add a text template
        $formFields['template'][] = FormManager::AddMultiText('ta_text', NULL, $this->GetRawNode('template'), 
            __('Enter the template. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10, NULL, 'template-override-controls');
        
        // Field for the style sheet (optional)
        $formFields['template'][] = FormManager::AddMultiText('ta_css', NULL, $this->GetRawNode('styleSheet'), 
            __('Optional Stylesheet'), 's', 10, NULL, 'template-override-controls');

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

        // Present an error message if the module has not been configured. Don't prevent further configuration.
        if (!extension_loaded('curl') || $this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            $formFields['general'][] = FormManager::AddMessage(__('The Twitter Widget has not been configured yet, please ask your CMS Administrator to look at it for you.'), 'alert alert-danger');
        }

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', $formFields['template']);
        Theme::Set('form_fields_effect', $formFields['effect']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Set the field dependencies
        $this->setFieldDepencencies();
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __($this->displayType);
        $this->response->callBack = 'text_callback';
        // Append the templates to the response
        $this->response->extra = $this->settings['templates'];
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();

        // Same member variables as the Form call, except with POST variables for your form fields.
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // You are required to set a media id, which should be unique.
        $this->mediaid  = md5(Kit::uniqueId());

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        // You should validate all form input using the Kit::GetParam helper classes
        if (Kit::GetParam('searchTerm', _POST, _STRING) == '') {
            $this->response->SetError(__('Please enter a search term'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('searchTerm', Kit::GetParam('searchTerm', _POST, _STRING));
        $this->SetOption('effect', Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noTweetsMessage', Kit::GetParam('noTweetsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('resultType', Kit::GetParam('resultType', _POST, _STRING));
        $this->SetOption('tweetDistance', Kit::GetParam('tweetDistance', _POST, _INT));
        $this->SetOption('tweetCount', Kit::GetParam('tweetCount', _POST, _INT));
        $this->SetRaw('<template><![CDATA[' . Kit::GetParam('ta_text', _POST, _HTMLSTRING) . ']]></template><styleSheet><![CDATA[' . Kit::GetParam('ta_css', _POST, _HTMLSTRING) . ']]></styleSheet>');
        $this->SetOption('overrideTemplate', Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('templateId', Kit::GetParam('templateId', _POST, _WORD));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();

        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // The CMS provides the region width and height in case they are needed
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // Augment settings with templates
        $this->loadTemplates();

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $this->mediaid . '">');
    
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('template', __('Appearance'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = FormManager::AddTab('effect', __('Effect'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields['general'][] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        // Duration
        $formFields['general'][] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        // Search Term
        $formFields['general'][] = FormManager::AddText('searchTerm', __('Search Term'), $this->GetOption('searchTerm'), 
            __('Search term. You can test your search term in the twitter.com search box first.'), 's', 'required');

        // Type
        $formFields['general'][] = FormManager::AddCombo('resultType', __('Type'), $this->GetOption('resultType'),
            array(
                array('typeid' => 'mixed', 'type' => __('Mixed')), 
                array('typeid' => 'recent', 'type' => __('Recent')),
                array('typeid' => 'popular', 'type' => __('Popular')),
            ),
            'typeid',
            'type', 
            __('Recent shows only the most recent tweets, Popular the most popular and Mixed includes both popular and recent results.'), 't', 'required');

        // Distance
        $formFields['general'][] = FormManager::AddNumber('tweetDistance', __('Distance'), $this->GetOption('tweetDistance'), 
            __('Distance in miles that the tweets should be returned from. Set to 0 for no restrictions.'), 'd');

        // Distance
        $formFields['general'][] = FormManager::AddNumber('tweetCount', __('Count'), $this->GetOption('tweetCount'), 
            __('The number of Tweets to return.'), 'c');

        // Common fields
        $formFields['effect'][] = FormManager::AddCombo(
                'effect', 
                __('Effect'), 
                $this->GetOption('effect'),
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

        $formFields['effect'][] = FormManager::AddNumber('speed', __('Speed'), $this->GetOption('speed'), 
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['advanced'][] = FormManager::AddText('backgroundColor', __('Background Colour'), $this->GetOption('backgroundColor'), 
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'background-color-group');

        // Field empty
        $formFields['advanced'][] = FormManager::AddText('noTweetsMessage', __('No tweets'), $this->GetOption('noTweetsMessage'), 
            __('A message to display when there are no tweets returned by the search query'), 'n');

        $formFields['advanced'][] = FormManager::AddText('dateFormat', __('Date Format'), $this->GetOption('dateFormat'),
            __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

        $formFields['advanced'][] = FormManager::AddCheckbox('removeUrls', __('Remove URLs?'), $this->GetOption('removeUrls', 1), 
            __('Should URLs be removed from the Tweet Text. Most URLs do not compliment digital signage.'), 'u');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 60),
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

        // Encode up the template
        if (Config::GetSetting('SERVER_MODE') == 'Test' && $this->user->usertypeid == 1)
            $formFields['advanced'][] = FormManager::AddMessage('<pre>' . htmlentities(json_encode(array('id' => 'ID', 'value' => 'TITLE', 'template' => $this->GetRawNode('template'), 'css' => $this->GetRawNode('styleSheet')))) . '</pre>');

        // Template - for standard stuff
        $formFields['template'][] = FormManager::AddCombo('templateId', __('Template'), $this->GetOption('templateId', 'tweet-only'), 
            $this->settings['templates'], 
            'id', 
            'value', 
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        // Add a field for whether to override the template or not.
        // Default to 1 so that it will work correctly with old items (that didn't have a template selected at all)
        $formFields['template'][] = FormManager::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate', 0), 
        __('Tick if you would like to override the template.'), 'o');
        
        // Add a text template
        $formFields['template'][] = FormManager::AddMultiText('ta_text', NULL, $this->GetRawNode('template'), 
            __('Enter the template. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10, NULL, 'template-override-controls');
        
        // Field for the style sheet (optional)
        $formFields['template'][] = FormManager::AddMultiText('ta_css', NULL, $this->GetRawNode('styleSheet'), 
            __('Optional Stylesheet'), 's', 10, NULL, 'template-override-controls');

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

        // Present an error message if the module has not been configured. Don't prevent further configuration.
        if (!extension_loaded('curl') || $this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            $formFields['general'][] = FormManager::AddMessage(__('The Twitter Widget has not been configured yet, please ask your CMS Administrator to look at it for you.'), 'alert alert-danger');
        }

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', $formFields['template']);
        Theme::Set('form_fields_effect', $formFields['effect']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Set the field dependencies
        $this->setFieldDepencencies();

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->dialogTitle = __($this->displayType);
        $this->response->callBack = 'text_callback';
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        // Append the templates to the response
        $this->response->extra = $this->settings['templates'];
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        // The response must be returned.
        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $this->response = new ResponseManager();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit) {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        // You should validate all form input using the Kit::GetParam helper classes
        if (Kit::GetParam('searchTerm', _POST, _STRING) == '') {
            $this->response->SetError(__('Please enter a search term'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('searchTerm', Kit::GetParam('searchTerm', _POST, _STRING));
        $this->SetOption('effect', Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noTweetsMessage', Kit::GetParam('noTweetsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('resultType', Kit::GetParam('resultType', _POST, _STRING));
        $this->SetOption('tweetDistance', Kit::GetParam('tweetDistance', _POST, _INT));
        $this->SetOption('tweetCount', Kit::GetParam('tweetCount', _POST, _INT));
        $this->SetOption('removeUrls', Kit::GetParam('removeUrls', _POST, _CHECKBOX));
        $this->SetOption('overrideTemplate', Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('templateId', Kit::GetParam('templateId', _POST, _WORD));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));

        // Text Template
        $this->SetRaw('<template><![CDATA[' . Kit::GetParam('ta_text', _POST, _HTMLSTRING) . ']]></template><styleSheet><![CDATA[' . Kit::GetParam('ta_css', _POST, _HTMLSTRING) . ']]></styleSheet>');
        
        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    private function setFieldDepencencies()
    {
        // Add a dependency
        $this->response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'none'), '.background-color-group' => array('display' => 'none')));
        $this->response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'none'), '.background-color-group' => array('display' => 'none')));
        $this->response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'block'), '.background-color-group' => array('display' => 'block')), 'not');
        $this->response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'block'), '.background-color-group' => array('display' => 'block')), 'not');
    }

    protected function getToken() {

        // Prepare the URL
        $url = 'https://api.twitter.com/oauth2/token';

        // Prepare the consumer key and secret
        $key = base64_encode(urlencode($this->GetSetting('apiKey')) . ':' . urlencode($this->GetSetting('apiSecret')));

        // Check to see if we have the bearer token already cached
        if (Cache::has('bearer_' . $key)) {
            Debug::Audit('Bearer Token served from cache');
            return Cache::get('bearer_' . $key);
        }

        Debug::Audit('Bearer Token served from API');

        // Shame - we will need to get it.
        // and store it.
        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                    'POST /oauth2/token HTTP/1.1',
                    'Authorization: Basic ' . $key, 
                    'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                    'Content-Length: 29'
                ),
            CURLOPT_USERAGENT => 'Xibo Twitter Module',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array('grant_type' => 'client_credentials')),
            CURLOPT_URL => $url,
        );

        // Proxy support
        if (Config::GetSetting('PROXY_HOST') != '' && !Config::isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = Config::GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = Config::GetSetting('PROXY_PORT');

            if (Config::GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = Config::GetSetting('PROXY_AUTH');
        }

        $curl = curl_init();

        // Set options
        curl_setopt_array($curl, $httpOptions);

        // Call exec
        if (!$result = curl_exec($curl)) {
            // Log the error
            Debug::Error('Error contacting Twitter API: ' . curl_error($curl));
            return false;
        }

        // We want to check for a 200
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] != 200) {
            Debug::Error('Twitter API returned ' . $result . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            Debug::Error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // See if we can parse the body as JSON.
        $body = json_decode($result);

        // We have a 200 - therefore we want to think about caching the bearer token
        // First, lets check its a bearer token
        if ($body->token_type != 'bearer') {
            Debug::Error('Twitter API returned OK, but without a bearer token. ' . var_export($body, true));
            return false;
        }

        // It is, so lets cache it
        // long times...
        Cache::put('bearer_' . $key, $body->access_token, 100000);

        return $body->access_token;
    }

    protected function searchApi($token, $term, $resultType = 'mixed', $geoCode = '', $count = 15)
    {
        // Construct the URL to call
        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        $queryString = '?q=' . urlencode(trim($term)) . 
            '&result_type=' . $resultType . 
            '&count=' . $count . 
            '&include_entities=true';

        if ($geoCode != '')
            $queryString .= '&geocode=' . $geoCode;

        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                    'GET /1.1/search/tweets.json' . $queryString . 'HTTP/1.1',
                    'Host: api.twitter.com',
                    'Authorization: Bearer ' . $token
                ),
            CURLOPT_USERAGENT => 'Xibo Twitter Module',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url . $queryString,
        );

        // Proxy support
        if (Config::GetSetting('PROXY_HOST') != '' && !Config::isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = Config::GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = Config::GetSetting('PROXY_PORT');

            if (Config::GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = Config::GetSetting('PROXY_AUTH');
        }

        Debug::Audit('Calling API with: ' . $url . $queryString);

        $curl = curl_init();
        curl_setopt_array($curl, $httpOptions);
        $result = curl_exec($curl);

        // Get the response headers
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] == 0) {
            // Unable to connect
            Debug::Error('Unable to reach twitter api.');
            return false;
        }
        else if ($outHeaders['http_code'] != 200) {
            Debug::Error('Twitter API returned ' . $outHeaders['http_code'] . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            Debug::Error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // Parse out header and body
        $body = json_decode($result);

        return $body;
    }

    protected function getTwitterFeed($displayId = 0, $isPreview = true)
    {
        if (!extension_loaded('curl')) {
            trigger_error(__('cURL extension is required for Twitter'));
            return false;
        }

        // Do we need to add a geoCode?
        $geoCode = '';
        $distance = $this->GetOption('tweetDistance');
        if ($distance != 0) {
            // Use the display ID or the default.
            if ($displayId != 0) {
                // Look up the lat/long
                $display = new Display();
                $display->displayId = $displayId;
                $display->Load();

                $defaultLat = $display->latitude;
                $defaultLong = $display->longitude;
            }
            else {
                $defaultLat = Config::GetSetting('DEFAULT_LAT');
                $defaultLong = Config::GetSetting('DEFAULT_LONG');
            }

            // Built the geoCode string.
            $geoCode = implode(',', array($defaultLat, $defaultLong, $distance)) . 'mi';
        }

        // Connect to twitter and get the twitter feed.
        $key = md5($this->GetOption('searchTerm') . $this->GetOption('resultType') . $this->GetOption('tweetCount', 15) . $geoCode);
        
        if (!Cache::has($key) || Cache::get($key) == '') {

            Debug::Audit('Querying API for ' . $this->GetOption('searchTerm'));

            // We need to search for it
            if (!$token = $this->getToken())
                return false;

            // We have the token, make a tweet
            if (!$data = $this->searchApi($token, $this->GetOption('searchTerm'), $this->GetOption('resultType'), $geoCode, $this->GetOption('tweetCount', 15)))
                return false;

            // Cache it
            Cache::put($key, $data, $this->GetSetting('cachePeriod'));
        }
        else {
            Debug::Audit('Served from Cache');
            $data = Cache::get($key);
        }

        Debug::Audit(var_export(json_encode($data), true));

        // Get the template
        $template = $this->GetRawNode('template');

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $template, $matches);

        // Build an array to return
        $return = array();

        // Media Object to get profile images
        $media = new Media();
        $layout = new Layout();

        // Expiry time for any media that is downloaded
        $expires = time() + ($this->GetSetting('cachePeriodImages') * 60 * 60);
        
        // Remove URL setting
        $removeUrls = $this->GetOption('removeUrls', 1);

        // If we have nothing to show, display a no tweets message.
        if (count($data->statuses) <= 0) {
            // Create ourselves an empty tweet so that the rest of the code can continue as normal
            $user = new stdClass();
            $user->name = '';
            $user->screen_name = '';
            $user->profile_image_url = '';

            $tweet = new stdClass();
            $tweet->text = $this->GetOption('noTweetsMessage', __('There are no tweets to display'));
            $tweet->created_at = date("Y-m-d H:i:s");
            $tweet->user = $user;

            // Append to our statuses
            $data->statuses[] = $tweet;
        }

        // This should return the formatted items.
        foreach ($data->statuses as $tweet) {
            // Substitute for all matches in the template
            $rowString = $template;

            foreach ($matches[0] as $sub) {
                // Always clear the stored template replacement
                $replace = '';

                // Maybe make this more generic?
                switch ($sub) {
                    case '[Tweet]':
                        // Get the tweet text to operate on
                        $tweetText = $tweet->text;

                        // Replace URLs with their display_url before removal
                        if (isset($tweet->entities->urls)) {
                            foreach ($tweet->entities->urls as $url) {
                                $tweetText = str_replace($url->url, $url->display_url, $tweetText);
                            }
                        }

                        // Handle URL removal if requested
                        if ($removeUrls == 1) {
                            $tweetText = preg_replace("((https?|ftp|gopher|telnet|file|notes|ms-help):((\/\/)|(\\\\))+[\w\d:#\@%\/;$()~_?\+-=\\\.&]*)", '', $tweetText);
                        }

                        $replace = emoji_unified_to_html($tweetText);
                        break;

                    case '[User]':
                        $replace = $tweet->user->name;
                        break;

                    case '[ScreenName]':
                        $replace = $tweet->user->screen_name;
                        break;

                    case '[Date]':
                        $replace = date($this->GetOption('dateFormat', Config::GetSetting('DATE_FORMAT')), DateManager::getDateFromGregorianString($tweet->created_at));
                        break;

                    case '[ProfileImage]':
                        // Grab the profile image
                        if ($tweet->user->profile_image_url != '') {
                            $file = $media->addModuleFileFromUrl($tweet->user->profile_image_url, 'twitter_' . $tweet->user->id, $expires);

                            // Tag this layout with this file
                            $layout->AddLk($this->layoutid, 'module', $file['mediaId']);

                            $replace = ($isPreview) ? '<img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';
                        }
                        break;

                    case '[Photo]':
                        // See if there are any photos associated with this tweet.
                        if (isset($tweet->entities->media) && count($tweet->entities->media) > 0) {
                            // Only take the first one
                            $photoUrl = $tweet->entities->media[0]->media_url;

                            if ($photoUrl != '') {
                                $file = $media->addModuleFileFromUrl($photoUrl, 'twitter_photo_' . $tweet->user->id . '_' . $tweet->entities->media[0]->id_str, $expires);
                                $replace = ($isPreview) ? '<img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';

                                // Tag this layout with this file
                                $layout->AddLk($this->layoutid, 'module', $file['mediaId']);
                            }
                        }

                        break;

                    default:
                        $replace = '';
                }

                $rowString = str_replace($sub, $replace, $rowString);
            }

            // Substitute the replacement we have found (it might be '')
            $return[] = $rowString;
        }
        
        // Return the data array
        return $return;
    }

    /**
     * GetResource
     *     Return the rendered resource to be used by the client (or a preview)
     *     for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return string
     */
    public function GetResource($displayId = 0)
    {
        // Make sure we are set up correctly
        if ($this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            Debug::Error('Twitter Module not configured. Missing API Keys');
            return '';
        }

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Information from the Module
        $duration = $this->duration;
        
        // Generate a JSON string of substituted items.
        $items = $this->getTwitterFeed($displayId, $isPreview);

        // Return empty string if there are no items to show.
        if (count($items) == 0)
            return '';

        $options = array(
            'type' => $this->type,
            'fx' => $this->GetOption('effect', 'none'),
            'speed' => $this->GetOption('speed', 500),
            'duration' => $duration,
            'durationIsPerItem' => ($this->GetOption('durationIsPerItem', 0) == 1),
            'numItems' => count($items),
            'itemsPerPage' => 1,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // Replace the control meta with our data from twitter
        $controlMeta = '<!-- NUMITEMS=' . count($items) . ' -->' . PHP_EOL . '<!-- DURATION=' . ($this->GetOption('durationIsPerItem', 0) == 0 ? $duration : ($duration * count($items))) . ' -->';
        $template = str_replace('<!--[[[CONTROLMETA]]]-->', $controlMeta, $template);

        // Replace the head content
        $headContent  = '';

        // Add the CSS if it isn't empty
        $css = $this->GetRawNode('styleSheet');
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        $backgroundColor = $this->GetOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<link href="' . (($isPreview) ? 'modules/theme/twitter/' : '') . 'emoji.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Add some scripts to the JavaScript Content
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        // Need the cycle plugin?
        if ($this->GetOption('effect') != 'none') {
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        }

        // Need the marquee plugin?
        if (stripos($this->GetOption('effect'), 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . Kit::jsonEncode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    public function HoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();

        // You can add anything you like to this, or completely replace it

        return $output;
    }
    
    public function IsValid() {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
?>
