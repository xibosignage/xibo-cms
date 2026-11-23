<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Entity;

/**
 * Class ReportResult
 * @package Xibo\Entity
 *
 */
class ReportResult implements \JsonSerializable
{
    /**
     * Number of total records
     * @var int
     */
    public $recordsTotal;

    /**
     * Chart data points
     * @var array|null
     */
    public $chart;

    /**
     * Error message
     * @var null|string
     */
    public $error;

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
     * @param array $chart
     * @param null|string $error
     */
    public function __construct(
        array $metadata = [],
        array $table = [],
        int   $recordsTotal = 0,
        array $chart = [],
        ?string $error = null
    ) {
        $this->metadata = $metadata;
        $this->table = $table;
        $this->recordsTotal = $recordsTotal;
        $this->chart = $chart;
        $this->error = $error;

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

    public function jsonSerialize(): array
    {
        return [
            'metadata' => $this->metadata,
            'table' => $this->table,
            'recordsTotal' => $this->recordsTotal,
            'chart' => $this->chart,
            'error' => $this->error
        ];
    }
}
