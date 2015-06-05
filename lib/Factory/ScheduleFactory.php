<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Schedule;
use Xibo\Exception\NotFoundException;

class ScheduleFactory
{
    /**
     * @param int $eventId
     * @return Schedule
     * @throws NotFoundException
     */
    public static function getById($eventId)
    {
        $events = ScheduleFactory::query(null, ['eventId' => $eventId]);

        if (count($events) <= 0)
            throw new NotFoundException();

        return $events[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public static function getByDisplayGroupId($displayGroupId)
    {
        return ScheduleFactory::query(null, ['displayGroupId' => $displayGroupId]);
    }

    /**
     * @param int $ownerId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public static function getByOwnerId($ownerId)
    {
        $events = ScheduleFactory::query(null, ['ownerId' => $ownerId]);

        if (count($events) <= 0)
            throw new NotFoundException();

        return $events;
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Schedule]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        return [];
    }
}