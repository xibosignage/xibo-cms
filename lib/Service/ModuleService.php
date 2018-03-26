<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ModuleService.php)
 */


namespace Xibo\Service;


use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Exception\NotFoundException;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ModuleService
 * @package Xibo\Service
 */
class ModuleService implements ModuleServiceInterface
{
    /**
     * @var \Slim\Slim
     */
    public $app;

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
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /** @var  EventDispatcherInterface */
    private $dispatcher;

    /**
     * @inheritdoc
     */
    public function __construct($app, $store, $pool, $log, $config, $date, $sanitizer, $dispatcher)
    {
        $this->app = $app;
        $this->store = $store;
        $this->pool = $pool;
        $this->logService = $log;
        $this->configService = $config;
        $this->dateService = $date;
        $this->sanitizerService = $sanitizer;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @inheritdoc
     */
    public function get($module, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory)
    {
        $object = $this->getByClass($module->class, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory);

        $object->setModule($module);

        return $object;
    }

    /**
     * @inheritdoc
     */
    public function getByClass($className, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory)
    {
        if (!\class_exists($className))
            throw new NotFoundException(__('Class %s not found', $className));

        /* @var \Xibo\Widget\ModuleWidget $object */
        $object = new $className(
            $this->app,
            $this->store,
            $this->pool,
            $this->logService,
            $this->configService,
            $this->dateService,
            $this->sanitizerService,
            $this->dispatcher,
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
            $playlistFactory
        );

        return $object;
    }
}