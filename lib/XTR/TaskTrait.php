<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskTrait.php)
 */


namespace Xibo\XTR;
use Slim\Slim;
use Stash\Interfaces\PoolInterface;
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
 * Class TaskTrait
 * @package Xibo\XTR
 */
trait TaskTrait
{
    /** @var  Slim */
    private $app;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /** @var  SanitizerServiceInterface */
    private $sanitizer;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  PoolInterface */
    private $pool;

    /** @var  DateServiceInterface */
    private $date;

    /** @var  User */
    private $user;

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  UpgradeFactory */
    private $upgradeFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  NotificationFactory */
    private $notificationFactory;

    /** @var  UserNotificationFactory */
    private $userNotificationFactory;

    /** @var  array */
    private $options;

    /** @var  string */
    private $runMessage;

    /** @inheritdoc */
    public function setApp($app)
    {
        $this->app = $app;
        return $this;
    }

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
    public function setOptions($options)
    {
        if (property_exists($this, 'defaultConfig'))
            $options = array_merge($this->defaultConfig, $options);

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
    public function setFactories($userFactory, $userGroupFactory, $layoutFactory, $displayFactory, $upgradeFactory, $mediaFactory, $notificationFactory, $userNotificationFactory)
    {
        $this->userGroupFactory = $userGroupFactory;
        $this->userFactory = $userFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->upgradeFactory = $upgradeFactory;
        $this->mediaFactory = $mediaFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
        return $this;
    }

    /** @inheritdoc */
    public function getRunMessage()
    {
        return $this->runMessage;
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
}