<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Event;

use Xibo\Support\Exception\ConfigurationException;

/**
 * This class represents a schedule criteria request event. It is responsible for initializing,
 * managing, and retrieving schedule criteria. The class provides methods for adding types,
 * metrics, and their associated conditions and values.
 */
class ScheduleCriteriaRequestEvent extends Event implements ScheduleCriteriaRequestInterface
{
    public static $NAME = 'schedule.criteria.request';
    private array $criteria = [];
    private ?int $currentTypeIndex = null;
    private array $currentMetric = [];
    private array $defaultConditions = [];

    public function __construct()
    {
        // Initialize default conditions in key-value format
        $this->defaultConditions = [
            'set' => __('Is set'),
            'lt' => __('Less than'),
            'lte' => __('Less than or equal to'),
            'eq' => __('Equal to'),
            'neq' => __('Not equal to'),
            'gte' => __('Greater than or equal to'),
            'gt' => __('Greater than'),
            'contains' => __('Contains'),
            'ncontains' => __('Not contains'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function addType(string $id, string $type): self
    {
        // Ensure that 'types' key exists
        if (!isset($this->criteria['types'])) {
            $this->criteria['types'] = [];
        }

        // Check if the type already exists
        foreach ($this->criteria['types'] as $index => $existingType) {
            if ($existingType['id'] === $id) {
                // If the type exists, update currentTypeIndex and return
                $this->currentTypeIndex = $index;
                return $this;
            }
        }

        // If the type doesn't exist, add it in the criteria array
        $this->criteria['types'][] = [
            'id' => $id,
            'name' => $type,
            'metrics' => []
        ];

        // Set the current type index for chaining
        $this->currentTypeIndex = count($this->criteria['types']) - 1;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addMetric(string $id, string $name): self
    {
        // Ensure the current type is set
        if (!isset($this->criteria['types'][$this->currentTypeIndex])) {
            throw new ConfigurationException(__('Current type is not set.'));
        }

        // initialize the metric to add
        $metric = [
            'id' => $id,
            'name' => $name,
            'conditions' => $this->formatConditions($this->defaultConditions),
            'isUsingDefaultConditions' => true,
            'values' => null
        ];

        // Reference the current type's metrics
        $metrics = &$this->criteria['types'][$this->currentTypeIndex]['metrics'];

        // Check if the metric already exists
        foreach ($metrics as &$existingMetric) {
            if ($existingMetric['id'] === $id) {
                // If the metric exists, set currentMetric and return
                $this->currentMetric = $existingMetric;
                return $this;
            }
        }

        // If the metric doesn't exist, add it to the metrics array
        $metrics[] = $metric;

        // Set the current metric for chaining
        $this->currentMetric = $metric;

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function addCondition(array $conditions): self
    {
        // Retain default conditions if provided condition array is empty
        if (empty($conditions)) {
            return $this;
        }

        // Ensure current type is set
        if (!isset($this->criteria['types'][$this->currentTypeIndex])) {
            throw new ConfigurationException(__('Current type is not set.'));
        }

        // Validate conditions
        foreach ($conditions as $id => $name) {
            if (!array_key_exists($id, $this->defaultConditions)) {
                throw new ConfigurationException(__('Invalid condition ID: %s', $id));
            }
        }

        // Reference the current type's metrics
        $metrics = &$this->criteria['types'][$this->currentTypeIndex]['metrics'];

        // Find the current metric and handle conditions
        foreach ($metrics as &$metric) {
            if ($metric['id'] === $this->currentMetric['id']) {
                if ($metric['isUsingDefaultConditions']) {
                    // If metric is using default conditions, replace with new ones
                    $metric['conditions'] = $this->formatConditions($conditions);
                    $metric['isUsingDefaultConditions'] = false;
                } else {
                    // Merge the new conditions with existing ones, avoiding duplicates
                    $existingConditions = $metric['conditions'];
                    $newConditions = $this->formatConditions($conditions);

                    // Combine the two condition arrays
                    $mergedConditions = array_merge($existingConditions, $newConditions);

                    // Remove duplicates
                    $finalConditions = array_unique($mergedConditions, SORT_REGULAR);

                    $metric['conditions'] = array_values($finalConditions);
                }

                break;
            }
        }

        return $this;
    }

    /**
     * Format conditions from key-value to the required array structure.
     *
     * @param array $conditions
     * @return array
     */
    private function formatConditions(array $conditions): array
    {
        $formattedConditions = [];
        foreach ($conditions as $id => $name) {
            $formattedConditions[] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        return $formattedConditions;
    }

    /**
     * @inheritDoc
     */
    public function addValues(string $inputType, array $values): self
    {
        // Ensure current type is set
        if (!isset($this->criteria['types'][$this->currentTypeIndex])) {
            throw new ConfigurationException(__('Current type is not set.'));
        }

        // Restrict input types to 'dropdown', 'number', 'text' and 'date'
        $allowedInputTypes = ['dropdown', 'number', 'text', 'date'];
        if (!in_array($inputType, $allowedInputTypes)) {
            throw new ConfigurationException(__('Invalid input type.'));
        }

        // Reference the metrics of the current type
        $metrics = &$this->criteria['types'][$this->currentTypeIndex]['metrics'];

        // Find the current metric and add or update values
        foreach ($metrics as &$metric) {
            if ($metric['id'] === $this->currentMetric['id']) {
                // Check if the input type matches the existing one (if any)
                if (isset($metric['values']['inputType']) && $metric['values']['inputType'] !== $inputType) {
                    throw new ConfigurationException(__('Input type does not match.'));
                }

                // Format the new values
                $formattedValues = [];
                foreach ($values as $id => $title) {
                    $formattedValues[] = [
                        'id' => $id,
                        'title' => $title
                    ];
                }

                // Merge new values with existing ones, avoiding duplicates
                $existingValues = $metric['values']['values'] ?? [];

                // Combine the two value arrays
                $mergedValues = array_merge($existingValues, $formattedValues);

                // Remove duplicates
                $uniqueFormattedValues = array_unique($mergedValues, SORT_REGULAR);

                // Update the metric's values
                $metric['values'] = [
                    'inputType' => $inputType,
                    'values' => array_values($uniqueFormattedValues)
                ];

                break;
            }
        }

        return $this;
    }

    /**
     * Get the criteria array.
     *
     * @return array
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    /**
     * Get the default conditions array.
     *
     * @return array
     */
    public function getCriteriaDefaultCondition(): array
    {
        return $this->formatConditions($this->defaultConditions);
    }
}
