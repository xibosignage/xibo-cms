<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Slim\Slim;
use Xibo\Helper\Config;
use Xibo\Helper\DateInterface;
use Xibo\Helper\Log;
use Xibo\Helper\ObjectVars;
use Xibo\Helper\SanitizerInterface;
use Xibo\Storage\StorageInterface;

trait EntityTrait
{
    /**
     * The App
     * @var Slim
     */
    private $app;

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
     * @param Slim $app
     * @return mixed
     */
    public function setApp($app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Get App
     * @return Slim
     */
    protected function getApp()
    {
        if ($this->app == null)
            throw new \RuntimeException(__('Entity Application not set'));

        return $this->app;
    }

    /**
     * Get Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->getApp()->pool;
    }

    /**
     * Get Store
     * @return StorageInterface
     */
    protected function getStore()
    {
        return $this->getApp()->store;
    }

    /**
     * Get Log
     * @return Log
     */
    protected function getLog()
    {
        return $this->getApp()->logHelper;
    }

    /**
     * Get Date
     * @return DateInterface
     */
    protected function getDate()
    {
        return $this->getApp()->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerInterface
     */
    protected function getSanitizer()
    {
        return $this->getApp()->sanitizerService;
    }

    /**
     * Get Config
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getApp()->configService;
    }
}