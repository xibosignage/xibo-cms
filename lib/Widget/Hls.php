<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (GoogleTraffic.php)
 */


namespace Xibo\Widget;


use Respect\Validation\Validator as v;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\ModuleFactory;

/**
 * Class Hls
 * @package Xibo\Widget
 */
class Hls extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /** @inheritdoc */
    public function init()
    {
        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'HLS';
            $module->type = 'hls';
            $module->class = 'Xibo\Widget\Hls';
            $module->description = 'HLS Video Stream';
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
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a link'), 'uri');
    }

    /**
     * Set common options
     */
    private function setCommonOptions()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));

        // This causes some android devices to switch to a hardware accellerated web view
        $this->setOption('transparency', 0);

        // Ensure we have the necessary files linked up
        $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/web/modules/vendor/hls/hls.min.js');
        $media->save();
        $this->assignMedia($media->mediaId);

        $this->setOption('hlsId', $media->mediaId);

        $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/web/modules/vendor/hls/hls-1px-transparent.png');
        $media->save();
        $this->assignMedia($media->mediaId);

        $this->setOption('posterId', $media->mediaId);
    }

    /**
     * @return int
     */
    public function isValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
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
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/hls/hls.min.js') . '"></script>';
        $javaScriptContent .= '
<script type="text/javascript">
    
    $(document).ready(function() {

        if(Hls.isSupported()) {
            var video = document.getElementById("video");
            var hls = new Hls({
                autoStartLoad: true,
                startPosition : -1,
                capLevelToPlayerSize: false,
                debug: false,
                defaultAudioCodec: undefined,
                enableWorker: true
            });
            hls.loadSource("' . urldecode($this->getOption('uri')) . '");
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
              video.play();
            });
            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    switch(data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            // try to recover network error
                            //console.log("fatal network error encountered, try to recover");
                            hls.startLoad();
                            break;
                        
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            //console.log("fatal media error encountered, try to recover");
                            hls.recoverMediaError();
                            break;
                            
                        default:
                            // cannot recover
                            hls.destroy();
                            break;
                    }
                }
            });
         }
    });
</script>';

        // Parse any other library references
        $javaScriptContent = $this->parseLibraryReferences($isPreview, $javaScriptContent);

        $body = $this->parseLibraryReferences($isPreview, '<video id="video" poster="' . $this->getResourceUrl('vendor/hls/hls-1px-transparent.png') . '"' . (($this->getOption('mute', 0) == 1) ? 'muted' : '') . '></video>');

        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false, 'notifyDisplays' => true]);

        return $this->renderTemplate([
            'viewPortWidth' => ($isPreview) ? $this->region->width : '[[ViewPortWidth]]',
            'javaScript' => $javaScriptContent,
            'body' => $body,
            'head' => '
<style type="text/css">
video {
    width: 100%; 
    height: 100%;
}
</style>'
        ], 'get-resource');
    }
}