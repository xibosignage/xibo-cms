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

use Intervention\Image\ImageManagerStatic as Img;
use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;

class Image extends ModuleWidget
{
    /**
     * Validate
     */
    public function validate()
    {
        // Validate
        if (!v::int()->min(1)->validate($this->getDuration()))
            throw new \InvalidArgumentException(__('You must enter a duration.'));
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        // Set the properties specific to Images
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name', $this->getOption('name')));
        $this->setOption('scaleType', $this->getSanitizer()->getString('scaleTypeId', 'center'));
        $this->setOption('align', $this->getSanitizer()->getString('alignId', 'center'));
        $this->setOption('valign', $this->getSanitizer()->getString('valignId', 'middle'));

        $this->validate();
        $this->saveWidget();
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
        if ($this->module->previewEnabled == 0)
            return parent::preview($width, $height);

        $proportional = ($this->getOption('scaleType') == 'stretch') ? 0 : 1;
        $align = $this->getOption('align', 'center');
        $vAlign = $this->getOption('valign', 'middle');

        $html = '<div style="display:table; width:100%; height: ' . $height . 'px">
            <div style="text-align:' . $align . '; display: table-cell; vertical-align: ' . $vAlign . ';">
                <img src="' . $this->getApp()->urlFor('library.download', ['id' => $this->getMediaId()]) . '?preview=1&width=' . $width . '&height=' . $height . '&proportional=' . $proportional . '" />
            </div>
        </div>';

        // Show the image - scaled to the aspect ratio of this region (get from GET)
        return $html;
    }

    /**
     * Hover preview
     * @return string
     */
    public function hoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = parent::hoverPreview();

        try {
            $output .= '<div class="hoverPreview">';
            $output .= '    <img src="' . $this->getApp()->urlFor('library.download', ['id' => $this->getMediaId()]) . '?preview=1&width=200&height=200&proportional=1" alt="Hover Preview">';
            $output .= '</div>';
        } catch (NotFoundException $e) {
            $this->getLog()->error('Cannot find image to show in HoverPreview. WidgetId: %d', $this->getWidgetId());
        }

        return $output;
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        $this->getLog()->debug('Image Module: GetResource for %d', $this->getMediaId());

        $media = $this->mediaFactory->getById($this->getMediaId());
        $libraryLocation = $this->getConfig()->GetSetting('LIBRARY_LOCATION');
        $filePath = $libraryLocation . $media->storedAs;
        $proportional = $this->getSanitizer()->getInt('proportional', 1) == 1;
        $preview = $this->getSanitizer()->getInt('preview', 0) == 1;
        $width = intval($this->getSanitizer()->getDouble('width'));
        $height = intval($this->getSanitizer()->getDouble('height'));

        // Work out the eTag first
        $this->getApp()->etag($media->md5 . $width . $height . $proportional . $preview);
        $this->getApp()->expires('+1 week');

        // Preview or download?
        if ($this->getSanitizer()->getInt('preview', 0) == 1) {

            // Preview (we output the file to the browser with image headers
            Img::configure(array('driver' => 'gd'));

            $this->getLog()->debug('Preview Requested with Width and Height %d x %d', $width, $height);

            // Output a thumbnail?
            if ($width != 0 || $height != 0) {
                // Make a thumb
                $img = Img::make($filePath)->resize($width, $height, function($constraint) use ($proportional) {
                    if ($proportional)
                        $constraint->aspectRatio();
                });
            }
            else {
                // Load the whole image
                $this->getLog()->debug('Loading %s', $filePath);
                $eTag = $media->md5;
                $img = Img::make($filePath);
            }

            $this->getLog()->debug('Outputting Image Response');

            // Output the file
            echo $img->response();
        }
        else {
            // Download the file
            $this->download();
        }
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
