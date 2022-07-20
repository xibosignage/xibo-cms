<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Trait DataTablesDotNetTrait
 * @package Xibo\Controller
 *
 * Methods which implement the particular sorting/filtering requirements of DataTables.Net
 */
trait DataTablesDotNetTrait
{
    /**
     * Set the filter
     * @param array $extraFilter
     * @param SanitizerInterface|null $sanitizedRequestParams
     * @return array
     */
    protected function gridRenderFilter(array $extraFilter, $sanitizedRequestParams = null)
    {
        if ($sanitizedRequestParams === null) {
            return $extraFilter;
        }

        // Handle filtering
        $filter = [
            'start' => $sanitizedRequestParams->getInt('start', ['default' => 0]),
            'length' => $sanitizedRequestParams->getInt('length', ['default' => 10])
        ];

        $search = $sanitizedRequestParams->getArray('search', ['default' => []]);
        if (is_array($search) && isset($search['value'])) {
            $filter['search'] = $search['value'];
        } else if ($search != '') {
            $filter['search'] = $search;
        }

        // Merge with any extra filter items that have been provided
        $filter = array_merge($extraFilter, $filter);

        return $filter;
    }

    /**
     * Set the sort order
     * @param SanitizerInterface|array $sanitizedRequestParams
     * @return array
     */
    protected function gridRenderSort($sanitizedRequestParams)
    {
        if ($sanitizedRequestParams instanceof SanitizerInterface) {
            $columns = $sanitizedRequestParams->getArray('columns');
            $order = $sanitizedRequestParams->getArray('order');
        } else {
            $columns = $sanitizedRequestParams['columns'] ?? null;
            $order = $sanitizedRequestParams['order'] ?? null;
        }

        if ($columns === null
            || !is_array($columns)
            || count($columns) <= 0
            || $order === null
            || !is_array($order)
            || count($order) <= 0
        ) {
            return null;
        }

        return array_map(function ($element) use ($columns) {
            $val = (isset($columns[$element['column']]['name']) && $columns[$element['column']]['name'] != '')
                ? $columns[$element['column']]['name']
                : $columns[$element['column']]['data'];
            $val = preg_replace('/[^A-Za-z0-9_]/', '', $val);
            return '`' . $val . '`' . (($element['dir'] == 'desc') ? ' DESC' : '');
        }, $order);
    }
}