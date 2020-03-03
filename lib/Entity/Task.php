<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
use Cron\CronExpression;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Task
 * @package Xibo\XTR
 */
class Task implements \JsonSerializable
{
    use EntityTrait;

    public static $STATUS_RUNNING = 1;
    public static $STATUS_IDLE = 2;
    public static $STATUS_ERROR = 3;
    public static $STATUS_SUCCESS = 4;
    public static $STATUS_TIMEOUT = 5;

    public $taskId;
    public $name;
    public $configFile;
    public $class;
    public $status;
    public $pid = 0;
    public $options = [];
    public $schedule;
    public $lastRunDt = 0;
    public $lastRunStartDt;
    public $lastRunMessage;
    public $lastRunStatus;
    public $lastRunDuration = 0;
    public $lastRunExitCode = 0;
    public $isActive;
    public $runNow;

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
     * @return \DateTime|string
     * @throws \Exception
     */
    public function nextRunDate()
    {
        try {
            $cron = CronExpression::factory($this->schedule);

            if ($this->lastRunDt == 0)
                return (new \DateTime())->format('U');

            return $cron->getNextRunDate(\DateTime::createFromFormat('U', $this->lastRunDt))->format('U');
        } catch (\RuntimeException $e) {
            $this->getLog()->error('Invalid CRON expression for TaskId ' . $this->taskId);

            $this->status = self::$STATUS_ERROR;
            return (new \DateTime())->add(new \DateInterval('P1Y'))->format('U');
        }
    }

    /**
     * Set class and options
     * @throws NotFoundException
     */
    public function setClassAndOptions()
    {
        if ($this->configFile == null)
            throw new NotFoundException(__('No config file recorded for task. Please recreate.'));

        // Get the class and default set of options from the config file.
        if (!file_exists(PROJECT_ROOT . $this->configFile))
            throw new NotFoundException(__('Config file not found for Task'));

        $config = json_decode(file_get_contents(PROJECT_ROOT . $this->configFile), true);
        $this->class = $config['class'];
        $this->options = array_merge($config['options'], $this->options);
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    private function validate()
    {
        // Test the CRON expression
        if (empty($this->schedule))
            throw new InvalidArgumentException(__('Please enter a CRON expression in the Schedule'), 'schedule');

        try {
            $cron = CronExpression::factory($this->schedule);
            $cron->getNextRunDate();
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException(__('Invalid CRON expression in the Schedule'), 'schedule');
        }
    }

    /**
     * Save
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'connection' => 'default',
            'reconnect' => false
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->taskId == null)
            $this->add();
        else {
            // If we've transitioned from active to inactive, then reset the task status
            if ($this->getOriginalValue('isActive') != $this->isActive)
                $this->status = Task::$STATUS_IDLE;

            $this->edit($options);
        }
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `task` WHERE `taskId` = :taskId', ['taskId' => $this->taskId]);
    }

    private function add()
    {
        $this->taskId = $this->getStore()->insert('
            INSERT INTO `task` (`name`, `status`, `configFile`, `class`, `pid`, `options`, `schedule`, 
              `lastRunDt`, `lastRunMessage`, `lastRunStatus`, `lastRunDuration`, `lastRunExitCode`,
              `isActive`, `runNow`) VALUES
             (:name, :status, :configFile, :class, :pid, :options, :schedule, 
              :lastRunDt, :lastRunMessage, :lastRunStatus, :lastRunDuration, :lastRunExitCode,
              :isActive, :runNow)
        ', [
            'name' => $this->name,
            'status' => $this->status,
            'pid' => $this->pid,
            'configFile' => $this->configFile,
            'class' => $this->class,
            'options' => json_encode($this->options),
            'schedule' => $this->schedule,
            'lastRunDt' => $this->lastRunDt,
            'lastRunMessage' => $this->lastRunMessage,
            'lastRunStatus' => $this->lastRunStatus,
            'lastRunDuration' => $this->lastRunDuration,
            'lastRunExitCode' => $this->lastRunExitCode,
            'isActive' => $this->isActive,
            'runNow' => $this->runNow
        ]);
    }

    /**
     * @param array $options
     */
    private function edit($options)
    {
        $this->getStore()->update('
            UPDATE `task` SET 
              `name` = :name, 
              `status` = :status, 
              `pid` = :pid,
              `configFile` = :configFile,
              `class` = :class,
              `options` = :options, 
              `schedule` = :schedule, 
              `lastRunDt` = :lastRunDt, 
              `lastRunMessage` = :lastRunMessage,
              `lastRunStatus` = :lastRunStatus,
              `lastRunDuration` = :lastRunDuration,
              `lastRunExitCode` = :lastRunExitCode,
              `isActive` = :isActive,
              `runNow` = :runNow
             WHERE `taskId` = :taskId
        ', [
            'taskId' => $this->taskId,
            'name' => $this->name,
            'status' => $this->status,
            'pid' => $this->pid,
            'configFile' => $this->configFile,
            'class' => $this->class,
            'options' => json_encode($this->options),
            'schedule' => $this->schedule,
            'lastRunDt' => $this->lastRunDt,
            'lastRunMessage' => $this->lastRunMessage,
            'lastRunStatus' => $this->lastRunStatus,
            'lastRunDuration' => $this->lastRunDuration,
            'lastRunExitCode' => $this->lastRunExitCode,
            'isActive' => $this->isActive,
            'runNow' => $this->runNow
        ], $options['connection'], $options['reconnect']);
    }
}