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

use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManagerStatic as Img;
use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;

/**
 * Class Image
 * @package Xibo\Widget
 */
class Image extends ModuleWidget
{
    /** @inheritdoc */
    public function settingsForm()
    {
        return 'image-form-settings';
    }

    /** @inheritdoc */
    public function settings()
    {
        parent::settings();

        $this->module->settings['defaultScaleTypeId'] = $this->getSanitizer()->getString('defaultScaleTypeId');
    }

    /** @inheritdoc */
    public function setDefaultWidgetOptions()
    {
        parent::setDefaultWidgetOptions();

        $this->setOption('scaleType', $this->getSetting('defaultScaleTypeId', 'center'));
    }


    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'image-designer-javascript';
    }

    /**
     * Edit an Image Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?image",
     *  operationId="WidgetImageEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing image on a layout",
     *  description="For uploading new image files, please refer to POST /library documentation.
     *               For assigning existing image file to a Playlist please see POST /playlist/library/assign/{playlistId} documentation.
     *               This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The Widget ID",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
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
     *      name="useDuration",
     *      in="formData",
     *      description="Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="scaleTypeId",
     *      in="formData",
     *      description="Select scale type available options: center, stretch",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignId",
     *      in="formData",
     *      description="Horizontal alignment - left, center, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="valignId",
     *      in="formData",
     *      description="Vertical alignment - top, middle, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        // Set the properties specific to Images
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('scaleType', $this->getSanitizer()->getString('scaleTypeId', 'center'));
        $this->setOption('align', $this->getSanitizer()->getString('alignId', 'center'));
        $this->setOption('valign', $this->getSanitizer()->getString('valignId', 'middle'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        $this->isValid();
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::preview($width, $height);

        $proportional = ($this->getOption('scaleType') == 'stretch') ? 0 : 1;
        $align = $this->getOption('align', 'center');
        $vAlign = $this->getOption('valign', 'middle');

        $url = $this->getApp()->urlFor('module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]) . '?preview=1&width=' . $width . '&height=' . $height . '&proportional=' . $proportional . '&mediaId=' . $this->getMediaId();

        $html = '<div style="display:table; width:100%; height: ' . $height . 'px">
            <div style="text-align:' . $align . '; display: table-cell; vertical-align: ' . $vAlign . ';">
                <img src="' . $url . '" />
            </div>
        </div>';

        // Show the image - scaled to the aspect ratio of this region (get from GET)
        return $html;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $this->getLog()->debug('Image Module: GetResource for ' . $this->getMediaId());

        $media = $this->mediaFactory->getById($this->getMediaId());
        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $filePath = $libraryLocation . $media->storedAs;
        $proportional = $this->getSanitizer()->getInt('proportional', 1) == 1;
        $preview = $this->getSanitizer()->getInt('preview', 0) == 1;
        $cache = $this->getSanitizer()->getInt('cache', 0) == 1;
        $width = intval($this->getSanitizer()->getDouble('width'));
        $height = intval($this->getSanitizer()->getDouble('height'));

        // Get the extension if we can (module files do not have an extension stored with them)
        if (stripos($media->storedAs, '.') > -1) {
            $extension = explode('.', $media->storedAs)[1];
        } else if (stripos($media->fileName, '.')) {
            $extension = explode('.', $media->fileName)[1];
        } else {
            $extension = null;
        }

        // Preview or download?
        if ($preview) {

            // We expect the preview to load, manipulate and output a thumbnail (even on error).
            // therefore we need to end output buffering and wipe any output so far.
            // this means that we do not buffer the image output into memory
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Preview (we output the file to the browser with image headers)
            try {
                // should we use a cache?
                if (!$cache || ($cache && !file_exists($libraryLocation . 'tn_' . $media->storedAs))) {
                    // Not cached, or cache not required, lets load it again
                    Img::configure(array('driver' => 'gd'));

                    $this->getLog()->debug('Preview Requested with Width and Height '. $width . ' x ' . $height);
                    $this->getLog()->debug('Loading ' . $filePath);

                    // Load the image
                    $img = Img::make($filePath);

                    // Output a thumbnail?
                    if ($width != 0 || $height != 0) {
                        // Make a thumb
                        $img->resize($width, $height, function ($constraint) use ($proportional) {
                            if ($proportional)
                                $constraint->aspectRatio();
                        });
                    }

                    $this->getLog()->debug('Outputting Image Response');

                    // Output Etags
                    $this->getApp()->etag($media->md5 . $width . $height . $proportional . $preview);
                    $this->getApp()->expires('+1 week');

                    // Should we cache?
                    if ($cache) {
                        $this->getLog()->debug('Saving cached copy to tn_');

                        // Save the file
                        $img->save($libraryLocation . 'tn_' . $media->storedAs);
                    }

                    // Output the file
                    echo $img->encode($extension);

                } else if ($cache) {
                    // File exists, output it directly
                    $img = Img::make($libraryLocation . 'tn_' . $media->storedAs);
                    echo $img->encode($extension);
                }
            } catch (NotReadableException $notReadableException) {
                $this->getLog()->debug($notReadableException->getTraceAsString());
                $this->getLog()->error('Image not readable: ' . $notReadableException->getMessage());

                // Output the thumbnail
                $img = Img::make($this->getConfig()->uri('img/error.png', true));

                if ($width != 0 || $height != 0) {
                    $img->resize($width, $height, function ($constraint) use ($proportional) {
                        if ($proportional)
                            $constraint->aspectRatio();
                    });
                }

                echo $img->encode();
            }
        } else {
            // Download the file
            $this->download();
        }
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getMedia()->released == 0) {
            $this->statusMessage = __('%s is pending conversion', $this->getMedia()->name);
            return self::$STATUS_PLAYER;
        } elseif ($this->getMedia()->released == 2) {
            $resizeLimit = $this->getConfig()->getSetting('DEFAULT_RESIZE_LIMIT');
            $this->statusMessage = __('%s is too large. Please ensure that none of the images in your layout are larger than %s pixels on their longest edge. Please check the allowed Resize Limit in Administration -> Settings', $this->getMedia()->name, $resizeLimit);
            throw new InvalidArgumentException(__('%s is too large. Please ensure that none of the images in your layout are larger than %s pixels on their longest edge. Please check the allowed Resize Limit in Administration -> Settings', $this->getMedia()->name, $resizeLimit), 'name');
        }

        if (!v::intType()->min(1, true)->validate($this->getDuration()))
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');

        return self::$STATUS_VALID;
    }
}
