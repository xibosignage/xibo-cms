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

use Widget\Module;

class Flash extends Module
{
    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function Preview($width, $height, $scaleOverride = 0)
    {
        // Never previewed in the browser.
        return $this->previewIcon();
    }

    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->previewEnabled == 0)
            return $this->Preview($width, $height, $scaleOverride);

        $url = 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=GetResource&raw=true&preview=true&scale_override=' . $scaleOverride . '&layoutid=' . $this->layoutid . '&regionid=' . $this->regionid . '&mediaid=' . $this->mediaid . '&lkid=' . $this->lkid . '&width=' . $width . '&height=' . $height;

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
    public function GetResource($displayId = 0)
    {
        $this->download();
        exit();
    }

    /**
     * Is this module valid
     * @return int
     */
    public function IsValid()
    {
        // Yes
        return 1;
    }
}
