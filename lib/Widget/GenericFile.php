<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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

/**
 * Class GenericFile
 * @package Xibo\Widget
 */
class GenericFile extends ModuleWidget
{
    /** @inheritdoc */
    public function edit()
    {
        // Non-editable
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // Videos are never previewed in the browser.
        return $this->previewIcon();
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $this->download();
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
