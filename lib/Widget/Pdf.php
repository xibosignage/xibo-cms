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

use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Exception\InvalidArgumentException;

/**
 * Class Pdf
 * @package Xibo\Widget
 */
class Pdf extends ModuleWidget
{
    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'pdf-designer-javascript';
    }

    /**
     * @inheritDoc
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/pdf.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/pdf.worker.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/compatibility.js')->save();
    }

    /**
     * Edit PDF Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?pdf",
     *  operationId="WidgetPdfEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing pdf on a layout",
     *  description="Parameters for editing existing pdf on a layout, for adding new files, please refer to POST /library documentation",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
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
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
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
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Set the properties specific to this module
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration()))
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $this->initialiseGetResource();

        // Replace the View Port Width?
        $data['viewPortWidth'] = $this->isPreview() ? $this->region->width : '[[ViewPortWidth]]';

        $duration = $this->getCalculatedDurationForGetResource();

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $duration,
            'durationIsPerItem' => false,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => intval($this->getPreviewWidth()),
            'previewHeight' => intval($this->getPreviewHeight())
        );

        // File name?
        $data['file'] = ($this->isPreview()) ? $this->urlFor('library.download', ['id' => $this->getMediaId()]) : $this->getMedia()->storedAs;

        // Replace the head content
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/pdfjs/pdf.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/pdfjs/compatibility.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '</script>';

        $data['pdfWorkerSrc'] = $this->getResourceUrl('vendor/pdfjs/pdf.worker.js');

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data, 'get-resource-pdf');
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache duration because this module doesn't rely on any external sources and we're just
        // creating some HTML.
        return 86400 * 365;
    }
}