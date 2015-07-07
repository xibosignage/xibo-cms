<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Xibo\Helper\ObjectVars;
use Xibo\Helper\Sanitize;

trait EntityTrait
{
    private $hash = null;
    private $loaded = false;
    private $deleting = false;

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

        foreach ($properties as $prop => $val) {
            if (property_exists($this, $prop)) {

                if (stripos(strrev($prop), 'dI') === 0 || in_array($prop, $intProperties))
                    $val = intval($val);
                else if (in_array($prop, $stringProperties))
                    $val = Sanitize::string($val);

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
}