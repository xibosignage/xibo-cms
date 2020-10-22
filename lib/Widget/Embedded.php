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
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Embedded
 * @package Xibo\Widget
 */
class Embedded extends ModuleWidget
{
    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'embedded-designer-javascript';
    }

    /**
     * @inheritDoc
     */
    public function InstallFiles()
    {
        // Extends parent's method
        parent::installFiles();
        
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?embedded",
     *  operationId="widgetEmbeddedEdit",
     *  tags={"widget"},
     *  summary="Edit a Embedded Widget",
     *  description="Edit Embedded Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
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
     *      name="isPreNavigate",
     *      in="formData",
     *      description="Flag (0,1) - Should this Widget be loaded off screen so that it is made ready in the background? Dynamic content will run.",
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
     *      name="embedHtml_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
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
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('transparency', $sanitizedParams->getCheckbox('transparency'));
        $this->setOption('scaleContent', $sanitizedParams->getCheckbox('scaleContent'));
        $this->setOption('isPreNavigate', $sanitizedParams->getCheckbox('isPreNavigate'));
        $this->setRawNode('embedHtml', $request->getParam('embedHtml', null));
        $this->setOption('embedHtml_advanced', $sanitizedParams->getCheckbox('embedHtml_advanced'));
        $this->setRawNode('embedScript', $request->getParam('embedScript', null));
        $this->setRawNode('embedStyle', $request->getParam('embedStyle', null));

        // Save the widget
        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }

        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Construct the response HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width);

        // Include some vendor items and javascript
        $this
            ->appendJavaScriptFile('vendor/jquery.min.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendJavaScript('var xiboICTargetId = ' . $this->getWidgetId() . ';')
            ->appendJavaScriptFile('xibo-interactive-control.js')
            ->appendRaw('javaScript', $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('embedScript', null)))
            ->appendCss($this->parseLibraryReferences($this->isPreview(), $this->getRawNode('embedStyle', null)))
            ->appendFontCss()
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)))
            ->appendOptions([
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height
            ])
            ->appendJavaScript('
                $(document).ready(function() { if(typeof EmbedInit === "function"){ EmbedInit(); } });
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
