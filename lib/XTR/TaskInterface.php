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
     * @param \Xibo\Helper\SanitizerService $sanitizer the service; getSanitizer() builds a sanitizer from it
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