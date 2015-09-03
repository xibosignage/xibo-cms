<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
class Finance extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '', $typeOverride = NULL) {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = ($typeOverride == NULL) ? 'finance' : $typeOverride;

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
            $this->InstallModule('Finance', 'Yahoo Finance', 'forms/library.gif', 1, 1, array());
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
    }

    /** 
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/theme/finance/*.template.json') as $template) {
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
        // Cache Period
        $formFields[] = FormManager::AddText('cachePeriod', __('Cache Period'), $this->GetSetting('cachePeriod', 300), 
            __('Enter the number of seconds you would like to cache twitter search results.'), 'c', 'required');
        
        return $formFields;
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings()
    {
        $this->settings['cachePeriod'] = Kit::GetParam('cachePeriod', _POST, _INT, 300);

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
        $formFields['advanced'][] = FormManager::AddText('noRecordsMessage', __('No Records'), NULL,
            __('A message to display when there are no records returned by the search query'), 'n');

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

        $formFields['template'][] = FormManager::AddText('yql', __('Query'), NULL,
            __('The YQL query to use for data'), '', '', 'template-override-controls');

        $formFields['template'][] = FormManager::AddText('item', __('Item'), NULL,
            __('The item wanted, can be comma separated.'), '');

        $formFields['template'][] = FormManager::AddText('resultIdentifier', __('Result Identifier'), NULL,
            __('The name of the result identifier returned by the YQL.'), '', '', 'template-override-controls');
        
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

        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('yql', Kit::GetParam('yql', _POST, _STRING));
        $this->SetOption('item', Kit::GetParam('item', _POST, _STRING));
        $this->SetOption('resultIdentifier', Kit::GetParam('resultIdentifier', _POST, _STRING));
        $this->SetOption('effect', Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noRecordsMessage', Kit::GetParam('noRecordsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
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
        $tabs[] = FormManager::AddTab('results', __('Results'));
        Theme::Set('form_tabs', $tabs);

        $formFields['general'][] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        // Duration
        $formFields['general'][] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

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
        $formFields['advanced'][] = FormManager::AddText('noRecordsMessage', __('No records'), $this->GetOption('noRecordsMessage'),
            __('A message to display when there are no records returned by the search query'), 'n');

        $formFields['advanced'][] = FormManager::AddText('dateFormat', __('Date Format'), $this->GetOption('dateFormat'),
            __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

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

        $formFields['template'][] = FormManager::AddText('yql', __('Query'), $this->GetOption('yql'),
            __('The YQL query to use for data'), '', '', 'template-override-controls');

        $formFields['template'][] = FormManager::AddText('item', __('Item'), $this->GetOption('item'),
            __('The item wanted, can be comma separated.'), '');

        $formFields['template'][] = FormManager::AddText('resultIdentifier', __('Result Identifier'), $this->GetOption('resultIdentifier'),
            __('The name of the result identifier returned by the YQL.'), '', '', 'template-override-controls');
        
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

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', $formFields['template']);
        Theme::Set('form_fields_effect', $formFields['effect']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);
        Theme::Set('form_fields_results', array());

        // Set the field dependencies
        $this->setFieldDepencencies();

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->dialogTitle = __($this->displayType);
        $this->response->callBack = 'text_callback';
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        // Append the templates to the response
        $this->response->extra = $this->settings['templates'];
        $this->response->AddButton(__('Get Results'), 'requestTab("results", "index.php?p=module&q=exec&mod=' . $this->type . '&method=requestTab&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&mediaid=' . $this->mediaid . '")');
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

        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('yql', Kit::GetParam('yql', _POST, _STRING));
        $this->SetOption('item', Kit::GetParam('item', _POST, _STRING));
        $this->SetOption('resultIdentifier', Kit::GetParam('resultIdentifier', _POST, _STRING));
        $this->SetOption('effect', Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noRecordsMessage', Kit::GetParam('noRecordsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', Kit::GetParam('dateFormat', _POST, _STRING));
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

    /**
     * Get YQL Data
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     */
    protected function getYql()
    {
        // Construct the YQL
        // process items
        $items = $this->getOption('item');

        if (strstr($items, ','))
            $items = explode(',', $items);
        else
            $items = array($items);

        // quote each item
        $items = array_map(function ($element) {
            return '\'' . trim($element) . '\'';
        }, $items);

        $yql = str_replace('[Item]', implode(',', $items), $this->getOption('yql'));

        // Fire off a request for the data
        $key = md5($yql);

        if (!Cache::has($key) || Cache::get($key) == '') {

            Debug::Audit('Querying API for ' . $yql);

            if (!$data = $this->request($yql)) {
                return false;
            }

            // Cache it
            Cache::put($key, $data, $this->getSetting('cachePeriod', 300));

        } else {
            Debug::Audit('Served from Cache');
            $data = Cache::get($key);
        }

        Debug::Audit('Finance data returned: ' . var_export($data, true));

        // Pull out the results according to the resultIdentifier
        // If the element to return is an array and we aren't, then box.
        $results = $data[$this->getOption('resultIdentifier')];

        if (array_key_exists(0, $results))
            return $results;
        else
            return array($results);
    }

    /**
     * Request from Yahoo API
     * @param $yql
     * @return array|bool
     */
    private function request($yql)
    {
        // Encode the YQL and make the request
        $url = 'https://query.yahooapis.com/v1/public/yql?q=' . urlencode($yql) . '&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';
        //$url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quote%20where%20symbol%20in%20(%22TEC.PA%22)&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=';

        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Xibo Digital Signage',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url
        );

        // Proxy support
        if (\Config::GetSetting('PROXY_HOST') != '' && !\Config::isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = \Config::GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = \Config::GetSetting('PROXY_PORT');

            if (\Config::GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = \Config::GetSetting('PROXY_AUTH');
        }

        $curl = curl_init();
        curl_setopt_array($curl, $httpOptions);
        $result = curl_exec($curl);

        // Get the response headers
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] == 0) {
            // Unable to connect
            \Debug::Error('Unable to reach API. No Host Found (HTTP Code 0). Curl Error = ' . curl_error($curl));
            return false;
        }
        else if ($outHeaders['http_code'] != 200) {
            \Debug::Error('API returned ' . $outHeaders['http_code'] . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            \Debug::Error('Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // Parse out header and body
        $body = json_decode($result, true);

        return $body['query']['results'];
    }

    /**
     * Run through the data and substitute into the template
     * @param $data
     * @param $source
     * @return mixed
     */
    private function makeSubstitutions($data, $source)
    {
        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));

            // Match that in the array
            if (isset($data[$replace]))
                $source = str_replace($sub, $data[$replace], $source);
        }

        return $source;
    }

    /**
     * Get Tab
     */
    public function requestTab()
    {
        if (!$data = $this->getYql())
            trigger_error(__('No data returned, please check error log.'), E_USER_ERROR);

        $cols = array(
            array('name' => 'key', 'title' => __('Substitute')),
            array('name' => 'value', 'title' => __('Value'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();
        foreach ($data[0] as $key => $value) {
            $rows[] = array('key' => $key, 'value' => $value);
        }

        Theme::Set('table_rows', $rows);
        Theme::Render('table_render');
        exit();
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
        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Information from the Module
        $duration = $this->duration;

        // Generate a JSON string of items.
        if (!$items = $this->getYql($displayId, $isPreview)) {
            return '';
        }

        // Run through each item and substitute with the template
        $itemTemplate = $this->GetRawNode('template');
        $renderedItems = array();

        foreach ($items as $item) {
            $renderedItems[] = $this->makeSubstitutions($item, $itemTemplate);
        }

        Debug::Audit('Items: ' . var_export($items, true));
        Debug::Audit('Rendered items: ' . var_export($renderedItems, true));

        $options = array(
            'type' => $this->type,
            'fx' => $this->GetOption('effect', 'none'),
            'speed' => $this->GetOption('speed', 500),
            'duration' => $duration,
            'durationIsPerItem' => ($this->GetOption('durationIsPerItem', 0) == 1),
            'numItems' => count($renderedItems),
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
        if ($this->GetSetting('effect') != 'none') {
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        }

        // Need the marquee plugin?
        if (stripos($this->GetSetting('effect'), 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';
        
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . Kit::jsonEncode($renderedItems) . ';';
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
