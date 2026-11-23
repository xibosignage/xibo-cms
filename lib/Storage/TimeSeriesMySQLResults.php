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

namespace Xibo\Storage;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;

/**
 * Class TimeSeriesMySQLResults
 * @package Xibo\Storage
 */
class TimeSeriesMySQLResults implements TimeSeriesResultsInterface
{
    /**
     * Statement
     * @var \PDOStatement
     */
    private $object;

    /**
     * Total number of stats
     */
    public $totalCount;

    /**
     * @inheritdoc
     */
    public function __construct($stmtObject = null)
    {
        $this->object = $stmtObject;
    }

    /**
     * @inheritdoc
     */
    public function getArray()
    {
        return $this->object->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @inheritDoc */
    public function getIdFromRow($row)
    {
        return $row['statId'];
    }

    /** @inheritDoc */
    public function getDateFromValue($value)
    {
        return DateFormatHelper::createFromTimestamp($value);
    }

    /** @inheritDoc */
    public function getEngagementsFromRow($row, $decoded = true)
    {
        if ($decoded) {
            return isset($row['engagements']) ? json_decode($row['engagements']) : [];
        } else {
            return $row['engagements'] ?? '[]';
        }
    }

    /** @inheritDoc */
    public function getTagFilterFromRow($row)
    {
        // Tags
        // Mongo uses object structure for this data, so we need to mimic the structure
        $entry = (object)[
            'dg' => [],
            'layout' => [],
            'media' => []
        ];

        // Display Tags
        if (array_key_exists('displayTags', $row) && !empty($row['displayTags'])) {
            $tags = explode(',', $row['displayTags']);
            foreach ($tags as $tag) {
                $tag = explode('|', $tag);
                $value = $tag[1] ?? null;
                $entry->dg[] = [
                    'tag' => $tag[0],
                    'value' => ($value === 'null') ? null : $value
                ];
            }
        }

        // Layout Tags
        if (array_key_exists('layoutTags', $row) && !empty($row['layoutTags'])) {
            $tags = explode(',', $row['layoutTags']);
            foreach ($tags as $tag) {
                $tag = explode('|', $tag);
                $value = $tag[1] ?? null;
                $entry->layout[] = [
                    'tag' => $tag[0],
                    'value' => ($value === 'null') ? null : $value
                ];
            }
        }

        // Media Tags
        if (array_key_exists('mediaTags', $row) && !empty($row['mediaTags'])) {
            $tags = explode(',', $row['mediaTags']);
            foreach ($tags as $tag) {
                $tag = explode('|', $tag);
                $value = $tag[1] ?? null;
                $entry->media[] = [
                    'tag' => $tag[0],
                    'value' => ($value === 'null') ? null : $value
                ];
            }
        }

        return $entry;
    }

    /**
     * @inheritdoc
     */
    public function getNextRow()
    {
        return $this->object->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @inheritdoc
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }
}