<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskInterface.php)
 */


namespace Xibo\XTR;
use Slim\Helper\Set;
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
     * @param SanitizerServiceInterface $sanitizer
     * @return $this
     */
    public function setSanitizer($sanitizer);

    /**
     * @param DateServiceInterface $date
     * @return $this
     */
    public function setDate($date);

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
     * @param User $user
     * @return $this
     */
    public function setUser($user);

    /**
     * @param Set $container
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