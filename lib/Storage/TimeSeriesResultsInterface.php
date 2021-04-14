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
 * Interface TimeSeriesResultsInterface
 * @package Xibo\Service
 */
interface TimeSeriesResultsInterface
{
    /**
     * Time series results constructor
     * @param null $object
     */
    public function __construct($object = null);

    /**
     * Get statistics array
     * @return array
     */
    public function getArray();

    /**
     * Get next row
     * @return array|false
     */
    public function getNextRow();

    /**
     * Get total number of stats
     * @return integer
     */
    public function getTotalCount();

    /**
     * @param $row
     * @return string|int
     */
    public function getIdFromRow($row);

    /**
     * @param $row
     * @param bool $decoded Should the engagements be decoded or strings?
     * @return array
     */
    public function getEngagementsFromRow($row, $decoded = true);

    /**
     * @param $row
     * @return array
     */
    public function getTagFilterFromRow($row);

    /**
     * @param string $value
     * @return \Carbon\Carbon
     */
    public function getDateFromValue($value);

}