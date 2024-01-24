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


namespace Xibo\Service;

use Carbon\Carbon;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Factory\ScheduleFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DeadlockException;
use Xibo\XMR\CollectNowAction;
use Xibo\XMR\DataUpdateAction;

/**
 * Class DisplayNotifyService
 * @package Xibo\Service
 */
class DisplayNotifyService implements DisplayNotifyServiceInterface
{
    /** @var ConfigServiceInterface */
    private $config;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  PoolInterface */
    private $pool;

    /** @var  PlayerActionServiceInterface */
    private $playerActionService;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var bool */
    private $collectRequired = false;

    /** @var int[] */
    private $displayIds = [];

    /** @var int[] */
    private $displayIdsRequiringActions = [];

    /** @var string[] */
    private $keysProcessed = [];

    /** @inheritdoc */
    public function __construct($config, $log, $store, $pool, $playerActionService, $scheduleFactory)
    {
        $this->config = $config;
        $this->log = $log;
        $this->store = $store;
        $this->pool = $pool;
        $this->playerActionService = $playerActionService;
        $this->scheduleFactory = $scheduleFactory;
    }

    /** @inheritdoc */
    public function init()
    {
        $this->collectRequired = false;
        return $this;
    }

    /** @inheritdoc */
    public function collectNow()
    {
        $this->collectRequired = true;
        return $this;
    }

    /** @inheritdoc */
    public function collectLater()
    {
        $this->collectRequired = false;
        return $this;
    }

    /** @inheritdoc */
    public function processQueue()
    {
        if (count($this->displayIds) <= 0) {
            return;
        }

        $this->log->debug('Process queue of ' . count($this->displayIds) . ' display notifications');

        // We want to do 3 things.
        // 1. Drop the Cache for each displayId
        // 2. Update the mediaInventoryStatus on each DisplayId to 3 (pending)
        // 3. Fire a PlayerAction if appropriate - what is appropriate?!

        // Unique our displayIds
        $displayIds = array_values(array_unique($this->displayIds, SORT_NUMERIC));

        // Make a list of them that we can use in the update statement
        $qmarks = str_repeat('?,', count($displayIds) - 1) . '?';

        try {
            // This runs on the default connection which will already be committed and closed by the time we get
            // here. This doesn't run in a transaction.
            $this->store->updateWithDeadlockLoop(
                'UPDATE `display` SET mediaInventoryStatus = 3 WHERE displayId IN (' . $qmarks . ')',
                $displayIds,
                'default',
                false
            );
        } catch (DeadlockException $deadlockException) {
            $this->log->error('Failed to update media inventory status: ' . $deadlockException->getMessage());
        }

        // Dump the cache
        foreach ($displayIds as $displayId) {
            $this->pool->deleteItem(Display::getCachePrefix() . $displayId);
        }

        // Player actions
        $this->processPlayerActions();
    }

    /**
     * Process Actions
     */
    private function processPlayerActions()
    {
        if (count($this->displayIdsRequiringActions) <= 0) {
            return;
        }

        $this->log->debug('Process queue of ' . count($this->displayIdsRequiringActions) . ' display actions');

        $displayIdsRequiringActions = array_values(array_unique($this->displayIdsRequiringActions, SORT_NUMERIC));
        $qmarks = str_repeat('?,', count($displayIdsRequiringActions) - 1) . '?';
        $displays = $this->store->select(
            'SELECT displayId, xmrChannel, xmrPubKey, display FROM `display` WHERE displayId IN (' . $qmarks . ')',
            $displayIdsRequiringActions
        );

        foreach ($displays as $display) {
            $stdObj = new \stdClass();
            $stdObj->displayId = $display['displayId'];
            $stdObj->xmrChannel = $display['xmrChannel'];
            $stdObj->xmrPubKey = $display['xmrPubKey'];
            $stdObj->display = $display['display'];

            try {
                $this->playerActionService->sendAction($stdObj, new CollectNowAction());
            } catch (\Exception $e) {
                $this->log->notice(
                    'DisplayId ' .
                    $display['displayId'] .
                    ' Save would have triggered Player Action, but the action failed with message: ' . $e->getMessage()
                );
            }
        }
    }

    /** @inheritdoc */
    public function notifyByDisplayId($displayId)
    {
        $this->log->debug('Notify by DisplayId ' . $displayId);

        // Don't process if the displayId is already in the collection (there is little point in running the
        // extra query)
        if (in_array($displayId, $this->displayIds)) {
            return;
        }

        $this->displayIds[] = $displayId;

        if ($this->collectRequired) {
            $this->displayIdsRequiringActions[] = $displayId;
        }
    }

    /** @inheritdoc */
    public function notifyByDisplayGroupId($displayGroupId)
    {
        $this->log->debug('Notify by DisplayGroupId ' . $displayGroupId);

        if (in_array('displayGroup_' . $displayGroupId, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $displayGroupId . ' skipping this time.');
            return;
        }

        $sql = '
          SELECT DISTINCT `lkdisplaydg`.displayId 
            FROM `lkdgdg`
              INNER JOIN `lkdisplaydg`
              ON `lkdisplaydg`.displayGroupID = `lkdgdg`.childId
           WHERE `lkdgdg`.parentId = :displayGroupId
        ';

        foreach ($this->store->select($sql, ['displayGroupId' => $displayGroupId]) as $row) {
            // Don't process if the displayId is already in the collection
            if (in_array($row['displayId'], $this->displayIds)) {
                continue;
            }

            $this->displayIds[] = $row['displayId'];

            $this->log->debug(
                'DisplayGroup[' . $displayGroupId .'] change caused notify on displayId[' .
                $row['displayId'] . ']'
            );

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'displayGroup_' . $displayGroupId;
    }

    /** @inheritdoc */
    public function notifyByCampaignId($campaignId)
    {
        $this->log->debug('Notify by CampaignId ' . $campaignId);

        if (in_array('campaign_' . $campaignId, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $campaignId . ' skipping this time.');
            return;
        }

        $sql = '
            SELECT DISTINCT display.displayId, 
                schedule.eventId, 
                schedule.fromDt, 
                schedule.toDt, 
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
             FROM `schedule`
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON lkdisplaydg.DisplayID = display.displayID
               INNER JOIN (
                  SELECT campaignId
                    FROM campaign
                   WHERE campaign.campaignId = :activeCampaignId
                   UNION
                  SELECT DISTINCT parent.campaignId
                    FROM `lkcampaignlayout` child
                      INNER JOIN `lkcampaignlayout` parent
                      ON parent.layoutId = child.layoutId 
                   WHERE child.campaignId = :activeCampaignId
                      
               ) campaigns
               ON campaigns.campaignId = `schedule`.campaignId
             WHERE (
                  (`schedule`.FromDT < :toDt AND IFNULL(`schedule`.toDt, UNIX_TIMESTAMP()) > :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
              )
            UNION
            SELECT DISTINCT display.DisplayID,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
             WHERE `lkcampaignlayout`.CampaignID = :activeCampaignId2
            UNION
            SELECT `lkdisplaydg`.displayId,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
              FROM `lkdisplaydg`
                INNER JOIN `lklayoutdisplaygroup`
                ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
             WHERE `lkcampaignlayout`.campaignId = :assignedCampaignId
            UNION
            SELECT `schedule_sync`.displayId,
                schedule.eventId,
                schedule.fromDt,
                schedule.toDt,
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
             FROM `schedule`
                INNER JOIN `schedule_sync`
                    ON `schedule_sync`.eventId = `schedule`.eventId
                INNER JOIN `lkcampaignlayout`
                    ON `lkcampaignlayout`.layoutId = `schedule_sync`.layoutId
             WHERE `lkcampaignlayout`.campaignId = :assignedCampaignId
             AND (
                  (`schedule`.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
              )
        ';

        $currentDate = Carbon::now();
        $rfLookAhead = $currentDate->copy()->addSeconds($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD'));

        $params = [
            'fromDt' => $currentDate->subHour()->format('U'),
            'toDt' => $rfLookAhead->format('U'),
            'activeCampaignId' => $campaignId,
            'activeCampaignId2' => $campaignId,
            'assignedCampaignId' => $campaignId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            // Don't process if the displayId is already in the collection (there is little point in running the
            // extra query)
            if (in_array($row['displayId'], $this->displayIds)) {
                continue;
            }

            // Is this schedule active?
            if ($row['eventId'] != 0) {
                $scheduleEvents = $this->scheduleFactory
                    ->createEmpty()
                    ->hydrate($row)
                    ->getEvents($currentDate, $rfLookAhead);

                if (count($scheduleEvents) <= 0) {
                    $this->log->debug(
                        'Skipping eventId ' . $row['eventId'] .
                        ' because it doesnt have any active events in the window'
                    );
                    continue;
                }
            }

            $this->log->debug(
                'Campaign[' . $campaignId .'] 
                change caused notify on displayId[' . $row['displayId'] . ']'
            );

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'campaign_' . $campaignId;
    }

    /** @inheritdoc */
    public function notifyByDataSetId($dataSetId)
    {
        $this->log->debug('notifyByDataSetId: dataSetId: ' . $dataSetId);

        if (in_array('dataSet_' . $dataSetId, $this->keysProcessed)) {
            $this->log->debug('notifyByDataSetId: already processed.');
            return;
        }

        // Set the Sync task to runNow
        $this->store->update('UPDATE `task` SET `runNow` = 1 WHERE `class` LIKE :taskClassLike', [
            'taskClassLike' => '%WidgetSyncTask%',
        ]);

        // Query the schedule for any data connectors.
        // This is a simple test to see if there are ever any schedules for this dataSetId
        // TODO: this could be improved.
        $sql = '
            SELECT DISTINCT display.displayId
             FROM `schedule`
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON `lkdisplaydg`.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON `lkdisplaydg`.DisplayID = `display`.displayID
            WHERE `schedule`.dataSetId = :dataSetId
        ';

        foreach ($this->store->select($sql, ['dataSetId' => $dataSetId]) as $row) {
            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'dataSet_' . $dataSetId;
    }

    /** @inheritdoc */
    public function notifyByPlaylistId($playlistId)
    {
        $this->log->debug('Notify by PlaylistId ' . $playlistId);

        if (in_array('playlist_' . $playlistId, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $playlistId . ' skipping this time.');
            return;
        }

        $sql = '
            SELECT DISTINCT display.displayId, 
                schedule.eventId, 
                schedule.fromDt, 
                schedule.toDt, 
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
             FROM `schedule`
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON lkdisplaydg.DisplayID = display.displayID
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.campaignId = `schedule`.campaignId
               INNER JOIN `region`
               ON `lkcampaignlayout`.layoutId = region.layoutId
               INNER JOIN `playlist`
               ON `playlist`.regionId = `region`.regionId
             WHERE `playlist`.playlistId = :playlistId
              AND (
                  (schedule.FromDT < :toDt AND IFNULL(`schedule`.toDt, UNIX_TIMESTAMP()) > :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
              )
            UNION
            SELECT DISTINCT display.DisplayID,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
               INNER JOIN `region`
               ON `lkcampaignlayout`.layoutId = region.layoutId
               INNER JOIN `playlist`
               ON `playlist`.regionId = `region`.regionId
             WHERE `playlist`.playlistId = :playlistId
            UNION
            SELECT `lkdisplaydg`.displayId,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
              FROM `lkdisplaydg`
                INNER JOIN `lklayoutdisplaygroup`
                ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                INNER JOIN `region`
                ON `lkcampaignlayout`.layoutId = region.layoutId
                INNER JOIN `playlist`
                ON `playlist`.regionId = `region`.regionId
             WHERE `playlist`.playlistId = :playlistId
        ';

        $currentDate = Carbon::now();
        $rfLookAhead = $currentDate->copy()->addSeconds($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD'));

        $params = [
            'fromDt' => $currentDate->subHour()->format('U'),
            'toDt' => $rfLookAhead->format('U'),
            'playlistId' => $playlistId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            // Don't process if the displayId is already in the collection (there is little point in running the
            // extra query)
            if (in_array($row['displayId'], $this->displayIds)) {
                continue;
            }

            // Is this schedule active?
            if ($row['eventId'] != 0) {
                $scheduleEvents = $this->scheduleFactory
                    ->createEmpty()
                    ->hydrate($row)
                    ->getEvents($currentDate, $rfLookAhead);

                if (count($scheduleEvents) <= 0) {
                    $this->log->debug(
                        'Skipping eventId ' . $row['eventId'] .
                        ' because it doesnt have any active events in the window'
                    );
                    continue;
                }
            }

            $this->log->debug(
                'Playlist[' . $playlistId .'] change caused notify on displayId[' .
                $row['displayId'] . ']'
            );

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'playlist_' . $playlistId;
    }

    /** @inheritdoc */
    public function notifyByLayoutCode($code)
    {
        if (in_array('layoutCode_' . $code, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $code . ' skipping this time.');
            return;
        }

        $this->log->debug('Notify by Layout Code: ' . $code);

        // Get the Display Ids we need to notify
        $sql = '
            SELECT DISTINCT display.displayId, 
                schedule.eventId, 
                schedule.fromDt, 
                schedule.toDt, 
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
             FROM `schedule`
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON lkdisplaydg.DisplayID = display.displayID
               INNER JOIN (
                  SELECT DISTINCT campaignId
                        FROM layout
                        INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                        INNER JOIN action on layout.layoutId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN action on region.regionId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN playlist ON playlist.regionId = region.regionId
                           INNER JOIN widget on playlist.playlistId = widget.playlistId
                           INNER JOIN action on widget.widgetId = action.sourceId
                    WHERE
                        action.layoutCode = :code AND
                        layout.publishedStatusId = 1
               ) campaigns
               ON campaigns.campaignId = `schedule`.campaignId
             WHERE (
                  (`schedule`.FromDT < :toDt AND IFNULL(`schedule`.toDt, UNIX_TIMESTAMP()) > :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
              )
            UNION
            SELECT DISTINCT display.displayId,
                schedule.eventId,
                schedule.fromDt,
                schedule.toDt,
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
            FROM `schedule`
                     INNER JOIN `lkscheduledisplaygroup`
                        ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                     INNER JOIN `lkdgdg`
                        ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                     INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                     INNER JOIN `display`
                        ON lkdisplaydg.DisplayID = display.displayID
            WHERE schedule.actionLayoutCode = :code 
              AND (
                  (`schedule`.FromDT < :toDt AND IFNULL(`schedule`.toDt, UNIX_TIMESTAMP()) > :fromDt)
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\'
                  )
              )
            UNION
            SELECT DISTINCT display.DisplayID,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
             WHERE `lkcampaignlayout`.CampaignID IN (
                  SELECT DISTINCT campaignId
                        FROM layout
                        INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                        INNER JOIN action on layout.layoutId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN action on region.regionId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN playlist ON playlist.regionId = region.regionId
                           INNER JOIN widget on playlist.playlistId = widget.playlistId
                           INNER JOIN action on widget.widgetId = action.sourceId
                    WHERE
                        action.layoutCode = :code AND layout.publishedStatusId = 1
                    )
            UNION
            SELECT `lkdisplaydg`.displayId,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
              FROM `lkdisplaydg`
                INNER JOIN `lklayoutdisplaygroup`
                ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
             WHERE `lkcampaignlayout`.campaignId IN ( 
                  SELECT DISTINCT campaignId
                        FROM layout
                        INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                        INNER JOIN action on layout.layoutId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN action on region.regionId = action.sourceId
                    WHERE action.layoutCode = :code AND layout.publishedStatusId = 1
                  UNION
                    SELECT DISTINCT campaignId
                      FROM layout
                           INNER JOIN lkcampaignlayout ON lkcampaignlayout.layoutId = layout.layoutId
                           INNER JOIN region ON region.layoutId = layout.layoutId
                           INNER JOIN playlist ON playlist.regionId = region.regionId
                           INNER JOIN widget on playlist.playlistId = widget.playlistId
                           INNER JOIN action on widget.widgetId = action.sourceId
                    WHERE
                        action.layoutCode = :code AND layout.publishedStatusId = 1
                  )
        ';

        $currentDate = Carbon::now();
        $rfLookAhead = $currentDate->copy()->addSeconds($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD'));

        $params = [
            'fromDt' => $currentDate->subHour()->format('U'),
            'toDt' => $rfLookAhead->format('U'),
            'code' => $code
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            // Don't process if the displayId is already in the collection (there is little point in running the
            // extra query)
            if (in_array($row['displayId'], $this->displayIds)) {
                continue;
            }

            // Is this schedule active?
            if ($row['eventId'] != 0) {
                $scheduleEvents = $this->scheduleFactory
                    ->createEmpty()
                    ->hydrate($row)
                    ->getEvents($currentDate, $rfLookAhead);

                if (count($scheduleEvents) <= 0) {
                    $this->log->debug(
                        'Skipping eventId ' . $row['eventId'] .
                        ' because it doesnt have any active events in the window'
                    );
                    continue;
                }
            }

            $this->log->debug(sprintf(
                'Saving Layout with code %s, caused notify on
                 displayId[' . $row['displayId'] . ']',
                $code
            ));

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'layoutCode_' . $code;
    }

    /** @inheritdoc */
    public function notifyByMenuBoardId($menuId)
    {
        $this->log->debug('Notify by MenuBoard ID ' . $menuId);

        if (in_array('menuBoard_' . $menuId, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $menuId . ' skipping this time.');
            return;
        }

        $sql = '
           SELECT DISTINCT display.displayId, 
                schedule.eventId, 
                schedule.fromDt, 
                schedule.toDt, 
                schedule.recurrence_type AS recurrenceType,
                schedule.recurrence_detail AS recurrenceDetail,
                schedule.recurrence_range AS recurrenceRange,
                schedule.recurrenceRepeatsOn,
                schedule.lastRecurrenceWatermark,
                schedule.dayPartId
             FROM `schedule`
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON lkdisplaydg.DisplayID = display.displayID
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.campaignId = `schedule`.campaignId
               INNER JOIN `region`
               ON `region`.layoutId = `lkcampaignlayout`.layoutId
               INNER JOIN `playlist`
               ON `playlist`.regionId = `region`.regionId
               INNER JOIN `widget`
               ON `widget`.playlistId = `playlist`.playlistId
               INNER JOIN `widgetoption`
               ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'menuId\'
                    AND `widgetoption`.value = :activeMenuId
            WHERE (
               (schedule.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
                  OR `schedule`.recurrence_range >= :fromDt 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
               )
           UNION
           SELECT DISTINCT display.displayId,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
               INNER JOIN `region`
               ON `region`.layoutId = `lkcampaignlayout`.layoutId
               INNER JOIN `playlist`
               ON `playlist`.regionId = `region`.regionId
               INNER JOIN `widget`
               ON `widget`.playlistId = `playlist`.playlistId
               INNER JOIN `widgetoption`
               ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'menuId\'
                    AND `widgetoption`.value = :activeMenuId2
           UNION
           SELECT DISTINCT `lkdisplaydg`.displayId,
                0 AS eventId, 
                0 AS fromDt, 
                0 AS toDt, 
                NULL AS recurrenceType, 
                NULL AS recurrenceDetail,
                NULL AS recurrenceRange,
                NULL AS recurrenceRepeatsOn,
                NULL AS lastRecurrenceWatermark,
                NULL AS dayPartId
              FROM `lklayoutdisplaygroup`
                INNER JOIN `lkdgdg`
                ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                INNER JOIN `lkdisplaydg`
                ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                INNER JOIN `region`
                ON `region`.layoutId = `lkcampaignlayout`.layoutId
                INNER JOIN `playlist`
               ON `playlist`.regionId = `region`.regionId
                INNER JOIN `widget`
                ON `widget`.playlistId = `playlist`.playlistId
                INNER JOIN `widgetoption`
                ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'menuId\'
                    AND `widgetoption`.value = :activeMenuId3
        ';

        $currentDate = Carbon::now();
        $rfLookAhead = $currentDate->copy()->addSeconds($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD'));

        $params = [
            'fromDt' => $currentDate->subHour()->format('U'),
            'toDt' => $rfLookAhead->format('U'),
            'activeMenuId' => $menuId,
            'activeMenuId2' => $menuId,
            'activeMenuId3' => $menuId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            // Don't process if the displayId is already in the collection (there is little point in running the
            // extra query)
            if (in_array($row['displayId'], $this->displayIds)) {
                $this->log->debug('displayId ' . $row['displayId'] . ' already in collection, skipping.');
                continue;
            }

            // Is this schedule active?
            if ($row['eventId'] != 0) {
                $scheduleEvents = $this->scheduleFactory
                    ->createEmpty()
                    ->hydrate($row)
                    ->getEvents($currentDate, $rfLookAhead);

                if (count($scheduleEvents) <= 0) {
                    $this->log->debug(
                        'Skipping eventId ' . $row['eventId'] .
                        ' because it doesnt have any active events in the window'
                    );
                    continue;
                }
            }

            $this->log->debug('MenuBoard[' . $menuId .'] change caused notify on displayId[' . $row['displayId'] . ']');

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired) {
                $this->displayIdsRequiringActions[] = $row['displayId'];
            }
        }

        $this->keysProcessed[] = 'menuBoard_' . $menuId;

        $this->log->debug('Finished notify for Menu Board ID ' . $menuId);
    }

    /** @inheritdoc */
    public function notifyDataUpdate(Display $display, int $widgetId): void
    {
        if (in_array('dataUpdate_' . $display->displayId . '_' . $widgetId, $this->keysProcessed)) {
            $this->log->debug('notifyDataUpdate: Already processed displayId: ' . $display->displayId
                . ', widgetId: ' . $widgetId . ', skipping this time.');
            return;
        }
        $this->log->debug('notifyDataUpdate: Process displayId: ' . $display->displayId . ', widgetId: ' . $widgetId);

        try {
            $this->playerActionService->sendAction($display, new DataUpdateAction($widgetId));
        } catch (\Exception $e) {
            $this->log->notice('notifyDataUpdate: displayId: ' . $display->displayId
                . ', save would have triggered Player Action, but the action failed with message: ' . $e->getMessage());
        }
    }
}
