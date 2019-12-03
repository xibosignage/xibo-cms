<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-15 Daniel Garner
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

use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;

class LocalVideo extends ModuleWidget
{
    
    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'localvideo-designer-javascript';
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?localVideo",
     *  operationId="WidgetLocalVideoEdit",
     *  tags={"widget"},
     *  summary="Edit a Local Video Widget",
     *  description="Edit a Local Video Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="A local file path or URL to the video. This can be RTSP stream.",
     *      type="string",
     *      required=true
     *   ),
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
     *      description="Flag (0, 1) Should the video be muted?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFullScreen",
     *      in="formData",
     *      description="Should the video expand over the top of existing content and show in full screen?",
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
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        // Set some options
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('scaleType', $this->getSanitizer()->getString('scaleTypeId', 'aspect'));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));
        $this->setOption('showFullScreen', $this->getSanitizer()->getCheckbox('showFullScreen'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        $this->isValid();

        // Save the widget
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Validate
        if (!v::stringType()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a full path name giving the location of this video on the client'), 'uri');

        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration()))
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');

        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        return $this->previewIcon();
    }

    /** @inheritdoc */
    public function getResource($displayId)
    {
        // Get resource isn't required for this module.
        return null;
    }
}
