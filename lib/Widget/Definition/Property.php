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

namespace Xibo\Widget\Definition;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Str;
use Respect\Validation\Validator as v;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\ValueTooLargeException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * A Property
 * @SWG\Definition()
 */
class Property implements \JsonSerializable
{
    /**
     * @SWG\Property(description="ID, saved as a widget option")
     * @var string
     */
    public $id;

    /**
     * @SWG\Property(description="Type, determines the field type")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="Title: shown in the property panel")
     * @var string
     */
    public $title;

    /**
     * @SWG\Property(description="Help Text: shown in the property panel")
     * @var string
     */
    public $helpText;

    /** @var \Xibo\Widget\Definition\Rule  */
    public $validation;

    /**
     * @SWG\Property()
     * @var string An optional default value
     */
    public $default;

    /** @var \Xibo\Widget\Definition\Option[] */
    public $options;

    /** @var \Xibo\Widget\Definition\Test[]  */
    public $visibility = [];

    /** @var string The element variant */
    public $variant;

    /** @var string The data format */
    public $format;

    /** @var bool Should library refs be permitted in the value? */
    public $allowLibraryRefs = false;

    /** @var bool Should asset refs be permitted in the value? */
    public $allowAssetRefs = false;

    /** @var bool Should translations be parsed in the value? */
    public $parseTranslations = false;

    /** @var bool Should the property be included in the XLF? */
    public $includeInXlf = false;

    /** @var bool Should the property be sent into Elements */
    public $sendToElements = false;

    /** @var bool Should the default value be written out to widget options */
    public $saveDefault = false;

    /** @var \Xibo\Widget\Definition\PlayerCompatibility */
    public $playerCompatibility;

    /** @var string HTML to populate a custom popover to be shown next to the input */
    public $customPopOver;

    /** @var string HTML selector of the element that this property depends on */
    public $dependsOn;

    /** @var string ID of the target element */
    public $target;

    /** @var string The mode of the property */
    public $mode;

    /** @var string The group ID of the property */
    public $propertyGroupId;

    /** @var mixed The value assigned to this property. This is set from widget options, or settings, never via XML  */
    public $value;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'type' => $this->type,
            'variant' => $this->variant,
            'format' => $this->format,
            'title' => $this->title,
            'mode' => $this->mode,
            'target' => $this->target,
            'propertyGroupId' => $this->propertyGroupId,
            'helpText' => $this->helpText,
            'validation' => $this->validation,
            'default' => $this->default,
            'options' => $this->options,
            'customPopOver' => $this->customPopOver,
            'playerCompatibility' => $this->playerCompatibility,
            'visibility' => $this->visibility,
            'allowLibraryRefs' => $this->allowLibraryRefs,
            'allowAssetRefs' => $this->allowAssetRefs,
            'parseTranslations' => $this->parseTranslations,
            'saveDefault' => $this->saveDefault,
            'dependsOn' => $this->dependsOn,
            'sendToElements' => $this->sendToElements,
        ];
    }

    /**
     * Add an option
     * @param string $name
     * @param string $image
     * @param array $set
     * @param string $title
     * @return $this
     */
    public function addOption(string $name, string $image, array $set, string $title): Property
    {
        $option = new Option();
        $option->name = $name;
        $option->image = $image;
        $option->set = $set;
        $option->title = __($title);
        $this->options[] = $option;
        return $this;
    }

    /**
     * Add a visibility test
     * @param string $type
     * @param string|null $message
     * @param array $conditions
     * @return $this
     */
    public function addVisibilityTest(string $type, ?string $message, array $conditions): Property
    {
        $this->visibility[] = $this->parseTest($type, $message, $conditions);
        return $this;
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string|null $key
     * @return \Xibo\Widget\Definition\Property
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function setDefaultByType(SanitizerInterface $params, ?string $key = null): Property
    {
        $this->default = $this->getByType($params, $key);
        return $this;
    }

    /**
     * @param SanitizerInterface $params
     * @param string|null $key
     * @param bool $ignoreDefault
     * @return Property
     * @throws InvalidArgumentException
     */
    public function setValueByType(
        SanitizerInterface $params,
        ?string $key = null,
        bool $ignoreDefault = false
    ): Property {
        $value = $this->getByType($params, $key);
        if ($value !== $this->default || $ignoreDefault || $this->saveDefault) {
            $this->value = $value;
        }
        return $this;
    }

    /**
     * @param array $properties A key/value array of all properties for this entity (be it module or template)
     * @param string $stage What stage are we at?
     * @return Property
     * @throws InvalidArgumentException
     * @throws ValueTooLargeException
     */
    public function validate(array $properties, string $stage): Property
    {
        if (!empty($this->value) && strlen($this->value) > 67108864) {
            throw new ValueTooLargeException(sprintf(__('Value too large for %s'), $this->title), $this->id);
        }

        // Skip if no validation.
        if ($this->validation === null
            || ($stage === 'save' && !$this->validation->onSave)
            || ($stage === 'status' && !$this->validation->onStatus)
        ) {
            return $this;
        }

        foreach ($this->validation->tests as $test) {
            // We have a test, evaulate its conditions.
            $exceptions = [];

            foreach ($test->conditions as $condition) {
                try {
                    // Assume we're testing the field we belong to, and if that's empty use the default value
                    $testValue = $this->value ?? $this->default;

                    // What value are we testing against (only used by certain types)
                    if (empty($condition->field)) {
                        $valueToTestAgainst = $condition->value;
                    } else {
                        // If a field and a condition value is provided, test against those, ignoring my own field value
                        if (!empty($condition->value)) {
                            $testValue = $condition->value;
                        }

                        $valueToTestAgainst = $properties[$condition->field] ?? null;
                    }

                    // Do we have a message
                    $message = empty($test->message) ? null : __($test->message);

                    switch ($condition->type) {
                        case 'required':
                            // We will accept the default value here
                            if (empty($testValue) && empty($this->default)) {
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('Missing required property %s'), $this->title),
                                    $this->id
                                );
                            }
                            break;

                        case 'uri':
                            if (!empty($testValue)
                                && !v::url()->validate($testValue)
                            ) {
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('%s must be a valid URI'), $this->title),
                                    $this->id
                                );
                            }
                            break;

                        case 'windowsPath':
                            // Ensure the path is a valid Windows file path ending in a file, not a directory
                            $windowsPathRegex = '/^(?P<Root>[A-Za-z]:)(?P<Relative>(?:\\\\[^<>:"\/\\\\|?*\r\n]+)+)(?P<File>\\\\[^<>:"\/\\\\|?*\r\n]+)$/';

                            // Check if the test value is not empty and does not match the regular expression
                            if (!empty($testValue)
                                && !preg_match($windowsPathRegex, $testValue)
                            ) {
                                // Throw an InvalidArgumentException if the test value is not a valid Windows path
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('%s must be a valid Windows path'), $this->title),
                                    $this->id
                                );
                            }

                            break;

                        case 'interval':
                            if (!empty($testValue)) {
                                // Try to create a date interval from it
                                $dateInterval = CarbonInterval::createFromDateString($testValue);
                                if ($dateInterval === false) {
                                    throw new InvalidArgumentException(
                                    // phpcs:ignore Generic.Files.LineLength
                                        __('That is not a valid date interval, please use natural language such as 1 week'),
                                        'customInterval'
                                    );
                                }

                                // Use now and add the date interval to it
                                $now = Carbon::now();
                                $check = $now->copy()->add($dateInterval);

                                if ($now->equalTo($check)) {
                                    throw new InvalidArgumentException(
                                    // phpcs:ignore Generic.Files.LineLength
                                        $message ?? __('That is not a valid date interval, please use natural language such as 1 week'),
                                        $this->id
                                    );
                                }
                            }
                            break;

                        case 'eq':
                            if ($testValue != $valueToTestAgainst) {
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('%s must equal %s'), $this->title, $valueToTestAgainst),
                                    $this->id,
                                );
                            }
                            break;

                        case 'neq':
                            if ($testValue == $valueToTestAgainst) {
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('%s must not equal %s'), $this->title, $valueToTestAgainst),
                                    $this->id,
                                );
                            }
                            break;

                        case 'contains':
                            if (!empty($testValue) && !Str::contains($testValue, $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                    $message ?? sprintf(__('%s must contain %s'), $this->title, $valueToTestAgainst),
                                    $this->id,
                                );
                            }
                            break;

                        case 'ncontains':
                            if (!empty($testValue) && Str::contains($testValue, $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                // phpcs:ignore Generic.Files.LineLength
                                    $message ?? sprintf(__('%s must not contain %s'), $this->title, $valueToTestAgainst),
                                    $this->id,
                                );
                            }
                            break;

                        case 'lt':
                            // Value must be < to the condition value, or field value
                            if (!($testValue < $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                // phpcs:ignore Generic.Files.LineLength
                                    $message ?? sprintf(__('%s must be less than %s'), $this->title, $valueToTestAgainst),
                                    $this->id
                                );
                            }
                            break;

                        case 'lte':
                            // Value must be <= to the condition value, or field value
                            if (!($testValue <= $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                // phpcs:ignore Generic.Files.LineLength
                                    $message ?? sprintf(__('%s must be less than or equal to %s'), $this->title, $valueToTestAgainst),
                                    $this->id
                                );
                            }
                            break;

                        case 'gte':
                            // Value must be >= to the condition value, or field value
                            if (!($testValue >= $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                // phpcs:ignore Generic.Files.LineLength
                                    $message ?? sprintf(__('%s must be greater than or equal to %s'), $this->title, $valueToTestAgainst),
                                    $this->id
                                );
                            }
                            break;

                        case 'gt':
                            // Value must be > to the condition value, or field value
                            if (!($testValue > $valueToTestAgainst)) {
                                throw new InvalidArgumentException(
                                // phpcs:ignore Generic.Files.LineLength
                                    $message ?? sprintf(__('%s must be greater than %s'), $this->title, $valueToTestAgainst),
                                    $this->id
                                );
                            }
                            break;

                        default:
                            // Nothing to validate
                    }
                } catch (InvalidArgumentException $invalidArgumentException) {
                    // If we are an AND test, all conditions must pass, so we know already to exception here.
                    if ($test->type === 'and') {
                        throw $invalidArgumentException;
                    }

                    // We're an OR
                    $exceptions[] = $invalidArgumentException;
                }
            }

            // If we are an OR then make sure all conditions have failed.
            $countOfFailures = count($exceptions);
            if ($test->type === 'or' && $countOfFailures === count($test->conditions)) {
                throw $exceptions[0];
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
    private function getByType(SanitizerInterface $params, ?string $key = null)
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
            case 'richText':
                return $params->getParam($key);
            case 'text':
                if ($this->variant === 'sql') {
                    // Handle raw SQL clauses
                    return str_ireplace(Sql::DISALLOWED_KEYWORDS, '', $params->getParam($key));
                } else {
                    return $params->getString($key);
                }
            default:
                return $params->getString($key);
        }
    }

    /**
     * Apply any filters on the data.
     * @return void
     */
    public function applyFilters(): void
    {
        if ($this->variant === 'uri' || $this->type === 'commandBuilder') {
            $this->value = urlencode($this->value);
        }
    }

    /**
     * Reverse filters
     * @return void
     */
    public function reverseFilters(): void
    {
        $this->value = $this->reverseFiltersOnValue($this->value);
    }

    /**
     * @param mixed $value
     * @return mixed|string
     */
    public function reverseFiltersOnValue(mixed $value): mixed
    {
        if (($this->variant === 'uri' || $this->type === 'commandBuilder') && !empty($value)) {
            $value = urldecode($value);
        }
        return $value;
    }

    /**
     * Should this property be represented with CData
     * @return bool
     */
    public function isCData(): bool
    {
        return $this->type === 'code' || $this->type === 'richText';
    }

    /**
     * @param string $type
     * @param string $message
     * @param array $conditions
     * @return Test
     */
    public function parseTest(string $type, string $message, array $conditions): Test
    {
        $test = new Test();
        $test->type = $type ?: 'and';
        $test->message = $message;

        foreach ($conditions as $item) {
            $condition = new Condition();
            $condition->type = $item['type'];
            $condition->field = $item['field'];
            $condition->value = $item['value'];
            $test->conditions[] = $condition;
        }
        return $test;
    }
}
