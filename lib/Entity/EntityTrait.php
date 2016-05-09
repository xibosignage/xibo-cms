<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Xibo\Helper\ObjectVars;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class EntityTrait
 * used by all entities
 * @package Xibo\Entity
 */
trait EntityTrait
{
    private $hash = null;
    private $loaded = false;
    private $permissionsClass = null;

    public $buttons = [];
    private $jsonExclude = ['buttons', 'jsonExclude'];

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @return $this
     */
    protected function setCommonDependencies($store, $log)
    {
        $this->store = $store;
        $this->log = $log;
        return $this;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->log;
    }

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

                if ((stripos(strrev($prop), 'dI') === 0 || in_array($prop, $intProperties)) && !in_array($prop, $stringProperties))
                    $val = intval($val);
                else if (in_array($prop, $stringProperties))
                    $val = filter_var($val, FILTER_SANITIZE_STRING);
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
}