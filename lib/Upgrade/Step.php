<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Step.php)
 */


namespace Xibo\Upgrade;
use Slim\Helper\Set;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface Step
 * @package Xibo\Upgrade
 */
interface Step
{
    /**
     * Step constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config);

    /**
     * @param Set $container
     */
    public function doStep($container);
}