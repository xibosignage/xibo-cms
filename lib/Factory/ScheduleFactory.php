<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
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
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DayPartFactory $dayPartFactory
     * @param UserFactory $userFactory
     * @param ScheduleReminderFactory $scheduleReminderFactory
     * @param ScheduleExclusionFactory $scheduleExclusionFactory
     * @param User $user
     */
    public function __construct(
        $config,
        $pool,
        $displayGroupFactory,
        $dayPartFactory,
        $userFactory,
        $scheduleReminderFactory,
        $scheduleExclusionFactory,
        $user,
        private readonly ScheduleCriteriaFactory $scheduleCriteriaFactory
    ) {
        $this->setAclDependencies($user, $userFactory);
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
            $this->getDispatcher(),
            $this->config,
            $this->pool,
            $this->displayGroupFactory,
            $this->dayPartFactory,
            $this->userFactory,
            $this->scheduleReminderFactory,
            $this->scheduleExclusionFactory,
            $this->scheduleCriteriaFactory
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
        if ($displayGroupId == null) {
            return [];
        }

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

    public function getByParentCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'parentCampaignId' => $campaignId]);
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
     * @param $syncGroupId
     * @return Schedule[]
     */
    public function getBySyncGroupId($syncGroupId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'syncGroupId' => $syncGroupId]);
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
                `layoutLinks`.layoutId, 
                `layoutLinks`.status, 
                `layoutLinks`.duration,
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
                schedule.maxPlaysPerHour,
                schedule.isGeoAware,
                schedule.geoLocation,
                schedule.actionTriggerCode,
                schedule.actionType,
                schedule.actionLayoutCode,
                schedule.syncGroupId,
                `campaign`.campaign,
                `campaign`.campaignId as groupKey,
                `campaign`.cyclePlaybackEnabled as cyclePlayback,
                `campaign`.playCount,
                `command`.command,
                `lkscheduledisplaygroup`.displayGroupId,
                `daypart`.isAlways,
                `daypart`.isCustom,
                `syncLayout`.layoutId AS syncLayoutId,
                `syncLayout`.status AS syncLayoutStatus, 
                `syncLayout`.duration AS syncLayoutDuration,
                `schedule`.dataSetId,
                `schedule`.dataSetParams
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
                LEFT OUTER JOIN (
                    SELECT `layout`.layoutId,
                        `layout`.status, 
                        `layout`.duration,
                        `lkcampaignlayout`.campaignId,
                        `lkcampaignlayout`.displayOrder
                      FROM `layout`
                        INNER JOIN `lkcampaignlayout`
                        ON `lkcampaignlayout`.LayoutID = `layout`.layoutId  
                     WHERE `layout`.retired = 0
                        AND `layout`.parentId IS NULL
                ) layoutLinks
                ON `campaign`.CampaignID = `layoutLinks`.campaignId  
                LEFT OUTER JOIN `command`
                ON `command`.commandId = `schedule`.commandId
                LEFT OUTER JOIN `schedule_sync`
                ON `schedule_sync`.eventId = `schedule`.eventId';

        // do this only if we have a Display.
        if (!$options['useGroupId']) {
            $SQL .= ' AND `schedule_sync`.displayId = :displayId';
        }

        $SQL .= ' LEFT OUTER JOIN `layout` syncLayout
                 ON `syncLayout`.layoutId = `schedule_sync`.layoutId
                  AND `syncLayout`.retired = 0 
                  AND `syncLayout`.parentId IS NULL
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
            
            ORDER BY `schedule`.DisplayOrder,
                CASE WHEN `campaign`.listPlayOrder = \'block\' THEN `schedule`.FromDT ELSE 0 END,
                CASE WHEN `campaign`.listPlayOrder = \'block\' THEN `campaign`.campaignId ELSE 0 END,
                IFNULL(`layoutLinks`.displayOrder, 0),
                `schedule`.FromDT,
                `schedule`.eventId
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

        if (is_array($sortOrder)) {
            $newSortOrder = [];
            foreach ($sortOrder as $sort) {
                if ($sort == '`recurringEvent`') {
                    $newSortOrder[] = '`recurrence_type`';
                    continue;
                }

                if ($sort == '`recurringEvent` DESC') {
                    $newSortOrder[] = '`recurrence_type` DESC';
                    continue;
                }

                if ($sort == '`icon`') {
                    $newSortOrder[] = '`eventTypeId`';
                    continue;
                }

                if ($sort == '`icon` DESC') {
                    $newSortOrder[] = '`eventTypeId` DESC';
                    continue;
                }

                $newSortOrder[] = $sort;
            }
            $sortOrder = $newSortOrder;
        }

        $select = '
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
            parentCampaign.campaign AS parentCampaignName,
            parentCampaign.type AS parentCampaignType,
            `command`.commandId,
            `command`.command,
            `schedule`.dayPartId,
            `schedule`.syncTimezone,
            `schedule`.syncEvent,
            `schedule`.shareOfVoice,
            `schedule`.maxPlaysPerHour,
            `schedule`.isGeoAware,
            `schedule`.geoLocation,
            `schedule`.actionTriggerCode,
            `schedule`.actionType,
            `schedule`.actionLayoutCode,
            `schedule`.parentCampaignId,
            `schedule`.syncGroupId,
            `daypart`.isAlways,
            `daypart`.isCustom,
            `syncgroup`.name AS syncGroupName,
            `schedule`.modifiedBy,
            `user`.userName as modifiedByName,
            `schedule`.createdOn,
            `schedule`.updatedOn,
            `schedule`.name,
            `schedule`.dataSetId,
            `schedule`.dataSetParams
        ';

        $body = ' FROM `schedule`
            INNER JOIN `daypart`
            ON `daypart`.dayPartId = `schedule`.dayPartId
            LEFT OUTER JOIN `campaign`
            ON campaign.CampaignID = `schedule`.CampaignID
            LEFT OUTER JOIN `campaign` parentCampaign
            ON parentCampaign.campaignId = `schedule`.parentCampaignId
            LEFT OUTER JOIN `command`
            ON `command`.commandId = `schedule`.commandId
            LEFT OUTER JOIN `syncgroup`
            ON `syncgroup`.syncGroupId = `schedule`.syncGroupId
            LEFT OUTER JOIN `user`
            ON `user`.userId = `schedule`.modifiedBy
          WHERE 1 = 1';

        if ($parsedFilter->getInt('eventId') !== null) {
            $body .= ' AND `schedule`.eventId = :eventId ';
            $params['eventId'] = $parsedFilter->getInt('eventId');
        }

        if ($parsedFilter->getInt('eventTypeId') !== null) {
            $body .= ' AND `schedule`.eventTypeId = :eventTypeId ';
            $params['eventTypeId'] = $parsedFilter->getInt('eventTypeId');
        }

        if ($parsedFilter->getInt('campaignId') !== null) {
            $body .= ' AND `schedule`.campaignId = :campaignId ';
            $params['campaignId'] = $parsedFilter->getInt('campaignId');
        }

        if ($parsedFilter->getInt('parentCampaignId') !== null) {
            $body .= ' AND `schedule`.parentCampaignId = :parentCampaignId ';
            $params['parentCampaignId'] = $parsedFilter->getInt('parentCampaignId');
        }

        if ($parsedFilter->getInt('adCampaignsOnly') === 1) {
            $body .= ' AND `schedule`.parentCampaignId IS NOT NULL AND `schedule`.eventTypeId = :eventTypeId ';
            $params['eventTypeId'] = Schedule::$INTERRUPT_EVENT;
        }

        if ($parsedFilter->getInt('recurring') !== null) {
            if ($parsedFilter->getInt('recurring') === 1) {
                $body .= ' AND `schedule`.recurrence_type IS NOT NULL ';
            } else if ($parsedFilter->getInt('recurring') === 0) {
                $body .= ' AND `schedule`.recurrence_type IS NULL ';
            }
        }

        if ($parsedFilter->getInt('geoAware') !== null) {
            $body .= ' AND `schedule`.isGeoAware = :geoAware ';
            $params['geoAware'] = $parsedFilter->getInt('geoAware');
        }

        if ($parsedFilter->getInt('ownerId') !== null) {
            $body .= ' AND `schedule`.userId = :ownerId ';
            $params['ownerId'] = $parsedFilter->getInt('ownerId');
        }

        if ($parsedFilter->getInt('dayPartId') !== null) {
            $body .= ' AND `schedule`.dayPartId = :dayPartId ';
            $params['dayPartId'] = $parsedFilter->getInt('dayPartId');
        }

        // Only 1 date
        if ($parsedFilter->getInt('fromDt') !== null && $parsedFilter->getInt('toDt') === null) {
            $body .= ' AND schedule.fromDt > :fromDt ';
            $params['fromDt'] = $parsedFilter->getInt('fromDt');
        }

        if ($parsedFilter->getInt('toDt') !== null && $parsedFilter->getInt('fromDt') === null) {
            $body .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['toDt'] = $parsedFilter->getInt('toDt');
        }
        // End only 1 date

        // Both dates
        if ($parsedFilter->getInt('fromDt') !== null && $parsedFilter->getInt('toDt') !== null) {
            $body .= ' AND schedule.fromDt < :toDt ';
            $body .= ' AND IFNULL(schedule.toDt, schedule.fromDt) >= :fromDt ';
            $params['fromDt'] = $parsedFilter->getInt('fromDt');
            $params['toDt'] = $parsedFilter->getInt('toDt');
        }
        // End both dates

        if ($parsedFilter->getIntArray('displayGroupIds') != null) {
            // parameterize the selected display/groups and number of selected display/groups
            $selectedDisplayGroupIds = implode(',', $parsedFilter->getIntArray('displayGroupIds'));
            $numberOfSelectedDisplayGroups = count($parsedFilter->getIntArray('displayGroupIds'));

            // build date filter for sub-queries for shared schedules
            $sharedScheduleDateFilter = '';
            if ($parsedFilter->getInt('futureSchedulesFrom') !== null
                && $parsedFilter->getInt('futureSchedulesTo') === null
            ) {
                // Get schedules that end after this date, or that recur after this date
                $sharedScheduleDateFilter .= ' AND (IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom
             OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0)
              AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ';
                $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
            }

            if ($parsedFilter->getInt('futureSchedulesFrom') !== null
                && $parsedFilter->getInt('futureSchedulesTo') !== null
            ) {
                // Get schedules that end after this date, or that recur after this date
                $sharedScheduleDateFilter .= ' AND ((schedule.fromDt < :futureSchedulesTo 
            AND IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom)
             OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0
              AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ) ';
                $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
                $params['futureSchedulesTo'] = $parsedFilter->getInt('futureSchedulesTo');
            }

            // non Schedule grid filter, keep it the way it was.
            if ($parsedFilter->getInt('sharedSchedule') === null &&
                $parsedFilter->getInt('directSchedule') === null
            ) {
                $body .= ' AND `schedule`.eventId IN (
                    SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                    WHERE displayGroupId IN (' . $selectedDisplayGroupIds . ')
                     ) ';
            } else {
                // Schedule grid query
                // check what options we were provided with and adjust query accordingly.
                $sharedSchedule = ($parsedFilter->getInt('sharedSchedule') === 1);
                $directSchedule =  ($parsedFilter->getInt('directSchedule') === 1);

                // shared and direct
                // events scheduled directly on the selected displays/groups
                // and scheduled on all selected displays/groups
                // Example : Two Displays selected, return only events scheduled directly to both of them
                if ($sharedSchedule && $directSchedule) {
                    $body .= ' AND `schedule`.eventId IN (
                        SELECT `lkscheduledisplaygroup`.eventId
                         FROM `lkscheduledisplaygroup`
                          INNER JOIN `schedule` ON `schedule`.eventId = `lkscheduledisplaygroup`.eventId
                          WHERE displayGroupId IN (' . $selectedDisplayGroupIds . ')' .
                        $sharedScheduleDateFilter . '
                          GROUP BY eventId
                          HAVING COUNT(DISTINCT displayGroupId) >= ' .
                        $numberOfSelectedDisplayGroups .
                        ') ';
                }

                // shared and not direct
                // 1 - events scheduled on the selected display/groups
                // 2 - events scheduled on a display group selected display is a member of
                // 3 - events scheduled on a parent display group of selected display group
                // and scheduled on all selected displays/groups
                // Example : Two Displays selected, return only events scheduled directly to both of them
                if ($sharedSchedule && !$directSchedule) {
                    $body .= ' AND (
                        ( `schedule`.eventId IN (
                        SELECT `lkscheduledisplaygroup`.eventId 
                        FROM `lkscheduledisplaygroup`
                         INNER JOIN `schedule` ON `schedule`.eventId = `lkscheduledisplaygroup`.eventId
                         WHERE displayGroupId IN (' . $selectedDisplayGroupIds . ')' .
                         $sharedScheduleDateFilter . '
                          GROUP BY eventId
                          HAVING COUNT(DISTINCT displayGroupId) >= ' . $numberOfSelectedDisplayGroups . '
                        ))
                        OR `schedule`.eventID IN (
                        SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                            INNER JOIN `schedule` ON `schedule`.eventId = `lkscheduledisplaygroup`.eventId
                            INNER JOIN `lkdgdg` ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId 
                            INNER JOIN `lkdisplaydg` ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                            WHERE `lkdisplaydg`.DisplayID IN (
                                SELECT lkdisplaydg.displayId FROM lkdisplaydg
                                 INNER JOIN displaygroup ON lkdisplaydg.displayGroupId = displaygroup.displayGroupId 
                                 WHERE lkdisplaydg.displayGroupId IN (' . $selectedDisplayGroupIds . ')
                            AND displaygroup.isDisplaySpecific = 1 ) ' .
                            $sharedScheduleDateFilter . '
                            GROUP BY eventId
                            HAVING COUNT(DISTINCT `lkdisplaydg`.displayId) >= ' .
                            $numberOfSelectedDisplayGroups . '
                        )
                        OR `schedule`.eventID IN (
                            SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                            INNER JOIN `schedule` ON `schedule`.eventId = `lkscheduledisplaygroup`.eventId
                            INNER JOIN `lkdgdg` ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                            WHERE `lkscheduledisplaygroup`.displayGroupId IN (
                            SELECT lkdgdg.childId FROM lkdgdg
                             WHERE lkdgdg.parentId IN (' . $selectedDisplayGroupIds .')  AND lkdgdg.depth > 0)' .
                            $sharedScheduleDateFilter . '
                            GROUP BY eventId
                            HAVING COUNT(DISTINCT `lkscheduledisplaygroup`.displayGroupId) >= ' .
                            $numberOfSelectedDisplayGroups . '    
                        )
                     ) ';
                }

                // not shared and direct (old default)
                // events scheduled directly on selected displays/groups
                if (!$sharedSchedule && $directSchedule) {
                    $body .= ' AND `schedule`.eventId IN (
                    SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                    WHERE displayGroupId IN (' . $selectedDisplayGroupIds . ')
                     ) ';
                }

                // not shared and not direct (new default)
                // 1 - events scheduled on the selected display/groups
                // 2 - events scheduled on display members of the selected display group
                // 2 - events scheduled on a display group selected display is a member of
                // 3 - events scheduled on a parent display group of selected display group
                if (!$sharedSchedule && !$directSchedule) {
                    $body .= ' AND (
                        ( `schedule`.eventId IN (SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                         WHERE displayGroupId IN (' . $selectedDisplayGroupIds . ')) )
                        OR `schedule`.eventID IN (
                        SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                            INNER JOIN `lkdgdg` ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId 
                            INNER JOIN `lkdisplaydg` ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                            INNER JOIN displaygroup ON lkdisplaydg.displayGroupId = displaygroup.displayGroupId
                            WHERE `lkdisplaydg`.DisplayID IN (
                                SELECT lkdisplaydg.displayId FROM lkdisplaydg 
                                 WHERE lkdisplaydg.displayGroupId IN (' . $selectedDisplayGroupIds . ')
                            ) AND displaygroup.isDisplaySpecific = 1 
                        )
                        OR `schedule`.eventID IN (
                        SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                            INNER JOIN
                             `lkdisplaydg` ON lkdisplaydg.DisplayGroupID = `lkscheduledisplaygroup`.displayGroupId
                            WHERE `lkdisplaydg`.DisplayID IN (
                                SELECT lkdisplaydg.displayId FROM lkdisplaydg 
                                INNER JOIN displaygroup ON lkdisplaydg.displayGroupId = displaygroup.displayGroupId
                                 WHERE lkdisplaydg.displayGroupId IN (' . $selectedDisplayGroupIds . ')
                            AND displaygroup.isDisplaySpecific = 1 ) 
                        )
                        OR `schedule`.eventID IN (
                                SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup`
                                WHERE `lkscheduledisplaygroup`.displayGroupId IN (
                                SELECT lkdgdg.childId FROM lkdgdg 
                                WHERE lkdgdg.parentId IN (' . $selectedDisplayGroupIds .')  AND lkdgdg.depth > 0)  
                        )
                     ) ';
                }
            }
        }

        // Future schedules?
        if ($parsedFilter->getInt('futureSchedulesFrom') !== null
            && $parsedFilter->getInt('futureSchedulesTo') === null
        ) {
            // Get schedules that end after this date, or that recur after this date
            $body .= ' AND (IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom
             OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0)
              AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ';
            $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
        }

        if ($parsedFilter->getInt('futureSchedulesFrom') !== null
            && $parsedFilter->getInt('futureSchedulesTo') !== null
        ) {
            // Get schedules that end after this date, or that recur after this date
            $body .= ' AND ((schedule.fromDt < :futureSchedulesTo 
            AND IFNULL(`schedule`.toDt, `schedule`.fromDt) >= :futureSchedulesFrom)
             OR `schedule`.recurrence_range >= :futureSchedulesFrom OR (IFNULL(`schedule`.recurrence_range, 0) = 0
              AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\') ) ';
            $params['futureSchedulesFrom'] = $parsedFilter->getInt('futureSchedulesFrom');
            $params['futureSchedulesTo'] = $parsedFilter->getInt('futureSchedulesTo');
        }

        // Restrict to mediaId - meaning layout schedules of which the layouts contain the selected mediaId
        if ($parsedFilter->getInt('mediaId') !== null) {
            $body .= '
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
            $body .= '
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

        if ($parsedFilter->getInt('syncGroupId') !== null) {
            $body .= ' AND `schedule`.syncGroupId = :syncGroupId ';
            $params['syncGroupId'] = $parsedFilter->getInt('syncGroupId');
        }

        if ($parsedFilter->getString('name') != null) {
            $terms = explode(',', $parsedFilter->getString('name'));
            $logicalOperator = $parsedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'schedule',
                'name',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        // Sorting?
        $order = '';
        if ($parsedFilter->getInt('gridFilter') === 1 && $sortOrder === null) {
            $order = ' ORDER BY
                            CASE WHEN `schedule`.fromDt = 0 THEN 0
                                 WHEN `schedule`.recurrence_type <> \'\' THEN 1
                                 ELSE 2 END,
                            eventId';
        } else if (is_array($sortOrder) && !empty($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        // Paging
        $limit = '';
        if ($parsedFilter->hasParam('start') && $parsedFilter->hasParam('length')) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0])
                . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'isPriority',
                    'syncTimezone',
                    'isAlways',
                    'isCustom',
                    'syncEvent',
                    'recurrenceMonthlyRepeatsOn',
                    'isGeoAware',
                    'maxPlaysPerHour',
                    'modifiedBy',
                    'dataSetId',
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
