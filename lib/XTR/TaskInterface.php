<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskInterface.php)
 */


namespace Xibo\XTR;
use Slim\Slim;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Task;
use Xibo\Entity\User;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UpgradeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     * @param Slim $app
     * @return $this
     */
    public function setApp($app);

    /**
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param UpgradeFactory $upgradeFactory
     * @param MediaFactory $mediaFactory
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     * @return $this
     */
    public function setFactories($userFactory, $userGroupFactory, $layoutFactory, $displayFactory, $upgradeFactory, $mediaFactory, $notificationFactory, $userNotificationFactory);

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