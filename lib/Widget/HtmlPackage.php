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

use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class HtmlPackage
 * @package Xibo\Widget
 */
class HtmlPackage extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'HTML Package';
            $module->type = 'htmlpackage';
            $module->class = 'Xibo\Widget\HtmlPackage';
            $module->description = 'A module for displaying HTML packages in .htz format';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 1;
            $module->regionSpecific = 0;
            $module->renderAs = 'native';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->validExtensions = 'htz';
            $module->settings = [];
            $module->installName = 'htmlpackage';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function settingsForm()
    {
        // Return the name of the TWIG file to render the settings form
        return 'htmlpackage-form-settings';
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function settings(Request $request, Response $response): Response
    {
        parent::settings($request, $response);

        if ($this->module->enabled != 0) {
            if ($this->getSanitizer($request->getParams())->getInt('updateInterval') <= 0) {
                throw new InvalidArgumentException(__('Update Interval must be a positive number'), 'updateInterval');
            }
        }

        $this->module->settings['updateInterval'] = $this->getSanitizer($request->getParams())->getInt('updateInterval', ['default' => 259200]);

        return $response;
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::intType()->min(1, true)->validate($this->getDuration())) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }
    }

    /**
     * @inheritDoc
     */
    public function layoutEditorJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'htmlpackage-designer-javascript';
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?htmlPackage",
     *  operationId="WidgetHtmlPackageEdit",
     *  tags={"widget"},
     *  summary="Edit a HtmlPackage Widget",
     *  description="Edit HtmlPackage Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="nominatedFile",
     *      in="formData",
     *      description="Enter a nominated file name that player will attempt to open after extracting the .htz archive",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update Interval for this Widget",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Set the properties specific to this module
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('nominatedFile', urlencode($sanitizedParams->getString('nominatedFile')));
        $this->setOption('updateInterval', $this->getSetting('updateInterval', 259200));

        $this->saveWidget();

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        return '';
    }
}
