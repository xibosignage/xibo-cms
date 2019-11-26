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
namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class ScheduleExclusion
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class ScheduleExclusion implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="Excluded Schedule ID")
     * @var int
     */
    public $scheduleExclusionId;

    /**
     * @SWG\Property(description="The eventId that this Excluded Schedule applies to")
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the from date of an excluded recurring event in CMS time."
     * )
     * @var int
     */
    public $fromDt;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the to date of an excluded recurring event in CMS time."
     * )
     * @var int
     */
    public $toDt;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function save()
    {
        $this->getStore()->insert('INSERT INTO `scheduleexclusions` (`eventId`, `fromDt`, `toDt`) VALUES (:eventId, :fromDt, :toDt)', [
            'eventId' => $this->eventId,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
        ]);
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `scheduleexclusions` WHERE `scheduleExclusionId` = :scheduleExclusionId', [
            'scheduleExclusionId' => $this->scheduleExclusionId
        ]);
    }
}