<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
namespace Xibo\Widget;


class Video extends ModuleWidget
{
    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'video-form-settings';
    }

    /**
     * Process any module settings
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $this->module->settings['defaultMute'] = $this->getSanitizer()->getCheckbox('defaultMute');

        if ($this->getModule()->defaultDuration !== 0)
            throw new \InvalidArgumentException(__('The Video Module must have a default duration of 0 to detect the end of videos.'));

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    /**
     * Edit a Video Widget
     * @SWG\Post(
     *  path="/playlist/widget/video/{playlistId}",
     *  operationId="WidgetVideoEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing video on a layout",
     *  description="Parameters for editing existing video on a layout, for adding new videos, please refer to POST /library documentation",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Edit only - Optional Widget Name",
     *      type="string",
     *      required=false
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
     *      name="scaleTypeId",
     *      in="formData",
     *      description="How should the video be scaled, available options: aspect, stretch",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mute",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the video be muted?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the video loop (only for duration > 0 )?",
     *      type="integer",
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
        $this->setOption('scaleType', $this->getSanitizer()->getString('scaleTypeId', 'aspect'));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));

        // Only loop if the duration is > 0
        if ($this->getUseDuration() == 0 || $this->getDuration() == 0)
            $this->setOption('loop', 0);
        else
            $this->setOption('loop', $this->getSanitizer()->getCheckbox('loop'));

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

    /**
     * Is this module valid
     * @return int
     */
    public function isValid()
    {
        // Yes
        return 1;
    }
}
