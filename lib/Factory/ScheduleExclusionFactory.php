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

namespace Xibo\Factory;

use Xibo\Entity\ScheduleExclusion;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ScheduleExclusionFactory
 * @package Xibo\Factory
 */
class ScheduleExclusionFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

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
        return new ScheduleExclusion($this->getStore(), $this->getLog());
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
        $entries = array();

        $sql = 'SELECT * FROM `scheduleexclusions` WHERE eventId = :eventId';

        foreach ($this->getStore()->select($sql, array('eventId' => $this->getSanitizer()->getInt('eventId', $filterBy))) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}