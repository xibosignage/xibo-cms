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

namespace Xibo\Entity;

/**
 * Class ReportResult
 * @package Xibo\Entity
 *
 */
class ReportResult
{
    /**
     * Number of total records
     * @var int
     */
    public $recordsTotal;

    /**
     * Is it a chart report?
     * @var bool|false
     */
    public $hasChartData;

    /**
     * Chart data points
     * @var array|null
     */
    public $chart;

    /**
     * Metadata that is used in the report preview or in the email template
     * @var array
     */
    public $metadata;

    /**
     * Datatable Records
     * @var array
     */
    public $table;

    /**
     * ReportResult constructor.
     * @param array $metadata
     * @param array $table
     * @param int $recordsTotal
     * @param null|array $chart
     * @param bool|false $hasChartData
     */
    public function __construct(
        array $metadata = [],
        array $table = [],
        $recordsTotal = 0,
        $chart = null,
        $hasChartData = false
    ) {
        $this->metadata = $metadata;
        $this->table = $table;
        $this->chart = $chart;
        $this->hasChartData = $hasChartData;
        $this->recordsTotal = $recordsTotal;

        return $this;
    }

    public function getMetaData(): array
    {
        return $this->metadata;
    }

    public function getRows(): array
    {
        return $this->table;
    }

    public function countLast(): int
    {
        return $this->recordsTotal;
    }
}
