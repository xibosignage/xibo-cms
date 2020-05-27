<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Audio.php)
 */


namespace Xibo\Widget;


class Audio extends ModuleWidget
{

    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'audio-designer-javascript';
    }

    /**
     * Edit an Audio Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?audio",
     *  operationId="WidgetAudioEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing audio widget on a layout",
     *  description="For uploading new audio files, please refer to POST /library documentation.
     *               For assigning existing audio file to a Playlist please see POST /playlist/library/assign/{playlistId} documentation.
     *               This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The Widget ID",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Edit Only - (0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Edit Only - The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Edit Only - The Widget name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="mute",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the audio be muted?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the audio loop (only for duration > 0 )?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function edit()
    {
        // Set the properties specific to this module
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        // Only loop if the duration is > 0
        if ($this->getUseDuration() == 0 || $this->getDuration() == 0) {
            $this->setDuration(0);
            $this->setOption('loop', 0);
        } else {
            $this->setOption('loop', $this->getSanitizer()->getCheckbox('loop'));
        }

        $this->saveWidget();
    }

    /**
     * Override previewAsClient
     * @param float $width
     * @param float $height
     * @param int $scaleOverride
     * @return string
     */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        return $this->previewIcon();
    }

    /**
     * Determine duration
     * @param $fileName
     * @return int
     */
    public function determineDuration($fileName = null)
    {
        // If we don't have a file name, then we use the default duration of 0 (end-detect)
        if ($fileName === null)
            return 0;

        $this->getLog()->debug('Determine Duration from %s', $fileName);
        $info = new \getID3();
        $file = $info->analyze($fileName);
        return intval($this->getSanitizer()->getDouble('playtime_seconds', 0, $file));
    }

    /**
     * Set default widget options
     */
    public function setDefaultWidgetOptions()
    {
        parent::setDefaultWidgetOptions();
        $this->setOption('mute', $this->getSetting('defaultMute', 0));
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        $this->download();
    }

    /** @inheritdoc */
    public function isValid()
    {
        return self::$STATUS_PLAYER;
    }
}