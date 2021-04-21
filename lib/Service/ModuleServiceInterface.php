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


use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Module;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\ModuleWidget;

/**
 * Interface ModuleServiceInterface
 * @package Xibo\Service
 */
interface ModuleServiceInterface
{
    /**
     * ModuleServiceInterface constructor.
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerService $sanitizer
     */
    public function __construct($store, $pool, $log, $config, $sanitizer);

    /**
     * @param Module $module
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PlaylistFactory $playlistFactory
     * @param MenuBoardFactory $menuBoardFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param Twig $view
     * @param HttpCacheProvider $cacheProvider
     * @return ModuleWidget
     */
    public function get($module, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory, $menuBoardFactory, $menuBoardCategoryFactory, $view, HttpCacheProvider $cacheProvider);

    /**
     * @param string $className
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PlaylistFactory $playlistFactory
     * @param MenuBoardFactory $menuBoardFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param Twig $view
     * @param HttpCacheProvider $cacheProvider
     * @return ModuleWidget
     */
    public function getByClass($className, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory, $menuBoardFactory, $menuBoardCategoryFactory, $view, HttpCacheProvider $cacheProvider);
}