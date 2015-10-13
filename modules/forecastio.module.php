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
 *
 *
 * This is a template module used to demonstrate how a module for Xibo can be made.
 *
 * The class name must be equal to the $this->type and the file name must be equal to modules/type.module.php
 */ 
include_once('modules/3rdparty/forecast.php');
use Forecast\Forecast;

class ForecastIo extends Module
{
    private $resourceFolder;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '') {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = 'forecastio';

        // This is the code schema version, it should be 1 for a new module and should be incremented each time the 
        // module data structure changes.
        // It is used to install / update your module and to put updated modules down to the display clients.
        $this->codeSchemaVersion = 1;

        // The resource folder
        $this->resourceFolder = 'modules/theme/forecastio/weather_icons/';
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Install or Update this module
     */
    public function InstallOrUpdate() {
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
            $this->InstallModule('Forecast IO', 'Weather forecasting from Forecast IO', 'forms/library.gif', 1, 1, array());
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

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm() {
        // Output any form fields (formatted via a Theme file)
        // These are appended to the bottom of the "Edit" form in Module Administration
        
        // API Key
        $formFields[] = FormManager::AddText('apiKey', __('API Key'), $this->GetSetting('apiKey'), 
            __('Enter your API Key from Forecast IO.'), 'a', 'required');
        
        // Cache Period
        $formFields[] = FormManager::AddText('cachePeriod', __('Cache Period'), $this->GetSetting('cachePeriod', 300), 
            __('Enter the number of seconds you would like to cache long/lat requests for. Forecast IO offers 1000 requests a day.'), 'c', 'required');
        
        return $formFields;
    }

    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFileFromFolder($this->resourceFolder);
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings() {
        // Process any module settings you asked for.
        $apiKey = Kit::GetParam('apiKey', _POST, _STRING, '');

        if ($apiKey == '')
            $this->ThrowError(__('Missing API Key'));

        $this->settings['apiKey'] = $apiKey;
        $this->settings['cachePeriod'] = Kit::GetParam('cachePeriod', _POST, _INT, 300);

        // Return an array of the processed settings.
        return $this->settings;
    }

    /** 
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/theme/forecastio/*.template.json') as $template) {
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
        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // All modules will have:
        //  $this->layoutid
        //  $this->regionid

        // You also have access to $settings, which is the array of settings you configured for your module.
        // Augment settings with templates
        $this->loadTemplates();

        // The CMS provides the region width and height in case they are needed
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('advanced', __('Appearance'));
        $tabs[] = FormManager::AddTab('forecast', __('Forecast'));

        Theme::Set('form_tabs', $tabs);

	    $formFields['general'][] = FormManager::AddText('name', __('Name'), NULL,
            __('An optional name for this media'), 'n');

        $formFields['general'][] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields['general'][] = FormManager::AddCheckbox('useDisplayLocation', __('Use the Display Location'), $this->GetOption('useDisplayLocation'), 
            __('Use the location configured on the display'), 'd');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = FormManager::AddNumber('latitude', __('Latitude'), $this->GetOption('latitude'),
            __('The Latitude for this weather module'), 'l', '', 'locationControls');

        $formFields['general'][] = FormManager::AddNumber('longitude', __('Longitude'), $this->GetOption('longitude'),
            __('The Longitude for this weather module'), 'g', '', 'locationControls');

        $formFields['advanced'][] = FormManager::AddCombo('templateId', __('Weather Template'), $this->GetOption('templateId'), 
            $this->settings['templates'], 
            'id', 
            'value', 
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        $formFields['advanced'][] = FormManager::AddCombo('icons', __('Icons'), $this->GetOption('icons'), 
            $this->iconsAvailable(), 
            'id', 
            'value', 
            __('Select the icon set you would like to use.'), 't', 'icon-controls');

        $formFields['advanced'][] = FormManager::AddNumber('size', __('Size'), $this->GetOption('size', 1), 
            __('Set the size. Start at 1 and work up until the widget fits your region appropriately.'), 's', 'number', 'template-selector-control');

        $formFields['advanced'][] = FormManager::AddCombo('units', __('Units'), $this->GetOption('units'),
            $this->unitsAvailable(), 
            'id', 
            'value', 
            __('Select the units you would like to use.'), 'u');

        $formFields['advanced'][] = FormManager::AddCombo('lang', __('Language'), TranslationEngine::GetLocale(2),
            $this->supportedLanguages(),
            'id',
            'value',
            __('Select the language you would like to use.'), 'l');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 60),
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

	    $formFields['advanced'][] = FormManager::AddCheckbox('dayConditionsOnly', __('Only show Daytime weather conditions'), 1,
            __('Tick if you would like to only show the Daytime weather conditions.'), 'd');

	    $formFields['general'][] = FormManager::AddText('color', __('Colour'), '#000',
            __('Please select a colour for the foreground text.'), 'c', 'required');

        $formFields['advanced'][] = FormManager::AddCheckbox('overrideTemplate', __('Override the template?'), 0, 
            __('Tick if you would like to override the template.'), 'o');

        $formFields['advanced'][] = FormManager::AddMultiText('currentTemplate', __('Template for Current Forecast'), NULL, 
            __('Enter the template for the current forecast. For a list of substitutions click "Request Forecast" below.'), 't', 10, 'required', 'template-override-controls');

        $formFields['advanced'][] = FormManager::AddMultiText('dailyTemplate', __('Template for Daily Forecast'), NULL, 
            __('Enter the template for the daily forecast. Replaces [dailyForecast] in main template.'), 't', 10, NULL, 'template-override-controls');

        $formFields['advanced'][] = FormManager::AddMultiText('styleSheet', __('CSS Style Sheet'), NULL, __('Enter a CSS style sheet to style the weather widget'), 'c', 10, 'required', 'template-override-controls');
        
        $formFields['forecast'][] = FormManager::AddMessage(__('Please press Request Forecast'));

        // Configure the field dependencies
        $this->SetFieldDepencencies();

        // Append the Templates to the response
        $this->response->extra = $this->settings['templates'];

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);
        Theme::Set('form_fields_forecast', $formFields['forecast']);
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Forecast IO');
        $this->response->callBack = 'forecastIoFormSetup';
        
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Request Forecast'), 'requestTab("forecast", "index.php?p=module&q=exec&mod=' . $this->type . '&method=requestTab&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&mediaid=' . $this->mediaid . '")');
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

        //Other Properties
	$name 	      = Kit::GetParam('name', _POST, _STRING);

	// You are required to set a media id, which should be unique.
        $this->mediaid  = md5(uniqid());

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        // You can store any additional options for your module using the SetOption method
	$this->SetOption('name', $name);
        $this->SetOption('useDisplayLocation', Kit::GetParam('useDisplayLocation', _POST, _CHECKBOX));
        $this->SetOption('color', Kit::GetParam('color', _POST, _STRING));
        $this->SetOption('longitude', Kit::GetParam('longitude', _POST, _DOUBLE));
        $this->SetOption('latitude', Kit::GetParam('latitude', _POST, _DOUBLE));
        $this->SetOption('templateId', Kit::GetParam('templateId', _POST, _STRING));
        $this->SetOption('icons', Kit::GetParam('icons', _POST, _STRING));
        $this->SetOption('overrideTemplate', Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('size', Kit::GetParam('size', _POST, _INT));
        $this->SetOption('units', Kit::GetParam('units', _POST, _WORD));
        $this->SetOption('lang', Kit::GetParam('lang', _POST, _WORD));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));
        $this->SetOption('dayConditionsOnly', Kit::GetParam('dayConditionsOnly', _POST, _CHECKBOX));

        $this->SetRaw('<styleSheet><![CDATA[' . Kit::GetParam('styleSheet', _POST, _HTMLSTRING) . ']]></styleSheet>
            <currentTemplate><![CDATA[' . Kit::GetParam('currentTemplate', _POST, _HTMLSTRING) . ']]></currentTemplate>
            <dailyTemplate><![CDATA[' . Kit::GetParam('dailyTemplate', _POST, _HTMLSTRING) . ']]></dailyTemplate>');
        
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

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $this->mediaid . '">');

        // Augment settings with templates
        $this->loadTemplates();

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('advanced', __('Appearance'));
        $tabs[] = FormManager::AddTab('forecast', __('Forecast'));

        Theme::Set('form_tabs', $tabs);

	$formFields['general'][] = FormManager::AddText('name', __('Name'), $this->GetOption('name'),
            __('An optional name for this media'), 'n');

        $formFields['general'][] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields['general'][] = FormManager::AddCheckbox('useDisplayLocation', __('Use the Display Location'), $this->GetOption('useDisplayLocation'), 
            __('Use the location configured on the display'), 'd');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = FormManager::AddNumber('latitude', __('Latitude'), $this->GetOption('latitude'),
            __('The Latitude for this weather module'), 'l', '', 'locationControls');

        $formFields['general'][] = FormManager::AddNumber('longitude', __('Longitude'), $this->GetOption('longitude'),
            __('The Longitude for this weather module'), 'g', '', 'locationControls');

        $formFields['advanced'][] = FormManager::AddCombo('templateId', __('Weather Template'), $this->GetOption('templateId'),
            $this->settings['templates'], 
            'id', 
            'value', 
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        $formFields['advanced'][] = FormManager::AddCombo('icons', __('Icons'), $this->GetOption('icons'), 
            $this->iconsAvailable(), 
            'id', 
            'value', 
            __('Select the icon set you would like to use.'), 't', 'icon-controls');

        $formFields['advanced'][] = FormManager::AddNumber('size', __('Size'), $this->GetOption('size', 1), 
            __('Set the size. Start at 1 and work up until the widget fits your region appropriately.'), 's', 'number', 'template-selector-control');

        $formFields['advanced'][] = FormManager::AddCombo('units', __('Units'), $this->GetOption('units'),
            $this->unitsAvailable(), 
            'id', 
            'value', 
            __('Select the units you would like to use.'), 'u');

        $formFields['advanced'][] = FormManager::AddCombo('lang', __('Language'), $this->GetOption('lang', TranslationEngine::GetLocale(2)),
            $this->supportedLanguages(),
            'id',
            'value',
            __('Select the language you would like to use.'), 'l');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 60),
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

        $formFields['advanced'][] = FormManager::AddCheckbox('dayConditionsOnly', __('Only show Daytime weather conditions'), $this->GetOption('dayConditionsOnly', 1),
            __('Tick if you would like to only show the Daytime weather conditions.'), 'd');

        $formFields['general'][] = FormManager::AddText('color', __('Colour'), $this->GetOption('color', '000'), 
            __('Please select a colour for the foreground text.'), 'c', 'required');

        $formFields['advanced'][] = FormManager::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate'), 
            __('Tick if you would like to override the template.'), 'o');

        $formFields['advanced'][] = FormManager::AddMultiText('currentTemplate', __('Template for Current Forecast'), $this->GetRawNode('currentTemplate'), 
            __('Enter the template for the current forecast. For a list of substitutions click "Request Forecast" below.'), 't', 10, 'required', 'template-override-controls');

        $formFields['advanced'][] = FormManager::AddMultiText('dailyTemplate', __('Template for Daily Forecast'), $this->GetRawNode('dailyTemplate'), 
            __('Enter the template for the current forecast. Replaces [dailyForecast] in main template.'), 't', 10, NULL, 'template-override-controls');

        $formFields['advanced'][] = FormManager::AddMultiText('styleSheet', __('CSS Style Sheet'), $this->GetRawNode('styleSheet'), 
            __('Enter a CSS style sheet to style the weather widget'), 'c', 10, 'required', 'template-override-controls');
        
        $formFields['forecast'][] = FormManager::AddMessage(__('Please press Request Forecast to show the current forecast and all available substitutions.'));

        // Encode up the template
        if (Config::GetSetting('SERVER_MODE') == 'Test' && $this->user->usertypeid == 1)
            $formFields['forecast'][] = FormManager::AddMessage('<pre>' . htmlentities(json_encode(array('id' => 'ID', 'value' => 'TITLE', 'main' => $this->GetRawNode('currentTemplate'), 'daily' => $this->GetRawNode('dailyTemplate'), 'css' => $this->GetRawNode('styleSheet')))) . '</pre>');

        // Configure the field dependencies
        $this->SetFieldDepencencies();

        // Append the Templates to the response
        $this->response->extra = $this->settings['templates'];

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);
        Theme::Set('form_fields_forecast', $formFields['forecast']);
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Forecast IO');
        $this->response->callBack = 'forecastIoFormSetup';
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Request Forecast'), 'requestTab("forecast", "index.php?p=module&q=exec&mod=' . $this->type . '&method=requestTab&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&mediaid=' . $this->mediaid . '")');
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
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        //Other Properties
	$name = Kit::GetParam('name', _POST, _STRING);

	// You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        // You can store any additional options for your module using the SetOption method
	$this->SetOption('name', $name);
        $this->SetOption('useDisplayLocation', Kit::GetParam('useDisplayLocation', _POST, _CHECKBOX));
        $this->SetOption('color', Kit::GetParam('color', _POST, _STRING, '#000'));
        $this->SetOption('longitude', Kit::GetParam('longitude', _POST, _DOUBLE));
        $this->SetOption('latitude', Kit::GetParam('latitude', _POST, _DOUBLE));
        $this->SetOption('templateId', Kit::GetParam('templateId', _POST, _STRING));
        $this->SetOption('icons', Kit::GetParam('icons', _POST, _STRING));
        $this->SetOption('overrideTemplate', Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('size', Kit::GetParam('size', _POST, _INT));
        $this->SetOption('units', Kit::GetParam('units', _POST, _WORD));
        $this->SetOption('lang', Kit::GetParam('lang', _POST, _WORD));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));
        $this->SetOption('dayConditionsOnly', Kit::GetParam('dayConditionsOnly', _POST, _CHECKBOX));

        $this->SetRaw('<styleSheet><![CDATA[' . Kit::GetParam('styleSheet', _POST, _HTMLSTRING) . ']]></styleSheet>
            <currentTemplate><![CDATA[' . Kit::GetParam('currentTemplate', _POST, _HTMLSTRING) . ']]></currentTemplate>
            <dailyTemplate><![CDATA[' . Kit::GetParam('dailyTemplate', _POST, _HTMLSTRING) . ']]></dailyTemplate>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    private function SetFieldDepencencies()
    {
        // Add a dependency
        $locationControls_0 = array(
                '.locationControls' => array('display' => 'block')
            );

        $locationControls_1 = array(
                '.locationControls' => array('display' => 'none')
            );

        $this->response->AddFieldAction('useDisplayLocation', 'init', false, $locationControls_0, 'is:checked');
        $this->response->AddFieldAction('useDisplayLocation', 'change', false, $locationControls_0, 'is:checked');
        $this->response->AddFieldAction('useDisplayLocation', 'init', true, $locationControls_1, 'is:checked');
        $this->response->AddFieldAction('useDisplayLocation', 'change', true, $locationControls_1, 'is:checked');
        $this->response->AddFieldAction('templateId', 'init', 'picture', array('.icon-controls' => array('display' => 'block')));
        $this->response->AddFieldAction('templateId', 'change', 'picture', array('.icon-controls' => array('display' => 'block')));
        $this->response->AddFieldAction('templateId', 'init', 'picture', array('.icon-controls' => array('display' => 'none')), 'not');
        $this->response->AddFieldAction('templateId', 'change', 'picture', array('.icon-controls' => array('display' => 'none')), 'not');
        
        // When the override template check box is ticked, we want to expose the advanced controls and we want to hide the template selector
        $this->response->AddFieldAction('overrideTemplate', 'init', false, 
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.reloadTemplateButton' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $this->response->AddFieldAction('overrideTemplate', 'change', false, 
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.reloadTemplateButton' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $this->response->AddFieldAction('overrideTemplate', 'init', true, 
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.reloadTemplateButton' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');
        $this->response->AddFieldAction('overrideTemplate', 'change', true, 
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.reloadTemplateButton' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');
    }

    private function iconsAvailable() 
    {
        // Scan the forecast io folder for icons
        $icons = array();

        foreach (array_diff(scandir($this->resourceFolder), array('..', '.')) as $file) {
            if (stripos($file, '.png'))
                $icons[] = array('id' => $file, 'value' => ucfirst(str_replace('-', ' ', str_replace('.png', '', $file))));
        }

        return $icons;
    }

    /**
     * Units supported by Forecast.IO API
     * @return array The Units Available
     */
    private function unitsAvailable()
    {
        return array(
                array('id' => 'auto', 'value' => 'Automatically select based on geographic location', 'tempUnit' => ''),
                array('id' => 'ca', 'value' => 'Canada', 'tempUnit' => 'F'),
                array('id' => 'si', 'value' => 'Standard International Units', 'tempUnit' => 'C'),
                array('id' => 'uk', 'value' => 'United Kingdom', 'tempUnit' => 'C'),
                array('id' => 'us', 'value' => 'United States', 'tempUnit' => 'F'),
            );
    }

    /**
     * Languages supported by Forecast.IO API
     * @return array The Supported Language
     */
    private function supportedLanguages()
    {
        return array(
            array('id' => 'en', 'value' => __('English')),
            array('id' => 'bs', 'value' => __('Bosnian')),
            array('id' => 'de', 'value' => __('German')),
            array('id' => 'es', 'value' => __('Spanish')),
            array('id' => 'fr', 'value' => __('French')),
            array('id' => 'it', 'value' => __('Italian')),
            array('id' => 'nl', 'value' => __('Dutch')),
            array('id' => 'pl', 'value' => __('Polish')),
            array('id' => 'pt', 'value' => __('Portuguese')),
            array('id' => 'ru', 'value' => __('Russian')),
            array('id' => 'tet', 'value' => __('Tetum')),
            array('id' => 'tr', 'value' => __('Turkish')),
            array('id' => 'x-pig-latin', 'value' => __('lgpay Atinlay'))
        );
    }

    // Request content for this tab
    public function requestTab()
    {
        $tab = Kit::GetParam('tab', _POST, _WORD);

        if (!$data = $this->getForecastData(0))
            die(__('No data returned, please check error log.'));

        $cols = array(
                array('name' => 'forecast', 'title' => __('Forecast')),
                array('name' => 'key', 'title' => __('Substitute')),
                array('name' => 'value', 'title' => __('Value'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();
        foreach ($data['currently'] as $key => $value) {
            if (stripos($key, 'time')) {
                $value = DateManager::getLocalDate($value);
            }

            $rows[] = array('forecast' => __('Current'), 'key' => $key, 'value' => $value);
        }

        foreach ($data['daily']['data'][0] as $key => $value) {
            if (stripos($key, 'time')) {
                $value = DateManager::getLocalDate($value);
            }

            $rows[] = array('forecast' => __('Daily'), 'key' => $key, 'value' => $value);
        }

        Theme::Set('table_rows', $rows);
        Theme::Render('table_render');
        exit();
    }

    // Get the forecast data for the provided display id
    private function getForecastData($displayId)
    {
        $defaultLat = Config::GetSetting('DEFAULT_LAT');
        $defaultLong = Config::GetSetting('DEFAULT_LONG');

        if ($this->GetOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {
            
                $display = new Display();
                $display->displayId = $displayId;
                $display->Load();

                $defaultLat = $display->latitude;
                $defaultLong = $display->longitude;
            }
        }
        else {
            $defaultLat = $this->GetOption('latitude', $defaultLat);
            $defaultLong = $this->GetOption('longitude', $defaultLong);
        }

        $apiKey = $this->GetSetting('apiKey');
        if ($apiKey == '')
            die(__('Incorrectly configured module'));

        // Query the API and Dump the Results.
        $forecast = new Forecast($apiKey);

        $apiOptions = array('units' => $this->GetOption('units', 'auto'), 'lang' => $this->GetOption('lang', 'en'), 'exclude' => 'flags,minutely,hourly');
        $key = md5($defaultLat . $defaultLong . 'null' . implode('.', $apiOptions));

        if (!Cache::has($key)) {
            Debug::LogEntry('audit', 'Getting Forecast from the API', $this->type, __FUNCTION__);
            if (!$data = $forecast->get($defaultLat, $defaultLong, null, $apiOptions)) {
                return false;
            }

            // If the response is empty, cache it for less time
            $cacheDuration = $this->GetSetting('cachePeriod');

            // Cache
            Cache::put($key, $data, $cacheDuration);
        }
        else {
            Debug::LogEntry('audit', 'Getting Forecast from the Cache with key: ' . $key, $this->type, __FUNCTION__);
            $data = Cache::get($key);
        }

        //Debug::Audit('Data: ' . var_export($data, true));

        // Icon Mappings
        $icons = array(
                'unmapped' => 'wi-alien',
                'clear-day' => 'wi-day-sunny',
                'clear-night' => 'wi-night-clear',
                'rain' => 'wi-rain',
                'snow' => 'wi-snow',
                'sleet' => 'wi-hail',
                'wind' => 'wi-windy',
                'fog' => 'wi-fog',
                'cloudy' => 'wi-cloudy',
                'partly-cloudy-day' => 'wi-day-cloudy',
                'partly-cloudy-night' => 'wi-night-partly-cloudy',
            );

        // Temperature Unit Mappings
        $temperatureUnit = '';
        foreach ($this->unitsAvailable() as $unit) {
            if ($unit['id'] == $this->GetOption('units', 'auto')) {
                $temperatureUnit = $unit['tempUnit'];
                break;
            }
        }

        // Are we set to only show daytime weather conditions?
        if ($this->GetOption('dayConditionsOnly') == 1) {
            if ($data->currently->icon == 'partly-cloudy-night')
                $data->currently->icon = 'clear-day';
        }

        $data->currently->wicon = (isset($icons[$data->currently->icon]) ? $icons[$data->currently->icon] : $icons['unmapped']);
        $data->currently->temperatureFloor = (isset($data->currently->temperature) ? floor($data->currently->temperature) : '--');
        $data->currently->summary = (isset($data->currently->summary) ? $data->currently->summary : '--');
        $data->currently->weekSummary = (isset($data->daily->summary) ? $data->daily->summary : '--');
        $data->currently->temperatureUnit = $temperatureUnit;

        // Convert a stdObject to an array
        $data = json_decode(json_encode($data), true);

        // Process the icon for each day
        for ($i = 0; $i < 7; $i++) {
            // Are we set to only show daytime weather conditions?
            if ($this->GetOption('dayConditionsOnly') == 1) {
                if ($data['daily']['data'][$i]['icon'] == 'partly-cloudy-night')
                    $data['daily']['data'][$i]['icon'] = 'clear-day';
            }

            $data['daily']['data'][$i]['wicon'] = (isset($icons[$data['daily']['data'][$i]['icon']]) ? $icons[$data['daily']['data'][$i]['icon']] : $icons['unmapped']);
            $data['daily']['data'][$i]['temperatureMaxFloor'] = (isset($data['daily']['data'][$i]['temperatureMax'])) ? floor($data['daily']['data'][$i]['temperatureMax']) : '--';
            $data['daily']['data'][$i]['temperatureMinFloor'] = (isset($data['daily']['data'][$i]['temperatureMin'])) ? floor($data['daily']['data'][$i]['temperatureMin']) : '--';
            $data['daily']['data'][$i]['temperatureFloor'] = ($data['daily']['data'][$i]['temperatureMinFloor'] != '--' && $data['daily']['data'][$i]['temperatureMaxFloor'] != '--') ? floor((($data['daily']['data'][$i]['temperatureMinFloor'] + $data['daily']['data'][$i]['temperatureMaxFloor']) / 2)) : '--';
            $data['daily']['data'][$i]['temperatureUnit'] = $temperatureUnit;
        }

        return $data;
    }

    private function makeSubstitutions($data, $source)
    {
        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));

            // Handling for date/time
            if (stripos($replace, 'time|') > -1) {
                $timeSplit = explode('|', $replace);

                $time = DateManager::getLocalDate($data['time'], $timeSplit[1]);

                // Pull time out of the array
                $source = str_replace($sub, $time, $source);
            }
            else {
                // Match that in the array
                if (isset($data[$replace]))
                    $source = str_replace($sub, $data[$replace], $source);
            }
        }

        return $source;
    }

    /**
     * GetResource
     *     Return the rendered resource to be used by the client (or a preview)
     *     for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     */
    public function GetResource($displayId = 0)
    {
        // Behave exactly like the client.
        if (!$data = $this->getForecastData($displayId))
            return '';

        // A template is provided which contains a number of different libraries that might
        // be useful (jQuery, etc).
        $pathPrefix = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true') ? 'modules/theme/forecastio/weather_icons/' : '';
        
        // Get the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        $headContent = '
            <link href="' . $pathPrefix . 'weather-icons.min.css" rel="stylesheet" media="screen">
            <style type="text/css">
                .container { color: ' . $this->GetOption('color', '000'). '; }
                #content { zoom: ' . $this->GetOption('size', 1). '; }
                ' . $this->GetRawNode('styleSheet') . '
            </style>
        ';
        
        // Add our fonts.css file
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $headContent .= '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);
        
        // Make some body content
        $body = $this->GetRawNode('currentTemplate');
        $dailyTemplate = $this->GetRawNode('dailyTemplate');

        // Handle the daily template (if its here)
        if (stripos($body, '[dailyForecast]')) {
            // Pull it out, and run substitute over it for each day
            $dailySubs = '';
            // Substitute for every day (i.e. 7 times).
            for ($i = 0; $i < 7; $i++) {
                $dailySubs .= $this->makeSubstitutions($data['daily']['data'][$i], $dailyTemplate);
            }

            // Substitute the completed template
            $body = str_replace('[dailyForecast]', $dailySubs, $body);
        }

        // Run replace over the main template
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $this->makeSubstitutions($data['currently'], $body), $template);

        // Replace any icon sets
        $template = str_replace('[[ICONS]]', ((($isPreview) ? 'modules/theme/forecastio/weather_icons/' : '') . $this->GetOption('icons')), $template);
        
        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
                'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
                'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
                'originalWidth' => $this->width,
                'originalHeight' => $this->height,
                'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
            );

        $javaScriptContent  = '<script src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $javaScriptContent .= '<script src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script>

            var options = ' . json_encode($options) . '

            $(document).ready(function() {
                $("body").xiboLayoutScaler(options);
            });
        </script>';

        // Replace the After body Content
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Return that content.
        return $template;
    }

    public function HoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::HoverPreview();

        // You can add anything you like to this, or completely replace it

        return $output;
    }

    public function GetName() {
        return $this->GetOption('name');
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
