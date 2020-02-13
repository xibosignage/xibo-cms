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
 * Class GenericFile
 * @package Xibo\Widget
 */
class GenericFile extends ModuleWidget
{
    /** @inheritdoc */
    public function edit(Request $request, Response $response): Response
    {
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
