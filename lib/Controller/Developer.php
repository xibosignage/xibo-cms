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

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Helper\SendFile;
use Xibo\Service\MediaService;
use Xibo\Service\UploadService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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
     * Display the module templates page
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
     * Show Module templates in a grid
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateGrid(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());

        $templates = $this->moduleTemplateFactory->loadUserTemplates(
            $this->gridRenderSort($params),
            $this->gridRenderFilter(
                [
                    'id' => $params->getInt('id'),
                    'templateId' => $params->getString('templateId'),
                    'dataType' => $params->getString('dataType'),
                ],
                $params
            )
        );

        foreach ($templates as $template) {
            if ($this->isApi($request)) {
                break;
            }

            $template->includeProperty('buttons');

            if ($this->getUser()->checkEditable($template) &&
                $this->getUser()->featureEnabled('developer.edit')
            ) {
                // Edit button
                $template->buttons[] = [
                    'id' => 'template_button_edit',
                    'url' => $this->urlFor($request, 'developer.templates.view.edit', ['id' => $template->id]),
                    'text' => __('Edit'),
                    'class' => 'XiboRedirectButton',
                ];

                $template->buttons[] = [
                    'id' => 'template_button_export',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor($request, 'developer.templates.export', ['id' => $template->id]),
                    'text' => __('Export XML'),
                ];

                $template->buttons[] = [
                    'id' => 'template_button_copy',
                    'url' => $this->urlFor($request, 'developer.templates.form.copy', ['id' => $template->id]),
                    'text' => __('Copy'),
                ];
            }

            if ($this->getUser()->featureEnabled('developer.edit') &&
                $this->getUser()->checkPermissionsModifyable($template)
            ) {
                $template->buttons[] = ['divider' => true];
                // Permissions for Module Template
                $template->buttons[] = [
                    'id' => 'template_button_permissions',
                    'url' => $this->urlFor(
                        $request,
                        'user.permissions.form',
                        ['entity' => 'ModuleTemplate', 'id' => $template->id]
                    ),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi',
                                ['entity' => 'ModuleTemplate', 'id' => $template->id]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'template_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $template->templateId],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi.form',
                                ['entity' => 'ModuleTemplate']
                            )
                        ],
                        ['name' => 'content-id-name', 'value' => 'id']
                    ]
                ];
            }

            if ($this->getUser()->checkDeleteable($template) &&
                $this->getUser()->featureEnabled('developer.delete')
            ) {
                $template->buttons[] = ['divider' => true];
                // Delete button
                $template->buttons[] = [
                    'id' => 'template_button_delete',
                    'url' => $this->urlFor($request, 'developer.templates.form.delete', ['id' => $template->id]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'developer.templates.delete',
                                ['id' => $template->id]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'template_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $template->templateId]
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->moduleTemplateFactory->countLast();
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

        $this->getState()->template = 'developer-template-edit-page';
        $this->getState()->setData([
            'template' => $template,
            'propertiesJSON' => json_encode($template->properties),
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
        $title = $params->getString('title', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a title'), 'title');
        }]);
        $dataType = $params->getString('dataType', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a data type'), 'dataType');
        }]);
        $showIn = $params->getString('showIn', ['throw' => function () {
            throw new InvalidArgumentException(
                __('Please select relevant editor which should show this Template'),
                'showIn'
            );
        }]);

        // do we have a template selected?
        if (!empty($params->getString('copyTemplateId'))) {
            // get the selected template
            $copyTemplate = $this->moduleTemplateFactory->getByDataTypeAndId(
                $dataType,
                $params->getString('copyTemplateId')
            );

            // get the template xml and load to document.
            $xml = new \DOMDocument();
            $xml->loadXML($copyTemplate->getXml());

            // get template node, make adjustments from the form
            $templateNode = $xml->getElementsByTagName('template')[0];
            $this->setNode($xml, 'id', $templateId, false, $templateNode);
            $this->setNode($xml, 'title', $title, false, $templateNode);
            $this->setNode($xml, 'showIn', $showIn, false, $templateNode);

            // create template with updated xml.
            $template = $this->moduleTemplateFactory->createUserTemplate($xml->saveXML());
        } else {
            // The most basic template possible.
            $template = $this->moduleTemplateFactory->createUserTemplate('<?xml version="1.0"?>
        <template>
            <id>' . $templateId . '</id>
            <title>' . $title . '</title>
            <type>static</type>
            <dataType>' . $dataType . '</dataType>
            <showIn>'. $showIn . '</showIn>
            <properties></properties>
        </template>');
        }

        $template->ownerId = $this->getUser()->userId;
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
        $title = $params->getString('title', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a title'), 'title');
        }]);
        $dataType = $params->getString('dataType', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply a data type'), 'dataType');
        }]);
        $showIn = $params->getString('showIn', ['throw' => function () {
            throw new InvalidArgumentException(
                __('Please select relevant editor which should show this Template'),
                'showIn'
            );
        }]);

        $template->dataType = $dataType;
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
        $template->templateId = $templateId;
        $this->setNode($document, 'id', $templateId, false);
        $this->setNode($document, 'title', $title, false);
        $this->setNode($document, 'showIn', $showIn, false);
        $this->setNode($document, 'dataType', $dataType, false);
        $this->setNode($document, 'onTemplateRender', $onTemplateRender);
        $this->setNode($document, 'onTemplateVisible', $onTemplateVisible);

        // Stencil nodes.
        $stencilNodes = $document->getElementsByTagName('stencil');
        if ($stencilNodes->count() <= 0) {
            $stencilNode = $document->createElement('stencil');
            $document->documentElement->appendChild($stencilNode);
        } else {
            $stencilNode = $stencilNodes[0];
        }

        $this->setNode($document, 'twig', $twig, true, $stencilNode);
        $this->setNode($document, 'hbs', $hbs, true, $stencilNode);
        $this->setNode($document, 'style', $style, true, $stencilNode);
        $this->setNode($document, 'head', $head, true, $stencilNode);

        // Properties.
        // this is different because we want to replace the properties node with a new one.
        if (!empty($properties)) {
            // parse json and create a new properties node
            $newPropertiesXml = $this->moduleTemplateFactory->parseJsonPropertiesToXml($properties);

            $propertiesNodes = $document->getElementsByTagName('properties');

            if ($propertiesNodes->count() <= 0) {
                $document->documentElement->appendChild(
                    $document->importNode($newPropertiesXml->documentElement, true)
                );
            } else {
                $document->documentElement->replaceChild(
                    $document->importNode($newPropertiesXml->documentElement, true),
                    $propertiesNodes[0]
                );
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

    public function getAvailableDataTypes(Request $request, Response $response)
    {
        $params = $this->getSanitizer($request->getParams());
        $dataTypes = $this->moduleFactory->getAllDataTypes();

        if ($params->getString('dataType') !== null) {
            foreach ($dataTypes as $dataType) {
                if ($dataType->id === $params->getString('dataType')) {
                    $dataTypes = [$dataType];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($dataTypes);

        return $this->render($request, $response);
    }

    /**
     * Export module template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function templateExport(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $template = $this->moduleTemplateFactory->getUserTemplateById($id);

        if ($template->ownership !== 'user') {
            throw new AccessDeniedException();
        }

        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $template->templateId . '.xml';

        $template->getDocument()->save($tempFileName);

        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            $template->templateId . '.xml'
        )->withHeader('Content-Type', 'text/xml;charset=utf-8'));
    }

    /**
     * Import xml file and create module template
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws ConfigurationException
     */
    public function templateImport(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getLog()->debug('Import Module Template');

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);

        $options = [
            'upload_dir' => $libraryFolder . 'temp/',
            'accept_file_types' => '/\.xml/i',
            'libraryQuotaFull' => false,
        ];

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        $uploadService = new UploadService($libraryFolder . 'temp/', $options, $this->getLog(), $this->getState());
        $uploadHandler = $uploadService->createUploadHandler();

        $uploadHandler->setPostProcessor(function ($file, $uploadHandler) use ($libraryFolder) {
            // Return right away if the file already has an error.
            if (!empty($file->error)) {
                return $file;
            }

            $this->getUser()->isQuotaFullByUser(true);

            $filePath = $libraryFolder . 'temp/' . $file->fileName;

            // load the xml from uploaded file
            $xml = new \DOMDocument();
            $xml->load($filePath);

            // Add the Template
            $moduleTemplate = $this->moduleTemplateFactory->createUserTemplate($xml->saveXML());
            $moduleTemplate->ownerId = $this->getUser()->userId;
            $moduleTemplate->save();

            // Tidy up the temporary file
            @unlink($filePath);

            return $file;
        });

        $uploadHandler->post();

        $this->setNoOutput(true);

        return $this->render($request, $response);
    }

    /**
     * Show module template copy form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function templateCopyForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->getUserTemplateById($id);

        if (!$this->getUser()->checkViewable($moduleTemplate)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'developer-template-form-copy';
        $this->getState()->setData([
            'template' => $moduleTemplate,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy module template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function templateCopy(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->getUserTemplateById($id);

        if (!$this->getUser()->checkViewable($moduleTemplate)) {
            throw new AccessDeniedException();
        }

        $params = $this->getSanitizer($request->getParams());

        $newTemplate = clone $moduleTemplate;
        $newTemplate->templateId = $params->getString('templateId');
        $newTemplate->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $newTemplate->templateId),
            'id' => $newTemplate->id,
            'data' => $newTemplate
        ]);

        return $this->render($request, $response);
    }

    /**
     * Show module template delete form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function templateDeleteForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->getUserTemplateById($id);

        if (!$this->getUser()->checkDeleteable($moduleTemplate)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'developer-template-form-delete';
        $this->getState()->setData([
            'template' => $moduleTemplate,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete module template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function templateDelete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->getUserTemplateById($id);

        if (!$this->getUser()->checkDeleteable($moduleTemplate)) {
            throw new AccessDeniedException();
        }

        $moduleTemplate->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $moduleTemplate->templateId)
        ]);

        return $this->render($request, $response);
    }
}
