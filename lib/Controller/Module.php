<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/module',
        operationId: 'moduleSearch',
        description: 'Get a list of all modules available to this CMS',
        summary: 'Module Search',
        tags: ['module']
    )]
    #[OA\Parameter(
        name: 'moduleId',
        description: 'Filter by module ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'Filter by module name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by module name, ID, or description',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'name',
                'description',
                'regionSpecific',
                'defaultDuration',
                'previewEnabled',
                'assignable',
                'enabled',
                'isError'
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Parameter(
        name: 'start',
        description: 'The offset to start results from (for pagination)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'length',
        description: 'The number of records to return (page size)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/Module')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $sortOrder = $this->gridRenderSort(
            $parsedQueryParams,
            $this->isJson($request)
        );

        $filter = [
            'name' => $parsedQueryParams->getString('name'),
            'extension' => $parsedQueryParams->getString('extension'),
            'moduleId' => $parsedQueryParams->getInt('moduleId'),
            'keyword' => $parsedQueryParams->getString('keyword'),
            'start' => $parsedQueryParams->getInt('start'),
            'length' => $parsedQueryParams->getInt('length'),
        ];

        $totalCount = 0;
        $modules = $this->moduleFactory->getAllExceptCanvas($filter, $sortOrder, $totalCount);

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $totalCount)
            ->withJson($modules);
    }

    #[OA\Get(
        path: '/module/{moduleId}',
        operationId: 'ModuleSearchById',
        description: 'Get the Module object specified by the provided moduleId',
        summary: 'Module Search By ID',
        tags: ['module']
    )]
    #[OA\Parameter(
        name: 'moduleId',
        description: 'Numeric ID of the Module to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Module')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {

        $module = $this->moduleFactory->getById($id);

        return $response
            ->withStatus(200)
            ->withJson($module);
    }

    // phpcs:disable
    #[OA\Get(
        path: '/module/properties/{id}',
        operationId: 'getModuleProperties',
        description: 'Get a module properties which are needed to for the editWidget call',
        summary: 'Get Module Properties',
        tags: ['module']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The ModuleId',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Property')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    // phpcs:enable
    public function getProperties(Request $request, Response $response, $id): Response|ResponseInterface
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
     * Settings
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function settings(Request $request, Response $response, $id): Response|ResponseInterface
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
        if ($module->allowPreview === 0 && $sanitizedParams->getCheckbox('previewEnabled') == 1) {
            throw new InvalidArgumentException(__('Preview is disabled'));
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
     * Clear Cache
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function clearCache(Request $request, Response $response, $id): Response|ResponseInterface
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

    #[OA\Get(
        path: '/module/templates/{dataType}',
        operationId: 'moduleTemplateSearch',
        description: 'Get a list of templates available for a particular data type',
        summary: 'Module Template Search',
        tags: ['module']
    )]
    #[OA\Parameter(
        name: 'dataType',
        description: 'DataType to return templates for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Type to return templates for',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'An array of module templates for the provided datatype',
        content: new OA\JsonContent(ref: '#/components/schemas/ModuleTemplate')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param string $dataType
     * @return Response
     * @throws GeneralException
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
    #[OA\Get(
        path: '/module/template/{dataType}/properties/{id}',
        operationId: 'getModuleTemplateProperties',
        description: 'Get a module template properties which are needed to for the editWidget call',
        summary: 'Get Module Template Properties',
        tags: ['module']
    )]
    #[OA\Parameter(
        name: 'dataType',
        description: 'The Template DataType',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Template Id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        )
    )]
    /**
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
    public function getTemplateProperties(
        Request $request,
        Response $response,
        string $dataType,
        string $id
    ): Response|ResponseInterface {
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
     * @param Request $request
     * @param Response $response
     * @param string $assetId the ID of the asset to serve
     * @return ResponseInterface
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function assetDownload(Request $request, Response $response, string $assetId): ResponseInterface
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

    /**
     * Get the library modules list
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getLibraryModules(Request $request, Response $response): Response
    {
        $modules = $this->moduleFactory->getLibraryModules();

        return $response->withStatus(200)->withJson($modules);
    }
}
