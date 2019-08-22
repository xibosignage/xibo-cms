<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016-2018 Spring Signage Ltd
 * (TaskTrait.php)
 */

namespace Xibo\XTR;

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Task;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class TaskTrait
 * @package Xibo\XTR
 */
trait TaskTrait
{
    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /** @var  SanitizerServiceInterface */
    private $sanitizer;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  TimeSeriesStoreInterface */
    private $timeSeriesStore;

    /** @var  PoolInterface */
    private $pool;

    /** @var  DateServiceInterface */
    private $date;

    /** @var  User */
    private $user;

    /** @var  Task */
    private $task;

    /** @var  array */
    private $options;

    /** @var  string */
    private $runMessage;

    /** @inheritdoc */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /** @inheritdoc */
    public function setLogger($logger)
    {
        $this->log = $logger;
        return $this;
    }

    /** @inheritdoc */
    public function setSanitizer($sanitizer)
    {
        $this->sanitizer = $sanitizer;
        return $this;
    }

    /** @inheritdoc */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    /** @inheritdoc */
    public function setTask($task)
    {
        $options = $task->options;

        if (property_exists($this, 'defaultConfig'))
            $options = array_merge($this->defaultConfig, $options);

        $this->task = $task;
        $this->options = $options;
        return $this;
    }

    /** @inheritdoc */
    public function setStore($store)
    {
        $this->store = $store;
        return $this;
    }

    /** @inheritdoc */
    public function setTimeSeriesStore($timeSeriesStore)
    {
        $this->timeSeriesStore = $timeSeriesStore;
        return $this;
    }

    /** @inheritdoc */
    public function setPool($pool)
    {
        $this->pool = $pool;
        return $this;
    }

    /** @inheritdoc */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /** @inheritdoc */
    public function getRunMessage()
    {
        return $this->runMessage;
    }

    /**
     * Get task
     * @return Task
     */
    private function getTask()
    {
        return $this->task;
    }

    /**
     * @param $option
     * @param $default
     * @return mixed
     */
    private function getOption($option, $default)
    {
        return isset($this->options[$option]) ? $this->options[$option] : $default;
    }

    /**
     * Append Run Message
     * @param $message
     */
    private function appendRunMessage($message)
    {
        if ($this->runMessage === null)
            $this->runMessage = '';

        $this->runMessage .= $message . PHP_EOL;
    }
}