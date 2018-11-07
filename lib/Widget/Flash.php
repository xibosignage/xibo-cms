<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
 * Class Flash
 * @package Xibo\Widget
 */
class Flash extends ModuleWidget
{

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'flash-designer-javascript';
    }

    /** @inheritdoc */
    public function editForm()
    {
        return 'generic-form-edit';
    }

    /** @inheritdoc */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::preview($width, $height, $scaleOverride);

        $url = $this->getApp()->urlFor('module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]);

        return '<object width="' . $width . '" height="' . $height . '">
            <param name="movie" value="' . $url . '"></param>
            <param name="allowFullScreen" value="false"></param>
            <param name="allowscriptaccess" value="always"></param>
            <param name="wmode" value="transaprent"></param>
            <embed src="' . $url . '"
                   type="application/x-shockwave-flash"
                   allowscriptaccess="always"
                   allowfullscreen="true"
                   width="' . $width . '" height="' . $height . '"
                   wmode="transparent">
            </embed>
        </object>';
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
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
