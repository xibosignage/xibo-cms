<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (EntityServiceTrait.php)
 */


namespace Xibo\Entity;
use Slim\Helper\Set;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\FactoryServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Trait EntityServiceTrait
 * @package Xibo\Entity
 */
trait EntityServiceTrait
{
    private $container;

    /**
     * Set DIC
     * @param Set $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @return FactoryServiceInterface
     */
    public function getFactoryService()
    {
        return $this->container->factoryService;
    }

    /**
     * Get Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->container->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->container->store;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->container->logService;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->container->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->container->sanitizerService;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    protected function getConfig()
    {
        if ($this->container->configService == null)
            throw new \RuntimeException('Entity Config Service called before it has been set in container');

        return $this->container->configService;
    }

    /**
     * Get Player Service
     * @return PlayerActionServiceInterface
     */
    public function getPlayerService()
    {
        return $this->container->playerService;
    }
}