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
    private $criteria = [];
    private $currentTypeIndex = null;
    private $currentMetric = null;
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
        // Initialize the type in the criteria array
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
        $metric = [
            'id' => $id,
            'name' => $name,
            'conditions' => $this->formatConditions($this->defaultConditions),
            'values' => null
        ];

        // Add the metric to the current type
        if (isset($this->criteria['types'][$this->currentTypeIndex])) {
            $this->criteria['types'][$this->currentTypeIndex]['metrics'][] = $metric;
            $this->currentMetric = $metric;
        } else {
            throw new ConfigurationException(__('Current type is not set.'));
        }

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function addCondition(array $conditions): self
    {
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

        // Assign conditions to the current metric
        foreach ($this->criteria['types'][$this->currentTypeIndex]['metrics'] as &$metric) {
            if ($metric['name'] === $this->currentMetric['name']) {
                $metric['conditions'] = $this->formatConditions($conditions);
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
        // Restrict input types to 'dropdown', 'number', 'text' and 'date'
        $allowedInputTypes = ['dropdown', 'number', 'text', 'date'];
        if (!in_array($inputType, $allowedInputTypes)) {
            throw new ConfigurationException(__('Invalid input type.'));
        }

        // Add values to the current metric
        if (isset($this->criteria['types'][$this->currentTypeIndex])) {
            foreach ($this->criteria['types'][$this->currentTypeIndex]['metrics'] as &$metric) {
                // check if the current metric matches the metric from the current iteration
                if ($metric['name'] === $this->currentMetric['name']) {
                    // format the values to separate id and title
                    $formattedValues = [];
                    foreach ($values as $id => $title) {
                        $formattedValues[] = [
                            'id' => $id,
                            'title' => $title
                        ];
                    }

                    $metric['values'] = [
                        'inputType' => $inputType,
                        'values' => $formattedValues
                    ];
                }
            }
        } else {
            throw new ConfigurationException(__('Current type is not set.'));
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
