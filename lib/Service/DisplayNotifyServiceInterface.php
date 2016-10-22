<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DisplayNotifyServiceInterface.php)
 */


namespace Xibo\Service;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\DisplayFactory;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface DisplayNotifyServiceInterface
 * @package Xibo\Service
 */
interface DisplayNotifyServiceInterface
{
    /**
     * DisplayNotifyServiceInterface constructor.
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param PoolInterface $pool
     * @param PlayerActionServiceInterface $playerActionService
     */
    public function __construct($config, $store, $log, $pool, $playerActionService);

    /**
     * Initialise
     * @param DisplayFactory $factory
     * @return $this
     */
    public function init($factory);

    /**
     * @return $this
     */
    public function collectNow();

    /**
     * @return $this
     */
    public function collectLater();

    /**
     * Process Queue of Display Notifications
     * @return $this
     */
    public function processQueue();

    /**
     * Notify by Display Id
     * @param $displayId
     */
    public function notifyByDisplayId($displayId);

    /**
     * Notify by Display Group Id
     * @param $displayGroupId
     */
    public function notifyByDisplayGroupId($displayGroupId);

    /**
     * Notify by CampaignId
     * @param $campaignId
     */
    public function notifyByCampaignId($campaignId);

    /**
     * Notify by DataSetId
     * @param $dataSetId
     */
    public function notifyByDataSetId($dataSetId);

    /**
     * Notify by PlaylistId
     * @param $playlistId
     */
    public function notifyByPlaylistId($playlistId);
}