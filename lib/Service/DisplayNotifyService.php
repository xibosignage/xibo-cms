<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DisplayNotifyService.php)
 */


namespace Xibo\Service;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Factory\DisplayFactory;
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

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var bool */
    private $collectRequired = false;

    /** @var int[] */
    private $displayIds = [];

    /** @var int[] */
    private $displayIdsRequiringActions = [];

    /** @inheritdoc */
    public function __construct($config, $log, $store, $pool, $playerActionService)
    {
        $this->config = $config;
        $this->log = $log;
        $this->store = $store;
        $this->pool = $pool;
        $this->playerActionService = $playerActionService;
    }

    /** @inheritdoc */
    public function init($factory)
    {
        $this->collectRequired = false;
        $this->displayFactory = $factory;
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

        // Create a new connection
        $this->store->setConnection();

        // We want to do 3 things.
        // 1. Drop the Cache for each displayId
        // 2. Update the mediaInventoryStatus on each DisplayId to 3 (pending)
        // 3. Fire a PlayerAction if appropriate - what is appropriate?!

        // Unique our displayIds
        $displayIds = array_unique($this->displayIds, SORT_NUMERIC);

        // Make a list of them that we can use in the update statement
        $qmarks = str_repeat('?,', count($displayIds) - 1) . '?';

        $this->store->update('UPDATE `display` SET mediaInventoryStatus = 3 WHERE displayId IN (' . $qmarks . ')', $displayIds);

        // Dump the cache
        foreach ($displayIds as $displayId) {
            $this->pool->deleteItem(Display::getCachePrefix() . $displayId);
        }

        // Player actions
        $this->processPlayerActions();

        // Close the connetion
        $this->store->commitIfNecessary();
        $this->store->close();
    }

    /**
     * Process Actions
     */
    private function processPlayerActions()
    {
        $displayIdsRequiringActions = array_unique($this->displayIdsRequiringActions, SORT_NUMERIC);
        $qmarks = str_repeat('?,', count($displayIdsRequiringActions) - 1) . '?';
        $displays = $this->store->select('SELECT displayId, xmrChannel, xmrPubKey FROM `display` WHERE displayId IN (' . $qmarks . ')', $displayIdsRequiringActions);

        foreach ($displays as $display) {
            $stdObj = new \stdClass();
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
        $this->displayIds[] = $displayId;

        if ($this->collectRequired)
            $this->displayIdsRequiringActions[] = $displayId;
    }

    /** @inheritdoc */
    public function notifyByDisplayGroupId($displayGroupId)
    {
        $sql = '
          SELECT DISTINCT `lkdisplaydg`.displayId 
            FROM `lkdgdg`
              INNER JOIN `lkdisplaydg`
              ON `lkdisplaydg`.displayGroupID = `lkdgdg`.childId
           WHERE `lkdgdg`.parentId = :displayGroupId
        ';

        foreach ($this->store->select($sql, ['displayGroupId' => $displayGroupId]) as $row) {
            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }
    }

    /** @inheritdoc */
    public function notifyByCampaignId($campaignId)
    {
        $sql = '
            SELECT DISTINCT display.displayId
             FROM `schedule`
               INNER JOIN `schedule_detail`
               ON schedule_detail.eventid = schedule.eventid
               INNER JOIN `lkscheduledisplaygroup`
               ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
               INNER JOIN `lkdgdg`
               ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
               INNER JOIN `lkdisplaydg`
               ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
               INNER JOIN `display`
               ON lkdisplaydg.DisplayID = display.displayID
             WHERE `schedule`.CampaignID = :activeCampaignId
              AND `schedule_detail`.FromDT < :fromDt
              AND `schedule_detail`.ToDT > :toDt
            UNION
            SELECT DISTINCT display.DisplayID
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
             WHERE `lkcampaignlayout`.CampaignID = :activeCampaignId2
            UNION
            SELECT `lkdisplaydg`.displayId
              FROM `lkdisplaydg`
                INNER JOIN `lklayoutdisplaygroup`
                ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
             WHERE `lkcampaignlayout`.campaignId = :assignedCampaignId
        ';

        $currentDate = time();
        $rfLookAhead = $this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD');
        $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

        $params = [
            'fromDt' => $rfLookAhead,
            'toDt' => $currentDate - 3600,
            'activeCampaignId' => $campaignId,
            'activeCampaignId2' => $campaignId,
            'assignedCampaignId' => $campaignId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }
    }

    /** @inheritdoc */
    public function notifyByDataSetId($dataSetId)
    {
        $sql = '
           SELECT DISTINCT display.displayId
             FROM `schedule`
               INNER JOIN `schedule_detail`
               ON schedule_detail.eventid = schedule.eventid
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
               INNER JOIN `lkregionplaylist`
               ON `lkregionplaylist`.regionId = `region`.regionId
               INNER JOIN `widget`
               ON `widget`.playlistId = `lkregionplaylist`.playlistId
               INNER JOIN `widgetoption`
               ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId
            WHERE `schedule_detail`.FromDT < :fromDt
              AND `schedule_detail`.ToDT > :toDt
           UNION
           SELECT DISTINCT display.displayId
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
               INNER JOIN `region`
               ON `region`.layoutId = `lkcampaignlayout`.layoutId
               INNER JOIN `lkregionplaylist`
               ON `lkregionplaylist`.regionId = `region`.regionId
               INNER JOIN `widget`
               ON `widget`.playlistId = `lkregionplaylist`.playlistId
               INNER JOIN `widgetoption`
               ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId2
           UNION
           SELECT DISTINCT `lkdisplaydg`.displayId
              FROM `lklayoutdisplaygroup`
                INNER JOIN `lkdgdg`
                ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                INNER JOIN `lkdisplaydg`
                ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                INNER JOIN `region`
                ON `region`.layoutId = `lkcampaignlayout`.layoutId
                INNER JOIN `lkregionplaylist`
               ON `lkregionplaylist`.regionId = `region`.regionId
                INNER JOIN `widget`
                ON `widget`.playlistId = `lkregionplaylist`.playlistId
                INNER JOIN `widgetoption`
                ON `widgetoption`.widgetId = `widget`.widgetId
                    AND `widgetoption`.type = \'attrib\'
                    AND `widgetoption`.option = \'dataSetId\'
                    AND `widgetoption`.value = :activeDataSetId3
        ';

        $currentDate = time();
        $rfLookAhead = $this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD');
        $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

        $params = [
            'fromDt' => $rfLookAhead,
            'toDt' => $currentDate - 3600,
            'activeDataSetId' => $dataSetId,
            'activeDataSetId2' => $dataSetId,
            'activeDataSetId3' => $dataSetId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }
    }

    /** @inheritdoc */
    public function notifyByPlaylistId($playlistId)
    {
        $sql = '
            SELECT DISTINCT display.displayId
             FROM `schedule`
               INNER JOIN `schedule_detail`
               ON schedule_detail.eventid = schedule.eventid
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
               INNER JOIN `lkregionplaylist`
               ON `lkregionplaylist`.regionId = `region`.regionId
             WHERE `lkregionplaylist`.playlistId = :playlistId
              AND `schedule_detail`.FromDT < :fromDt
              AND `schedule_detail`.ToDT > :toDt
            UNION
            SELECT DISTINCT display.DisplayID
             FROM `display`
               INNER JOIN `lkcampaignlayout`
               ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
               INNER JOIN `region`
               ON `lkcampaignlayout`.layoutId = region.layoutId
               INNER JOIN `lkregionplaylist`
               ON `lkregionplaylist`.regionId = `region`.regionId
             WHERE `lkregionplaylist`.playlistId = :playlistId
            UNION
            SELECT `lkdisplaydg`.displayId
              FROM `lkdisplaydg`
                INNER JOIN `lklayoutdisplaygroup`
                ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                INNER JOIN `lkcampaignlayout`
                ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                INNER JOIN `region`
                ON `lkcampaignlayout`.layoutId = region.layoutId
                INNER JOIN `lkregionplaylist`
                ON `lkregionplaylist`.regionId = `region`.regionId
             WHERE `lkregionplaylist`.playlistId = :playlistId
        ';

        $currentDate = time();
        $rfLookAhead = $this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD');
        $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

        $params = [
            'fromDt' => $rfLookAhead,
            'toDt' => $currentDate - 3600,
            'playlistId' => $playlistId
        ];

        foreach ($this->store->select($sql, $params) as $row) {
            $this->displayIds[] = $row['displayId'];

            if ($this->collectRequired)
                $this->displayIdsRequiringActions[] = $row['displayId'];
        }
    }
}