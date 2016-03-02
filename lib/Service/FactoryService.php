<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (FactoryService.php)
 */


namespace Xibo\Service;

/**
 * Class FactoryService
 * @package Xibo\Service
 */
class FactoryService implements FactoryServiceInterface
{
    private $container;

    /**
     * @inheritdoc
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function get($factoryName)
    {
        if ($this->container->has('\Xibo\Factory\\' . $factoryName))
            return $this->container->get('\Xibo\Factory\\' . $factoryName);

        throw new \RuntimeException('Factory ' . $factoryName . ' not registered.');
    }
}