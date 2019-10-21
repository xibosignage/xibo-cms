<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Xibo\Factory\ModuleFactory;

/**
 * Class Spacer
 * @package Xibo\Widget
 */
class Spacer extends ModuleWidget
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
            $module->name = 'Spacer';
            $module->type = 'spacer';
            $module->class = 'Xibo\Widget\Spacer';
            $module->description = 'Make a Region empty for a specified duration';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->validExtensions = '';
            $module->settings = [];
            $module->installName = 'spacer';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'spacer-designer-javascript';
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?spacer",
     *  operationId="WidgetSpacerEdit",
     *  tags={"widget"},
     *  summary="Edit a Spacer Widget",
     *  description="Edit Spacer Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function edit()
    {
        // Set the properties specific to this module
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        $this->saveWidget();
    }

    /**
     * @inheritdoc
     */
    public function isValid()
    {
        // this is empty html, always valid
        return 1;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Construct the response HTML
        $this->initialiseGetResource()->appendViewPortWidth($this->region->width);
        return $this->finaliseGetResource();
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }
}
