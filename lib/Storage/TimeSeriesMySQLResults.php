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
        $rows = [];

        while ($row = $this->object->fetch(\PDO::FETCH_ASSOC)) {

            $entry = [];

            // Read the columns
            $entry['id'] = $row['statId'];
            $entry['type'] = $row['type'];
            $entry['start'] = $row['start'];
            $entry['end'] = $row['end'];
            $entry['layout'] = $row['layout'];
            $entry['display'] = $row['display'];
            $entry['media'] = $row['media'];
            $entry['tag'] = $row['tag'];
            $entry['duration'] = $row['duration'];
            $entry['count'] = $row['count'];
            $entry['displayId'] = $row['displayId'];
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];
            $entry['statDate'] = $row['statDate'];
            $entry['engagements'] = isset($row['engagements']) ? json_decode($row['engagements']) : [];

            $rows[] = $entry;
        }

        return ['statData'=> $rows];

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