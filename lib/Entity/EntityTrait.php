<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EntityTrait.php)
 */


namespace Xibo\Entity;


use Xibo\Helper\ObjectVars;

trait EntityTrait
{
    private $hash = null;
    public $buttons = [];

    /**
     * Hydrate an entity with properties
     *
     * @param array $properties
     *
     * @return self
     */
    public function hydrate(array $properties)
    {
        foreach ($properties as $prop => $val) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = (stripos(strrev($prop), 'dI') === 0) ? intval($val) : $val;
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
        $exclude = (property_exists($this, 'jsonExclude')) ? $this->jsonExclude : [];
        $exclude[] = 'jsonExclude';

        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($properties as $key => $value) {
            if (!in_array($key, $exclude)) {
                $json[$key] = $value;
            }
        }
        return $json;
    }
}