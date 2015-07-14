<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Schedule;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ScheduleFactory extends BaseFactory
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
     * Get by Campaign ID
     * @param int $campaignId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public static function getByCampaignId($campaignId)
    {
        return ScheduleFactory::query(null, ['campaignId' => $campaignId]);
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
        $entries = [];
        $params = [];

        $sql = '
        SELECT `schedule`.eventId,
            `schedule_detail`.fromDt,
            `schedule_detail`.toDt,
            `schedule`.is_priority AS isPriority,
            `schedule`.recurrence_type AS recurrenceType,
            campaign.campaignId,
            campaign.campaign
          FROM `schedule_detail`
            INNER JOIN `schedule`
            ON schedule_detail.EventID = `schedule`.EventID
            INNER JOIN campaign
            ON campaign.CampaignID = `schedule`.CampaignID
          WHERE 1 = 1
        ';

        if (Sanitize::getInt('fromDt', $filterBy) != null) {
            $sql .= ' AND schedule_detail.fromDt > :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
        }

        if (Sanitize::getInt('toDt', $filterBy) != null) {
            $sql .= ' AND schedule_detail.toDt <= :toDt ';
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }

        if (Sanitize::getIntArray('displayGroupIds', $filterBy) != null) {
            $sql .= ' AND `schedule`.eventId IN (SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup` WHERE displayGroupId IN (' . implode(',', Sanitize::getIntArray('displayGroupIds', $filterBy)) . ')) ';
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Schedule())->hydrate($row, ['intProperties' => ['isPriority']]);
        }

        return $entries;
    }
}