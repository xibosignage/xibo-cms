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
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Module
 * @package Xibo\Controller
 */
class Module extends Base
{
    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param ModuleFactory $moduleFactory
     */
    public function __construct(
        ModuleFactory $moduleFactory,
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        $this->moduleFactory = $moduleFactory;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * Display the module page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'module-page';

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/module",
     *  operationId="moduleSearch",
     *  tags={"module"},
     *  summary="Module Search",
     *  description="Get a list of all modules available to this CMS",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Module")
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'name' => $parsedQueryParams->getString('name'),
            'extension' => $parsedQueryParams->getString('extension'),
            'moduleId' => $parsedQueryParams->getInt('moduleId')
        ];

        $modules = $this->moduleFactory->getAllExceptCanvas($filter);

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi($request)) {
                break;
            }

            $module->includeProperty('buttons');

            // Edit button
            $module->buttons[] = [
                'id' => 'module_button_edit',
                'url' => $this->urlFor($request, 'module.settings.form', ['id' => $module->moduleId]),
                'text' => __('Configure')
            ];

            // Clear cache
            if ($module->regionSpecific == 1) {
                $module->buttons[] = [
                    'id' => 'module_button_clear_cache',
                    'url' => $this->urlFor($request, 'module.clear.cache.form', ['id' => $module->moduleId]),
                    'text' => __('Clear Cache'),
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'module.clear.cache', ['id' => $module->moduleId])
                        ],
                        ['name' => 'commit-method', 'value' => 'PUT']
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($modules);

        return $this->render($request, $response);
    }

    // phpcs:disable
    /**
     * @SWG\Get(
     *  path="/module/properties/{id}",
     *  operationId="getModuleProperties",
     *  tags={"module"},
     *  summary="Get Module Properties",
     *  description="Get a module properties which are needed to for the editWidget call",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The ModuleId",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Property")
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    // phpcs:enable
    public function getProperties(Request $request, Response $response, $id)
    {
        // Get properties, but return a key->value object for easy parsing.
        $props = [];
        foreach ($this->moduleFactory->getById($id)->properties as $property) {
            $props[$property->id] = [
                'type' => $property->type,
                'title' => $property->title,
                'helpText' => $property->helpText,
                'options' => $property->options,
            ];
        }

        $this->getState()->setData($props);
        return $this->render($request, $response);
    }

    /**
     * Settings Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function settingsForm(Request $request, Response $response, $id)
    {
        // Can we edit?
        if (!$this->getUser()->userTypeId == 1) {
            throw new AccessDeniedException();
        }

        $module = $this->moduleFactory->getById($id);

        // Pass to view
        $this->getState()->template = 'module-form-settings';
        $this->getState()->setData([
            'moduleId' => $id,
            'module' => $module,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Settings
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function settings(Request $request, Response $response, $id)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get the module
        $module = $this->moduleFactory->getById($id);

        // Default settings
        $module->enabled = $sanitizedParams->getCheckbox('enabled');
        $module->previewEnabled = $sanitizedParams->getCheckbox('previewEnabled');
        $module->defaultDuration = $sanitizedParams->getInt('defaultDuration');

        // Parse out any settings we ought to expect.
        foreach ($module->settings as $setting) {
            $setting->setValueByType($sanitizedParams, null, true);
        }

        // Preview is not allowed for generic file type
        if ($module->moduleId == 'core-genericfile' && $sanitizedParams->getCheckbox('previewEnabled') == 1) {
            throw new InvalidArgumentException(__('Preview is not allowed for generic file type'));
        }

        // Save
        $module->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Configured %s'), $module->name),
            'id' => $module->moduleId,
            'data' => $module
        ]);

        return $this->render($request, $response);
    }

    /**
     * Clear Cache Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function clearCacheForm(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->getById($id);

        $this->getState()->template = 'module-form-clear-cache';
        $this->getState()->autoSubmit = $this->getAutoSubmit('clearCache');
        $this->getState()->setData([
            'module' => $module,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Clear Cache
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function clearCache(Request $request, Response $response, $id)
    {
        $module = $this->moduleFactory->getById($id);
        if ($module->isDataProviderExpected()) {
            $this->moduleFactory->clearCacheForDataType($module->dataType);
        }

        $this->getState()->hydrate([
            'message' => __('Cleared the Cache')
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/module/templates/{dataType}",
     *  operationId="moduleTemplateSearch",
     *  tags={"module"},
     *  summary="Module Template Search",
     *  description="Get a list of templates available for a particular data type",
     *  @SWG\Parameter(
     *      name="dataType",
     *      in="path",
     *      description="DataType to return templates for",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="query",
     *      description="Type to return templates for",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="An array of module templates for the provided datatype",
     *      @SWG\Schema(ref="#/definitions/ModuleTemplate")
     *  )
     * )
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @param string $dataType
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function templateGrid(Request $request, Response $response, string $dataType): Response
    {
        if (empty($dataType)) {
            throw new InvalidArgumentException(__('Please provide a datatype'), 'dataType');
        }

        $params = $this->getSanitizer($request->getParams());
        $type = $params->getString('type');

        $templates = !empty($type)
            ? $this->moduleTemplateFactory->getByTypeAndDataType($type, $dataType)
            : $this->moduleTemplateFactory->getByDataType($dataType);

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($templates);
        return $this->render($request, $response);
    }

    // phpcs:disable
    /**
     * @SWG\Get(
     *  path="/module/template/{dataType}/properties/{id}",
     *  operationId="getModuleProperties",
     *  tags={"module"},
     *  summary="Get Module Template Properties",
     *  description="Get a module template properties which are needed to for the editWidget call",
     *  @SWG\Parameter(
     *      name="dataType",
     *      in="path",
     *      description="The Template DataType",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Template Id",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={"id":"string", "type":"string", "title":"string", "helpText":"string", "options":"array"}
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param string $dataType
     * @param string $id
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    // phpcs:enable
    public function getTemplateProperties(Request $request, Response $response, string $dataType, string $id)
    {
        // Get properties, but return a key->value object for easy parsing.
        $props = [];
        foreach ($this->moduleTemplateFactory->getByDataTypeAndId($dataType, $id)->properties as $property) {
            $props[$property->id] = [
                'id' => $property->id,
                'type' => $property->type,
                'title' => $property->title,
                'helpText' => $property->helpText,
                'options' => $property->options,
            ];
        }

        $this->getState()->setData($props);
        return $this->render($request, $response);
    }

    /**
     * Serve an asset
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @param string $assetId the ID of the asset to serve
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function assetDownload(Request $request, Response $response, string $assetId): Response
    {
        if (empty($assetId)) {
            throw new InvalidArgumentException(__('Please provide an assetId'), 'assetId');
        }

        // Get this asset from somewhere
        $asset = $this->moduleFactory->getAssetsFromAnywhereById(
            $assetId,
            $this->moduleTemplateFactory,
            $this->getSanitizer($request->getParams())->getCheckbox('isAlias')
        );
        $asset->updateAssetCache($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $this->getLog()->debug('assetDownload: found appropriate asset for assetId ' . $assetId);

        // The asset can serve itself.
        return $asset->psrResponse($request, $response, $this->getConfig()->getSetting('SENDFILE_MODE'));
    }
}
