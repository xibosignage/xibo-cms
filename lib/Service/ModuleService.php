<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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


namespace Xibo\Service;

use Stash\Interfaces\PoolInterface;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ModuleService
 * @package Xibo\Service
 */
class ModuleService implements ModuleServiceInterface
{

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var LogServiceInterface
     */
    private $logService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var SanitizerService
     */
    private $sanitizerService;

    /**
     * @inheritdoc
     */
    public function __construct($store, $pool, $log, $config, $sanitizer)
    {
        $this->store = $store;
        $this->pool = $pool;
        $this->logService = $log;
        $this->configService = $config;
        $this->sanitizerService = $sanitizer;
    }

    /**
     * @inheritdoc
     */
    public function get(
        $module,
        $moduleFactory,
        $mediaFactory,
        $dataSetFactory,
        $dataSetColumnFactory,
        $transitionFactory,
        $displayFactory,
        $commandFactory,
        $scheduleFactory,
        $permissionFactory,
        $userGroupFactory,
        $playlistFactory,
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $view,
        $cacheProvider
    ) {
        $object = $this->getByClass(
            $module->class,
            $moduleFactory,
            $mediaFactory,
            $dataSetFactory,
            $dataSetColumnFactory,
            $transitionFactory,
            $displayFactory,
            $commandFactory,
            $scheduleFactory,
            $permissionFactory,
            $userGroupFactory,
            $playlistFactory,
            $menuBoardFactory,
            $menuBoardCategoryFactory,
            $view,
            $cacheProvider
        );

        $object->setModule($module);

        return $object;
    }

    /**
     * @inheritdoc
     */
    public function getByClass(
        $className,
        $moduleFactory,
        $mediaFactory,
        $dataSetFactory,
        $dataSetColumnFactory,
        $transitionFactory,
        $displayFactory,
        $commandFactory,
        $scheduleFactory,
        $permissionFactory,
        $userGroupFactory,
        $playlistFactory,
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $view,
        $cacheProvider
    ) {
        if (!\class_exists($className)) {
            throw new NotFoundException(__('Class %s not found', $className));
        }

        /* @var \Xibo\Widget\ModuleWidget $object */
        $object = new $className(
            $this->store,
            $this->pool,
            $this->logService,
            $this->configService,
            $this->sanitizerService,
            $moduleFactory,
            $mediaFactory,
            $dataSetFactory,
            $dataSetColumnFactory,
            $transitionFactory,
            $displayFactory,
            $commandFactory,
            $scheduleFactory,
            $permissionFactory,
            $userGroupFactory,
            $playlistFactory,
            $menuBoardFactory,
            $menuBoardCategoryFactory,
            $view,
            $cacheProvider
        );

        return $object;
    }
}
