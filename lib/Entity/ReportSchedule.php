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

namespace Xibo\Entity;
use OpenApi\Attributes as OA;
use Respect\Validation\Validator as v;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class ReportSchedule
 * @package Xibo\Entity
 */
#[OA\Schema]
class ReportSchedule implements \JsonSerializable
{
    use EntityTrait;

    public static $SCHEDULE_DAILY   = '0 0 * * *';
    public static $SCHEDULE_WEEKLY  = '0 0 * * 1';
    public static $SCHEDULE_MONTHLY = '0 0 1 * *';
    public static $SCHEDULE_YEARLY  = '0 0 1 1 *';

    public $reportScheduleId;
    public $lastSavedReportId;
    public $name;
    public $reportName;
    public $filterCriteria;
    public $schedule;
    public $lastRunDt = 0;
    public $previousRunDt;
    public $createdDt;
    public $isActive = 1;
    public $fromDt = 0;
    public $toDt = 0;

    public $message;

    /**
     * @var string
     */
    #[OA\Property(description: 'The username of the User that owns this report schedule')]
    public $owner;

    /**
     * @var int
     */
    #[OA\Property(description: 'The ID of the User that owns this report schedule')]
    public $userId;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Save
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->reportScheduleId == null) {
            $this->add();
            $this->getLog()->debug('Adding report schedule');
        }
        else
        {
            $this->edit();
            $this->getLog()->debug('Editing a report schedule');
        }
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->name))
            throw new InvalidArgumentException(__('Missing name'), 'name');
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `reportschedule` WHERE `reportScheduleId` = :reportScheduleId', ['reportScheduleId' => $this->reportScheduleId]);
    }

    private function add()
    {
        $this->reportScheduleId = $this->getStore()->insert('
            INSERT INTO `reportschedule` (`name`, `lastSavedReportId`, `reportName`, `schedule`, `lastRunDt`, `previousRunDt`, `filterCriteria`, `userId`, `isActive`, `fromDt`, `toDt`, `message`, `createdDt`) VALUES
                                         (:name,  :lastSavedReportId,  :reportName,  :schedule,  :lastRunDt,  :previousRunDt,  :filterCriteria,  :userId,  :isActive,  :fromDt,  :toDt,  :message,  :createdDt)
        ', [
            'name' => $this->name,
            'lastSavedReportId' => $this->lastSavedReportId,
            'reportName' => $this->reportName,
            'schedule' => $this->schedule,
            'lastRunDt' => $this->lastRunDt,
            'previousRunDt' => $this->previousRunDt,
            'filterCriteria' => $this->filterCriteria,
            'userId' => $this->userId,
            'isActive' => $this->isActive,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'message' => $this->message,
            'createdDt' => $this->createdDt,
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $this->getStore()->update('
          UPDATE `reportschedule`
            SET `name` = :name,
            `lastSavedReportId` = :lastSavedReportId,
            `reportName` = :reportName,
            `schedule` = :schedule,
            `lastRunDt` = :lastRunDt,
            `previousRunDt` = :previousRunDt,
            `filterCriteria` = :filterCriteria,
            `userId` = :userId,
            `isActive` = :isActive,
            `fromDt` = :fromDt,
            `toDt` = :toDt,
            `message` = :message,
            `createdDt` = :createdDt            
           WHERE reportScheduleId = :reportScheduleId', [
            'reportScheduleId' => $this->reportScheduleId,
            'lastSavedReportId' => $this->lastSavedReportId,
            'name' => $this->name,
            'reportName' => $this->reportName,
            'schedule' => $this->schedule,
            'lastRunDt' => $this->lastRunDt,
            'previousRunDt' => $this->previousRunDt,
            'filterCriteria' => $this->filterCriteria,
            'userId' => $this->userId,
            'isActive' => $this->isActive,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'message' => $this->message,
            'createdDt' => $this->createdDt
        ]);
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->reportScheduleId;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Returns the last saved report id
     * @return integer
     */
    public function getLastSavedReportId()
    {
        return $this->lastSavedReportId;
    }
}