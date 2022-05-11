<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


namespace Xibo\Entity;
use Carbon\Carbon;
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

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function save()
    {
        if ($this->displayEventId == null)
            $this->add();
        else
            $this->edit();
    }

    private function add()
    {
        $this->displayEventId = $this->getStore()->insert('
            INSERT INTO `displayevent` (eventDate, start, end, displayID)
              VALUES (:eventDate, :start, :end, :displayId)
        ', [
            'eventDate' => Carbon::now()->format('U'),
            'start' => $this->start,
            'end' => $this->end,
            'displayId' => $this->displayId
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `displayevent` SET `end` = :end WHERE statId = :statId', [
            'displayevent' => $this->displayEventId, 'end' => $this->end
        ]);
    }

    /**
     * Record the display coming online
     * @param $displayId
     */
    public function displayUp($displayId)
    {
        $this->getStore()->update('UPDATE `displayevent` SET `end` = :toDt WHERE displayId = :displayId AND `end` IS NULL', [
            'toDt' => Carbon::now()->format('U'),
            'displayId' => $displayId
        ]);
    }
}