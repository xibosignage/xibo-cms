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

use Xibo\Factory\ModuleFactory;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
/**
 * Class SavedReport
 * @package Xibo\Widget
 */
class SavedReport extends ModuleWidget
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
            $module->name = 'Saved Reports';
            $module->type = 'savedreport';
            $module->class = 'Xibo\Widget\SavedReport';
            $module->description = 'A saved report to be stored in the library';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 0;
            $module->regionSpecific = 0;
            $module->renderAs = null;
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 10;
            $module->validExtensions = 'json';
            $module->settings = [];
            $module->installName = 'savedreport';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritdoc */
    public function edit(Request $request, Response $response, $id)
    {
        // Non-editable
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @param Request|null $request
     * @return string The Rendered Content
     */
    public function preview($width, $height, $scaleOverride = 0, Request $request = null)
    {
        // Videos are never previewed in the browser.
        return $this->previewIcon();
    }

    /**
     * Get Resource
     * @param Request $request
     * @param Response $response
     * @return mixed
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getResource(Request $request, Response $response)
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $this->download($request, $response);
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
