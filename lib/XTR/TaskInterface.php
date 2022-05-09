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
use Psr\Container\ContainerInterface;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Task;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Interface TaskInterface
 * @package Xibo\XTR
 */
interface TaskInterface
{
    /**
     * Set the app options
     * @param ConfigServiceInterface $config
     * @return $this
     */
    public function setConfig($config);

    /**
     * @param LogServiceInterface $logger
     * @return $this
     */
    public function setLogger($logger);

    /**
     * @param SanitizerInterface $sanitizer
     * @return $this
     */
    public function setSanitizer($sanitizer);

    /**
     * @param $array
     * @return SanitizerInterface
     */
    public function getSanitizer($array);

    /**
     * Set the task
     * @param Task $task
     * @return $this
     */
    public function setTask($task);

    /**
     * @param StorageServiceInterface $store
     * @return $this
     */
    public function setStore($store);

    /**
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @return $this
     */
    public function setTimeSeriesStore($timeSeriesStore);

    /**
     * @param PoolInterface $pool
     * @return $this
     */
    public function setPool($pool);

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function setDispatcher($dispatcher);

    /**
     * @param User $user
     * @return $this
     */
    public function setUser($user);

    /**
     * @param ContainerInterface $container
     * @return $this
     */
    public function setFactories($container);

    /**
     * @return $this
     */
    public function run();

    /**
     * Get the run message
     * @return string
     */
    public function getRunMessage();
}