<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
     * @param Widget $widget
     * @param bool $includeDefaults
     * @param bool $reverseFilters Reverse filters?
     * @return $this
     */
    public function decorateProperties(Widget $widget, bool $includeDefaults = false, bool $reverseFilters = true)
    {
        foreach ($this->properties as $property) {
            $property->value = $widget->getOptionValue($property->id, null);

            // Should we include defaults?
            if ($includeDefaults && $property->value === null) {
                $property->value = $property->default;
            }

            if ($property->value !== null) {
                if ($property->type === 'integer') {
                    $property->value = intval($property->value);
                } else if ($property->type === 'double' || $property->type === 'number') {
                    $property->value = doubleval($property->value);
                } else if ($property->type === 'checkbox') {
                    $property->value = intval($property->value);
                }
            }

            if ($reverseFilters) {
                $property->reverseFilters();
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
            $keyedProperties[$property['id']] = $property['value'] ?? null;
        }

        $decoratedProperties = [];
        foreach ($this->properties as $property) {
            $decoratedProperty = $keyedProperties[$property->id] ?? null;

            // Should we include defaults?
            if ($includeDefaults && $decoratedProperty === null) {
                $decoratedProperty = $property->default;
            }

            if ($decoratedProperty !== null) {
                if ($property->type === 'integer') {
                    $decoratedProperty = intval($decoratedProperty);
                } else if ($property->type === 'double' || $property->type === 'number') {
                    $decoratedProperty = doubleval($decoratedProperty);
                } else if ($property->type === 'checkbox') {
                    $decoratedProperty = intval($decoratedProperty);
                }
            }

            $decoratedProperty = $property->reverseFiltersOnValue($decoratedProperty);

            // Add our decorated property
            $decoratedProperties[$property->id] = $decoratedProperty;
        }
        return $decoratedProperties;
    }

    /**
     * @param bool $decorateForOutput true if we should decorate for output to either the preview or player
     * @param array|null $overrideValues a key/value array of values to use instead the stored property values
     * @param bool $includeDefaults include default values
     * @param bool $skipNullProperties skip null properties
     * @return array
     */
    public function getPropertyValues(
        bool $decorateForOutput = true,
        ?array $overrideValues = null,
        bool $includeDefaults = false,
        bool $skipNullProperties = false,
    ): array {
        $properties = [];
        foreach ($this->properties as $property) {
            $value = $overrideValues !== null ? ($overrideValues[$property->id] ?? null) : $property->value;

            if ($includeDefaults && $value === null) {
                $value = $property->default ?? null;
            }

            if ($skipNullProperties && $value === null) {
                continue;
            }

            // TODO: should we cast values to their appropriate field formats.
            if ($decorateForOutput) {
                // Does this property have library references?
                if ($property->allowLibraryRefs && !empty($value)) {
                    // Parse them out and replace for our special syntax.
                    // TODO: Can we improve this regex to ignore things we suspect are JavaScript array access?
                    $matches = [];
                    preg_match_all('/\[(.*?)\]/', $value, $matches);
                    foreach ($matches[1] as $match) {
                        // We ignore non-numbers and zero/negative integers
                        if (is_numeric($match) && intval($match) <= 0) {
                            $value = str_replace(
                                '[' . $match . ']',
                                '[[mediaId=' . $match . ']]',
                                $value
                            );
                        }
                    }
                }

                // Do we need to parse out any translations? We only do this on output.
                if ($property->parseTranslations && !empty($value)) {
                    $matches = [];
                    preg_match_all('/\|\|.*?\|\|/', $value, $matches);

                    foreach ($matches[0] as $sub) {
                        // Parse out the translatable string and substitute
                        $value = str_replace($sub, __(str_replace('||', '', $sub)), $value);
                    }
                }

                // Date format
                if ($property->variant === 'dateFormat' && !empty($value)) {
                    $value = DateFormatHelper::convertPhpToMomentFormat($value);
                }

                // Media selector
                if ($property->type === 'mediaSelector') {
                    $value = (!$value) ? '' : '[[mediaId=' . $value . ']]';
                }
            }
            $properties[$property->id] = $value;
        }
        return $properties;
    }

    /**
     * Gets the default value for a property
     * @param string $id
     * @return mixed
     */
    public function getPropertyDefault(string $id): mixed
    {
        foreach ($this->properties as $property) {
            if ($property->id === $id) {
                return $property->default;
            }
        }

        return null;
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException|\Xibo\Support\Exception\ValueTooLargeException
     */
    public function validateProperties(string $stage, $additionalProperties = []): void
    {
        // Go through all of our required properties, and validate that they are as they should be.
        // provide a key/value state of all current properties
        $properties = array_merge(
            $this->getPropertyValues(false, null, true),
            $additionalProperties,
        );

        foreach ($this->properties as $property) {
            $property->validate($properties, $stage);
        }
    }
}
