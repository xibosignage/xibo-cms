<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (FactoryServiceInterface.php)
 */


namespace Xibo\Service;


use Slim\Helper\Set;
use Xibo\Factory\BaseFactory;

/**
 * Interface FactoryServiceInterface
 * @package Xibo\Service
 */
interface FactoryServiceInterface
{
    /**
     * FactoryServiceInterface constructor.
     * @param Set $container
     */
    public function __construct($container);

    /**
     * Get Factory
     * @param $factoryName
     * @return BaseFactory
     */
    public function get($factoryName);
}