<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-2015 Daniel Garner
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
namespace Xibo\Widget;

use Xibo\Factory\MediaFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Cache;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Twitter extends Module
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     */
    public function installOrUpdate()
    {
        // This function should update the `module` table with information about your module.
        // The current version of the module in the database can be obtained in $this->schemaVersion
        // The current version of this code can be obtained in $this->codeSchemaVersion

        // $settings will be made available to all instances of your module in $this->module->settings. These are global settings to your module,
        // not instance specific (i.e. not settings specific to the layout you are adding the module to).
        // $settings will be collected from the Administration -> Modules CMS page.
        //
        // Layout specific settings should be managed with $this->SetOption in your add / edit forms.

        if ($this->module->schemaVersion <= 1) {
            // Install
            $this->InstallModule('Twitter', 'Twitter Search Module', 'forms/library.gif', 1, 1, array());
        } else {
            // Update
            // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)" with the updated items
        }

        // Check we are all installed
        $this->installFiles();

        // After calling either Install or Update your code schema version will match the database schema version and this method will not be called
        // again. This means that if you want to change those fields in an update to your module, you will need to increment your codeSchemaVersion.
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        MediaFactory::createModuleFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleFile('modules/xibo-text-render.js')->save();
        MediaFactory::createModuleFile('modules/xibo-layout-scaler.js')->save();
        MediaFactory::createModuleFile('modules/twitter/emoji.css')->save();
        MediaFactory::createModuleFile('modules/twitter/emoji.png')->save();
    }

    /**
     * Loads templates for this module
     */
    public function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob('modules/twitter/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        Log::debug(count($this->module->settings['templates']));
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'twitter-form-settings';
    }

    /**
     * Process any module settings
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = Sanitize::getString('apiKey');

        if ($apiKey == '')
            throw new \InvalidArgumentException(__('Missing API Key'));

        // Process any module settings you asked for.
        $apiSecret = Sanitize::getString('apiSecret');

        if ($apiSecret == '')
            throw new \InvalidArgumentException(__('Missing API Secret'));

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['apiSecret'] = $apiSecret;
        $this->module->settings['cachePeriod'] = Sanitize::getInt('cachePeriod', 300);
        $this->module->settings['cachePeriodImages'] = Sanitize::getInt('cachePeriodImages', 24);

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    /**
     * Return the Add Form
     */
    public function AddForm()
    {
        $response = $this->getState();

        // Augment settings with templates
        $this->loadTemplates();

        // Configure form
        $this->configureForm('AddMedia');

        $tabs = array();
        $tabs[] = Form::AddTab('general', __('General'));
        $tabs[] = Form::AddTab('template', __('Template'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = Form::AddTab('effect', __('Effect'));
        $tabs[] = Form::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields['general'][] = Form::AddText('name', __('Name'), NULL,
            __('An optional name for this media'), 'n');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = Form::AddNumber('duration', __('Duration'), NULL,
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        // Any values for the form fields should be added to the theme here.
        $formFields['general'][] = Form::AddText('searchTerm', __('Search Term'), NULL,
            __('Search term. You can test your search term in the twitter.com search box first.'), 's', 'required');

        // Type
        $formFields['general'][] = Form::AddCombo('resultType', __('Type'), 'mixed',
            array(
                array('typeid' => 'mixed', 'type' => __('Mixed')),
                array('typeid' => 'recent', 'type' => __('Recent')),
                array('typeid' => 'popular', 'type' => __('Popular')),
            ),
            'typeid',
            'type',
            __('Recent shows only the most recent tweets, Popular the most popular and Mixed includes both popular and recent results.'), 't', 'required');

        // Distance
        $formFields['general'][] = Form::AddNumber('tweetDistance', __('Distance'), NULL,
            __('Distance in miles that the tweets should be returned from. Set to 0 for no restrictions.'), 'd');

        // Distance
        $formFields['general'][] = Form::AddNumber('tweetCount', __('Count'), 15,
            __('The number of Tweets to return.'), 'c');

        // Common fields
        $formFields['effect'][] = Form::AddCombo(
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

        $formFields['effect'][] = Form::AddNumber('speed', __('Speed'), NULL,
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['advanced'][] = Form::AddText('backgroundColor', __('Background Colour'), NULL,
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'background-color-group');

        // Field empty
        $formFields['advanced'][] = Form::AddText('noTweetsMessage', __('No tweets'), NULL,
            __('A message to display when there are no tweets returned by the search query'), 'n');

        // Date format
        $formFields['advanced'][] = Form::AddText('dateFormat', __('Date Format'), 'd M',
            __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), 60,
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

        // Template - for standard stuff
        $formFields['template'][] = Form::AddCombo('templateId', __('Template'), $this->GetOption('templateId', 'tweet-only'),
            $this->module->settings['templates'],
            'id',
            'value',
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        // Add a field for whether to override the template or not.
        // Default to 1 so that it will work correctly with old items (that didn't have a template selected at all)
        $formFields['template'][] = Form::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate', 0),
            __('Tick if you would like to override the template.'), 'o');

        // Add a text template
        $formFields['template'][] = Form::AddMultiText('ta_text', NULL, null,
            __('Enter the template. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10, NULL, 'template-override-controls');

        // Field for the style sheet (optional)
        $formFields['template'][] = Form::AddMultiText('ta_css', NULL, null,
            __('Optional Stylesheet'), 's', 10, NULL, 'template-override-controls');

        // Add some field dependencies
        // When the override template check box is ticked, we want to expose the advanced controls and we want to hide the template selector
        $response->AddFieldAction('overrideTemplate', 'init', false,
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'change', false,
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'init', true,
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'change', true,
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');

        // Present an error message if the module has not been configured. Don't prevent further configuration.
        if (!extension_loaded('curl') || $this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            $formFields['general'][] = Form::AddMessage(__('The Twitter Widget has not been configured yet, please ask your CMS Administrator to look at it for you.'), 'alert alert-danger');
        }

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', $formFields['template']);
        Theme::Set('form_fields_effect', $formFields['effect']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Set the field dependencies
        $this->setFieldDepencencies($response);
        $response->html = Theme::RenderReturn('form_render');

        $response->callBack = 'text_callback';
        // Append the templates to the response
        $response->extra = $this->module->settings['templates'];
        $this->configureFormButtons($response);

        // The response must be returned.
        return $response;
    }

    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = $this->getState();

        // You should validate all form input using the \Kit::GetParam helper classes
        if (\Kit::GetParam('searchTerm', _POST, _STRING) == '')
            throw new InvalidArgumentException(__('Please enter a search term'));

        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('name', \Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('searchTerm', \Kit::GetParam('searchTerm', _POST, _STRING));
        $this->SetOption('effect', \Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', \Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', \Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noTweetsMessage', \Kit::GetParam('noTweetsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', \Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('resultType', \Kit::GetParam('resultType', _POST, _STRING));
        $this->SetOption('tweetDistance', \Kit::GetParam('tweetDistance', _POST, _INT));
        $this->SetOption('tweetCount', \Kit::GetParam('tweetCount', _POST, _INT));
        $this->setRawNode('template', \Kit::GetParam('ta_text', _POST, _HTMLSTRING));
        $this->setRawNode('styleSheet', \Kit::GetParam('ta_css', _POST, _HTMLSTRING));
        $this->SetOption('overrideTemplate', \Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));
        $this->SetOption('templateId', \Kit::GetParam('templateId', _POST, _WORD));

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = $this->getState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        // Augment settings with templates
        $this->loadTemplates();

        $tabs = array();
        $tabs[] = Form::AddTab('general', __('General'));
        $tabs[] = Form::AddTab('template', __('Appearance'), array(array('name' => 'enlarge', 'value' => true)));
        $tabs[] = Form::AddTab('effect', __('Effect'));
        $tabs[] = Form::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields['general'][] = Form::AddText('name', __('Name'), $this->GetOption('name'),
            __('An optional name for this media'), 'n');

        // Duration
        $formFields['general'][] = Form::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed.'), 'd', 'required');

        // Search Term
        $formFields['general'][] = Form::AddText('searchTerm', __('Search Term'), $this->GetOption('searchTerm'),
            __('Search term. You can test your search term in the twitter.com search box first.'), 's', 'required');

        // Type
        $formFields['general'][] = Form::AddCombo('resultType', __('Type'), $this->GetOption('resultType'),
            array(
                array('typeid' => 'mixed', 'type' => __('Mixed')),
                array('typeid' => 'recent', 'type' => __('Recent')),
                array('typeid' => 'popular', 'type' => __('Popular')),
            ),
            'typeid',
            'type',
            __('Recent shows only the most recent tweets, Popular the most popular and Mixed includes both popular and recent results.'), 't', 'required');

        // Distance
        $formFields['general'][] = Form::AddNumber('tweetDistance', __('Distance'), $this->GetOption('tweetDistance'),
            __('Distance in miles that the tweets should be returned from. Set to 0 for no restrictions.'), 'd');

        // Distance
        $formFields['general'][] = Form::AddNumber('tweetCount', __('Count'), $this->GetOption('tweetCount'),
            __('The number of Tweets to return.'), 'c');

        // Common fields
        $formFields['effect'][] = Form::AddCombo(
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

        $formFields['effect'][] = Form::AddNumber('speed', __('Speed'), $this->GetOption('speed'),
            __('The transition speed of the selected effect in milliseconds (normal = 1000) or the Marquee Speed in a low to high scale (normal = 1).'), 's', NULL, 'effect-controls');

        // A list of web safe colours
        $formFields['advanced'][] = Form::AddText('backgroundColor', __('Background Colour'), $this->GetOption('backgroundColor'),
            __('The selected effect works best with a background colour. Optionally add one here.'), 'c', NULL, 'background-color-group');

        // Field empty
        $formFields['advanced'][] = Form::AddText('noTweetsMessage', __('No tweets'), $this->GetOption('noTweetsMessage'),
            __('A message to display when there are no tweets returned by the search query'), 'n');

        $formFields['advanced'][] = Form::AddText('dateFormat', __('Date Format'), $this->GetOption('dateFormat'),
            __('The format to apply to all dates returned by the ticker. In PHP date format: http://uk3.php.net/manual/en/function.date.php'), 'f');

        $formFields['advanced'][] = Form::AddCheckbox('removeUrls', __('Remove URLs?'), $this->GetOption('removeUrls', 1),
            __('Should URLs be removed from the Tweet Text. Most URLs do not compliment digital signage.'), 'u');

        $formFields['advanced'][] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 60),
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per hour this could be set to 60.'),
            'n', 'required');

        // Encode up the template
        if (Config::GetSetting('SERVER_MODE') == 'Test' && $this->getUser()->userTypeId == 1)
            $formFields['advanced'][] = Form::AddMessage('<pre>' . htmlentities(json_encode(array('id' => 'ID', 'value' => 'TITLE', 'template' => $this->getRawNode('template', null), 'css' => $this->getRawNode('styleSheet', null)))) . '</pre>');

        // Template - for standard stuff
        $formFields['template'][] = Form::AddCombo('templateId', __('Template'), $this->GetOption('templateId', 'tweet-only'),
            $this->module->settings['templates'],
            'id',
            'value',
            __('Select the template you would like to apply. This can be overridden using the check box below.'), 't', 'template-selector-control');

        // Add a field for whether to override the template or not.
        // Default to 1 so that it will work correctly with old items (that didn't have a template selected at all)
        $formFields['template'][] = Form::AddCheckbox('overrideTemplate', __('Override the template?'), $this->GetOption('overrideTemplate', 0),
            __('Tick if you would like to override the template.'), 'o');

        // Add a text template
        $formFields['template'][] = Form::AddMultiText('ta_text', NULL, $this->getRawNode('template', null),
            __('Enter the template. Please note that the background colour has automatically coloured to your layout background colour.'), 't', 10, NULL, 'template-override-controls');

        // Field for the style sheet (optional)
        $formFields['template'][] = Form::AddMultiText('ta_css', NULL, $this->getRawNode('styleSheet', null),
            __('Optional Stylesheet'), 's', 10, NULL, 'template-override-controls');

        // Add some field dependencies
        // When the override template check box is ticked, we want to expose the advanced controls and we want to hide the template selector
        $response->AddFieldAction('overrideTemplate', 'init', false,
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'change', false,
            array(
                '.template-override-controls' => array('display' => 'none'),
                '.template-selector-control' => array('display' => 'block')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'init', true,
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');
        $response->AddFieldAction('overrideTemplate', 'change', true,
            array(
                '.template-override-controls' => array('display' => 'block'),
                '.template-selector-control' => array('display' => 'none')
            ), 'is:checked');

        // Present an error message if the module has not been configured. Don't prevent further configuration.
        if (!extension_loaded('curl') || $this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            $formFields['general'][] = Form::AddMessage(__('The Twitter Widget has not been configured yet, please ask your CMS Administrator to look at it for you.'), 'alert alert-danger');
        }

        // Modules should be rendered using the theme engine.
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_template', $formFields['template']);
        Theme::Set('form_fields_effect', $formFields['effect']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);

        // Set the field dependencies
        $this->setFieldDepencencies($response);

        $response->html = Theme::RenderReturn('form_render');
        $response->callBack = 'text_callback';
        // Append the templates to the response
        $response->extra = $this->module->settings['templates'];
        $this->configureFormButtons($response);
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');

        // The response must be returned.
        return $response;
    }

    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        $response = $this->getState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // You should validate all form input using the \Kit::GetParam helper classes
        if (\Kit::GetParam('searchTerm', _POST, _STRING) == '')
            throw new InvalidArgumentException(__('Please enter a search term'));

        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('name', \Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('searchTerm', \Kit::GetParam('searchTerm', _POST, _STRING));
        $this->SetOption('effect', \Kit::GetParam('effect', _POST, _STRING));
        $this->SetOption('speed', \Kit::GetParam('speed', _POST, _INT));
        $this->SetOption('backgroundColor', \Kit::GetParam('backgroundColor', _POST, _STRING));
        $this->SetOption('noTweetsMessage', \Kit::GetParam('noTweetsMessage', _POST, _STRING));
        $this->SetOption('dateFormat', \Kit::GetParam('dateFormat', _POST, _STRING));
        $this->SetOption('resultType', \Kit::GetParam('resultType', _POST, _STRING));
        $this->SetOption('tweetDistance', \Kit::GetParam('tweetDistance', _POST, _INT));
        $this->SetOption('tweetCount', \Kit::GetParam('tweetCount', _POST, _INT));
        $this->SetOption('removeUrls', \Kit::GetParam('removeUrls', _POST, _CHECKBOX));
        $this->SetOption('overrideTemplate', \Kit::GetParam('overrideTemplate', _POST, _CHECKBOX));
        $this->SetOption('templateId', \Kit::GetParam('templateId', _POST, _WORD));
        $this->SetOption('updateInterval', Kit::GetParam('updateInterval', _POST, _INT, 60));

        // Text Template
        $this->setRawNode('template', \Kit::GetParam('ta_text', _POST, _HTMLSTRING));
        $this->setRawNode('styleSheet', \Kit::GetParam('ta_css', _POST, _HTMLSTRING));

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();
        $response->callBack = 'refreshPreview("' . $this->regionid . '")';

        return $response;
    }

    /**
     * Set field dependencies
     * @param ApplicationState $response
     */
    private function setFieldDepencencies(&$response)
    {
        // Add a dependency
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'none'), '.background-color-group' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'none'), '.background-color-group' => array('display' => 'none')));
        $response->AddFieldAction('effect', 'init', 'none', array('.effect-controls' => array('display' => 'block'), '.background-color-group' => array('display' => 'block')), 'not');
        $response->AddFieldAction('effect', 'change', 'none', array('.effect-controls' => array('display' => 'block'), '.background-color-group' => array('display' => 'block')), 'not');
    }

    protected function getToken()
    {

        // Prepare the URL
        $url = 'https://api.twitter.com/oauth2/token';

        // Prepare the consumer key and secret
        $key = base64_encode(urlencode($this->GetSetting('apiKey')) . ':' . urlencode($this->GetSetting('apiSecret')));

        // Check to see if we have the bearer token already cached
        if (Cache::has('bearer_' . $key)) {
            Log::debug('Bearer Token served from cache');
            return Cache::get('bearer_' . $key);
        }

        Log::debug('Bearer Token served from API');

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
            Log::error('Error contacting Twitter API: ' . curl_error($curl));
            return false;
        }

        // We want to check for a 200
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] != 200) {
            Log::error('Twitter API returned ' . $result . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            $body = substr($result, $outHeaders['header_size']);
            // See if we can parse the error.
            $body = json_decode($result);

            Log::error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        $body = substr($result, $outHeaders['header_size']);
        // See if we can parse the body as JSON.
        $body = json_decode($result);

        // We have a 200 - therefore we want to think about caching the bearer token
        // First, lets check its a bearer token
        if ($body->token_type != 'bearer') {
            Log::error('Twitter API returned OK, but without a bearer token. ' . var_export($body, true));
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

        Log::debug('Calling API with: ' . $url . $queryString);

        $curl = curl_init();
        curl_setopt_array($curl, $httpOptions);
        $result = curl_exec($curl);

        // Get the response headers
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] == 0) {
            // Unable to connect
            Log::error('Unable to reach twitter api.');
            return false;
        } else if ($outHeaders['http_code'] != 200) {
            Log::error('Twitter API returned ' . $outHeaders['http_code'] . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            Log::error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

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
            } else {
                $defaultLat = Config::GetSetting('DEFAULT_LAT');
                $defaultLong = Config::GetSetting('DEFAULT_LONG');
            }

            // Built the geoCode string.
            $geoCode = implode(',', array($defaultLat, $defaultLong, $distance)) . 'mi';
        }

        // Connect to twitter and get the twitter feed.
        $key = md5($this->GetOption('searchTerm') . $this->GetOption('resultType') . $this->GetOption('tweetCount', 15) . $geoCode);

        if (!Cache::has($key) || Cache::get($key) == '') {

            Log::debug('Querying API for ' . $this->GetOption('searchTerm'));

            // We need to search for it
            if (!$token = $this->getToken())
                return false;

            // We have the token, make a tweet
            if (!$data = $this->searchApi($token, $this->GetOption('searchTerm'), $this->GetOption('resultType'), $geoCode, $this->GetOption('tweetCount', 15)))
                return false;

            // Cache it
            Cache::put($key, $data, $this->GetSetting('cachePeriod'));
        } else {
            Log::debug('Served from Cache');
            $data = Cache::get($key);
        }

        Log::debug(var_export(json_encode($data), true));

        // Get the template
        $template = $this->getRawNode('template', null);

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
                        $replace = date($this->GetOption('dateFormat', Config::GetSetting('DATE_FORMAT')), Date::getDateFromGregorianString($tweet->created_at));
                        break;

                    case '[ProfileImage]':
                        // Grab the profile image
                        if ($tweet->user->profile_image_url != '') {
                            $file = $media->addModuleFileFromUrl($tweet->user->profile_image_url, 'twitter_' . $tweet->user->id, $expires);

                            // Tag this layout with this file
                            $this->assignMedia($file['mediaId']);

                            $replace = ($isPreview) ? '<img src="index.php?p=content&q=getFile&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';
                        }
                        break;

                    case '[Photo]':
                        // See if there are any photos associated with this tweet.
                        if (isset($tweet->entities->media) && count($tweet->entities->media) > 0) {
                            // Only take the first one
                            $photoUrl = $tweet->entities->media[0]->media_url;

                            if ($photoUrl != '') {
                                $file = $media->addModuleFileFromUrl($photoUrl, 'twitter_photo_' . $tweet->user->id . '_' . $tweet->entities->media[0]->id_str, $expires);
                                $this->assignMedia($file['mediaId']);
                                $replace = ($isPreview) ? '<img src="index.php?p=content&q=getFile&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';
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
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        // Make sure we are set up correctly
        if ($this->GetSetting('apiKey') == '' || $this->GetSetting('apiSecret') == '') {
            Log::error('Twitter Module not configured. Missing API Keys');
            return '';
        }

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');
        $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        // Information from the Module
        $duration = $this->getDuration();

        // Generate a JSON string of substituted items.
        $items = $this->getTwitterFeed($displayId, $isPreview);

        // Return empty string if there are no items to show.
        if (count($items) == 0)
            return '';

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->GetOption('effect', 'none'),
            'speed' => $this->GetOption('speed', 500),
            'duration' => $duration,
            'durationIsPerItem' => ($this->GetOption('durationIsPerItem', 0) == 1),
            'numItems' => count($items),
            'itemsPerPage' => 1,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0)
        );

        // Replace the control meta with our data from twitter
        $controlMeta = '<!-- NUMITEMS=' . count($items) . ' -->' . PHP_EOL . '<!-- DURATION=' . ($this->GetOption('durationIsPerItem', 0) == 0 ? $duration : ($duration * count($items))) . ' -->';
        $template = str_replace('<!--[[[CONTROLMETA]]]-->', $controlMeta, $template);

        // Replace the head content
        $headContent = '';

        // Add the CSS if it isn't empty
        $css = $this->getRawNode('styleSheet', null);
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        $backgroundColor = $this->GetOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<link href="' . (($isPreview) ? 'modules/theme/twitter/' : '') . 'emoji.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';

        // Need the cycle plugin?
        if ($this->GetSetting('effect') != 'none') {
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        }

        // Need the marquee plugin?
        if (stripos($this->GetSetting('effect'), 'marquee'))
            $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery.marquee.min.js"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-text-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . \Kit::jsonEncode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', '', $template);

        return $template;
    }

    public function IsValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }
}
