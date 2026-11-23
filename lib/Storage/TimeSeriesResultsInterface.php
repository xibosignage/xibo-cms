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