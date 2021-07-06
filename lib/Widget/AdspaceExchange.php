<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

/**
 * Class AdspaceExchange
 * @package Xibo\Custom\XiboAdspace
 */
class AdspaceExchange extends ModuleWidget
{
    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Adspace Exchange';
            $module->type = 'adspaceexchange';
            $module->class = 'Xibo\Widget\AdspaceExchange';
            $module->viewPath = '../custom/XiboAdspace';
            $module->description = 'Adspace Exchange facilitates advertising content delivered to your network via Adspace\'s demand partners.';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 0;
            $module->regionSpecific = 1;
            $module->renderAs = 'native';
            $module->schemaVersion = 1;
            $module->defaultDuration = 10;
            $module->installName = 'xibo-adspaceexchange';
            $module->validExtensions = '';
            $module->settings = [];

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('key', $sanitizedParams->getString('key', [
            'default' => $this->getOption('key', '')
        ]));
        $this->saveWidget();
        return $response;
    }

    /**
     * @inheritDoc
     */
    public function isValid()
    {
        return 1;
    }

    /**
     * @inheritDoc
     */
    public function getResource($displayId = 0)
    {
        return null;
    }
}
