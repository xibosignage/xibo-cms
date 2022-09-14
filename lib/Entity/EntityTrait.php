<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Entity;


use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Helper\DateFormatHelper;
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
    private $canChangeOwner = true;

    public $buttons = [];
    private $jsonExclude = ['buttons', 'jsonExclude', 'originalValues', 'jsonInclude', 'datesToFormat'];

    /** @var array Original values hydrated */
    protected $originalValues = [];

    /** @var array Unmatched properties */
    private $unmatchedProperties = [];

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface */
    private $dispatcher;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    protected function setCommonDependencies($store, $log, $dispatcher)
    {
        $this->store = $store;
        $this->log = $log;
        $this->dispatcher = $dispatcher;
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
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface
    {
        if ($this->dispatcher === null) {
            $this->getLog()->error('getDispatcher: [entity] No dispatcher found, returning an empty one');
            $this->dispatcher = new EventDispatcher();
        }

        return $this->dispatcher;
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
        $doubleProperties = (array_key_exists('doubleProperties', $options)) ? $options['doubleProperties'] : [];
        $stringProperties = (array_key_exists('stringProperties', $options)) ? $options['stringProperties'] : [];
        $htmlStringProperties = (array_key_exists('htmlStringProperties', $options)) ? $options['htmlStringProperties'] : [];

        foreach ($properties as $prop => $val) {
            // Parse the property
            if ((stripos(strrev($prop), 'dI') === 0 || in_array($prop, $intProperties)) && !in_array($prop, $stringProperties)) {
                $val = intval($val);
            } else if (in_array($prop, $doubleProperties)) {
                $val = doubleval($val);
            } else if (in_array($prop, $stringProperties)) {
                $val = filter_var($val, FILTER_SANITIZE_STRING);
            } else if (in_array($prop, $htmlStringProperties)) {
                $val = htmlentities($val);
            }

            if (property_exists($this, $prop)) {
                $this->{$prop} =  $val;
                $this->originalValues[$prop] = $val;
            } else {
                $this->unmatchedProperties[$prop] = $val;
            }
        }

        return $this;
    }

    /**
     * Reset originals to current values
     */
    public function setOriginals()
    {
        foreach ($this->jsonSerialize() as $key => $value) {
            $this->originalValues[$key] = $value;
        }
    }

    /**
     * Get the original value of a property
     * @param string $property
     * @return null|mixed
     */
    public function getOriginalValue($property)
    {
        return (isset($this->originalValues[$property])) ? $this->originalValues[$property] : null;
    }

    /**
     * @param string $property
     * @param mixed $value
     * @return $this
     */
    public function setOriginalValue(string $property, $value)
    {
        $this->originalValues[$property] = $value;
        return $this;
    }

    /**
     * Has the provided property been changed from its original value
     * @param string $property
     * @return bool
     */
    public function hasPropertyChanged($property)
    {
        if (!property_exists($this, $property))
            return true;

        return $this->getOriginalValue($property) != $this->{$property};
    }

    /**
     * @param $property
     * @return bool
     */
    public function propertyOriginallyExisted($property)
    {
        return array_key_exists($property, $this->originalValues);
    }

    /**
     * Get all changed properties for this entity
     * @param bool $jsonEncodeArrays
     * @return array
     */
    public function getChangedProperties($jsonEncodeArrays = false)
    {
        $changedProperties = [];

        foreach ($this->jsonSerialize() as $key => $value) {
            if (!is_array($value) && !is_object($value) && $this->propertyOriginallyExisted($key) && $this->hasPropertyChanged($key)) {

                if ( isset($this->datesToFormat) && in_array($key, $this->datesToFormat)) {
                    $changedProperties[$key] = Carbon::createFromTimestamp($this->getOriginalValue($key))->format(DateFormatHelper::getSystemFormat()) . ' > ' . Carbon::createFromTimestamp($value)->format(DateFormatHelper::getSystemFormat());
                } else {
                    $changedProperties[$key] = $this->getOriginalValue($key) . ' > ' . $value;
                }
            }

            if (is_array($value) && $jsonEncodeArrays && $this->propertyOriginallyExisted($key) && $this->hasPropertyChanged($key)) {
                $changedProperties[$key] = json_encode($this->getOriginalValue($key)) . ' > ' . json_encode($value);
            }
        }

        return $changedProperties;
    }

    /**
     * Get an unmatched property
     * @param string $property
     * @param mixed $default The default value to return if the unmatched property doesn't exist.
     * @return null|mixed
     */
    public function getUnmatchedProperty(string $property, $default)
    {
        return $this->unmatchedProperties[$property] ?? $default;
    }

    /**
     * Json Serialize
     * @param bool $forAudit
     * @return array
     */
    public function jsonSerialize($forAudit = false)
    {
        $exclude = $this->jsonExclude;

        $properties = ObjectVars::getObjectVars($this);
        $json = [];
        foreach ($properties as $key => $value) {
            if (!in_array($key, $exclude) && !$forAudit) {
                $json[$key] = $value;
            } else if ($forAudit && in_array($key, $this->jsonInclude)) {
                $json[$key] = $value;
            }
        }
        return $json;
    }

    /**
     * To Array
     * @param bool $jsonEncodeArrays
     * @return array
     */
    public function toArray($jsonEncodeArrays = false)
    {
        $objectAsJson = $this->jsonSerialize();

        foreach ($objectAsJson as $key => $value) {
            if (isset($this->datesToFormat) && in_array($key, $this->datesToFormat)) {
                $objectAsJson[$key] = Carbon::createFromTimestamp($value)->format(DateFormatHelper::getSystemFormat());
            }

            if ($jsonEncodeArrays) {
                if (is_array($value)) {
                    $objectAsJson[$key] = json_encode($value);
                }
            }
        }

        return $objectAsJson;
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
     * Can the owner change?
     * @return bool
     */
    public function canChangeOwner()
    {
        return $this->canChangeOwner && method_exists($this, 'setOwner');
    }

    /**
     * @param bool $bool Can the owner be changed?
     */
    protected function setCanChangeOwner($bool)
    {
        $this->canChangeOwner = $bool;
    }

    /**
     * @param $entityId
     * @param $message
     * @param null $changedProperties
     * @param bool $jsonEncodeArrays
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    protected function audit($entityId, $message, $changedProperties = null, $jsonEncodeArrays = false)
    {
        $class = substr(get_class($this), strrpos(get_class($this), '\\') + 1);

        if ($changedProperties === null) {
            // No properties provided, so we should work them out
            // If we have originals, then get changed, otherwise get the current object state
            $changedProperties = (count($this->originalValues) <= 0)
                ? $this->toArray($jsonEncodeArrays)
                : $this->getChangedProperties($jsonEncodeArrays);
        } else if ($changedProperties !== false && count($changedProperties) <= 0) {
            // Only audit if properties have been provided
            return;
        }

        $this->getLog()->audit($class, $entityId, $message, $changedProperties);
    }

    /**
     * Compare two arrays, both keys and values.
     *
     * @param $array1
     * @param $array2
     * @param bool $compareValues
     * @return array
     */
    public function compareMultidimensionalArrays($array1, $array2, $compareValues = true)
    {
        $result = [];

        // go through arrays, compare keys and values
        // the compareValues flag is there for tag unlink - we're interested only in array keys
        foreach ($array1 as $key => $value) {

            if (!is_array($array2) || !array_key_exists($key, $array2)) {
                $result[$key] = $value;
                continue;
            }

            if ($value != $array2[$key] && $compareValues) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function handleTagAssign($tagToAssign)
    {
        if (!in_array($tagToAssign, $this->tags)) {
            if (empty($this->tags)) {
                // case when we do not have any Tags on our object
                $this->tags[] = $tagToAssign;
            } else {
                // go through existing Tags and compare Tag values
                // case for existing Tag, but with different value
                foreach ($this->tags as $key => $currentTag) {
                    if ($currentTag->tagId === $tagToAssign->tagId && $currentTag->value !== $tagToAssign->value) {
                        array_splice($this->tags, $key, 1);
                        $this->unassignTags[] = $currentTag;
                        $this->tags[] = $tagToAssign;
                    }
                }
                // if the Tag is still not in our array, add it now
                // case for a new Tag
                if (!in_array($tagToAssign, $this->tags)) {
                    $this->tags[] = $tagToAssign;
                }
            }
        } else {
            $this->getLog()->debug('No Tags to assign');
        }
    }

    public function updateFolders($table)
    {
        $this->getStore()->update('UPDATE `'. $table .'` SET permissionsFolderId = :permissionsFolderId, folderId = :folderId WHERE folderId = :oldFolderId', [
            'permissionsFolderId' => $this->permissionsFolderId,
            'folderId' => $this->folderId,
            'oldFolderId' => $this->getOriginalValue('folderId')
        ]);
    }
}
