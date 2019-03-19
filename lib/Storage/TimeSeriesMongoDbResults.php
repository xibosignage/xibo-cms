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
        $result = $this->object->toArray();

        $rows = [];

        foreach ($result as $row) {

            $entry = [];

            $entry['type'] = $row['type'];
            $entry['start'] = $row['start'];
            $entry['end'] = $row['end'];
            $entry['display'] = isset($row['display']) ? $row['display']: 'No display';
            $entry['layout'] = isset($row['layout']) ? $row['layout']: 'No layout';
            $entry['media'] = isset($row['media']) ? $row['media'] : 'No media' ;
            $entry['tag'] = $row['tag'];
            $entry['displayId'] = $row['displayId'];
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];

            $rows[] = $entry;
        }

        return ['statData'=> $rows];

    }

    public function getIterator()
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
}