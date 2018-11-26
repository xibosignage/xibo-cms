<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DisplayNotifyService.php)
 */


namespace Xibo\Service;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Exception\DeadlockException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XMR\CollectNowAction;

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

    /** @var  DateServiceInterface */
    private $dateService;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var bool */
    private $collectRequired = false;

    /** @var int[] */
    private $displayIds = [];

    /** @var int[] */
    private $displayIdsRequiringActions = [];

    /** @var string[] */
    private $keysProcessed = [];

    /** @inheritdoc */
    public function __construct($config, $log, $store, $pool, $playerActionService, $dateService, $scheduleFactory, $dayPartFactory)
    {
        $this->config = $config;
        $this->log = $log;
        $this->store = $store;
        $this->pool = $pool;
        $this->playerActionService = $playerActionService;
        $this->dateService = $dateService;
        $this->scheduleFactory = $scheduleFactory;
        $this->dayPartFactory = $dayPartFactory;
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
        if (count($this->displayIds) <= 0)
            return;

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
            $this->store->updateWithDeadlockLoop('UPDATE `display` SET mediaInventoryStatus = 3 WHERE displayId IN (' . $qmarks . ')', $displayIds);
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
        if (count($this->displayIdsRequiringActions) <= 0)
            return;

        $this->log->debug('Process queue of ' . count($this->displayIdsRequiringActions) . ' display actions');

        $displayIdsRequiringActions = array_values(array_unique($this->displayIdsRequiringActions, SORT_NUMERIC));
        $qmarks = str_repeat('?,', count($displayIdsRequiringActions) - 1) . '?';
        $displays = $this->store->select('SELECT displayId, xmrChannel, xmrPubKey FROM `display` WHERE displayId IN (' . $qmarks . ')', $displayIdsRequiringActions);

        foreach ($displays as $display) {
            $stdObj = new \stdClass();
            $stdObj->displayId = $display['displayId'];
            $stdObj->xmrChannel = $display['xmrChannel'];
            $stdObj->xmrPubKey = $display['xmrPubKey'];

            try {
                $this->playerActionService->sendAction($stdObj, new CollectNowAction());
            } catch (\Exception $e) {
                $this->log->notice('DisplayId ' . $display['displayId'] . ' Save would have triggered Player Action, but the action failed with message: ' . $e->getMessage());
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

        if ($this->collectRequired)
            $this->displayIdsRequiringActions[] = $displayId;
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

            $this->log->debug('DisplayGroup[' . $displayGroupId .'] change caused notify on displayId[' . $row['displayId'] . ']');

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
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
                  (`schedule`.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
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
        ';

        $currentDate = $this->dateService->parse();
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
                    $this->log->debug('Skipping eventId ' . $row['eventId'] . ' because it doesnt have any active events in the window');
                    continue;
                }
            }

            $this->log->debug('Campaign[' . $campaignId .'] change caused notify on displayId[' . $row['displayId'] . ']');

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }

        $this->keysProcessed[] = 'campaign_' . $campaignId;
    }

    /** @inheritdoc */
    public function notifyByDataSetId($dataSetId)
    {
        $this->log->debug('Notify by DataSetId ' . $dataSetId);

        if (in_array('dataSet_' . $dataSetId, $this->keysProcessed)) {
            $this->log->debug('Already processed ' . $dataSetId . ' skipping this time.');
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
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId
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
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId2
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
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId3
        ';

        $currentDate = $this->dateService->parse();
        $rfLookAhead = $currentDate->copy()->addSeconds($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD'));

        $params = [
            'fromDt' => $currentDate->subHour()->format('U'),
            'toDt' => $rfLookAhead->format('U'),
            'activeDataSetId' => $dataSetId,
            'activeDataSetId2' => $dataSetId,
            'activeDataSetId3' => $dataSetId
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
                    $this->log->debug('Skipping eventId ' . $row['eventId'] . ' because it doesnt have any active events in the window');
                    continue;
                }
            }

            $this->log->debug('DataSet[' . $dataSetId .'] change caused notify on displayId[' . $row['displayId'] . ']');

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }

        $this->keysProcessed[] = 'dataSet_' . $dataSetId;

        $this->log->debug('Finished notify for dataSetId ' . $dataSetId);
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
                  (schedule.FromDT < :toDt AND IFNULL(`schedule`.toDt, `schedule`.fromDt) > :fromDt) 
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

        $currentDate = $this->dateService->parse();
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
                    $this->log->debug('Skipping eventId ' . $row['eventId'] . ' because it doesnt have any active events in the window');
                    continue;
                }
            }

            $this->log->debug('Playlist[' . $playlistId .'] change caused notify on displayId[' . $row['displayId'] . ']');

            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }

        $this->keysProcessed[] = 'playlist_' . $playlistId;
    }
}