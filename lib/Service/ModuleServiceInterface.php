<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ModuleServiceInterface.php)
 */


namespace Xibo\Service;


use Slim\Slim;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Module;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\TransitionFactory;
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
     * @param Slim $app
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     */
    public function __construct($app, $store, $pool, $log, $config, $date, $sanitizer);

    /**
     * @param Module $module
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @return ModuleWidget
     */
    public function get($module, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory);
    /**
     * @param string $className
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @return ModuleWidget
     */
    public function getByClass($className, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory);
}