<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Daniel Garner
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
 * Class Embedded
 * @package Xibo\Widget
 */
class Embedded extends ModuleWidget
{
    /**
     * Install Files
     */
    public function InstallFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /**
     * Adds an Embedded Widget
     * @SWG\Post(
     *  path="/playlist/widget/embedded/{playlistId}",
     *  operationId="WidgetEmbeddedAdd",
     *  tags={"widget"},
     *  summary="Add a Embedded Widget",
     *  description="Add a new Embedded Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add an Embedded Widget",
     *      type="integer",
     *      required=true
     *   ),
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
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="transparency",
     *      in="formData",
     *      description="Flag (0,1) - Should the HTML be shown with transparent background? - not available on Windows Clients",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="scaleContent",
     *      in="formData",
     *      description="Flag (0,1) - Should the embedded content be scaled along with the layout?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embedHtml",
     *      in="formData",
     *      description="HTML to embed",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embedScript",
     *      in="formData",
     *      description="HEAD content to Embed (including script tags)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embedStyle",
     *      in="formData",
     *      description="Custom Style Sheets (CSS)",
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
     */
    public function add()
    {
        // Required Attributes
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('transparency', $this->getSanitizer()->getCheckbox('transparency'));
        $this->setOption('scaleContent', $this->getSanitizer()->getCheckbox('scaleContent'));
        $this->setRawNode('embedHtml', $this->getSanitizer()->getParam('embedHtml', null));
        $this->setRawNode('embedScript', $this->getSanitizer()->getParam('embedScript', null));
        $this->setRawNode('embedStyle', $this->getSanitizer()->getParam('embedStyle', null));

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('transparency', $this->getSanitizer()->getCheckbox('transparency'));
        $this->setOption('scaleContent', $this->getSanitizer()->getCheckbox('scaleContent'));
        $this->setRawNode('embedHtml', $this->getSanitizer()->getParam('embedHtml', null));
        $this->setRawNode('embedScript', $this->getSanitizer()->getParam('embedScript', null));
        $this->setRawNode('embedStyle', $this->getSanitizer()->getParam('embedStyle', null));

        // Save the widget
        $this->saveWidget();
    }

    /**
     * @inheritdoc
     */
    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        // Construct the response HTML
        $this->initialiseGetResource()->appendViewPortWidth($this->region->width);

        // Include some vendor items and javascript
        $this
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendRaw('javaScript', $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('embedScript', null)))
            ->appendCss($this->parseLibraryReferences($this->isPreview(), $this->getRawNode('embedStyle', null)))
            ->appendFontCss()
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)))
            ->appendOptions([
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height,
                'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
                'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
                'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
            ])
            ->appendJavaScript('
                $(document).ready(function() { EmbedInit(); });
                $("body").find("img").xiboImageRender(options);
            ')
            ->appendBody($this->parseLibraryReferences($this->isPreview(), $this->getRawNode('embedHtml', null)));

        // Do we want to scale?
        if ($this->getOption('scaleContent') == 1) {
            $this->appendJavaScript('
                $(document).ready(function() {
                    $("body").xiboLayoutScaler(options);
                });
            ');
        }

        return $this->finaliseGetResource();
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }
}
