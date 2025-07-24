<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
use Cron\CronExpression;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * @return \DateTime|string
     * @throws \Exception
     */
    public function nextRunDate(): \DateTime|string
    {
        try {
            try {
                $cron = new CronExpression($this->schedule);
            } catch (\Exception $e) {
                // Try and take the first X characters instead.
                try {
                    $cron = new CronExpression(substr($this->schedule, 0, strlen($this->schedule) - 2));
                } catch (\Exception) {
                    $this->getLog()->error('nextRunDate: cannot fix CRON syntax error  ' . $this->taskId);
                    throw $e;
                }
            }

            if ($this->lastRunDt == 0) {
                return (new \DateTime())->format('U');
            }

            return $cron->getNextRunDate(\DateTime::createFromFormat('U', $this->lastRunDt))->format('U');
        } catch (\Exception) {
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
    private function validate(): void
    {
        // Test the CRON expression
        if (empty($this->schedule)) {
            throw new InvalidArgumentException(__('Please enter a CRON expression in the Schedule'), 'schedule');
        }

        try {
            $cron = new CronExpression($this->schedule);
            $cron->getNextRunDate();
        } catch (\Exception $e) {
            $this->getLog()->info('run: CRON syntax error for taskId  ' . $this->taskId
                . ', e: ' . $e->getMessage());

            try {
                $trimmed = substr($this->schedule, 0, strlen($this->schedule) - 2);
                $cron = new CronExpression($trimmed);
                $cron->getNextRunDate();
            } catch (\Exception) {
                throw new InvalidArgumentException(__('Invalid CRON expression in the Schedule'), 'schedule');
            }

            // Swap to the trimmed (and correct) schedule
            $this->schedule = $trimmed;
        }
    }

    /**
     * Save
     * @throws InvalidArgumentException
     */
    public function save(array $options = []): void
    {
        $options = array_merge([
            'validate' => true,
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->taskId == null) {
            $this->add();
        } else {
            // If we've transitioned from active to inactive, then reset the task status
            if ($this->getOriginalValue('isActive') != $this->isActive) {
                $this->status = Task::$STATUS_IDLE;
            }

            $this->edit();
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

    private function edit()
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
        ]);
    }

    /**
     * Set this task to be started, updating the DB as necessary
     * @return $this
     */
    public function setStarted(): Task
    {
        // Set to running
        $this->status = \Xibo\Entity\Task::$STATUS_RUNNING;
        $this->lastRunStartDt = Carbon::now()->format('U');
        $this->pid = getmypid();

        $this->store->update('
            UPDATE `task` SET `status` = :status, lastRunStartDt = :lastRunStartDt, pid = :pid
             WHERE taskId = :taskId
        ', [
            'taskId' => $this->taskId,
            'status' => $this->status,
            'lastRunStartDt' => $this->lastRunStartDt,
            'pid' => $this->pid,
        ], 'xtr', true, false);

        return $this;
    }

    /**
     * Set this task to be finished, updating only the fields we might have changed
     * @return $this
     */
    public function setFinished(): Task
    {
        $this->getStore()->update('
            UPDATE `task` SET 
              `status` = :status, 
              `pid` = :pid,
              `lastRunDt` = :lastRunDt, 
              `lastRunMessage` = :lastRunMessage,
              `lastRunStatus` = :lastRunStatus,
              `lastRunDuration` = :lastRunDuration,
              `lastRunExitCode` = :lastRunExitCode,
              `runNow` = :runNow
             WHERE `taskId` = :taskId
        ', [
            'taskId' => $this->taskId,
            'status' => $this->status,
            'pid' => $this->pid,
            'lastRunDt' => $this->lastRunDt,
            'lastRunMessage' => $this->lastRunMessage,
            'lastRunStatus' => $this->lastRunStatus,
            'lastRunDuration' => $this->lastRunDuration,
            'lastRunExitCode' => $this->lastRunExitCode,
            'runNow' => $this->runNow
        ], 'xtr', true, false);

        return $this;
    }
}
