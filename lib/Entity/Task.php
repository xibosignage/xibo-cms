<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Task.php)
 */


namespace Xibo\Entity;
use Cron\CronExpression;
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

    public $taskId;
    public $name;
    public $class;
    public $status;
    public $pid;
    public $options = [];
    public $schedule;
    public $lastRunDt;
    public $lastRunMessage;
    public $lastRunStatus;
    public $lastRunDuration;
    public $lastRunExitCode;
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
     * @return \DateTime
     */
    public function nextRunDate()
    {
        $cron = CronExpression::factory($this->schedule);

        return $cron->getNextRunDate()->format('U');
    }

    public function save()
    {
        if ($this->taskId == null)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `task` WHERE `taskId` = :taskId', ['taskId' => $this->taskId]);
    }

    private function add()
    {
        $this->taskId = $this->getStore()->insert('
            INSERT INTO `task` (`name`, `status`, `class`, `pid`, `options`, `schedule`, 
              `lastRunDt`, `lastRunMessage`, `lastRunStatus`, `lastRunDuration`, `lastRunExitCode`,
              `isActive`, `runNow`) VALUES
             (:name, :status, :class, :pid, :options, :schedule, 
              :lastRunDt, :lastRunMessage, :lastRunStatus, :lastRunDuration, :lastRunExitCode,
              :isActive, :runNow)
        ', [
            'name' => $this->name,
            'status' => $this->status,
            'pid' => $this->pid,
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
}