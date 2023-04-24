<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Xibo\Helper\DateFormatHelper;

/**
 * A trait for common functionality in regard to properties on modules/module templates
 */
trait ModulePropertyTrait
{
    /**
     * @param \Xibo\Entity\Widget $widget
     * @param bool $includeDefaults
     * @return $this
     */
    public function decorateProperties(Widget $widget, bool $includeDefaults = false)
    {
        foreach ($this->properties as $property) {
            $property->value = $widget->getOptionValue($property->id, null);

            // Should we include defaults?
            if ($includeDefaults && $property->value === null) {
                $property->value = $property->default;
            }

            if ($property->type === 'integer' && $property->value !== null) {
                $property->value = intval($property->value);
            } else if (($property->type === 'double' || $property->type === 'number')
                && $property->value !== null
            ) {
                $property->value = doubleval($property->value);
            }

            if ($property->variant === 'uri' && !empty($value)) {
                $property->value = urldecode($property->value);
            }
        }
        return $this;
    }

    /**
     * @param array $properties
     * @param bool $includeDefaults
     * @return array
     */
    public function decoratePropertiesByArray(array $properties, bool $includeDefaults = false): array
    {
        // Flatten the properties array so that we can reference it by key.
        $keyedProperties = [];
        foreach ($properties as $property) {
            $keyedProperties[$property['id']] = $property['value'];
        }

        $decoratedProperties = [];
        foreach ($this->properties as $property) {
            $decoratedProperty = $keyedProperties[$property->id] ?? null;

            // Should we include defaults?
            if ($includeDefaults && $decoratedProperty === null) {
                $decoratedProperty = $property->default;
            }

            if ($property->type === 'integer' && $decoratedProperty !== null) {
                $decoratedProperty = intval($decoratedProperty);
            } else if (($property->type === 'double' || $property->type === 'number')
                && $decoratedProperty !== null
            ) {
                $decoratedProperty = doubleval($decoratedProperty);
            }

            if ($property->variant === 'uri' && !empty($value)) {
                $decoratedProperty = urldecode($decoratedProperty);
            }

            $decoratedProperties[$property->id] = $decoratedProperty;
        }
        return $decoratedProperties;
    }

    /**
     * @param bool $decorateForOutput true if we should decorate for output to either the preview or player
     * @param array|null $overrideValues a key/value array of values to use instead the stored property values
     * @return array
     */
    public function getPropertyValues(bool $decorateForOutput = true, ?array $overrideValues = null): array
    {
        $properties = [];
        foreach ($this->properties as $property) {
            $value = $overrideValues !== null ? ($overrideValues[$property->id] ?? null) : $property->value;

            // TODO: should we cast values to their appropriate field formats.
            if ($decorateForOutput) {
                // Does this property have library references?
                if ($property->allowLibraryRefs && !empty($value)) {
                    // Parse them out and replace for our special syntax.
                    $matches = [];
                    preg_match_all('/\[(.*?)\]/', $value, $matches);
                    foreach ($matches[1] as $match) {
                        if (is_numeric($match)) {
                            $value = str_replace(
                                '[' . $match . ']',
                                '[[mediaId=' . $match . ']]',
                                $value
                            );
                        }
                    }
                }

                if ($property->variant === 'dateFormat' && !empty($value)) {
                    $value = DateFormatHelper::convertPhpToMomentFormat($value);
                }

                if ($property->type === 'mediaSelector') {
                    $value = (!$value) ? '' : '[[mediaId=' . $value . ']]';
                }
            }
            $properties[$property->id] = $value;
        }
        return $properties;
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException|\Xibo\Support\Exception\ValueTooLargeException
     */
    public function validateProperties(): void
    {
        // Go through all of our required properties, and validate that they are as they should be.
        foreach ($this->properties as $property) {
            $property->validate();
        }
    }
}
