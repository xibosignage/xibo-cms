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
        $events = ScheduleFactory::query(null, ['disableUserCheck' => 1, 'eventId' => $eventId]);

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
        return ScheduleFactory::query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get by Campaign ID
     * @param int $campaignId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public static function getByCampaignId($campaignId)
    {
        return ScheduleFactory::query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    /**
     * @param int $ownerId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public static function getByOwnerId($ownerId)
    {
        return ScheduleFactory::query(null, ['disableUserCheck' => 1, 'ownerId' => $ownerId]);
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

        $useDetail = Sanitize::getInt('useDetail', $filterBy) == 1;

        $sql = '
        SELECT `schedule`.eventId, ';

        if ($useDetail) {
            $sql .= '
            `schedule_detail`.fromDt,
            `schedule_detail`.toDt,
            ';
        } else {
            $sql .= '
            `schedule`.fromDt,
            `schedule`.toDt,
            ';
        }

        $sql .= '
            `schedule`.userId,
            `schedule`.displayOrder,
            `schedule`.is_priority AS isPriority,
            `schedule`.recurrence_type AS recurrenceType,
            `schedule`.recurrence_detail AS recurrenceDetail,
            `schedule`.recurrence_range AS recurrenceRange,
            campaign.campaignId,
            campaign.campaign
          FROM `schedule`
            INNER JOIN campaign
            ON campaign.CampaignID = `schedule`.CampaignID
        ';

        if ($useDetail) {
            $sql .= '
            INNER JOIN `schedule_detail`
            ON schedule_detail.EventID = `schedule`.EventID
            ';
        }

        $sql .= '
          WHERE 1 = 1
        ';

        if (Sanitize::getInt('eventId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.eventId = :eventId ';
            $params['eventId'] = Sanitize::getInt('eventId', $filterBy);
        }

        if (!$useDetail && Sanitize::getInt('fromDt', $filterBy) !== null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
        }

        if (!$useDetail && Sanitize::getInt('toDt', $filterBy) !== null) {
            $sql .= ' AND schedule.toDt <= :toDt ';
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }

        if ($useDetail && Sanitize::getInt('fromDt', $filterBy) !== null) {
            $sql .= ' AND schedule_detail.fromDt > :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
        }

        if ($useDetail && Sanitize::getInt('toDt', $filterBy) !== null) {
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