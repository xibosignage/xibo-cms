<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2017 Spring Signage Ltd.
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

use InvalidArgumentException;
use Respect\Validation\Validator as v;

class VideoIn extends ModuleWidget
{
    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Video In';
            $module->type = 'videoin';
            $module->class = 'Xibo\Widget\VideoIn';
            $module->description = 'A module for displaying Video and Audio from an external source';
            $module->imageUri = 'forms/video.gif';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'native';
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
     * Validate
     */
    public function validate()
    {
        // Validate
        if (!v::stringType()->notEmpty()->validate($this->getOption('sourceId')))
            throw new InvalidArgumentException(__('Please Select the sourceId'));

        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration()))
            throw new InvalidArgumentException(__('You must enter a duration.'));
    }
    /**
     * Adds a Video In Widget
     * @SWG\Post(
     *  path="/playlist/widget/videoin/{playlistId}",
     *  operationId="WidgetVideoInAdd",
     *  tags={"widget"},
     *  summary="Add a Video In Widget",
     *  description="Add a new Video In Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Widget to",
     *      type="integer",
     *      required=true
     *  ),
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
     *      description="Flag (0, 1) Select only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="formData",
     *      description="Which device input should be shown? available options: HDMI, RGB, DVI, DP, OPS",
     *      type="string",
     *      required=true
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
    public function add()
    {
        // Set some options
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('sourceId', $this->getSanitizer()->getString('sourceId' , 'hdmi'));
        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     */
    public function edit()
    {
        // Set some options
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('sourceId', $this->getSanitizer()->getString('sourceId' ,'hdmi'));

        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    public function isValid()
    {
        // Client dependant
        return 2;
    }

    /**
     * Default code for the hover preview
     * @return string
     */
    public function hoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="well">';
        $output .= '<div class="preview-module-image"><img alt="' . __($this->module->name) . ' thumbnail" src="' . $this->getConfig()->uri('img/' . $this->module->imageUri) . '" /></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->module->name . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $this->getName() . '</li>';
        $output .= '    <li>' . __('Input') . ': ' . $this->getOption('sourceId') . '</li>';
        if ($this->getUseDuration() == 1)
            $output .= '    <li>' . __('Duration') . ': ' . $this->widget->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;

    }

    /** @inheritdoc */
    public function getResource($displayId)
    {
        // Get resource isn't required for this module

        return null;
    }
}
