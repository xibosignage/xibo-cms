<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Widget\Definition;

use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * A Property
 */
class Property implements \JsonSerializable
{
    public $id;
    public $type;
    public $title;
    public $helpText;

    /** @var string[] */
    public $validation = [];

    public $default;

    /** @var \Xibo\Widget\Definition\Option[] */
    public $options;

    /** @var \Xibo\Widget\Definition\Test[]  */
    public $visibility = [];

    /** @var \Xibo\Widget\Definition\SetDefault[]  */
    public $setDefault = [];

    /** @var string The element variant */
    public $variant;

    /** @var string The data format */
    public $format;

    /** @var string Custom CSS class to apply to the input */
    public $customClass;

    /** @var string Custom data to apply to the input */
    public $customData;

    /** @var bool Should library refs be permitted in the value? */
    public $allowLibraryRefs = false;

    /** @var bool Should asset refs be permitted in the value? */
    public $allowAssetRefs = false;

    /** @var \Xibo\Widget\Definition\PlayerCompatibility */
    public $playerCompatibility;

    /** @var string HTML to populate a custom popover to be shown next to the input */
    public $customPopOver;

    /** @var string HTML selector of the element that this property depends on */
    public $dependsOn;

    public $value;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'type' => $this->type,
            'variant' => $this->variant,
            'format' => $this->format,
            'title' => $this->title,
            'helpText' => $this->helpText,
            'validation' => $this->validation,
            'default' => $this->default,
            'options' => $this->options,
            'customPopOver' => $this->customPopOver,
            'playerCompatibility' => $this->playerCompatibility,
            'visibility' => $this->visibility,
            'setDefault' => $this->setDefault,
            'allowLibraryRefs' => $this->allowLibraryRefs,
            'dependsOn' => $this->dependsOn,
            'customClass' => $this->customClass,
            'customData' => $this->customData,
        ];
    }

    /**
     * Add an option
     * @param string $name
     * @param string $image
     * @param string $title
     * @return $this
     */
    public function addOption(string $name, string $image, string $title): Property
    {
        $option = new Option();
        $option->name = $name;
        $option->image = $image;
        $option->title = $title;
        $this->options[] = $option;
        return $this;
    }

    /**
     * Add a data pair
     * @param string $name
     * @param string $title
     * @return $this
     */
    public function addData(string $name, string $value): Property
    {
        $data = new Data();
        $data->name = $name;
        $data->value = $value;
        $this->customData[] = $data;
        return $this;
    }

    /**
     * Add a visibility test
     * @param string $type
     * @param array $conditions
     * @return $this
     */
    public function addVisibilityTest(string $type, array $conditions): Property
    {
        $test = new Test();
        $test->type = $type;

        foreach ($conditions as $item) {
            $condition = new Condition();
            $condition->type = $item['type'];
            $condition->field = $item['field'];
            $condition->value = $item['value'];
            $test->conditions[] = $condition;
        }

        $this->visibility[] = $test;
        return $this;
    }

    /**
     * Add a set default set with tests
     * @param string $testType
     * @param array $testConditions
     * @param string $field Set field attribute
     * @param string $value Set value node text
     * @return $this
     */
    public function addSetDefault(string $testType, array $testConditions, string $field, string $value): Property
    {
        // Tests
        $test = new Test();
        $test->type = $testType;

        foreach ($testConditions as $item) {
            $condition = new SetDefaultTestCondition();
            $condition->type = $item['type'];
            $condition->value = $item['value'];
            $test->conditions[] = $condition;
        }

        // Set Default
        $set = new SetDefault();
        $set->test = $test;
        $set->field = $field;
        $set->value = $value;
        $this->setDefault[] = $set;
        return $this;
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string|null $key
     * @return \Xibo\Widget\Definition\Property
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function setDefaultByType(SanitizerInterface $params, string $key = null): Property
    {
        $this->default = $this->getByType($params, $key);
        return $this;
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string|null $key
     * @return \Xibo\Widget\Definition\Property
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function setValueByType(SanitizerInterface $params, string $key = null): Property
    {
        $value = $this->getByType($params, $key);
        if ($value !== $this->default) {
            $this->value = $value;
        }
        return $this;
    }

    /**
     * @return \Xibo\Widget\Definition\Property
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function validate(): Property
    {
        foreach ($this->validation as $validation) {
            switch ($validation) {
                case 'required':
                    try {
                        if (empty($this->value)) {
                            throw new NotFoundException();
                        }
                    } catch (NotFoundException $notFoundException) {
                        throw new InvalidArgumentException(sprintf(
                            __('Missing required property %s'),
                            $this->id
                        ));
                    }
                    break;
                default:
                    // Nothing to validate
            }
        }
        return $this;
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string|null $key
     * @return bool|float|int|string|null
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function getByType(SanitizerInterface $params, string $key = null)
    {
        $key = $key ?: $this->id;

        if (!$params->hasParam($key) && $this->type !== 'checkbox') {
            // Clear the stored value and therefore use the default
            return null;
        }

        // Parse according to the type of field we're expecting
        switch ($this->type) {
            case 'checkbox':
                return $params->getCheckbox($key);

            case 'integer':
                return $params->getInt($key);

            case 'number':
                return $params->getDouble($key);

            case 'dropdown':
                $value = $params->getString($key);
                if ($value === null) {
                    return null;
                }

                $found = false;
                foreach ($this->options as $option) {
                    if ($option->name === $value) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    return $value;
                } else {
                    throw new InvalidArgumentException(
                        sprintf(__('%s is not a valid option'), $value),
                        $key
                    );
                }

            case 'code':
                return $params->getParam($key);
            case 'richText':
                return $params->getParam($key);
            case 'input':
            default:
                return $params->getString($key);
        }
    }
}
