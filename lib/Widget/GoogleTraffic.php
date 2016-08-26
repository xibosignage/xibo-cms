<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (GoogleTraffic.php)
 */


namespace Xibo\Widget;


use Xibo\Exception\ConfigurationException;
use Xibo\Factory\ModuleFactory;

/**
 * Class GoogleTraffic
 * @package Xibo\Widget
 */
class GoogleTraffic extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Google Traffic';
            $module->type = 'googletraffic';
            $module->class = 'Xibo\Widget\GoogleTraffic';
            $module->description = 'Google Traffic Map';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Install Files
     */
    public function InstallFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'googletraffic-form-settings';
    }

    /**
     * Process any module settings
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = $this->getSanitizer()->getString('apiKey');

        if ($apiKey == '')
            throw new \InvalidArgumentException(__('Missing API Key'));

        $this->module->settings['apiKey'] = $apiKey;
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setCommonOptions();
        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setCommonOptions();
        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Validate
     */
    private function validate()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        if ($this->getOption('zoom') == '')
            throw new \InvalidArgumentException(__('Please enter a zoom level'));
    }

    /**
     * Set common options
     */
    private function setCommonOptions()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('useDisplayLocation', $this->getSanitizer()->getCheckbox('useDisplayLocation'));
        $this->setOption('longitude', $this->getSanitizer()->getDouble('longitude'));
        $this->setOption('latitude', $this->getSanitizer()->getDouble('latitude'));
        $this->setOption('zoom', $this->getSanitizer()->getInt('zoom'));
    }

    public function isValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 2;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     * @throws ConfigurationException
     */
    public function getResource($displayId = 0)
    {
        // Behave exactly like the client.
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        if ($this->getSetting('apiKey') == '')
            throw new ConfigurationException(__('Module not configured. Missing API Key.'));

        // Get the lat/long
        $defaultLat = $this->getConfig()->GetSetting('DEFAULT_LAT');
        $defaultLong = $this->getConfig()->GetSetting('DEFAULT_LONG');

        if ($this->getOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {

                $display = $this->displayFactory->getById($displayId);

                if ($display->latitude != '' && $display->longitude != '') {
                    $defaultLat = $display->latitude;
                    $defaultLong = $display->longitude;
                } else {
                    $this->getLog()->error('Warning, display %s does not have a lat/long and yet a forecast widget is set to use display location.', $display->display);
                }
            }
        } else {
            $defaultLat = $this->getOption('latitude', $defaultLat);
            $defaultLong = $this->getOption('longitude', $defaultLong);
        }

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        return $this->renderTemplate([
            'viewPortWidth' => ($isPreview) ? $this->region->width : '[[ViewPortWidth]]',
            'apiKey' => $this->getSetting('apiKey'),
            'javaScript' => $javaScriptContent,
            'lat' => $defaultLat,
            'long' => $defaultLong,
            'zoom' => $this->getOption('zoom', 12)
        ], 'google-traffic-get-resource');
    }
}