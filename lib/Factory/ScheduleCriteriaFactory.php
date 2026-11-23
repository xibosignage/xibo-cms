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

use Xibo\Entity\ScheduleCriteria;

/**
 * Factory to return schedule criteria entities
 */
class ScheduleCriteriaFactory extends BaseFactory
{
    public function createEmpty(): ScheduleCriteria
    {
        return new ScheduleCriteria($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get all criteria for an event
     * @param int $eventId
     * @return ScheduleCriteria[]
     */
    public function getByEventId(int $eventId): array
    {
        $entries = [];

        foreach ($this->getStore()->select('SELECT * FROM `schedule_criteria` WHERE `eventId` = :eventId', [
            'eventId' => $eventId,
        ]) as $row) {
            // Create and hydrate
            $entries[] = $this->createEmpty()->hydrate($row);
        }
        return $entries;
    }
}
