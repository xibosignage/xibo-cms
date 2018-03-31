<?php
/*
 * Graph Xibo Module
 * Copyright (C) 2018 Lukas Zurschmiede
 *
 * This Xibo-Module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * This Xibo-Module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with This Xibo-Module.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);
namespace Xibo\Tests\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Class GraphTest
 * @package Xibo\Tests\Widget
 */
abstract class WidgetBase extends TestCase
{
    protected $app;
    protected $store;
    protected $pool;
    protected $log;
    protected $config;
    protected $date;
    protected $sanitizer;
    protected $dispatcher;
    protected $moduleFactory;
    protected $mediaFactory;
    protected $dataSetFactory;
    protected $dataSetColumnFactory;
    protected $transitionFactory;
    protected $displayFactory;
    protected $commandFactory;
    protected $scheduleFactory;
    protected $permissionFactory;
    protected $userGroupFactory;
    
    /**
     * ModuleWidget constructor: Creates an instance of a widget with all mocked Factories and so on.
     * 
     * @param Slim $app
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @param EventDispatcherInterface $dispatcher
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
     */
    protected function getInstance(string $widget): object
    {
        $this->app = $this->createMock(\Slim\Slim::class);
        $this->pool = $this->createMock(\Stash\Interfaces\PoolInterface::class);
        $this->dispatcher = $this->createMock(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
        $this->commandFactory = $this->createMock(\Xibo\Factory\CommandFactory::class);
        $this->dataSetColumnFactory = $this->createMock(\Xibo\Factory\DataSetColumnFactory::class);
        $this->dataSetFactory = $this->createMock(\Xibo\Factory\DataSetFactory::class);
        $this->displayFactory = $this->createMock(\Xibo\Factory\DisplayFactory::class);
        $this->mediaFactory = $this->createMock(\Xibo\Factory\MediaFactory::class);
        $this->moduleFactory = $this->createMock(\Xibo\Factory\ModuleFactory::class);
        $this->permissionFactory = $this->createMock(\Xibo\Factory\PermissionFactory::class);
        $this->scheduleFactory = $this->createMock(\Xibo\Factory\ScheduleFactory::class);
        $this->transitionFactory = $this->createMock(\Xibo\Factory\TransitionFactory::class);
        $this->userGroupFactory = $this->createMock(\Xibo\Factory\UserGroupFactory::class);
        $this->configService = $this->createMock(\Xibo\Service\ConfigServiceInterface::class);
        $this->dateService = $this->createMock(\Xibo\Service\DateServiceInterface::class);
        $this->logService = $this->createMock(\Xibo\Service\LogServiceInterface::class);
        $this->sanitizerService = $this->createMock(\Xibo\Service\SanitizerServiceInterface::class);
        $this->store = $this->createMock(\Xibo\Storage\StorageServiceInterface::class);

        return new $widget(
            $this->app,
            $this->store,
            $this->pool,
            $this->log,
            $this->config,
            $this->date,
            $this->sanitizer,
            $this->dispatcher,
            $this->moduleFactory,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory);
    }
    
    /**
     * Use this for testing a private or protected method.
     * 
     * @param Object $obj The Object to check the method on
     * @param String $name Name of the method to check
     * @param array $args Array with all arguments as values in the correct order
     */
    protected function callMethod($obj, $name, array $args)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }
    
    /**
     * Print out some debug information to the console
     * 
     * @param mixed $obj The Data to print out
     */
    protected function debug($obj)
    {
        $this->expectOutputString('');
        var_dump($obj);
    }
}

