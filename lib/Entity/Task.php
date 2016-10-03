<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Task.php)
 */


namespace Xibo\Entity;
use Cron\CronExpression;

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

    public $taskId;
    public $name;
    public $status;
    public $class;
    public $options = [];
    public $schedule;
    public $lastRunDt;
    public $lastRunMessage;
    public $lastRunStatus;
    public $isActive;
    public $runNow;

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

    }

    public function delete()
    {

    }
}