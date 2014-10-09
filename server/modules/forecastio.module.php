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
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '') {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = 'forecastio';

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

    private function InstallFiles() {
        $media = new Media();
        $media->AddModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->AddModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/weather-icons.min.css');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/WeatherIcons-Regular.otf');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/weathericons-regular-webfont.eot');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/weathericons-regular-webfont.svg');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/weathericons-regular-webfont.ttf');
        $media->AddModuleFile('modules/theme/forecastio/weather_icons/weathericons-regular-webfont.woff');
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

        // Check we are all installed
        $this->InstallFiles();

        // Return an array of the processed settings.
        return $this->settings;
    }
    
    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm() {
        // This is the logged in user and can be used to assess permissions
        $user =& $this->user;

        // All modules will have:
        //  $this->layoutid
        //  $this->regionid

        // You also have access to $settings, which is the array of settings you configured for your module.
        
        // The CMS provides the region width and height in case they are needed
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);

        // All forms should set some meta data about the form.
        // Usually, you would want this meta data to remain the same.
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $this->layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $this->regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
    
        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields[] = FormManager::AddCheckbox('useDisplayLocation', __('Use the Display Location'), $this->GetOption('useDisplayLocation'), 
            __('Use the location configured on the display'), 'd');

        // Any values for the form fields should be added to the theme here.
        $formFields[] = FormManager::AddNumber('longitude', __('Longitude'), $this->GetOption('longitude'), 
            __('The Longitude of this Display'), 'g', '', 'locationControls');

        $formFields[] = FormManager::AddNumber('latitude', __('Latitude'), $this->GetOption('latitude'), 
            __('The Latitude of this display'), 'l', '', 'locationControls');

        // Configure the field dependencies
        $this->SetFieldDepencencies();

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields', $formFields);
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Forecast IO');
        
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
    public function AddMedia() {
        // Same member variables as the Form call, except with POST variables for your form fields.
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // You are required to set a media id, which should be unique.
        $this->mediaid  = md5(uniqid());

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        // You can store any additional options for your module using the SetOption method
        $this->SetOption('useDisplayLocation', Kit::GetParam('useDisplayLocation', _POST, _CHECKBOX, 0));
        
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
    public function EditForm() {
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

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        $formFields[] = FormManager::AddCheckbox('useDisplayLocation', __('Use the Display Location'), $this->GetOption('useDisplayLocation'), 
            __('Use the location configured on the display'), 'd');

        // Any values for the form fields should be added to the theme here.
        $formFields[] = FormManager::AddNumber('longitude', __('Longitude'), $this->GetOption('longitude'), 
            __('The Longitude of this Display'), 'g', '', 'locationControls');

        $formFields[] = FormManager::AddNumber('latitude', __('Latitude'), $this->GetOption('latitude'), 
            __('The Latitude of this display'), 'l', '', 'locationControls');

        $formFields[] = FormManager::AddText('color', __('Colour'), $this->GetOption('color', '000'), 
            __('Please select a colour for the foreground text.'), 'c', 'required');

        // Configure the field dependencies
        $this->SetFieldDepencencies();

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields', $formFields);
        $this->response->html = Theme::RenderReturn('form_render');

        $this->response->dialogTitle = __('Forecast IO');
        $this->response->callBack = 'forecastIoFormSetup';
        // The response object outputs the required JSON object to the browser
        // which is then processed by the CMS JavaScript library (xibo-cms.js).
        $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&q=RegionOptions")');
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
        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // You must also provide a duration (all media items must provide this field)
        $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

        // You can store any additional options for your module using the SetOption method
        $this->SetOption('useDisplayLocation', Kit::GetParam('useDisplayLocation', _POST, _CHECKBOX, 0));
        $this->SetOption('color', Kit::GetParam('color', _POST, _STRING, '#000'));

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Usually you will want to load the region options form again once you have added your module.
        // In some cases you will want to load the edit form for that module
        $this->response->loadForm = true;
        $this->response->loadFormUri = "index.php?p=timeline&layoutid=$this->layoutid&regionid=$this->regionid&q=RegionOptions";
        
        return $this->response;
    }

    private function SetFieldDepencencies() {

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
    }

    /**
     * Preview
     * @param <double> $width
     * @param <double> $height
     * @return <string>
     */
    public function Preview($width, $height)
    {
        // Each module should be able to output a preview to use in the Layout Designer
        
        // If preview is not enabled for your module you can hand off to the base class
        // and it will output a basic preview for you
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        // In most cases your preview will want to load the GetResource call associated with the module
        // This imitates the client
        return $this->PreviewAsClient($width, $height);
    }

    /**
     * GetResource
     *     Return the rendered resource to be used by the client (or a preview)
     *     for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     */
    public function GetResource($displayId = 0) {
        // Make sure this module is installed correctly
        $this->InstallFiles();

        // Behave exactly like the client.
        if ($this->GetOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId == 0) {
                $defaultLat = Config::GetSetting('DEFAULT_LAT');
                $defaultLong = Config::GetSetting('DEFAULT_LONG');
            }
            else {
                Kit::ClassLoader('display');
                $display = new Display();
                $display->displayId = $displayId;
                $display->Load();

                $defaultLat = $display->latitude;
                $defaultLong = $display->longitude;
            }
        }

        $apiKey = $this->GetSetting('apiKey');
        if ($apiKey == '')
            die(__('Incorrectly configured module'));

        // Query the API and Dump the Results.
        $forecast = new Forecast($apiKey);

        $key = md5($defaultLat . $defaultLong . 'null' . implode('.', array('units' => 'auto', 'exclude' => 'flags,minutely,hourly')));

        if (!Cache::has($key)) {
            Debug::LogEntry('audit', 'Getting Forecast from the API', $this->type, __FUNCTION__);
            $data = $forecast->get($defaultLat, $defaultLong, null, array('units' => 'auto', 'exclude' => 'flags,minutely,hourly'));
            Cache::put($key, $data, $this->GetSetting('cachePeriod'));
        }
        else {
            Debug::LogEntry('audit', 'Getting Forecast from the Cache with key: ' . $key, $this->type, __FUNCTION__);
            $data = Cache::get($key);
        }
        Debug::LogEntry('audit', 'Data: ' . var_export($data, true), $this->type, __FUNCTION__);

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

        $icon = (isset($icons[$data->currently->icon]) ? $icons[$data->currently->icon] : $icons['unmapped']);
        $temperature = (isset($data->currently->temperature) ? floor($data->currently->temperature) : '--');
        $summary = (isset($data->currently->summary) ? $data->currently->summary : '--');

        //var_dump($data);

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
                body {
                    font-family:Arial;
                    margin:0;
                }

                .container {
                    color: ' . $this->GetOption('color', '000'). ';
                    text-align: center;
                    width: 150px;
                    height: 100px;
                }

                .icon {
                    font-size: 44px;
                }

                .desc {
                    margin-top: 10px;
                    font-size: 20px;
                }

                .powered-by {
                    font-size: 8px;
                }
            </style>
        ';

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);
        
        // Make some body content
        $body = '<div class="container">
            <div class="icon"><i class="wi ' . $icon . '"></i> ' . $temperature . '<i class="wi wi-degrees"></i></div>
            <div class="desc">' . $summary . '</div>
            <div class="powered-by">Powered by Forecast</div>
        </div>
        ';

        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $body, $template);
        
        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
                'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
                'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
                'originalWidth' => 150,
                'originalHeight' => 100,
                'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
            );

        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
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
    
    public function IsValid() {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
?>
