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
 * Class PowerPoint
 * @package Xibo\Widget
 */
class PowerPoint extends ModuleWidget
{
    /** @inheritdoc */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->saveWidget();

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'powerpoint-designer-javascript';
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Client dependant
        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function editForm(Request $request)
    {
        return 'generic-form-edit';
    }

    /**
     * @inheritDoc
     */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        return $this->previewIcon();
    }

    /**
     * @inheritDoc
     */
    public function getResource($displayId = 0)
    {
        return '';
    }
}
