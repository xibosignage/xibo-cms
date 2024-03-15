<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Module
 * @package Xibo\Controller
 */
class Developer extends Base
{
    public function __construct(
        private readonly ModuleFactory $moduleFactory,
        private readonly ModuleTemplateFactory $moduleTemplateFactory
    ) {
    }

    /**
     * Display the module page
     * @param Request $request
     * @param Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayTemplatePage(Request $request, Response $response): Response
    {
        $this->getState()->template = 'developer-template-page';

        return $this->render($request, $response);
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateGrid(Request $request, Response $response): Response
    {
        $templates = $this->moduleTemplateFactory->loadUserTemplates();

        foreach ($templates as $template) {
            if ($this->isApi($request)) {
                break;
            }

            $template->includeProperty('buttons');

            // Edit button
            $template->buttons[] = [
                'id' => 'template_button_edit',
                'url' => $this->urlFor($request, 'developer.templates.view.edit', ['id' => $template->id]),
                'text' => __('Edit'),
                'class' => 'XiboRedirectButton',
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($templates);

        return $this->render($request, $response);
    }

    /**
     * Shows an add form for a module template
     * @param Request $request
     * @param Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateAddForm(Request $request, Response $response): Response
    {
        $this->getState()->template = 'developer-template-form-add';
        return $this->render($request, $response);
    }

    /**
     * Display the module template page
     * @param Request $request
     * @param Response $response
     * @param mixed $id The template ID to edit.
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayTemplateEditPage(Request $request, Response $response, $id): Response
    {
        $template = $this->moduleTemplateFactory->getUserTemplateById($id);
        if ($template->ownership !== 'user') {
            throw new AccessDeniedException();
        }

        //TODO: temporary extraction of properties XML (should be a form field per property instead)
        $doc = $template->getDocument();
        /** @var \DOMElement $properties */
        $properties = $doc->getElementsByTagName('properties')[0];

        $this->getState()->template = 'developer-template-edit-page';
        $this->getState()->setData([
            'template' => $template,
            'properties' => $doc->saveXML($properties),
            // TODO: hardcoded for now
            'propertiesJSON' => '[]',
            // 'propertiesJSON' => '[{"id":"","value":null,"type":"message","variant":"","format":"","title":"Select a dataset to display appearance options.","mode":"","target":"","propertyGroupId":"","helpText":null,"validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":{"windows":"v3 R303","linux":"TBC","android":"v3 R304","webos":"TBC","tizen":"TBC","message":"Fit supported from:"},"rule":[{"type":"and","message":"234","conditions":[{"field":"dataSetId","type":"gt","value":"hey"},{"field":"dataSetId3","type":"egt","value":"hey2"}]}], "visibility":[{"type":"or","message":"111","conditions":[{"field":"something","type":"eq","value":""}]}, {"type":"and","message":"222","conditions":[{"field":"something","type":"eq","value":"222"}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":true,"dependsOn":null},{"id":"columns","value":null,"type":"datasetColumnSelector","variant":"","format":"","title":"Item Template","mode":"","target":"","propertyGroupId":"","helpText":"Enter text in the box below, used to display each article.","validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":"dataSetId"},{"id":"noDataMessage","value":null,"type":"richText","variant":"html","format":"","title":"No data message","mode":"","target":"","propertyGroupId":"","helpText":"A message to display when no data is returned from the source","validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":true,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"showHeadings","value":null,"type":"checkbox","variant":"","format":"","title":"Show the table headings?","mode":"","target":"","propertyGroupId":"","helpText":"Should the Table headings be shown?","validation":null,"default":1,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"rowsPerPage","value":null,"type":"number","variant":"","format":"","title":"Rows per page","mode":"","target":"","propertyGroupId":"","helpText":"Please enter the number of rows per page. 0 for no pages.","validation":null,"default":0,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"fontFamily","value":null,"type":"fontSelector","variant":"","format":"","title":"Font","mode":"","target":"","propertyGroupId":"","helpText":"Select a custom font - leave empty to use the default font.","validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"","value":null,"type":"header","variant":"main","format":"","title":"Colours","mode":"","target":"","propertyGroupId":"","helpText":null,"validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"fontColor","value":null,"type":"color","variant":"","format":"","title":"Font Colour","mode":"","target":"","propertyGroupId":"","helpText":"Use the colour picker to select the font colour","validation":null,"default":"#111","options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[{"type":"and","message":"","conditions":[{"field":"dataSetId","type":"neq","value":""}]}],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"text","value":null,"type":"textArea","variant":"","format":"","title":"Text","mode":"","target":"","propertyGroupId":"","helpText":null,"validation":null,"default":"Text","options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"fontFamily","value":null,"type":"fontSelector","variant":"","format":"","title":"Font Family","mode":"","target":"","propertyGroupId":"","helpText":"Select a custom font - leave empty to use the default font.","validation":null,"default":null,"options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"fontColor","value":null,"type":"color","variant":"","format":"","title":"Font Colour","mode":"","target":"","propertyGroupId":"","helpText":null,"validation":null,"default":"%THEME_COLOR%","options":null,"customPopOver":null,"playerCompatibility":null,"visibility":[],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null},{"id":"verticalAlign","value":null,"type":"dropdown","variant":"","format":"","title":"Vertical Align","mode":"","target":"","propertyGroupId":"","helpText":null,"validation":null,"default":"center","options":[{"name":"flex-start","image":"","set":[],"title":"Top"},{"name":"center","image":"","set":[],"title":"Middle"},{"name":"flex-end","image":"","set":[],"title":"Bottom"}],"customPopOver":null,"playerCompatibility":null,"visibility":[],"allowLibraryRefs":false,"allowAssetRefs":false,"parseTranslations":false,"dependsOn":null}]',
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a module template
     * @param Request $request
     * @param Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateAdd(Request $request, Response $response): Response
    {
        // When adding a template we just save the XML
        $params = $this->getSanitizer($request->getParams());
        $templateId = $params->getString('templateId', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a unique template ID'), 'templateId');
        }]);
        $dataType = $params->getString('dataType', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a data type'), 'dataType');
        }]);

        // The most basic template possible.
        $template = $this->moduleTemplateFactory->createUserTemplate('<?xml version="1.0"?>
        <template>
            <id>' . $templateId . '</id>
            <title>' . $templateId . '</title>
            <type>static</type>
            <dataType>' . $dataType . '</dataType>
            <showIn>layout</showIn>
            <properties></properties>
        </template>');

        $template->save();

        $this->getState()->hydrate([
            'httpState' => 201,
            'message' => __('Added'),
            'id' => $template->id,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit a module template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateEdit(Request $request, Response $response, $id): Response
    {
        $template = $this->moduleTemplateFactory->getUserTemplateById($id);

        $params = $this->getSanitizer($request->getParams());
        $templateId = $params->getString('templateId', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a unique template ID'), 'templateId');
        }]);
        $dataType = $params->getString('dataType', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a data type'), 'dataType');
        }]);

        $template->isEnabled = $params->getCheckbox('enabled');

        // TODO: validate?
        $twig = $params->getParam('twig');
        $hbs = $params->getParam('hbs');
        $style = $params->getParam('style');
        $head = $params->getParam('head');
        $properties = $params->getParam('properties');
        $onTemplateRender = $params->getParam('onTemplateRender');
        $onTemplateVisible = $params->getParam('onTemplateVisible');

        // We need to edit the XML we have for this template.
        $document = $template->getDocument();

        // Root nodes
        $this->setNode($document, 'onTemplateRender', $onTemplateRender);
        $this->setNode($document, 'onTemplateVisible', $onTemplateVisible);

        // Stencil nodes.
        $stencilNodes = $document->getElementsByTagName('stencil');
        if ($stencilNodes->count() <= 0) {
            $stencilNode = $document->createElement('stencil');
            $document->appendChild($stencilNode);
        } else {
            $stencilNode = $stencilNodes[0];
        }

        $this->setNode($document, 'twig', $twig, true, $stencilNode);
        $this->setNode($document, 'hbs', $hbs, true, $stencilNode);
        $this->setNode($document, 'style', $style, true, $stencilNode);
        $this->setNode($document, 'head', $head, true, $stencilNode);

        // Properties.
        // TODO: this is temporary pending a properties UI
        // this is different because we want to replace the properties node with a new one.
        if (!empty($properties)) {
            $newProperties = new \DOMDocument();
            $newProperties->loadXML($properties);

            // Do we have new nodes to import?
            if ($newProperties->childNodes->count() > 0) {
                $importedPropteries = $document->importNode($newProperties->documentElement, true);
                if ($importedPropteries !== false) {
                    $propertiesNodes = $document->getElementsByTagName('properties');
                    if ($propertiesNodes->count() <= 0) {
                        $document->appendChild($importedPropteries);
                    } else {
                        $document->documentElement->replaceChild($importedPropteries, $propertiesNodes[0]);
                    }
                }
            }
        }

        // All done.
        $template->setXml($document->saveXML());
        $template->save();

        if ($params->getCheckbox('isInvalidateWidget')) {
            $template->invalidate();
        }

        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $template->title),
            'id' => $template->id,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Helper function to set a node.
     * @param \DOMDocument $document
     * @param string $node
     * @param string $value
     * @param bool $cdata
     * @param \DOMElement|null $childNode
     * @return void
     * @throws \DOMException
     */
    private function setNode(
        \DOMDocument $document,
        string $node,
        string $value,
        bool $cdata = true,
        ?\DOMElement $childNode = null
    ): void {
        $addTo = $childNode ?? $document->documentElement;

        $nodes = $addTo->getElementsByTagName($node);
        if ($nodes->count() <= 0) {
            if ($cdata) {
                $element = $document->createElement($node);
                $cdata = $document->createCDATASection($value);
                $element->appendChild($cdata);
            } else {
                $element = $document->createElement($node, $value);
            }

            $addTo->appendChild($element);
        } else {
            /** @var \DOMElement $element */
            $element = $nodes[0];
            if ($cdata) {
                $cdata = $document->createCDATASection($value);
                $element->textContent = $value;

                if ($element->firstChild !== null) {
                    $element->replaceChild($cdata, $element->firstChild);
                } else {
                    //$element->textContent = '';
                    $element->appendChild($cdata);
                }
            } else {
                $element->textContent = $value;
            }
        }
    }
}
