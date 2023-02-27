<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
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
     * A grid of modules
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

        // TODO: do we need a filter?
        $modules = $this->moduleFactory->getAll();

        foreach ($modules as $module) {
            /* @var \Xibo\Entity\Module $module */

            if ($this->isApi($request)) {
                break;
            }

            $module->includeProperty('buttons');

            // Edit button
            $module->buttons[] = array(
                'id' => 'module_button_edit',
                'url' => $this->urlFor($request, 'module.settings.form', ['id' => $module->moduleId]),
                'text' => __('Configure')
            );

            // Clear cache
            if ($module->regionSpecific == 1) {
                $module->buttons[] = array(
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
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($modules);

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
        $moduleConfigLocked = $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 1
            || $this->getConfig()->getSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked';

        if (!$this->getUser()->userTypeId == 1) {
            throw new AccessDeniedException();
        }

        $module = $this->moduleFactory->getById($id);

        // Pass to view
        $this->getState()->template = 'module-form-settings';
        $this->getState()->setData([
            'moduleConfigLocked' => $moduleConfigLocked,
            'moduleId' => $id,
            'module' => $module,
            'help' => $this->getHelp()->link('Module', 'Edit')
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
            'help' => $this->getHelp()->link('Module', 'General')
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
        $module = $this->moduleFactory->createById((int)$id);
        $module->dumpCacheForModule();

        $this->getState()->hydrate([
            'message' => __('Cleared the Cache')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get a list of templates available for a particular data type
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

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = 0;
        $this->getState()->setData($this->moduleTemplateFactory->getByDataType($dataType));
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
     */
    public function assetDownload(Request $request, Response $response, string $assetId): Response
    {
        if (empty($assetId)) {
            throw new InvalidArgumentException(__('Please provide an assetId'), 'assetId');
        }

        // Get this asset from somewhere
        $asset = $this->moduleFactory->getAssetsFromAnywhereById($assetId, $this->moduleTemplateFactory);

        $this->getLog()->debug('assetDownload: found appropriate asset for assetId ' . $assetId);

        // The asset can serve itself.
        return $asset->psrResponse($request, $response);
    }
}
