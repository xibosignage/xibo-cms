<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Storage;

use Carbon\Carbon;

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
        return Carbon::createFromTimestamp($value);
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
        // Mimic the structure we have in Mongo.
        $entry['tagFilter'] = [
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
                $entry['tagFilter']['dg'][] = [
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
                $entry['tagFilter']['layout'][] = [
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
                $entry['tagFilter']['media'][] = [
                    'tag' => $tag[0],
                    'value' => ($value === 'null') ? null : $value
                ];
            }
        }
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