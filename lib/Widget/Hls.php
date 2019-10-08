<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (GoogleTraffic.php)
 */


namespace Xibo\Widget;


use Respect\Validation\Validator as v;
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
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'hls-designer-javascript';
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
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'hls';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?hls",
     *  operationId="WidgetHlsEdit",
     *  tags={"widget"},
     *  summary="Edit a HLS Widget",
     *  description="Edit HLS Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Select only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="URL to HLS video stream",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="mute",
     *      in="formData",
     *      description="Flag (0, 1) Should the video be muted?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="transparency",
     *      in="formData",
     *      description="Flag (0, 1), This causes some android devices to switch to a hardware accelerated web view",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));

        // This causes some android devices to switch to a hardware accellerated web view
        $this->setOption('transparency', 0);

        $this->isValid();

        // Save the widget
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a link'), 'uri');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Ensure we have the necessary files linked up
        $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/modules/vendor/hls/hls.min.js');
        $media->save();
        $this->assignMedia($media->mediaId);

        $this->setOption('hlsId', $media->mediaId);

        $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/modules/vendor/hls/hls-1px-transparent.png');
        $media->save();
        $this->assignMedia($media->mediaId);

        $this->setOption('posterId', $media->mediaId);

        // Render and output HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScriptFile('vendor/hls/hls.min.js')
            ->appendJavaScript('
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
            ')
            ->appendBody('<video id="video" poster="' . $this->getResourceUrl('vendor/hls/hls-1px-transparent.png') . '" ' . (($this->getOption('mute', 0) == 1) ? 'muted' : '') . '></video>')
            ->appendCss('
                video {
                    width: 100%; 
                    height: 100%;
                }
            ')
        ;

        return $this->finaliseGetResource();
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }
}