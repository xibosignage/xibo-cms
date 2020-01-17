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
use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ReportSchedule
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
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
    public $message;

    /**
     * @SWG\Property(description="The username of the User that owns this report schedule")
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(description="The ID of the User that owns this report schedule")
     * @var int
     */
    public $userId;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Save
     * @param array $options
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
            INSERT INTO `reportschedule` (`name`, `lastSavedReportId`, `reportName`, `schedule`, `lastRunDt`, `previousRunDt`, `filterCriteria`, `userId`, `isActive`, `message`, `createdDt`) VALUES
                                         (:name,  :lastSavedReportId,  :reportName,  :schedule,  :lastRunDt,  :previousRunDt,  :filterCriteria,  :userId,  :isActive,  :message,  :createdDt)
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