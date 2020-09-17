<?php
/*
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Slim\Http\ServerRequest as Request;

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
     * @param \Slim\Http\ServerRequest|null $request
     * @return array
     */
    protected function gridRenderFilter(array $extraFilter, Request $request = null)
    {
        if ($request === null) {
            return $extraFilter;
        }

        $parsedFilter = $this->getSanitizer($request->getParams());
        // Handle filtering
        $filter = [
            'start' => $parsedFilter->getInt('start', ['default' => 0]),
            'length' => $parsedFilter->getInt('length', ['default' => 10])
        ];

        $search = $request->getParam('search', array());
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
     * @param Request|array $request
     * @return array
     */
    protected function gridRenderSort($request)
    {
        if ($request instanceof Request) {
            $columns = $request->getParam('columns');
            $order = $request->getParam('order');
        } else {
            $columns = $request['columns'] ?? null;
            $order = $request['order'] ?? null;
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
            return ((isset($columns[$element['column']]['name']) && $columns[$element['column']]['name'] != '')
                    ? '`' . $columns[$element['column']]['name'] . '`'
                    : '`' . $columns[$element['column']]['data'] . '`')
                . (($element['dir'] == 'desc') ? ' DESC' : '');
        }, $order);
    }
}