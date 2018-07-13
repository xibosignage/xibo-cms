<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Pdf.php)
 */

namespace Xibo\Widget;

use Xibo\Exception\XiboException;

/**
 * Class Pdf
 * @package Xibo\Widget
 */
class Pdf extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/pdf.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/pdf.worker.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/pdfjs/compatibility.js')->save();
    }

    /**
     * Edit a pdf Widget
     * @SWG\Post(
     *  path="/playlist/widget/pdf/{playlistId}",
     *  operationId="WidgetPdfEdit",
     *  tags={"widget"},
     *  summary="Parameters for editing existing pdf on a layout",
     *  description="Parameters for editing existing pdf on a layout, for adding new files, please refer to POST /library documentation",
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
     */
    public function edit()
    {
        // Set the properties specific to this module
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('name', $this->getSanitizer()->getString('name'));


        $this->saveWidget();
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

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     * @throws XiboException
     */
    public function getResource($displayId = 0)
    {
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // If not preview or a display, then return the file directly
        if (!$isPreview && $displayId === 0) {
            $this->download();
            return '';
        }

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        $duration = $this->getCalculatedDurationForGetResource();

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $duration,
            'durationIsPerItem' => false,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        // File name?
        $data['file'] = ($isPreview) ? $this->getApp()->urlFor('library.download', ['id' => $this->getMediaId()]) : $this->getMedia()->storedAs;

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
        // We have a short cache duration because this module doesn't rely on any external sources and we're just
        // creating some HTML.
        return 1;
    }
}