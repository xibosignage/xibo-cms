<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
    public $default;

    /** @var \Xibo\Widget\Definition\Option[] */
    public $options;

    public $playerCompatability;
    
    public $value;

    /**
     * JSON serialise this property for the purposes of saving the value
     * @return array
     */
    public function jsonSerializeForSaving(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value
        ];
    }
    
    /** @inheritDoc */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'type' => $this->type,
            'title' => $this->title,
            'helpText' => $this->helpText,
            'default' => $this->default,
            'options' => $this->options,
            'playerCompatibility' => $this->playerCompatability
        ];
    }

    /**
     * Add an option
     * @param string $name
     * @param string $title
     * @return $this
     */
    public function addOption(string $name, string $title): Property
    {
        $option = new Option();
        $option->name = $name;
        $option->title = $title;
        $this->options[] = $option;
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
        $this->value = $this->getByType($params, $key);
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

        if (!$params->hasParam($key)) {
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

            case 'input':
            default:
                return $params->getString($key);
        }
    }
}
