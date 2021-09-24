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

use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManagerStatic as Img;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Video
 * @package Xibo\Widget
 */
class Video extends ModuleWidget
{

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'video-designer-javascript';
    }

    /** @inheritdoc */
    public function settingsForm()
    {
        return 'video-form-settings';
    }

    /** @inheritdoc */
    public function settings(Request $request, Response $response): Response
    {
        // Process any module settings you asked for.
        $this->module->settings['defaultMute'] = $this->getSanitizer($request->getParams())->getCheckbox('defaultMute');
        $this->module->settings['defaultScaleType'] = $this->getSanitizer($request->getParams())->getString('defaultScaleType');

        if ($this->getModule()->defaultDuration !== 0) {
            throw new InvalidArgumentException(__('The Video Module must have a default duration of 0 to detect the end of videos.'));
        }

        // Return an array of the processed settings.
        return $response;
    }

    /**
     * Edit a Video Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?video",
     *  operationId="WidgetVideoEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing video on a layout",
     *  description="For uploading new video files, please refer to POST /library documentation.
     *               For assigning existing video file to a Playlist please see POST /playlist/library/assign/{playlistId} documentation.
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
     *      description="Edit only - Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Edit Only - (0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Edit Only - The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="scaleTypeId",
     *      in="formData",
     *      description="How should the video be scaled, available options: aspect, stretch",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mute",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the video be muted?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Edit only - Flag (0, 1) Should the video loop (only for duration > 0 )?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFullScreen",
     *      in="formData",
     *      description="Edit only - Should the video expand over the top of existing content and show in full screen?",
     *      type="integer",
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
     * @inheritdoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Set the properties specific to this module
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('scaleType', $sanitizedParams->getString('scaleTypeId', ['default' => 'aspect']));
        $this->setOption('mute', $sanitizedParams->getCheckbox('mute'));
        $this->setOption('showFullScreen', $sanitizedParams->getCheckbox('showFullScreen'));

        // Only loop if the duration is > 0
        if ($this->getUseDuration() == 0 || $this->getDuration() == 0) {
            $this->setDuration(0);
            $this->setOption('loop', 0);
        } else {
            $this->setOption('loop', $sanitizedParams->getCheckbox('loop'));
        }
        
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0) {
            return parent::preview($width, $height, $scaleOverride);
        }

        $proportional = ($this->getOption('scaleType') == 'stretch') ? 0 : 1;
        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $filePath = $libraryLocation . $this->getMediaId() . '_videocover.png';

        if (!file_exists($filePath)) {
            return $this->previewAsClient($width, $height, $scaleOverride);
        } else {
            $url = $this->urlFor('library.download', ['regionId' => $this->region->regionId, 'id' => $this->getMediaId()]) . '?preview=1&width=' . $width . '&height=' . $height . '&proportional=' . $proportional;

            // Show the video cover image - scaled to the aspect ratio of this region (get from GET)
            return '<div style="display:table; width:100%; height: ' . $height . 'px">
            <div style=" display: table-cell;">
                <div style="text-align:center;">
                    <i class="fa module-preview-icon module-icon-video" style="position:fixed;"></i>
                </div>
                <div style="text-align:center;">
                    <img src="' . $url . '" />
                </div>
            </div>
        </div>';
        }
    }

    /** @inheritdoc */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        return $this->previewIcon();
    }

    /** @inheritdoc */
    public function determineDuration($fileName = null)
    {
        // If we don't have a file name, then we use the default duration of 0 (end-detect)
        if ($fileName === null)
            return 0;

        $this->getLog()->debug('Determine Duration from ' . $fileName);
        $info = new \getID3();
        $file = $info->analyze($fileName);
        return intval($this->getSanitizer($file)->getDouble('playtime_seconds', ['default' => 0]));
    }

    /** @inheritdoc */
    public function setDefaultWidgetOptions()
    {
        parent::setDefaultWidgetOptions();
        $this->setOption('mute', $this->getSetting('defaultMute', 0));
        $this->setOption('scaleType', $this->getSetting('defaultScaleType', 'aspect'));
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        return '';
    }

    /** @inheritdoc */
    public function isValid()
    {
        return self::$STATUS_VALID;
    }

    /** @inheritDoc */
    public function download(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());
        $this->getLog()->debug('Video Module: download for ' . $this->getMediaId());

        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $media = $this->mediaFactory->getById($this->getMediaId());
        $filePath = $libraryLocation . $media->mediaId . '_videocover.png';
        $thumbnailFilePath = $libraryLocation . 'tn_' . $media->mediaId . '_videocover.png';

        $this->getLog()->debug('Media Returned: ' . $filePath);

        $proportional = $sanitizedParams->getInt('proportional', ['default' => 1]) == 1;
        $preview = $sanitizedParams->getInt('preview', ['default' => 0]) == 1;
        $layoutPreview = $sanitizedParams->getInt('layoutPreview', ['default' => 0]) == 1;
        $cache = $sanitizedParams->getInt('cache', ['default' => 0]) == 1;
        $width = intval($sanitizedParams->getDouble('width'));
        $height = intval($sanitizedParams->getDouble('height'));

        $this->getLog()->debug('Preview: ' . var_export($preview, true));
        $this->getLog()->debug('Layout preview: ' . var_export($layoutPreview, true));

        // Preview or download?
        if ($preview && !$layoutPreview) {
            // We expect the preview to load, manipulate and output a thumbnail (even on error).
            // therefore we need to end output buffering and wipe any output so far.
            // this means that we do not buffer the image output into memory
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Preview (we output the file to the browser with image headers)
            try {
                // should we use a cache?
                if (!$cache || ($cache && !file_exists($thumbnailFilePath))) {
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
                    $httpCache = $this->cacheProvider;
                    $response = $httpCache->withEtag($response, $media->md5 . $width . $height . $proportional . $preview);
                    $response = $httpCache->withExpires($response,'+1 week');

                    // Should we cache?
                    if ($cache) {
                        $this->getLog()->debug('Saving cached copy to tn_');

                        // Save the file
                        $img->save($thumbnailFilePath);
                    }

                    // Output the file
                    echo $img->encode('png');

                } else if ($cache) {
                    // File exists, output it directly
                    $img = Img::make($thumbnailFilePath);
                    echo $img->encode('png');
                }
            } catch (NotReadableException $notReadableException) {
                $this->getLog()->debug($notReadableException->getTraceAsString());
                $this->getLog()->error('Video cover image not readable: ' . $notReadableException->getMessage());

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

            return $response;
        } else {
            // Download the file
            return parent::download($request, $response);
        }
    }

    public function hasThumbnail()
    {
        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $videoImageCoverExists = file_exists($libraryLocation . $this->getMediaId() . '_videocover.png');

        if ($videoImageCoverExists) {
            return true;
        } else {
            return false;
        }
    }
}
