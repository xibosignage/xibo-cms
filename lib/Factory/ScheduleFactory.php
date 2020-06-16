<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
namespace Xibo\Factory;


use Carbon\Carbon;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Schedule;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ScheduleFactory
 * @package Xibo\Factory
 */
class ScheduleFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var PoolInterface  */
    private $pool;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /** @var DayPartFactory */
    private $dayPartFactory;

    /** @var  UserFactory */
    private $userFactory;

    /** @var  ScheduleReminderFactory */
    private $scheduleReminderFactory;

    /** @var  ScheduleExclusionFactory */
    private $scheduleExclusionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DayPartFactory $dayPartFactory
     * @param UserFactory $userFactory
     * @param ScheduleReminderFactory $scheduleReminderFactory
     * @param ScheduleExclusionFactory $scheduleExclusionFactory
     */
    public function __construct($store, $log, $sanitizerService, $config, $pool, $displayGroupFactory, $dayPartFactory, $userFactory, $scheduleReminderFactory, $scheduleExclusionFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->config = $config;
        $this->pool = $pool;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->userFactory = $userFactory;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
        $this->scheduleExclusionFactory = $scheduleExclusionFactory;
    }

    /**
     * Create Empty
     * @return Schedule
     */
    public function createEmpty()
    {
        return new Schedule(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->pool,
            $this->displayGroupFactory,
            $this->dayPartFactory,
            $this->userFactory,
            $this->scheduleReminderFactory,
            $this->scheduleExclusionFactory
        );
    }

    /**
     * @param int $eventId
     * @return Schedule
     * @throws NotFoundException
     */
    public function getById($eventId)
    {
        $events = $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId]);

        if (count($events) <= 0)
            throw new NotFoundException();

        return $events[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Schedule]
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupIds' => [$displayGroupId]]);
    }

    /**
     * Get by Campaign ID
     * @param int $campaignId
     * @return array[Schedule]
     */
    public function getByCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return array[Schedule]
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'ownerId' => $ownerId]);
    }

    /**
     * Get by DayPartId
     * @param int $dayPartId
     * @return Schedule[]
     */
    public function getByDayPartId($dayPartId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'dayPartId' => $dayPartId]);
    }

    /**
     * @param int $displayId
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @param array $options
     * @return array
     */
    public function getForXmds($displayId, $fromDt, $toDt, $options = [])
    {
        $options = array_merge(['useGroupId' => false], $options);

        // We dial the fromDt back to the top of the day, so that we include dayPart events that start on this
        // day
        $params = array(
            'fromDt' => $fromDt->copy()->startOfDay()->format('U'),
            'toDt' => $toDt->format('U')
        );

        $this->getLog()->debug('Get events for XMDS: fromDt[' . $params['fromDt'] . '], toDt[' . $params['toDt'] . '], with options: ' . json_encode($options));

        // Add file nodes to the $fileElements
        // Firstly get all the scheduled layouts
        $SQL = '
            SELECT `schedule`.eventTypeId, 
                layout.layoutId, 
                `layout`.status, 
                `command`.code, 
                schedule.fromDt, 
                schedule.toDt,
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.recurrenceMonthlyRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.eventId, 
                schedule.is_priority AS isPriority,
                `schedule`.displayOrder,
                schedule.dayPartId,
                `schedule`.campaignId,
                `schedule`.commandId,
                schedule.syncTimezone,
                schedule.syncEvent,
                schedule.shareOfVoice,
                schedule.isGeoAware,
                schedule.geoLocation,
                `campaign`.campaign,
                `command`.command,
                `lkscheduledisplaygroup`.displayGroupId,
                `daypart`.isAlways,
                `daypart`.isCustom
               FROM `schedule`
                INNER JOIN `daypart`
                ON `daypart`.dayPartId = `schedule`.dayPartId
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                INNER JOIN `lkdgdg`
                ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
        ';

        if (!$options['useGroupId']) {
            // Only join in the display/display group link table if we are requesting this data for a display
            // otherwise the group we are looking for might not have any displays, and this join would therefore
            // remove any records.
            $SQL .= '
                INNER JOIN `lkdisplaydg`
                ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
            ';
        }

        $SQL .= '    
                LEFT OUTER JOIN `campaign`
                ON `schedule`.CampaignID = campaign.CampaignID
                LEFT OUTER JOIN `lkcampaignlayout`
                ON lkcampaignlayout.CampaignID = campaign.CampaignID
                LEFT OUTER JOIN `layout`
                ON lkcampaignlayout.LayoutID = layout.LayoutID
                  AND layout.retired = 0
                  AND layout.parentId IS NULL
                LEFT OUTER JOIN `command`
                ON `command`.commandId = `schedule`.commandId
        ';

        if ($options['useGroupId']) {
            $SQL .= ' WHERE `lkdgdg`.childId = :displayGroupId ';
            $params['displayGroupId'] = $options['displayGroupId'];
        } else {
            $SQL .= ' WHERE `lkdisplaydg`.DisplayID = :displayId ';
            $params['displayId'] = $displayId;
        }

        // Are we requesting a range or a single date/time?
        // only the inclusive range changes, but it is clearer to have the whole statement reprinted.
        // Ranged request
        $SQL .= ' 
            AND (
                  (schedule.FromDT <= :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
            )
            
            ORDER BY schedule.DisplayOrder, IFNULL(lkcampaignlayout.DisplayOrder, 0), schedule.FromDT, schedule.eventId
        ';

        return $this->getStore()->select($SQL, $params);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Schedule[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        $sql = '
        SELECT `schedule`.eventId, 
            `schedule`.eventTypeId,
            `schedule`.fromDt,
            `schedule`.toDt,
            `schedule`.userId,
            `schedule`.displayOrder,
            `schedule`.is_priority AS isPriority,
            `schedule`.recurrence_type AS recurrenceType,
            `schedule`.recurrence_detail AS recurrenceDetail,
            `schedule`.recurrence_range AS recurrenceRange,
            `schedule`.recurrenceRepeatsOn,
            `schedule`.recurrenceMonthlyRepeatsOn,
            `schedule`.lastRecurrenceWatermark,
            campaign.campaignId,
            campaign.campaign,
            `command`.commandId,
            `command`.command,
            `schedule`.dayPartId,
            `schedule`.syncTimezone,
            `schedule`.syncEvent,
            `schedule`.shareOfVoice,
            `schedule`.isGeoAware,
            `schedule`.geoLocation,
            `daypart`.isAlways,
            `daypart`.isCustom
          FROM `schedule`
            INNER JOIN `daypart`
            ON `daypart`.dayPartId = `schedule`.dayPartId
            LEFT OUTER JOIN `campaign`
            ON campaign.CampaignID = `schedule`.CampaignID
            LEFT OUTER JOIN `command`
            ON `command`.commandId = `schedule`.commandId
          WHERE 1 = 1
        ';

        if ($parsedFilter->getInt('eventId') !== null) {
            $sql .= ' AND `schedule`.eventId = :eventId ';
            $params['eventId'] = $parsedFilter->getInt('eventId');
        }

        if ($parsedFilter->getInt('eventTypeId') !== null) {
            $sql .= ' AND `schedule`.eventTypeId = :eventTypeId ';
            $params['eventTypeId'] = $parsedFilter->getInt('eventTypeId');
        }

        if ($parsedFilter->getInt('campaignId') !== null) {
            $sql .= ' AND `schedule`.campaignId = :campaignId ';
            $params['campaignId'] = $parsedFilter->getInt('campaignId');
        }

        if ($parsedFilter->getInt('ownerId') !== null) {
            $sql .= ' AND `schedule`.userId = :ownerId ';
            $params['ownerId'] = $parsedFilter->getInt('ownerId');
        }

        if ($parsedFilter->getInt('dayPartId') !== null) {
            $sql .= ' AND `schedule`.dayPartId = :dayPartId ';
            $params['dayPartId'] = $parsedFilter->getInt('dayPartId');
        }

        // Only 1 date
        if ($parsedFilter->getInt('fromDt') !== null && $parsedFilter->getInt('toDt') === null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $params['fromDt'] = $parsedFilter->getInt('fromDt');
        }

        if ($parsedFilter->getInt('toDt') !== null && $parsedFilter->getInt('fromDt') === null) {
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['toDt'] = $parsedFilter->getInt('toDt');
        }
        // End only 1 date

        // Both dates
        if ($parsedFilter->getInt('fromDt') !== null && $parsedFilter->getInt('toDt') !== null) {
            $sql .= ' AND schedule.fromDt < :toDt ';
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) >= :fromDt ';
            $params['fromDt'] = $parsedFilter->getInt('fromDt');
            $params['toDt'] = $parsedFilter->getInt('toDt');
        }
        // End both dates

        if ($parsedFilter->getIntArray('displayGroupIds') != null) {
            $sql .= ' AND `schedule`.eventId IN (SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup` WHERE displayGroupId IN (' . implode(',', $parsedFilter->getIntArray('displayGroupIds')) . ')) ';
        }

        // Future schedules?
        if ($parsedFilter->getInt('futureSchedulesFrom') !== null && $parsedFilter->getInt('futureSchedulesTo') === null) {
            // Get schedules that end after this date, or that recur after this date
            $sql .= ' AND (IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0) AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ';
            $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
        }

        if ($parsedFilter->getInt('futureSchedulesFrom') !== null && $parsedFilter->getInt('futureSchedulesTo') !== null) {
            // Get schedules that end after this date, or that recur after this date
            $sql .= ' AND ((schedule.fromDt < :futureSchedulesTo AND IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom) OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ) ';
            $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
            $params['futureSchedulesTo'] = $parsedFilter->getInt('futureSchedulesTo');
        }

        // Restrict to mediaId - meaning layout schedules of which the layouts contain the selected mediaId
        if ($parsedFilter->getInt('mediaId') !== null) {
            $sql .= '
                AND schedule.campaignId IN (
                    SELECT `lkcampaignlayout`.campaignId
                      FROM `lkwidgetmedia`
                       INNER JOIN `widget`
                       ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                       INNER JOIN `lkplaylistplaylist`
                        ON `widget`.playlistId = `lkplaylistplaylist`.childId
                        INNER JOIN `playlist`
                        ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                       INNER JOIN `region`
                       ON `region`.regionId = `playlist`.regionId
                       INNER JOIN layout
                       ON layout.LayoutID = region.layoutId
                       INNER JOIN `lkcampaignlayout`
                       ON lkcampaignlayout.layoutId = layout.layoutId
                     WHERE lkwidgetmedia.mediaId = :mediaId
                    UNION
                    SELECT `lkcampaignlayout`.campaignId
                      FROM `layout`
                       INNER JOIN `lkcampaignlayout`
                       ON lkcampaignlayout.layoutId = layout.layoutId
                     WHERE `layout`.backgroundImageId = :mediaId
                )
            ';
            $params['mediaId'] = $parsedFilter->getInt('mediaId');
        }

        // Restrict to playlistId - meaning layout schedules of which the layouts contain the selected playlistId
        if ($parsedFilter->getInt('playlistId') !== null) {

            $sql .= '
                AND schedule.campaignId IN (
                    SELECT `lkcampaignlayout`.campaignId
                      FROM `lkplaylistplaylist` 
                        INNER JOIN `playlist`
                        ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                       INNER JOIN `region`
                       ON `region`.regionId = `playlist`.regionId
                       INNER JOIN layout
                       ON layout.LayoutID = region.layoutId
                       INNER JOIN `lkcampaignlayout`
                       ON lkcampaignlayout.layoutId = layout.layoutId
                     WHERE `lkplaylistplaylist`.childId = :playlistId
                     
                )
            ';

            $params['playlistId'] = $parsedFilter->getInt('playlistId');

        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isPriority', 'syncTimezone', 'isAlways', 'isCustom', 'syncEvent', 'recurrenceMonthlyRepeatsOn', 'isGeoAware']]);
        }

        return $entries;
    }
}