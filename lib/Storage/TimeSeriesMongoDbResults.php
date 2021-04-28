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
 * Class TimeSeriesMongoDbResults
 * @package Xibo\Storage
 */
class TimeSeriesMongoDbResults implements TimeSeriesResultsInterface
{
    /**
     * Statement
     * @var \MongoDB\Driver\Cursor
     */
    private $object;

    /**
     * Total number of stats
     */
    public $totalCount;

    /**
     * Iterator
     * @var \IteratorIterator
     */
    private $iterator;

    /**
     * @inheritdoc
     */
    public function __construct($cursor = null)
    {
        $this->object = $cursor;
    }

    /** @inheritdoc */
    public function getArray()
    {
        $this->object->setTypeMap(['root' => 'array']);
        return $this->object->toArray();
    }

    /** @inheritDoc */
    public function getIdFromRow($row)
    {
        return (string)$row['id'];
    }

    /** @inheritDoc */
    public function getDateFromValue($value)
    {
        return Carbon::instance($value->toDateTime());
    }

    /** @inheritDoc */
    public function getEngagementsFromRow($row, $decoded = true)
    {
        if ($decoded) {
            return $row['engagements'] ?? [];
        } else {
            return isset($row['engagements']) ? json_encode($row['engagements']) : '[]';
        }
    }

    /** @inheritDoc */
    public function getTagFilterFromRow($row)
    {
        return $row['tagFilter'] ?? [
                'dg' => [],
                'layout' => [],
                'media' => []
            ];
    }

    /**
     * Gets an iterator for this result set
     * @return \IteratorIterator
     */
    private function getIterator()
    {
        if ($this->iterator == null) {
            $this->iterator = new \IteratorIterator($this->object);
            $this->iterator->rewind();
        }

        return $this->iterator;
    }

    /** @inheritdoc */
    public function getNextRow()
    {

        $this->getIterator();

        if ($this->iterator->valid()) {

            $document = $this->iterator->current();
            $this->iterator->next();

            return  (array) $document;
        }

        return false;

    }

    /** @inheritdoc */
    public function getTotalCount()
    {
        return $this->totalCount;
    }
}