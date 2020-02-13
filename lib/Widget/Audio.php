<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;

/**
 * Class Audio
 * @package Xibo\Widget
 */
class Audio extends ModuleWidget
{

    /** @inheritDoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'audio-designer-javascript';
    }

    /**
     * Edit an Audio Widget
     * @SWG\Put(
     *  path="/playlist/widget/audio/{playlistId}",
     *  operationId="WidgetAudioEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing audio widget on a layout",
     *  description="Parameters for editing existing audio widget on a layout, for adding new audio, please refer to POST /library documentation",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID",
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
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Set the properties specific to this module
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('mute', $sanitizedParams->getCheckbox('mute'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        // Only loop if the duration is > 0
        if ($this->getUseDuration() == 0 || $this->getDuration() == 0) {
            $this->setDuration(0);
            $this->setOption('loop', 0);
        } else {
            $this->setOption('loop', $sanitizedParams->getCheckbox('loop'));
        }

        $this->saveWidget();

        //return $response;
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
     * @throws \getid3_exception
     */
    public function determineDuration($fileName = null)
    {
        // If we don't have a file name, then we use the default duration of 0 (end-detect)
        if ($fileName === null) {
            return 0;
        }

        $this->getLog()->debug('Determine Duration from ' . $fileName);
        $info = new \getID3();
        $file = $info->analyze($fileName);

        $file = $this->getSanitizer($file);
        return intval($file->getString('playtime_seconds', ['default' => 0]));
    }

    /** @inheritDoc */
    public function setDefaultWidgetOptions()
    {
        parent::setDefaultWidgetOptions();
        $this->setOption('mute', $this->getSetting('defaultMute', 0));
    }

    /** @inheritDoc */
    public function getResource($displayId = 0)
    {

    }

    /** @inheritdoc */
    public function isValid()
    {
        return self::$STATUS_PLAYER;
    }
}