<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Entity;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayEvent
 * @package Xibo\Entity
 */
class DisplayEvent implements \JsonSerializable
{
    use EntityTrait;

    public $displayEventId;
    public $displayId;
    public $eventDate;
    public $start;
    public $end;
    public $eventTypeId;
    public $refId;
    public $detail;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Save displayevent
     * @return void
     */
    public function save(): void
    {
        if ($this->displayEventId == null) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    /**
     * Add a new displayevent
     * @return void
     */
    private function add(): void
    {
        $this->displayEventId = $this->getStore()->insert('
            INSERT INTO `displayevent` (eventDate, start, end, displayID, eventTypeId, refId, detail)
              VALUES (:eventDate, :start, :end, :displayId, :eventTypeId, :refId, :detail)
        ', [
            'eventDate' => Carbon::now()->format('U'),
            'start' => $this->start,
            'end' => $this->end,
            'displayId' => $this->displayId,
            'eventTypeId' => $this->eventTypeId,
            'refId' => $this->refId,
            'detail' => $this->detail,
        ]);
    }

    /**
     * Edit displayevent
     * @return void
     */
    private function edit(): void
    {
        $this->getStore()->update('
          UPDATE displayevent 
          SET end = :end,
            displayId = :displayId,
            eventTypeId = :eventTypeId,
            refId = :refId,
            detail = :detail
          WHERE displayEventId = :displayEventId
        ', [
            'displayEventId' => $this->displayEventId,
            'end' => $this->end,
            'displayId' => $this->displayId,
            'eventTypeId' => $this->eventTypeId,
            'refId' => $this->refId,
            'detail' => $this->detail,
        ]);
    }


    /**
     * Record end date for specified display and event type.
     * @param int $displayId
     * @param int|null $date
     * @param int $eventTypeId
     * @return void
     */
    public function eventEnd(int $displayId, int $eventTypeId = 1, ?int $date = null): void
    {
        $this->getLog()->debug(
            sprintf(
                'displayEvent : end display alert for eventType %s and displayId %d',
                $this->getEventNameFromId($eventTypeId),
                $displayId
            )
        );

        $this->getStore()->update(
            'UPDATE `displayevent` SET `end` = :toDt 
                      WHERE displayId = :displayId 
                        AND `end` IS NULL 
                        AND eventTypeId = :eventTypeId',
            [
                'toDt' => $date ?? Carbon::now()->format('U'),
                'displayId' => $displayId,
                'eventTypeId' => $eventTypeId,
            ]
        );
    }

    /**
     * Record end date for specified display, event type and refId
     * @param int $displayId
     * @param int $eventTypeId
     * @param int $refId
     * @param int|null $date
     * @return void
     */
    public function eventEndByReference(int $displayId, int $eventTypeId, int $refId, string $detail = null, ?int $date = null): void
    {
        $this->getLog()->debug(
            sprintf(
                'displayEvent : end display alert for refId %d, displayId %d and eventType %s',
                $refId,
                $displayId,
                $this->getEventNameFromId($eventTypeId),
            )
        );

        // When updating the event end, concatenate the end message to the current message
        $this->getStore()->update(
            'UPDATE `displayevent` SET 
                      `end` = :toDt, 
                      `detail` = CONCAT_WS(". ", NULLIF(CONCAT_WS(".", NULLIF(`detail`, "")), ""), :detail)
                      WHERE displayId = :displayId 
                        AND `end` IS NULL 
                        AND eventTypeId = :eventTypeId
                        AND refId = :refId',
            [
                'toDt' => $date ?? Carbon::now()->format('U'),
                'displayId' => $displayId,
                'eventTypeId' => $eventTypeId,
                'refId' => $refId,
                'detail' => $detail,
            ]
        );
    }

    /**
     * Match event type string from log to eventTypeId in database.
     * @param string $eventType
     * @return int
     */
    public function getEventIdFromString(string $eventType): int
    {
        return match ($eventType) {
            'Display Up/down' => 1,
            'App Start' => 2,
            'Power Cycle' => 3,
            'Network Cycle' => 4,
            'TV Monitoring' => 5,
            'Player Fault' => 6,
            'Command' => 7,
            default => 8
        };
    }

    /**
     * Match eventTypeId from database to string event name.
     * @param int $eventTypeId
     * @return string
     */
    public function getEventNameFromId(int $eventTypeId): string
    {
        return match ($eventTypeId) {
            1 => __('Display Up/down'),
            2 => __('App Start'),
            3 => __('Power Cycle'),
            4 => __('Network Cycle'),
            5 => __('TV Monitoring'),
            6 => __('Player Fault'),
            7 => __('Command'),
            default => __('Other')
        };
    }
}
