<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleConvertStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ScheduleConvertStep
 * @package Xibo\Upgrade
 */
class ScheduleConvertStep implements  Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * DataSetConvertStep constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config)
    {
        $this->store = $store;
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param \Slim\Helper\Set $container
     * @throws \Xibo\Exception\NotFoundException
     */
    public function doStep($container)
    {
        // Get all events and their Associated display group id's
        foreach ($this->store->select('SELECT eventId, displayGroupIds FROM `schedule`', []) as $event) {
            // Ping open the displayGroupIds
            $displayGroupIds = explode(',', $event['displayGroupIds']);

            // Construct some SQL to add the link
            $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES ';

            foreach ($displayGroupIds as $id) {
                $sql .= '(' . $event['eventId'] . ',' . $id . '),';
            }

            $sql = rtrim($sql, ',');

            $this->store->update($sql, []);
        }
    }
}