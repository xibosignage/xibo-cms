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

namespace Xibo\Factory;

use Xibo\Entity\ScheduleExclusion;

/**
 * Class ScheduleExclusionFactory
 * @package Xibo\Factory
 */
class ScheduleExclusionFactory extends BaseFactory
{
    /**
     * Load by Event Id
     * @param int $eventId
     * @return array[ScheduleExclusion]
     */
    public function getByEventId($eventId)
    {
        return $this->query(null, array('eventId' => $eventId));
    }

    /**
     * Create Empty
     * @return ScheduleExclusion
     */
    public function createEmpty()
    {
        return new ScheduleExclusion($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Create a schedule exclusion
     * @param int $eventId
     * @param int $fromDt
     * @param int $toDt
     * @return ScheduleExclusion
     */
    public function create($eventId, $fromDt, $toDt)
    {
        $scheduleExclusion = $this->createEmpty();
        $scheduleExclusion->eventId = $eventId;
        $scheduleExclusion->fromDt = $fromDt;
        $scheduleExclusion->toDt = $toDt;

        return $scheduleExclusion;
    }

    /**
     * Query Schedule exclusions
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[ScheduleExclusion]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $sql = 'SELECT * FROM `scheduleexclusions` WHERE eventId = :eventId';

        foreach ($this->getStore()->select($sql, array('eventId' => $sanitizedFilter->getInt('eventId'))) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}