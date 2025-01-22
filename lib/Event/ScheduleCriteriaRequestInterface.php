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
 * Interface for managing schedule criteria types, metrics, conditions, and values.
 *
 * Allows the addition of types, metrics, conditions, and values in a chained manner:
 * - Start with `addType()` to add a new type. Call `addType()` multiple times to add multiple types.
 * - Follow with `addMetric()` to add metrics under the specified type. Call `addMetric()` multiple times to add multiple
 * metrics to the current type.
 * - Optionally, call `addCondition()` after `addMetric()` to specify a set of conditions for the metric. If not called,
 * the system will automatically apply default conditions, which include all supported conditions.
 * - Conclude with `addValues()` immediately after `addMetric()` or `addCondition()` to specify a set of values for the metric. Each metric can
 * have one set of values.
 *
 * The added criteria are then parsed and displayed in the Schedule Criteria Form, enabling users to configure
 * scheduling conditions based on the specified parameters.
 */
interface ScheduleCriteriaRequestInterface
{
    /**
     * Add a new type to the criteria.
     *
     * @param string $id Unique identifier for the type.
     * @param string $type Name of the type.
     * @return self
     */
    public function addType(string $id, string $type): self;

    /**
     * Add a new metric to the current type.
     *
     * @param string $id Unique identifier for the metric.
     * @param string $name Name of the metric.
     * @return self
     * @throws ConfigurationException If the current type is not set.
     */
    public function addMetric(string $id, string $name): self;

    /**
     * Add conditions to the current metric.
     *
     * This method allows you to specify conditions for the currently added metric.
     * The list of accepted conditions includes:
     * - 'set' => 'Is set'
     * - 'lt' => 'Less than'
     * - 'lte' => 'Less than or equal to'
     * - 'eq' => 'Equal to'
     * - 'neq' => 'Not equal to'
     * - 'gte' => 'Greater than or equal to'
     * - 'gt' => 'Greater than'
     * - 'contains' => 'Contains'
     * - 'ncontains' => 'Not contains'
     *
     * **Important Notes:**
     * - The `addMetric` method **must** be called before using `addCondition`.
     *   If no metric is currently set, this method will throw a `ConfigurationException`.
     * - If this method is **not called** for a metric, the system will automatically
     *   provide the default conditions, which include **all the accepted conditions** listed above.
     *
     * Example usage:
     * ```
     * $event->addMetric('temp', 'Temperature')
     *        ->addCondition([
     *            'eq' => 'Equal to',
     *            'gt' => 'Greater than',
     *        ]);
     * ```
     *
     * @param array $conditions An associative array of conditions, where the key is the condition ID and the value is its name.
     * @return $this
     * @throws ConfigurationException If the current metric is not set.
     */
    public function addCondition(array $conditions): self;

    /**
     * Add values to the current metric. The input type must be either "dropdown", "string", "date", or "number".
     *
     * The values array should be formatted such that the index is the id and the value is the title/name of the value.
     * For "dropdown" input type, provide an array of values. For other input types ("string", "date", "number"),
     * the values array should be empty "[]".
     *
     * Example values for "dropdown":
     * [
     *     'id1' => 'Value 1',
     *     'id2' => 'Value 2'
     * ]
     *
     * @param string $inputType Type of input for the values ("dropdown", "string", "date", "number").
     * @param array $values Array of values to be associated with the metric, where the index is the id and the value is
     * the title.
     * @return self
     * @throws ConfigurationException If the current type or metric is not set.
     */
    public function addValues(string $inputType, array $values): self;
}
