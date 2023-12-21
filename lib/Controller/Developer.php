<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
                'url' => $this->urlFor($request, 'developer.templates.form.edit', ['id' => $template->id]),
                'text' => __('Edit')
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
     * Shows an edit form for a module template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateEditForm(Request $request, Response $response, $id): Response
    {
        $template = $this->moduleTemplateFactory->getUserTemplateById($id);
        if ($template->ownership !== 'user') {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'developer-template-form-edit';
        $this->getState()->setData([
            'id' => $id,
            'template' => $template,
            'xml' => $template->getXml(),
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
        $xml = $params->getParam('xml', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply the templates XML'), 'xml');
        }]);

        $template = $this->moduleTemplateFactory->createUserTemplate($xml);
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
        $xml = $params->getParam('xml', ['throw' => function () {
            throw new InvalidArgumentException(__('Please supply the templates XML'), 'xml');
        }]);

        // TODO: some checking that the templateId/dataType hasn't changed.
        $template->setXml($xml);
        $template->save();

        if ($params->getCheckbox('isInvalidateWidget')) {
            $template->invalidate();
        }

        return $this->render($request, $response);
    }
}
