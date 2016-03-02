<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Slim\Helper\Set;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\ObjectVars;
use Xibo\Helper\PlayerActionHelperInterface;
use Xibo\Helper\SanitizerInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Storage\StorageInterface;

/**
 * Class EntityTrait
 * used by all entities
 * @package Xibo\Entity
 */
trait EntityTrait
{
    /**
     * DI Container
     * @var Set
     */
    private $container;

    private $hash = null;
    private $loaded = false;
    private $permissionsClass = null;

    public $buttons = [];
    private $jsonExclude = ['buttons', 'jsonExclude'];

    /**
     * Hydrate an entity with properties
     *
     * @param array $properties
     * @param array $options
     *
     * @return self
     */
    public function hydrate(array $properties, $options = [])
    {
        $intProperties = (array_key_exists('intProperties', $options)) ? $options['intProperties'] : [];
        $stringProperties = (array_key_exists('stringProperties', $options)) ? $options['stringProperties'] : [];
        $htmlStringProperties = (array_key_exists('htmlStringProperties', $options)) ? $options['htmlStringProperties'] : [];

        foreach ($properties as $prop => $val) {
            if (property_exists($this, $prop)) {

                if (stripos(strrev($prop), 'dI') === 0 || in_array($prop, $intProperties))
                    $val = intval($val);
                else if (in_array($prop, $stringProperties))
                    $val = $this->getSanitizer()->string($val);
                else if (in_array($prop, $htmlStringProperties))
                    $val = htmlentities($val);

                $this->{$prop} =  $val;
            }
        }

        return $this;
    }

    /**
     * Json Serialize
     * @return array
     */
    public function jsonSerialize()
    {
        $exclude = $this->jsonExclude;

        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($properties as $key => $value) {
            if (!in_array($key, $exclude)) {
                $json[$key] = $value;
            }
        }
        return $json;
    }

    /**
     * Add a property to the excluded list
     * @param string $property
     */
    public function excludeProperty($property)
    {
        $this->jsonExclude[] = $property;
    }

    /**
     * Remove a property from the excluded list
     * @param string $property
     */
    public function includeProperty($property)
    {
        $this->jsonExclude = array_diff($this->jsonExclude, [$property]);
    }

    /**
     * Get the Permissions Class
     * @return string
     */
    public function permissionsClass()
    {
        return ($this->permissionsClass == null) ? get_class($this) : $this->permissionsClass;
    }

    /**
     * Set the Permissions Class
     * @param string $class
     */
    protected function setPermissionsClass($class)
    {
        $this->permissionsClass = $class;
    }

    /**
     * Set app
     * @param Set $container
     * @return mixed
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get App
     * @return Set
     */
    protected function getContainer()
    {
        if ($this->container == null)
            throw new \RuntimeException(__('Entity Application not set'));

        return $this->container;
    }

    /**
     * Get Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->getContainer()->pool;
    }

    /**
     * Get Store
     * @return StorageInterface
     */
    protected function getStore()
    {
        return $this->getContainer()->store;
    }

    /**
     * Get Log
     * @return Log
     */
    protected function getLog()
    {
        return $this->getContainer()->logHelper;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->getContainer()->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerInterface
     */
    protected function getSanitizer()
    {
        return $this->getContainer()->sanitizerService;
    }

    /**
     * Get Config
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getContainer()->configService;
    }

    /**
     * Get Player Service
     * @return PlayerActionHelperInterface
     */
    public function getPlayerService()
    {
        return $this->getContainer()->playerActionService;
    }
}