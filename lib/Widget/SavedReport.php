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
 * Class SavedReport
 * @package Xibo\Widget
 */
class SavedReport extends ModuleWidget
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
    public function edit(Request $request, Response $response): Response
    {
        // Non-editable
        return $response;
    }

    /**
     * @inheritDoc
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // Videos are never previewed in the browser.
        return $this->previewIcon();
    }

    /**
     * @inheritDoc
     */
    public function getResource($displayId = 0)
    {
        return '';
    }

    /** @inheritDoc */
    public function download(Request $request, Response $response): Response
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        return parent::download($request, $response);
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
