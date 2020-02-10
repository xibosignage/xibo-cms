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

namespace Xibo\XTR;

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Task;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
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

    /** @var  SanitizerService */
    private $sanitizerService;

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

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    public function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
    }

    /** @inheritdoc */
    public function setSanitizer($sanitizer)
    {
        $this->sanitizerService = $sanitizer;
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