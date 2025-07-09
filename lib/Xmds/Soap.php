<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

namespace Xibo\Xmds;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Monolog\Logger;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Entity\Region;
use Xibo\Entity\RequiredFile;
use Xibo\Entity\Schedule;
use Xibo\Event\DataConnectorScriptRequestEvent;
use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlayerFaultFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\LinkSigner;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Status;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\DeadlockException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;
use Xibo\Xmds\Listeners\XmdsDataConnectorListener;

/**
 * Class Soap
 * @package Xibo\Xmds
 */
class Soap
{
    /**
     * @var Display
     */
    protected $display;

    /** @var Carbon */
    protected $fromFilter;
    /** @var Carbon */
    protected $toFilter;
    /** @var Carbon */
    protected $localFromFilter;
    /** @var Carbon */
    protected $localToFilter;

    /**
     * @var LogProcessor
     */
    protected $logProcessor;

    /** @var  PoolInterface */
    protected $pool;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  TimeSeriesStoreInterface */
    private $timeSeriesStore;

    /** @var  LogServiceInterface */
    private $logService;

    /** @var  SanitizerService */
    private $sanitizerService;

    /** @var  ConfigServiceInterface */
    protected $configService;

    /** @var  RequiredFileFactory */
    protected $requiredFileFactory;

    /** @var  ModuleFactory */
    protected $moduleFactory;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  DataSetFactory */
    protected $dataSetFactory;

    /** @var  DisplayFactory */
    protected $displayFactory;

    /** @var  UserGroupFactory */
    protected $userGroupFactory;

    /** @var  BandwidthFactory */
    protected $bandwidthFactory;

    /** @var  MediaFactory */
    protected $mediaFactory;

    /** @var  WidgetFactory */
    protected $widgetFactory;

    /** @var  RegionFactory */
    protected $regionFactory;

    /** @var  NotificationFactory */
    protected $notificationFactory;

    /** @var  DisplayEventFactory */
    protected $displayEventFactory;

    /** @var  ScheduleFactory */
    protected $scheduleFactory;

    /** @var  DayPartFactory */
    protected $dayPartFactory;

    /** @var  PlayerVersionFactory */
    protected $playerVersionFactory;

    /** @var \Xibo\Factory\SyncGroupFactory */
    protected $syncGroupFactory;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /** @var \Xibo\Factory\CampaignFactory */
    private $campaignFactory;

    /** @var PlayerFaultFactory */
    protected $playerFaultFactory;

    /**
     * Soap constructor.
     * @param LogProcessor $logProcessor
     * @param PoolInterface $pool
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizer
     * @param ConfigServiceInterface $config
     * @param RequiredFileFactory $requiredFileFactory
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param DataSetFactory $dataSetFactory
     * @param DisplayFactory $displayFactory
     * @param UserFactory $userGroupFactory
     * @param BandwidthFactory $bandwidthFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param RegionFactory $regionFactory
     * @param NotificationFactory $notificationFactory
     * @param DisplayEventFactory $displayEventFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DayPartFactory $dayPartFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \Xibo\Factory\CampaignFactory $campaignFactory
     * @param SyncGroupFactory $syncGroupFactory
     */
    public function __construct(
        $logProcessor,
        $pool,
        $store,
        $timeSeriesStore,
        $log,
        $sanitizer,
        $config,
        $requiredFileFactory,
        $moduleFactory,
        $layoutFactory,
        $dataSetFactory,
        $displayFactory,
        $userGroupFactory,
        $bandwidthFactory,
        $mediaFactory,
        $widgetFactory,
        $regionFactory,
        $notificationFactory,
        $displayEventFactory,
        $scheduleFactory,
        $dayPartFactory,
        $playerVersionFactory,
        $dispatcher,
        $campaignFactory,
        $syncGroupFactory,
        $playerFaultFactory
    ) {
        $this->logProcessor = $logProcessor;
        $this->pool = $pool;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->logService = $log;
        $this->sanitizerService = $sanitizer;
        $this->configService = $config;
        $this->requiredFileFactory = $requiredFileFactory;
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->displayFactory = $displayFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->bandwidthFactory = $bandwidthFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->regionFactory = $regionFactory;
        $this->notificationFactory = $notificationFactory;
        $this->displayEventFactory = $displayEventFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dispatcher = $dispatcher;
        $this->campaignFactory = $campaignFactory;
        $this->syncGroupFactory = $syncGroupFactory;
        $this->playerFaultFactory = $playerFaultFactory;
    }

    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Time Series Store
     * @return TimeSeriesStoreInterface
     */
    protected function getTimeSeriesStore()
    {
        return $this->timeSeriesStore;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    protected function getConfig()
    {
        return $this->configService;
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher(): EventDispatcher
    {
        if ($this->dispatcher === null) {
            $this->getLog()->error('getDispatcher: [soap] No dispatcher found, returning an empty one');
            $this->dispatcher = new EventDispatcher();
        }

        return $this->dispatcher;
    }

    /**
     * Get Required Files (common)
     * @param $serverKey
     * @param $hardwareKey
     * @param bool $httpDownloads
     * @param bool $isSupportsDataUrl Does the callee support data URLs in widgets?
     * @param bool $isSupportsDependency Does the callee support a separate dependency call?
     * @return string
     * @throws \DOMException
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    protected function doRequiredFiles(
        $serverKey,
        $hardwareKey,
        bool $httpDownloads,
        bool $isSupportsDataUrl = false,
        bool $isSupportsDependency = false
    ) {
        $this->logProcessor->setRoute('RequiredFiles');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Sender', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/requiredFiles');
        $cache->setInvalidationMethod(Invalidation::OLD);

        $output = $cache->get();

        // Required files are cached for 4 hours
        if ($cache->isHit()) {
            $this->getLog()->info('Returning required files from Cache for display ' . $this->display->display);

            // Resign HTTP links and extend expiry
            $document = new \DOMDocument('1.0');
            $document->loadXML($output);

            $cdnUrl = $this->configService->getSetting('CDN_URL');

            foreach ($document->documentElement->childNodes as $node) {
                if ($node instanceof \DOMElement) {
                    if ($node->getAttribute('download') === 'http') {
                        $type = match ($node->getAttribute('type')) {
                            'layout' => 'L',
                            'media' => 'M',
                            default => 'P',
                        };

                        // HTTP download for a v3 player will have saved the type and fileType as M and media
                        // respectively, which is different to what we use in the URL.
                        $assetType = $node->getAttribute('assetType');
                        if (!empty($assetType)) {
                            $type = 'P';
                            $fileType = $assetType;
                        } else {
                            $fileType = $node->getAttribute('fileType');
                        }

                        // Use the realId if we have it.
                        $realId = $node->getAttribute('realId');
                        if (empty($realId)) {
                            $realId = $node->getAttribute('id');
                        }

                        // Generate a new URL.
                        $newUrl = LinkSigner::generateSignedLink(
                            $this->display,
                            $this->configService->getApiKeyDetails()['encryptionKey'],
                            $cdnUrl,
                            $type,
                            $realId,
                            $node->getAttribute('saveAs'),
                            $fileType,
                        );

                        $node->setAttribute('path', $newUrl);
                    }
                }
            }

            $output = $document->saveXML();

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

            return $output;
        }

        // We need to regenerate
        // Lock the cache
        $cache->lock(120);

        // Get all required files for this display.
        // we will use this to drop items from the requirefile table if they are no longer in required files
        $rfIds = array_map(function ($element) {
            return intval($element['rfId']);
        }, $this->getStore()->select('SELECT rfId FROM `requiredfile` WHERE displayId = :displayId', [
            'displayId' => $this->display->displayId
        ]));
        $newRfIds = [];

        // Build a new RF
        $requiredFilesXml = new \DOMDocument('1.0');
        $fileElements = $requiredFilesXml->createElement('files');
        $requiredFilesXml->appendChild($fileElements);

        // Filter criteria
        $this->setDateFilters();

        // Add the filter dates to the RF xml document
        $fileElements->setAttribute('generated', Carbon::now()->format(DateFormatHelper::getSystemFormat()));
        $fileElements->setAttribute('fitlerFrom', $this->fromFilter->format(DateFormatHelper::getSystemFormat()));
        $fileElements->setAttribute('fitlerTo', $this->toFilter->format(DateFormatHelper::getSystemFormat()));

        // Player dependencies
        // -------------------
        // Output player dependencies such as the player bundle, fonts, etc.
        // 1) get a list of dependencies.
        $dependencyListEvent = new XmdsDependencyListEvent($this->display);

        $this->getDispatcher()->dispatch($dependencyListEvent, 'xmds.dependency.list');

        // 2) Each dependency returned needs to be added to RF.
        foreach ($dependencyListEvent->getDependencies() as $dependency) {
            // Add a new required file for this.
            $this->addDependency(
                $newRfIds,
                $requiredFilesXml,
                $fileElements,
                $httpDownloads,
                $isSupportsDependency,
                $dependency
            );
        }
        // ------------
        // Dependencies finished

        // Default Layout
        $defaultLayoutId = ($this->display->defaultLayoutId === null || $this->display->defaultLayoutId === 0)
            ? $this->getConfig()->getSetting('DEFAULT_LAYOUT')
            : $this->display->defaultLayoutId;

        // Get a list of all layout ids in the schedule right now
        // including any layouts that have been associated to our Display Group
        try {
            $dbh = $this->getStore()->getConnection();

            $SQL = '
                SELECT DISTINCT `lklayoutdisplaygroup`.layoutId
                  FROM `lklayoutdisplaygroup`
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                    INNER JOIN `layout`
                    ON `layout`.layoutID = `lklayoutdisplaygroup`.layoutId
                 WHERE lkdisplaydg.DisplayID = :displayId
                ORDER BY layoutId
            ';

            $params = [
                'displayId' => $this->display->displayId
            ];

            $this->getLog()->sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            // Build a list of Layouts
            $layouts = [];

            // Our layout list will always include the default layout
            if ($defaultLayoutId != null) {
                $layouts[] = $defaultLayoutId;
            }

            // Build up the other layouts into an array
            foreach ($sth->fetchAll() as $row) {
                $parsedRow = $this->getSanitizer($row);
                $layouts[] = $parsedRow->getInt('layoutId');
            }

            // Also look at the schedule
            foreach ($this->scheduleFactory->getForXmds(
                $this->display->displayId,
                $this->fromFilter,
                $this->toFilter
            ) as $row) {
                $parsedRow = $this->getSanitizer($row);
                $schedule = $this->scheduleFactory->createEmpty()->hydrate($row);

                // Is this scheduled event a synchronised timezone?
                // if it is, then we get our events with respect to the timezone of the display
                $isSyncTimezone = ($schedule->syncTimezone == 1 && !empty($this->display->timeZone));

                try {
                    if ($isSyncTimezone) {
                        $scheduleEvents = $schedule->getEvents($this->localFromFilter, $this->localToFilter);
                    } else {
                        $scheduleEvents = $schedule->getEvents($this->fromFilter, $this->toFilter);
                    }
                } catch (GeneralException $e) {
                    $this->getLog()->error('Unable to getEvents for ' . $schedule->eventId);
                    continue;
                }

                if (count($scheduleEvents) <= 0) {
                    continue;
                }

                $this->getLog()->debug(count($scheduleEvents) . ' events for eventId ' . $schedule->eventId);

                // Sync events
                $layoutId = ($schedule->eventTypeId == Schedule::$SYNC_EVENT)
                    ? $parsedRow->getInt('syncLayoutId')
                    : $parsedRow->getInt('layoutId');

                // Layout codes (action events)
                $layoutCode = $parsedRow->getString('actionLayoutCode');
                if ($layoutId != null &&
                    (
                        $schedule->eventTypeId == Schedule::$LAYOUT_EVENT ||
                        $schedule->eventTypeId == Schedule::$OVERLAY_EVENT ||
                        $schedule->eventTypeId == Schedule::$INTERRUPT_EVENT ||
                        $schedule->eventTypeId == Schedule::$CAMPAIGN_EVENT ||
                        $schedule->eventTypeId == Schedule::$MEDIA_EVENT ||
                        $schedule->eventTypeId == Schedule::$PLAYLIST_EVENT ||
                        $schedule->eventTypeId == Schedule::$SYNC_EVENT
                    )
                ) {
                    $layouts[] = $layoutId;
                }

                if (!empty($layoutCode) && $schedule->eventTypeId == Schedule::$ACTION_EVENT) {
                    $actionEventLayout = $this->layoutFactory->getByCode($layoutCode);
                    if ($actionEventLayout->status <= 3) {
                        $layouts[] = $actionEventLayout->layoutId;
                    } else {
                        $this->getLog()->error(sprintf(__('Scheduled Action Event ID %d contains an invalid Layout linked to it by the Layout code.'), $schedule->eventId));
                    }
                }

                // Data Connectors
                if ($isSupportsDependency && $schedule->eventTypeId === Schedule::$DATA_CONNECTOR_EVENT) {
                    $dataSet = $this->dataSetFactory->getById($row['dataSetId']);

                    if ($dataSet->dataConnectorSource != 'user_defined') {
                        // Dispatch an event to save the data connector javascript from the connector
                        $dataConnectorScriptRequestEvent = new DataConnectorScriptRequestEvent($dataSet);
                        $this->getDispatcher()
                            ->dispatch($dataConnectorScriptRequestEvent, DataConnectorScriptRequestEvent::$NAME);
                    }

                    $this->addDependency(
                        $newRfIds,
                        $requiredFilesXml,
                        $fileElements,
                        $httpDownloads,
                        $isSupportsDependency,
                        XmdsDataConnectorListener::getDataConnectorDependency($libraryLocation, $row['dataSetId']),
                    );
                }
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of layouts. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get a list of layouts');
        }

        // workout if any of the layouts we have in our list has Actions pointing to another Layout.
        $actionLayoutIds = [];
        $processedLayoutIds = [];
        foreach ($layouts as $layoutId) {
            // this is recursive function, as we need to get 2nd level nesting and beyond
            $this->layoutFactory->getActionPublishedLayoutIds($layoutId, $actionLayoutIds, $processedLayoutIds);
        }

        // merge the Action layouts to our array, we need the player to download all resources on them
        if (!empty($actionLayoutIds)) {
            $layouts = array_unique(array_merge($layouts, $actionLayoutIds));
        }

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        try {
            $dbh = $this->getStore()->getConnection();

            // Run a query to get all required files for this display.
            // Include the following:
            // DownloadOrder:
            //  1 - Media Linked to Displays
            //  2 - Media Linked to Widgets in the Scheduled Layouts (linked through Playlists)
            //  3 - Background Images for all Scheduled Layouts
            //  4 - Media linked to display profile (linked through PlayerSoftware)
            $SQL = "
                SELECT 1 AS DownloadOrder,
                    `media`.storedAs AS path,
                    `media`.mediaID AS id,
                    `media`.`MD5`,
                    `media`.FileSize,
                    `media`.released
                  FROM `media`
                    INNER JOIN `display_media`
                    ON `display_media`.mediaid = `media`.mediaId
                 WHERE `display_media`.displayId = :displayId
                UNION ALL
                SELECT 2 AS DownloadOrder,
                    `media`.storedAs AS path,
                    `media`.mediaID AS id,
                    `media`.`MD5`,
                    `media`.FileSize,
                    `media`.released
                  FROM `media`
                    INNER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.MediaID
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkmediadisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                UNION ALL
                SELECT 3 AS DownloadOrder,
                    `media`.storedAs AS path,
                    `media`.mediaID AS id,
                    `media`.`MD5`,
                    `media`.FileSize,
                    `media`.released
                  FROM region
                    INNER JOIN playlist
                    ON playlist.regionId = region.regionId
                    INNER JOIN lkplaylistplaylist
                    ON lkplaylistplaylist.parentId = playlist.playlistId
                    INNER JOIN widget
                    ON widget.playlistId = lkplaylistplaylist.childId
                    INNER JOIN lkwidgetmedia
                    ON widget.widgetId = lkwidgetmedia.widgetId
                    INNER JOIN media
                    ON media.mediaId = lkwidgetmedia.mediaId
                 WHERE region.layoutId IN (%s)
                UNION ALL
                SELECT 4 AS DownloadOrder,
                     `media`.storedAs AS path,
                     `media`.mediaId AS id,
                     `media`.`MD5`,
                     `media`.FileSize,
                     `media`.released
                  FROM `media`
                 WHERE `media`.mediaID IN (
                    SELECT backgroundImageId
                      FROM `layout`
                     WHERE layoutId IN (%s)
                 )
            ";

            $params = ['displayId' => $this->display->displayId];

            $SQL .= ' ORDER BY DownloadOrder ';

            // Sub layoutId list
            $SQL = sprintf($SQL, $layoutIdList, $layoutIdList);

            $this->getLog()->sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            // Prepare a SQL statement in case we need to update the MD5 and FileSize on media nodes.
            $mediaSth = $dbh->prepare('UPDATE media SET `MD5` = :md5, FileSize = :size WHERE MediaID = :mediaid');

            // Keep a list of path names added to RF to prevent duplicates
            $pathsAdded = [];
            $cdnUrl = $this->configService->getSetting('CDN_URL');

            foreach ($sth->fetchAll() as $row) {
                $parsedRow = $this->getSanitizer($row);
                // Media
                $path = $parsedRow->getString('path');
                $id = $parsedRow->getParam('id');
                $md5 = $row['MD5'];
                $fileSize = $parsedRow->getInt('FileSize');
                $released = $parsedRow->getInt('released');

                // Check we haven't added this before
                if (in_array($path, $pathsAdded)) {
                    continue;
                }

                // Do we need to calculate a new MD5?
                // If they are empty calculate them and save them back to the media.
                if ($md5 == '' || $fileSize == 0) {
                    $md5 = md5_file($libraryLocation . $path);
                    $fileSize = filesize($libraryLocation . $path);

                    // Update the media record with this information
                    $mediaSth->execute(['md5' => $md5, 'size' => $fileSize, 'mediaid' => $id]);
                }

                // Add nonce
                $mediaNonce = $this->requiredFileFactory
                    ->createForMedia($this->display->displayId, $id, $fileSize, $path, $released)
                    ->save();

                // skip media which has released == 0 or 2
                if ($released == 0 || $released == 2) {
                    continue;
                }

                $newRfIds[] = $mediaNonce->rfId;

                // Add the file node
                $file = $requiredFilesXml->createElement('file');
                $file->setAttribute('type', 'media');
                $file->setAttribute('id', $id);
                $file->setAttribute('size', $fileSize);
                $file->setAttribute('md5', $md5);

                if ($httpDownloads) {
                    // Serve a link instead (standard HTTP link)
                    $file->setAttribute('path', LinkSigner::generateSignedLink(
                        $this->display,
                        $this->configService->getApiKeyDetails()['encryptionKey'],
                        $cdnUrl,
                        'M',
                        $id,
                        $path,
                    ));
                    $file->setAttribute('saveAs', $path);
                    $file->setAttribute('download', 'http');
                } else {
                    $file->setAttribute('download', 'xmds');
                    $file->setAttribute('path', $path);
                }

                $fileElements->appendChild($file);

                // Add to paths added
                $pathsAdded[] = $path;
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of required files. ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            return new \SoapFault('Sender', 'Unable to get a list of files');
        }

        // Get an array of modules to use
        $modules = $this->moduleFactory->getKeyedArrayOfModules();

        // Reset the paths added array to start again with layouts
        $pathsAdded = [];

        $cdnUrl = $this->configService->getSetting('CDN_URL');

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {
            try {
                // Check we haven't added this before
                if (in_array($layoutId, $pathsAdded)) {
                    continue;
                }

                // Load this layout
                $layout = $this->layoutFactory->concurrentRequestLock($this->layoutFactory->loadById($layoutId));
                try {
                    $layout->loadPlaylists();

                    // Make sure its XLF is up-to-date
                    $path = $layout->xlfToDisk(['notify' => false]);
                } finally {
                    $this->layoutFactory->concurrentRequestRelease($layout);
                }

                // If the status is *still* 4, then we skip this layout as it cannot build
                if ($layout->status === Status::$STATUS_INVALID) {
                    $this->getLog()->debug('Skipping layoutId ' . $layout->layoutId . ' which wont build');
                    continue;
                }

                // For layouts the MD5 column is the layout xml
                $fileSize = filesize($path);
                $md5 = md5_file($path);
                $fileName = basename($path);

                // Log
                $this->getLog()->debug('MD5 for layoutid ' . $layoutId . ' is: [' . $md5 . ']');

                // Add nonce
                $layoutNonce = $this->requiredFileFactory
                    ->createForLayout($this->display->displayId, $layoutId, $fileSize, $fileName)
                    ->save();
                $newRfIds[] = $layoutNonce->rfId;

                // Add the Layout file element
                $file = $requiredFilesXml->createElement('file');
                $file->setAttribute('type', 'layout');
                $file->setAttribute('id', $layoutId);
                $file->setAttribute('size', $fileSize);
                $file->setAttribute('md5', $md5);

                // add Layout code only if code identifier is set on the Layout.
                if ($layout->code != null) {
                    $file->setAttribute('code', $layout->code);
                }

                // Permissive check for http layouts - always allow unless windows and <= 120
                $supportsHttpLayouts = !($this->display->clientType == 'windows' && $this->display->clientCode <= 120);

                if ($httpDownloads && $supportsHttpLayouts) {
                    // Serve a link instead (standard HTTP link)
                    $file->setAttribute('path', LinkSigner::generateSignedLink(
                        $this->display,
                        $this->configService->getApiKeyDetails()['encryptionKey'],
                        $cdnUrl,
                        'L',
                        $layoutId,
                        $fileName,
                    ));
                    $file->setAttribute('saveAs', $fileName);
                    $file->setAttribute('download', 'http');
                } else {
                    $file->setAttribute('download', 'xmds');
                    $file->setAttribute('path', $layoutId);
                }

                // Get the Layout Modified Date
                // To cover for instances where modifiedDt isn't correctly recorded
                // use the createdDt instead.
                $layoutModifiedDt = Carbon::createFromFormat(
                    DateFormatHelper::getSystemFormat(),
                    $layout->modifiedDt ?? $layout->createdDt
                );

                // merge regions and drawers
                /** @var Region[] $allRegions */
                $allRegions = array_merge($layout->regions, $layout->drawers);

                // Load the layout XML and work out if we have any ticker / text / dataset media items
                // Append layout resources before layout, so they are downloaded first.
                // If layouts are set to expire immediately, the new layout will use the old resources if
                // the layout is downloaded first.
                foreach ($allRegions as $region) {
                    $playlist = $region->getPlaylist();
                    $playlist->setModuleFactory($this->moduleFactory);

                    // Playlists might mean we include a widget more than once per region
                    // if so, we only want to download a single copy of its resource node
                    // if it is included in 2 regions - we most likely want a copy for each
                    $resourcesAdded = [];

                    foreach ($playlist->expandWidgets() as $widget) {
                        if (!array_key_exists($widget->type, $modules)) {
                            $this->getLog()->debug('Unknown type of widget: ' . $widget->type);
                            continue;
                        }

                        // Any global widgets, html based widgets or region specific widgets
                        if ($widget->type === 'global'
                            || $modules[$widget->type]->renderAs === 'html'
                            || $modules[$widget->type]->regionSpecific == 1
                        ) {
                            // If we've already parsed this widget in this region, then don't bother doing it again
                            // we will only generate the same details.
                            if (in_array($widget->widgetId, $resourcesAdded)) {
                                continue;
                            }

                            // We've added this widget already
                            $resourcesAdded[] = $widget->widgetId;

                            // Get the widget modified date
                            // we will use the latter of this vs the layout modified date as the updated attribute
                            // on required files
                            $widgetModifiedDt = Carbon::createFromTimestamp($widget->modifiedDt);

                            // Updated date is the greatest of layout/widget modified date
                            $updatedDt = ($layoutModifiedDt->greaterThan($widgetModifiedDt))
                                ? $layoutModifiedDt
                                : $widgetModifiedDt;

                            // If this is a canvas region, then only send the data, unless we're the global
                            // widget
                            $isShouldSendHtml = $region->type !== 'canvas' || $widget->type === 'global';
                            if ($isShouldSendHtml) {
                                // Add the resource node to the XML for this widfget.
                                $getResourceRf = $this->requiredFileFactory
                                    ->createForGetResource($this->display->displayId, $widget->widgetId)
                                    ->save();
                                $newRfIds[] = $getResourceRf->rfId;

                                // Append this item to required files
                                $resourceFile = $requiredFilesXml->createElement('file');
                                $resourceFile->setAttribute('type', 'resource');
                                $resourceFile->setAttribute('id', $widget->widgetId);
                                $resourceFile->setAttribute('layoutid', $layoutId);
                                $resourceFile->setAttribute('regionid', $region->regionId);
                                $resourceFile->setAttribute('mediaid', $widget->widgetId);
                            }

                            // Get the module
                            $dataModule = $modules[$widget->type];

                            // Does this also have an associated data file?
                            // we add this for < XMDS v7 as well, because the record is used by the widget sync task
                            // the player shouldn't receive it.
                            if ($dataModule->isDataProviderExpected()) {
                                // A node specifically for the widget data.
                                if ($isSupportsDataUrl) {
                                    // Newer player (v4 onward), add widget node for returning data
                                    $dataFile = $requiredFilesXml->createElement('file');
                                    $dataFile->setAttribute('type', 'widget');
                                    $dataFile->setAttribute('id', $widget->widgetId);
                                    $dataFile->setAttribute(
                                        'updateInterval',
                                        $widget->getOptionValue(
                                            'updateInterval',
                                            $dataModule->getPropertyDefault('updateInterval') ?? 120,
                                        )
                                    );
                                    $fileElements->appendChild($dataFile);
                                } else if ($isShouldSendHtml) {
                                    // Older player, needs to change the updated date on the resource node
                                    // Has our widget been updated recently?
                                    // TODO: Does this need to be the most recent updated date for all the widgets in
                                    //  this region?
                                    $dataProvider = $dataModule->createDataProvider($widget);
                                    $dataProvider->setDisplayProperties(
                                        $this->display->latitude ?: $this->getConfig()->getSetting('DEFAULT_LAT'),
                                        $this->display->longitude ?: $this->getConfig()->getSetting('DEFAULT_LONG'),
                                        $this->display->displayId
                                    );

                                    try {
                                        $widgetDataProviderCache = $this->moduleFactory
                                            ->createWidgetDataProviderCache();
                                        $cacheKey = $this->moduleFactory->determineCacheKey(
                                            $dataModule,
                                            $widget,
                                            $this->display->displayId,
                                            $dataProvider,
                                            $dataModule->getWidgetProviderOrNull()
                                        );

                                        // We do not pass a modifiedDt in here because we always expect to be cached
                                        $cacheDt = $widgetDataProviderCache->getCacheDate($dataProvider, $cacheKey);
                                        if ($cacheDt !== null) {
                                            $updatedDt = ($cacheDt->greaterThan($updatedDt))
                                                ? $cacheDt
                                                : $updatedDt;
                                        }
                                    } catch (\Exception) {
                                        $this->getLog()->error(
                                            'doRequiredFiles: Failed to get data cache modified date for widgetId '
                                            . $widget->widgetId
                                        );
                                    }
                                }

                                // Used by WidgetSync to understand when to keep the cache warm
                                $getDataRf = $this->requiredFileFactory
                                    ->createForGetData($this->display->displayId, $widget->widgetId)
                                    ->save();
                                $newRfIds[] = $getDataRf->rfId;
                            }

                            if ($isShouldSendHtml) {
                                // Append our resource node.
                                $resourceFile->setAttribute('updated', $updatedDt->format('U'));
                                $fileElements->appendChild($resourceFile);
                            }
                        }

                        // Add any assets from this widget/template (unless assetId already added)
                        if (!in_array('module_' . $widget->type, $resourcesAdded)) {
                            foreach ($modules[$widget->type]->getAssets() as $asset) {
                                // Do not send assets if they are CMS only
                                if (!$asset->isSendToPlayer()) {
                                    continue;
                                }

                                $asset->updateAssetCache($libraryLocation);

                                // Add a new required file for this.
                                try {
                                    $this->addDependency(
                                        $newRfIds,
                                        $requiredFilesXml,
                                        $fileElements,
                                        $httpDownloads,
                                        $isSupportsDependency,
                                        $asset->getDependency()
                                    );
                                } catch (\Exception $exception) {
                                    $this->getLog()->error('Invalid asset: ' . $exception->getMessage());
                                }
                            }

                            $resourcesAdded[] = 'module_' . $widget->type;
                        }

                        // Templates (there should only be one)
                        if ($modules[$widget->type]->isTemplateExpected()) {
                            $templateId = $widget->getOptionValue('templateId', null);
                            if ($templateId !== null && !in_array('template_' . $templateId, $resourcesAdded)) {
                                // Get this template and its assets
                                $templates = $this->widgetFactory->getTemplatesForWidgets(
                                    $modules[$widget->type],
                                    [$widget]
                                );

                                foreach ($templates as $template) {
                                    foreach ($template->getAssets() as $asset) {
                                        // Do not send assets if they are CMS only
                                        if (!$asset->isSendToPlayer()) {
                                            continue;
                                        }

                                        $asset->updateAssetCache($libraryLocation);

                                        // Add a new required file for this.
                                        try {
                                            $this->addDependency(
                                                $newRfIds,
                                                $requiredFilesXml,
                                                $fileElements,
                                                $httpDownloads,
                                                $isSupportsDependency,
                                                $asset->getDependency()
                                            );
                                        } catch (\Exception $exception) {
                                            $this->getLog()->error('Invalid asset: ' . $exception->getMessage());
                                        }
                                    }
                                }

                                $resourcesAdded[] = 'template_' . $templateId;
                            }
                        }
                    }
                }

                // Append Layout
                $fileElements->appendChild($file);

                // Add to paths added
                $pathsAdded[] = $layoutId;
            } catch (NotFoundException) {
                $this->getLog()->error('Layout not found - ID: ' . $layoutId . ', skipping');
                continue;
            } catch (GeneralException $e) {
                $this->getLog()->error('Cannot generate layout - ID: ' . $layoutId
                    . ', skipping, e = ' . $e->getMessage());
                continue;
            }
        }

        // Add Purge List node
        $purgeList = $requiredFilesXml->createElement('purge');
        $fileElements->appendChild($purgeList);

        try {
            $dbh = $this->getStore()->getConnection();

            // get list of mediaId/storedAs that should be purged from the Player storage
            // records in that table older than provided expiryDate, should be removed by the task
            $sth = $dbh->prepare('SELECT mediaId, storedAs FROM purge_list');
            $sth->execute();

            // Add a purge list item for each file
            foreach ($sth->fetchAll() as $row) {
                $item = $requiredFilesXml->createElement('item');
                $item->setAttribute('id', $row['mediaId']);
                $item->setAttribute('storedAs', $row['storedAs']);

                $purgeList->appendChild($item);
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of purge_list files. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get purge list files');
        }

        $this->getLog()->debug($requiredFilesXml->saveXML());

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Cache
        $cache->set($output);

        // RF cache expires every 4 hours
        $cache->expiresAfter(3600*4);
        $this->getPool()->saveDeferred($cache);

        // Remove any required files that remain in the array of rfIds
        $rfIds = array_values(array_diff($rfIds, $newRfIds));
        if (count($rfIds) > 0) {
            $this->getLog()->debug('Removing ' . count($rfIds) . ' from requiredfiles');

            try {
                // Execute this on the default connection
                $this->getStore()->updateWithDeadlockLoop(
                    'DELETE FROM `requiredfile` WHERE rfId IN ('
                        . implode(',', array_fill(0, count($rfIds), '?')) . ')',
                    $rfIds
                );
            } catch (DeadlockException $deadlockException) {
                $this->getLog()->error('Deadlock when deleting required files - ignoring and continuing with request');
            }
        }

        // Set any remaining required files to have 0 bytes requested (as we've generated a new nonce)
        $this->getStore()->update('
            UPDATE `requiredfile`
                SET `bytesRequested` = 0
              WHERE `displayId` = :displayId
                AND `type` NOT IN (\'W\', \'D\')
        ', [
            'displayId' => $this->display->displayId
        ]);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

        return $output;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param array $options
     * @return mixed
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doSchedule($serverKey, $hardwareKey, $options = [])
    {
        $this->logProcessor->setRoute('Schedule');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);
        $options = array_merge(['dependentsAsNodes' => false, 'includeOverlays' => false], $options);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Sender', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/schedule');
        $cache->setInvalidationMethod(Invalidation::OLD);

        $output = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->info(sprintf(
                'Returning Schedule from Cache for display %s. Options %s.',
                $this->display->display,
                json_encode($options)
            ));

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$SCHEDULE, strlen($output));

            return $output;
        }

        // We need to regenerate
        // Lock the cache
        $cache->lock(120);

        // Generate the Schedule XML
        $scheduleXml = new \DOMDocument('1.0');
        $layoutElements = $scheduleXml->createElement('schedule');
        $scheduleXml->appendChild($layoutElements);

        // Filter criteria
        $this->setDateFilters();

        // Add the filter dates to the RF xml document
        $layoutElements->setAttribute('generated', Carbon::now()->format(DateFormatHelper::getSystemFormat()));
        $layoutElements->setAttribute('filterFrom', $this->fromFilter->format(DateFormatHelper::getSystemFormat()));
        $layoutElements->setAttribute('filterTo', $this->toFilter->format(DateFormatHelper::getSystemFormat()));

        // Default Layout
        $defaultLayoutId = ($this->display->defaultLayoutId === null || $this->display->defaultLayoutId === 0)
            ? intval($this->getConfig()->getSetting('DEFAULT_LAYOUT', 0))
            : $this->display->defaultLayoutId;

        try {
            // Dependencies
            // ------------
            $moduleDependents = [];
            $dependencyListEvent = new XmdsDependencyListEvent($this->display);
            $this->getDispatcher()->dispatch($dependencyListEvent, 'xmds.dependency.list');

            // Add each resolved dependency to our list of global dependents.
            foreach ($dependencyListEvent->getDependencies() as $dependency) {
                $moduleDependents[] = basename($dependency->path);
            }

            // Add file nodes to the $fileElements
            // Firstly get all the scheduled layouts
            $events = $this->scheduleFactory->getForXmds(
                $this->display->displayId,
                $this->fromFilter,
                $this->toFilter,
                $options
            );

            // If our dependents are nodes, then build a list of layouts we can use to query for nodes
            $layoutDependents = [];

            // Layouts
            $layoutIds = [];

            // Add the default layout if it isn't empty.
            if ($defaultLayoutId !== 0) {
                $layoutIds[] = $defaultLayoutId;
            }

            // Preparse events
            foreach ($events as $event) {
                $layoutId = ($event['eventTypeId'] == Schedule::$SYNC_EVENT) ? $event['syncLayoutId'] : $event['layoutId'];
                if (!empty($layoutId) && !in_array($layoutId, $layoutIds)) {
                    $layoutIds[] = $layoutId;
                }
            }

            $SQL = '
                SELECT DISTINCT `region`.layoutId, `media`.storedAs
                  FROM region
                    INNER JOIN playlist
                    ON playlist.regionId = region.regionId
                    INNER JOIN lkplaylistplaylist
                    ON lkplaylistplaylist.parentId = playlist.playlistId
                    INNER JOIN widget
                    ON widget.playlistId = lkplaylistplaylist.childId
                    INNER JOIN lkwidgetmedia
                    ON widget.widgetId = lkwidgetmedia.widgetId
                    INNER JOIN media
                    ON media.mediaId = lkwidgetmedia.mediaId
                 WHERE region.layoutId IN (' . implode(',', $layoutIds) . ')
                    AND media.type <> \'module\'
            ';

            foreach ($this->getStore()->select($SQL, []) as $row) {
                if (!array_key_exists($row['layoutId'], $layoutDependents))
                    $layoutDependents[$row['layoutId']] = [];

                $layoutDependents[$row['layoutId']][] = $row['storedAs'];
            }

            $this->getLog()->debug(sprintf('Resolved dependents for Schedule: %s.', json_encode($layoutDependents, JSON_PRETTY_PRINT)));

            // Additional nodes.
            $overlayNodes = null;
            $actionNodes = null;
            $dataConnectorNodes = null;

            // We must have some results in here by this point
            foreach ($events as $row) {
                $parsedRow = $this->getSanitizer($row);
                $schedule = $this->scheduleFactory->createEmpty()->hydrate($row);

                // Is this scheduled event a synchronised timezone?
                // if it is, then we get our events with respect to the timezone of the display
                $isSyncTimezone = ($schedule->syncTimezone == 1 && !empty($this->display->timeZone));

                try {
                    if ($isSyncTimezone) {
                        $scheduleEvents = $schedule->getEvents($this->localFromFilter, $this->localToFilter);
                    } else {
                        $scheduleEvents = $schedule->getEvents($this->fromFilter, $this->toFilter);
                    }
                } catch (GeneralException $e) {
                    $this->getLog()->error('Unable to getEvents for ' . $schedule->eventId);
                    continue;
                }

                $this->getLog()->debug(count($scheduleEvents) . ' events for eventId ' . $schedule->eventId);

                // Does this event have any criteria?
                $criteriaNodes = [];
                if (count($scheduleEvents) > 0) {
                    $schedule->load(['loadDisplayGroups' => false]);

                    foreach ($schedule->criteria as $scheduleCriteria) {
                        $criteriaNode = $scheduleXml->createElement('criteria');
                        $criteriaNode->setAttribute('metric', $scheduleCriteria->type . '_' . $scheduleCriteria->metric);
                        $criteriaNode->setAttribute('condition', $scheduleCriteria->condition);
                        $criteriaNode->setAttribute('type', $scheduleCriteria->type);
                        $criteriaNode->textContent = $scheduleCriteria->value;
                        $criteriaNodes[] = $criteriaNode;
                    }
                }

                foreach ($scheduleEvents as $scheduleEvent) {
                    $eventTypeId = $row['eventTypeId'];

                    if ($row['eventTypeId'] == Schedule::$SYNC_EVENT) {
                        $layoutId = $row['syncLayoutId'];
                        $status = intval($row['syncLayoutStatus']);
                        $duration = $row['syncLayoutDuration'];
                    } else {
                        $layoutId = $row['layoutId'];
                        $status = intval($row['status']);
                        $duration = $row['duration'];
                    }

                    $commandCode = $row['code'];

                    // Handle the from/to date of the events we have been returned (they are all returned with respect to
                    // the current CMS timezone)
                    // Does the Display have a timezone?
                    if ($isSyncTimezone) {
                        $fromDt = Carbon::createFromTimestamp($scheduleEvent->fromDt, $this->display->timeZone)->format(DateFormatHelper::getSystemFormat());
                        $toDt = Carbon::createFromTimestamp($scheduleEvent->toDt, $this->display->timeZone)->format(DateFormatHelper::getSystemFormat());
                    } else {
                        $fromDt = Carbon::createFromTimestamp($scheduleEvent->fromDt)->format(DateFormatHelper::getSystemFormat());
                        $toDt = Carbon::createFromTimestamp($scheduleEvent->toDt)->format(DateFormatHelper::getSystemFormat());
                    }

                    $scheduleId = $row['eventId'];
                    $is_priority = $parsedRow->getInt('isPriority');

                    if ($eventTypeId == Schedule::$LAYOUT_EVENT ||
                        $eventTypeId == Schedule::$INTERRUPT_EVENT ||
                        $eventTypeId == Schedule::$CAMPAIGN_EVENT ||
                        $eventTypeId == Schedule::$MEDIA_EVENT ||
                        $eventTypeId == Schedule::$PLAYLIST_EVENT ||
                        $eventTypeId == Schedule::$SYNC_EVENT
                    ) {
                        // Ensure we have a layoutId (we may not if an empty campaign is assigned)
                        // https://github.com/xibosignage/xibo/issues/894
                        if ($layoutId == 0 || empty($layoutId)) {
                            $this->getLog()->info(sprintf('Player has empty event scheduled. Display = %s, EventId = %d', $this->display->display, $scheduleId));
                            continue;
                        }

                        // Check the layout status
                        // https://github.com/xibosignage/xibo/issues/743
                        if ($status > 3) {
                            $this->getLog()->info(sprintf('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId));
                            continue;
                        }

                        // Add a layout node to the schedule
                        $layout = $scheduleXml->createElement('layout');
                        $layout->setAttribute('file', $layoutId);
                        $layout->setAttribute('fromdt', $fromDt);
                        $layout->setAttribute('todt', $toDt);
                        $layout->setAttribute('scheduleid', $scheduleId);
                        $layout->setAttribute('priority', $is_priority);
                        $layout->setAttribute('syncEvent', ($row['eventTypeId'] == Schedule::$SYNC_EVENT) ? 1 : 0);
                        $layout->setAttribute('shareOfVoice', $row['shareOfVoice'] ?? 0);
                        $layout->setAttribute('duration', $duration ?? 0);
                        $layout->setAttribute('isGeoAware', $row['isGeoAware'] ?? 0);
                        $layout->setAttribute('geoLocation', $row['geoLocation'] ?? null);
                        $layout->setAttribute('cyclePlayback', $row['cyclePlayback'] ?? 0);
                        $layout->setAttribute('groupKey', $row['groupKey'] ?? 0);
                        $layout->setAttribute('playCount', $row['playCount'] ?? 0);
                        $layout->setAttribute('maxPlaysPerHour', $row['maxPlaysPerHour'] ?? 0);

                        // Handle dependents
                        if (array_key_exists($layoutId, $layoutDependents)) {
                            if ($options['dependentsAsNodes']) {
                                // Add the dependents to the layout as new nodes
                                $dependentNode = $scheduleXml->createElement('dependents');

                                foreach ($layoutDependents[$layoutId] as $storedAs) {
                                    $fileNode = $scheduleXml->createElement('file', $storedAs);

                                    $dependentNode->appendChild($fileNode);
                                }

                                $layout->appendChild($dependentNode);
                            } else {
                                // Add the dependents to the layout as an attribute
                                $layout->setAttribute('dependents', implode(',', $layoutDependents[$layoutId]));
                            }
                        }

                        // Add criteria notes
                        if (count($criteriaNodes) > 0) {
                            foreach ($criteriaNodes as $criteriaNode) {
                                $layout->appendChild($criteriaNode);
                            }
                        }

                        $layoutElements->appendChild($layout);
                    } elseif ($eventTypeId == Schedule::$COMMAND_EVENT) {
                        // Add a command node to the schedule
                        $command = $scheduleXml->createElement('command');
                        $command->setAttribute('date', $fromDt);
                        $command->setAttribute('scheduleid', $scheduleId);
                        $command->setAttribute('code', $commandCode);

                        // Add criteria notes
                        if (count($criteriaNodes) > 0) {
                            foreach ($criteriaNodes as $criteriaNode) {
                                $command->appendChild($criteriaNode);
                            }
                        }

                        $layoutElements->appendChild($command);
                    } elseif ($eventTypeId == Schedule::$OVERLAY_EVENT && $options['includeOverlays']) {
                        // Ensure we have a layoutId (we may not if an empty campaign is assigned)
                        // https://github.com/xibosignage/xibo/issues/894
                        if ($layoutId == 0 || empty($layoutId)) {
                            $this->getLog()->error(sprintf('Player has empty event scheduled. Display = %s, EventId = %d', $this->display->display, $scheduleId));
                            continue;
                        }

                        // Check the layout status
                        // https://github.com/xibosignage/xibo/issues/743
                        if (intval($row['status']) > 3) {
                            $this->getLog()->error(sprintf('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId));
                            continue;
                        }

                        if ($overlayNodes == null) {
                            $overlayNodes = $scheduleXml->createElement('overlays');
                        }

                        $overlay = $scheduleXml->createElement('overlay');
                        $overlay->setAttribute('file', $layoutId);
                        $overlay->setAttribute('fromdt', $fromDt);
                        $overlay->setAttribute('todt', $toDt);
                        $overlay->setAttribute('scheduleid', $scheduleId);
                        $overlay->setAttribute('priority', $is_priority);
                        $overlay->setAttribute('duration', $row['duration'] ?? 0);
                        $overlay->setAttribute('isGeoAware', $row['isGeoAware'] ?? 0);
                        $overlay->setAttribute('geoLocation', $row['geoLocation'] ?? null);

                        // Add criteria notes
                        if (count($criteriaNodes) > 0) {
                            foreach ($criteriaNodes as $criteriaNode) {
                                $overlay->appendChild($criteriaNode);
                            }
                        }

                        // Add to the overlays node list
                        $overlayNodes->appendChild($overlay);
                    } elseif ($eventTypeId == Schedule::$ACTION_EVENT) {
                        if ($actionNodes == null) {
                            $actionNodes = $scheduleXml->createElement('actions');
                        }
                        $action = $scheduleXml->createElement('action');
                        $action->setAttribute('fromdt', $fromDt);
                        $action->setAttribute('todt', $toDt);
                        $action->setAttribute('scheduleid', $scheduleId);
                        $action->setAttribute('priority', $is_priority);
                        $action->setAttribute('duration', $row['duration'] ?? 0);
                        $action->setAttribute('isGeoAware', $row['isGeoAware'] ?? 0);
                        $action->setAttribute('geoLocation', $row['geoLocation'] ?? null);
                        $action->setAttribute('triggerCode', $row['actionTriggerCode']);
                        $action->setAttribute('actionType', $row['actionType']);
                        $action->setAttribute('layoutCode', $row['actionLayoutCode']);
                        $action->setAttribute('commandCode', $commandCode);

                        // Add criteria notes
                        if (count($criteriaNodes) > 0) {
                            foreach ($criteriaNodes as $criteriaNode) {
                                $action->appendChild($criteriaNode);
                            }
                        }

                        $actionNodes->appendChild($action);
                    } else if ($eventTypeId === Schedule::$DATA_CONNECTOR_EVENT) {
                        if ($dataConnectorNodes == null) {
                            $dataConnectorNodes = $scheduleXml->createElement('dataConnectors');
                        }

                        $dataConnector = $scheduleXml->createElement('connector');
                        $dataConnector->setAttribute('fromdt', $fromDt);
                        $dataConnector->setAttribute('todt', $toDt);
                        $dataConnector->setAttribute('scheduleid', $scheduleId);
                        $dataConnector->setAttribute('priority', $is_priority);
                        $dataConnector->setAttribute('duration', $row['duration'] ?? 0);
                        $dataConnector->setAttribute('isGeoAware', $row['isGeoAware'] ?? 0);
                        $dataConnector->setAttribute('geoLocation', $row['geoLocation'] ?? null);
                        $dataConnector->setAttribute('dataSetId', $row['dataSetId']);
                        $dataConnector->setAttribute('dataParams', urlencode($row['dataSetParams']));
                        $dataConnector->setAttribute('js', 'dataSet_' . $row['dataSetId'] . '.js');

                        // Add criteria notes
                        if (count($criteriaNodes) > 0) {
                            foreach ($criteriaNodes as $criteriaNode) {
                                $dataConnector->appendChild($criteriaNode);
                            }
                        }

                        $dataConnectorNodes->appendChild($dataConnector);
                    }
                }
            }

            // Add the overlay nodes if we had any
            if ($overlayNodes != null) {
                $layoutElements->appendChild($overlayNodes);
            }

            // Add Actions nodes if we had any
            if ($actionNodes != null) {
                $layoutElements->appendChild($actionNodes);
            }

            // Add Data Connector nodes if we had any
            if ($dataConnectorNodes != null) {
                $layoutElements->appendChild($dataConnectorNodes);
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Error getting the schedule. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get the schedule');
        }

        // Default Layout
        try {
            // is it valid?
            $defaultLayout = $this->layoutFactory->getById($defaultLayoutId);

            if ($defaultLayout->status >= Status::$STATUS_INVALID) {
                $this->getLog()->error(sprintf('Player has invalid default Layout. Display = %s, LayoutId = %d',
                    $this->display->display,
                    $defaultLayout->layoutId));
            }

            // Are we interleaving the default? And is the default valid?
            if ($this->display->incSchedule == 1 && $defaultLayout->status < Status::$STATUS_INVALID) {
                // Add as a node at the end of the schedule.
                $layout = $scheduleXml->createElement("layout");

                $layout->setAttribute("file", $defaultLayoutId);
                $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
                $layout->setAttribute("todt", '2030-01-19 00:00:00');
                $layout->setAttribute("scheduleid", 0);
                $layout->setAttribute("priority", 0);
                $layout->setAttribute('duration', $defaultLayout->duration);

                if ($options['dependentsAsNodes'] && array_key_exists($defaultLayoutId, $layoutDependents)) {
                    $dependentNode = $scheduleXml->createElement("dependents");

                    foreach ($layoutDependents[$defaultLayoutId] as $storedAs) {
                        $fileNode = $scheduleXml->createElement("file", $storedAs);

                        $dependentNode->appendChild($fileNode);
                    }

                    $layout->appendChild($dependentNode);
                }

                $layoutElements->appendChild($layout);
            }

            // Add on the default layout node
            $default = $scheduleXml->createElement("default");
            $default->setAttribute("file", $defaultLayoutId);
            $default->setAttribute('duration', $defaultLayout->duration);

            if ($options['dependentsAsNodes'] && array_key_exists($defaultLayoutId, $layoutDependents)) {
                $dependentNode = $scheduleXml->createElement("dependents");

                foreach ($layoutDependents[$defaultLayoutId] as $storedAs) {
                    $fileNode = $scheduleXml->createElement("file", $storedAs);

                    $dependentNode->appendChild($fileNode);
                }

                $default->appendChild($dependentNode);
            }

            $layoutElements->appendChild($default);
        } catch (\Exception $exception) {
            $this->getLog()->error('Default Layout Invalid: ' . $exception->getMessage());

            // Add the splash screen on as the default layout (ID 0)
            $default = $scheduleXml->createElement('default');
            $default->setAttribute('file', 0);
            $layoutElements->appendChild($default);
        }

        // Add on a list of global dependants
        $globalDependents = $scheduleXml->createElement('dependants');

        foreach ($moduleDependents as $dep) {
            $dependent = $scheduleXml->createElement('file', $dep);
            $globalDependents->appendChild($dependent);
        }
        $layoutElements->appendChild($globalDependents);

        // Format the output
        $scheduleXml->formatOutput = true;

        $this->getLog()->debug($scheduleXml->saveXML());

        $output = $scheduleXml->saveXML();

        // Cache
        $cache->set($output);
        $cache->expiresAt($this->toFilter);
        $this->getPool()->saveDeferred($cache);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$SCHEDULE, strlen($output));

        return $output;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $mediaId
     * @param $type
     * @param $reason
     * @return bool|\SoapFault
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason)
    {
        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $logXml
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doSubmitLog($serverKey, $hardwareKey, $logXml)
    {
        $this->logProcessor->setRoute('SubmitLog');

        // Sanitize
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);

        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Sender', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // Load the XML into a DOMDocument
        $document = new \DOMDocument('1.0');

        if (!$document->loadXML($logXml)) {
            $this->getLog()->error(
                'Malformed XML from Player, this will be discarded. The Raw XML String provided is: ' . $logXml
            );
            $this->getLog()->debug('XML log: ' . $logXml);
            return true;
        }

        // Current log level
        $logLevel = \Xibo\Service\LogService::resolveLogLevel($this->display->getLogLevel());
        $discardedLogs = 0;

        // Store processed logs in an array
        $logs = [];

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) {
                continue;
            }
            // Zero out the common vars
            $scheduleId = '';
            $layoutId = '';
            $mediaId = '';
            $method = '';
            $thread = '';
            $type = '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            // Each element should have a category and a date
            $date = $node->getAttribute('date');
            $cat = strtolower($node->getAttribute('category'));

            if ($date == '' || $cat == '') {
                $this->getLog()->error('Log submitted without a date or category attribute');
                continue;
            }

            // special handling for event
            // this will create record in displayevent table
            // and is not added to the logs.
            if ($cat == 'event') {
                $this->createDisplayAlert($node);
                continue;
            }

            // Does this meet the current log level?
            if ($cat == 'error') {
                $recordLogLevel = Logger::ERROR;
                $levelName = 'ERROR';
            } else if ($cat == 'audit' || $cat == 'trace') {
                $recordLogLevel = Logger::DEBUG;
                $levelName = 'DEBUG';
            } else if ($cat == 'debug') {
                $recordLogLevel = Logger::INFO;
                $levelName = 'INFO';
            } else {
                $recordLogLevel = Logger::NOTICE;
                $levelName = 'NOTICE';
            }

            if ($recordLogLevel < $logLevel) {
                $discardedLogs++;
                continue;
            }

            // Adjust the date according to the display timezone
            $date = $this->adjustDisplayLogDate($date, DateFormatHelper::getSystemFormat());

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements) {
                if ($nodeElements->nodeName == 'scheduleID') {
                    $scheduleId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'layoutID') {
                    $layoutId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'mediaID') {
                    $mediaId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'type') {
                    $type = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'method') {
                    $method = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'message') {
                    $message = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == 'thread') {
                    if ($nodeElements->textContent != '') {
                        $thread = '[' . $nodeElements->textContent . '] ';
                    }
                }
            }

            // If the message is still empty, take the entire node content
            if ($message == '') {
                $message = $node->textContent;
            }
            // Add the IDs to the message
            if ($scheduleId != '') {
                $message .= ' scheduleId: ' . $scheduleId;
            }
            if ($layoutId != '') {
                $message .= ' layoutId: ' . $layoutId;
            }
            if ($mediaId != '') {
                $message .= ' mediaId: ' . $mediaId;
            }
            // Trim the page if it is over 50 characters.
            $page = $thread . $method . $type;

            if (strlen($page) >= 50) {
                $page = substr($page, 0, 49);
            }

            $logs[] = [
                $this->logProcessor->getUid(),
                $date,
                'PLAYER',
                $levelName,
                $page,
                'POST',
                $message,
                0,
                $this->display->displayId
            ];
        }

        if (count($logs) > 0) {
            // Insert
            $sql = '
                INSERT INTO `log` (
                    `runNo`,
                    `logdate`,
                    `channel`,
                    `type`,
                    `page`,
                    `function`,
                    `message`,
                    `userid`,
                    `displayid`
                ) VALUES 
            ';

            // Build our query
            $params = [];

            // We're going to make params for each row/column
            $i = 0;
            $row = 0;
            foreach ($logs as $log) {
                $row++;
                $sql .= '(';
                foreach ($log as $field) {
                    $i++;
                    $key = $row . '_' . $i;
                    $sql .= ':' . $key . ',';
                    $params[$key] = $field;
                }
                $sql = rtrim($sql, ',');
                $sql .= '),';
            }
            $sql = rtrim($sql, ',');

            // Insert
            $this->getStore()->update($sql, $params);
        } else {
            $this->getLog()->info('0 logs resolved from log package');
        }

        if ($discardedLogs > 0) {
            $this->getLog()->info(
                'Discarded ' . $discardedLogs . ' logs.
                 Consider adjusting your display profile log level. Resolved level is ' . $logLevel
            );
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$SUBMITLOG, strlen($logXml));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $statXml
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doSubmitStats($serverKey, $hardwareKey, $statXml)
    {
        $this->logProcessor->setRoute('SubmitStats');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);
        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        $this->getLog()->debug('Received XML. ' . $statXml);

        if ($statXml == '') {
            throw new \SoapFault('Receiver', 'Stat XML is empty.');
        }

        // Store an array of parsed stat data for insert
        $now = Carbon::now();

        // Get the display timezone to use when adjusting log dates.
        $defaultTimeZone = $this->getConfig()->getSetting('defaultTimezone');

        // Count stats processed from XML
        $statCount = 0;

        // Load the XML into a DOMDocument
        $document = new \DOMDocument('1.0');
        $document->loadXML($statXml);

        $splashScreenErrorLogged = false;
        $backgroundWidgetErrorLogged = false;
        $widgetIdsNotFound = [];
        $memoryCache = [];

        // Cache of scheduleIds, counts and deleted entities
        $schedules = [];
        $campaigns = [];
        $deletedScheduleIds = [];
        $deletedCampaignIds = [];

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) {
                continue;
            }

            // Each element should have these attributes
            $fromDt = $node->getAttribute('fromdt');
            $toDt = $node->getAttribute('todt');
            $type = strtolower($node->getAttribute('type'));
            $duration = $node->getAttribute('duration');
            $count = $node->getAttribute('count');
            $count = ($count != '') ? (int) $count : 1;

            // Pull out engagements
            $engagements = [];
            foreach ($node->childNodes as $nodeElements) {
                /* @var \DOMElement $nodeElements */
                if ($nodeElements->nodeName == 'engagements') {
                    $i = 0;
                    foreach ($nodeElements->childNodes as $child) {
                        /* @var \DOMElement $child */
                        if ($child->nodeName == 'engagement') {
                            $engagements[$i]['tag'] = $child->getAttribute('tag');
                            $engagements[$i]['duration'] = (int) $child->getAttribute('duration');
                            $engagements[$i]['count'] = (int) $child->getAttribute('count');
                            $i++;
                        }
                    }
                }
            }

            // Validate
            // --------
            // Check we have the minimum required data
            if ($fromDt == '' || $toDt == '' || $type == '') {
                $this->getLog()->info('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            // Exactly the same dates are not supported
            if ($fromDt == $toDt) {
                $this->getLog()->debug('Ignoring a Stat record because the fromDt ('
                    . $fromDt. ') and toDt (' . $toDt. ') are the same');
                continue;
            }

            // Adjust the date according to the display timezone
            // stats are returned in player local date/time
            // the CMS will have been configured with that Player's timezone, so we can convert accordingly.
            try {
                // From date
                $fromDt = ($this->display->timeZone != null)
                    ? Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $fromDt, $this->display->timeZone)
                        ->tz($defaultTimeZone)
                    : Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $fromDt);

                // To date
                $toDt = ($this->display->timeZone != null)
                    ? Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $toDt, $this->display->timeZone)
                        ->tz($defaultTimeZone)
                    : Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $toDt);

                // Do we need to set the duration of this record (we will do for older individually collected stats)
                if ($duration == '') {
                    $duration = $toDt->diffInSeconds($fromDt);
                }
            } catch (\Exception $e) {
                // Protect against the date format being unreadable
                $this->getLog()->error('Stat with a from or to date that cannot be understood. fromDt: '
                    . $fromDt . ', toDt: ' . $toDt . '. E = ' . $e->getMessage());
                continue;
            }

            // From date cannot be ahead of to date
            if ($fromDt > $toDt) {
                $this->getLog()->debug('Ignoring a Stat record because the fromDt ('
                    . $fromDt . ') is greater than toDt (' . $toDt . ')');
                continue;
            }

            // check maximum retention period against stat date, do not record if it's older than max stat age
            $maxAge = intval($this->getConfig()->getSetting('MAINTENANCE_STAT_MAXAGE'));
            if ($maxAge != 0) {
                $maxAgeDate = Carbon::now()->subDays($maxAge);

                if ($toDt->isBefore($maxAgeDate)) {
                    $this->getLog()->debug('Stat older than max retention period, skipping.');
                    continue;
                }
            }

            // If the duration is enormous, then we have an erroneous message from the player
            if ($duration > (86400 * 365)) {
                $this->getLog()->debug('Dates are too far apart');
                continue;
            }

            // Simple validation end
            // ---------------------
            // from here on we need to look things up

            // ScheduleId is supplied to all layout stats, but not event stats.
            $scheduleId = $node->getAttribute('scheduleid');
            if (empty($scheduleId)) {
                $scheduleId = 0;
            }

            $layoutId = $node->getAttribute('layoutid');

            // Ignore the splash screen
            if ($layoutId == 'splash') {
                // only logging this message one time
                if (!$splashScreenErrorLogged) {
                    $splashScreenErrorLogged = true;
                    $this->getLog()->info('Splash Screen Statistic Ignored');
                }
                continue;
            }

            // Slightly confusing behaviour here to support old players without introduction a different call in
            // XMDS v=5.
            // MediaId is actually the widgetId (since 1.8) and the mediaId is looked up by this service
            $widgetId = $node->getAttribute('mediaid');
            $mediaId = null;

            // Ignore old "background" stat records.
            if ($widgetId === 'background') {
                if (!$backgroundWidgetErrorLogged) {
                    $backgroundWidgetErrorLogged = true;
                    $this->getLog()->info('Ignoring old "background" stat record.');
                }
                continue;
            }

            // The mediaId (really widgetId) might well be null
            if ($widgetId == 'null' || $widgetId == '') {
                $widgetId = 0;
            } else {
                // Try to get details for this widget
                try {
                    if (in_array($widgetId, $widgetIdsNotFound)) {
                        continue;
                    }

                    // Do we have it in cache?
                    if (!array_key_exists('w_' . $widgetId, $memoryCache)) {
                        $memoryCache['w_' . $widgetId] = $this->widgetFactory->getMediaByWidgetId($widgetId);
                    }
                    $mediaId = $memoryCache['w_' . $widgetId];

                    // If the mediaId is empty, then we can assume we're a stat for a region specific widget
                    if ($mediaId === null) {
                        $type = 'widget';
                    }
                } catch (NotFoundException $notFoundException) {
                    // Widget isn't found
                    // we can only log this and move on
                    // only logging this message one time
                    if (!in_array($widgetId, $widgetIdsNotFound)) {
                        $widgetIdsNotFound[] = $widgetId;
                        $this->getLog()->error('Stat for a widgetId that doesnt exist: ' . $widgetId);
                    }
                    continue;
                }
            }

            $tag = $node->getAttribute('tag');
            if ($tag == 'null') {
                $tag = null;
            }

            // Cache a count for this scheduleId
            $parentCampaignId = 0;
            $parentCampaign = null;

            if ($scheduleId > 0 && !in_array($scheduleId, $deletedScheduleIds)) {
                try {
                    // Lookup this schedule
                    if (!array_key_exists($scheduleId, $schedules)) {
                        // Look up the campaign.
                        $schedules[$scheduleId] = $this->scheduleFactory->getById($scheduleId);
                    }
                    $parentCampaignId = $schedules[$scheduleId]->parentCampaignId ?? 0;
                } catch (NotFoundException $notFoundException) {
                    $this->getLog()->error('Schedule with ID ' . $scheduleId . ' no-longer exists');
                    $deletedScheduleIds[] = $scheduleId;
                }

                // Does this event have a parent campaign?
                if (!empty($parentCampaignId) && !in_array($parentCampaignId, $deletedCampaignIds)) {
                    try {
                        // Look it up
                        if (!array_key_exists($parentCampaignId, $campaigns)) {
                            $campaigns[$parentCampaignId] = $this->campaignFactory->getById($parentCampaignId);
                        }

                        // Set the parent campaign so that it is recorded with the stat record
                    $parentCampaign = $campaigns[$parentCampaignId];

                    // For a layout stat we should increment the number of plays on the Campaign
                    if ($type === 'layout' && $campaigns[$parentCampaignId]->type === 'ad') {
                        // spend/impressions multiplier for this display
                        $spend = empty($this->display->costPerPlay)
                            ? 0
                            : ($count * $this->display->costPerPlay);
                        $impressions = empty($this->display->impressionsPerPlay)
                            ? 0
                            : ($count * $this->display->impressionsPerPlay);

                            // record
                            $parentCampaign->incrementPlays($count, $spend, $impressions);
                        }
                    } catch (NotFoundException $notFoundException) {
                        $deletedCampaignIds[] = $parentCampaignId;
                        $this->getLog()->error('Campaign with ID ' . $parentCampaignId . ' no-longer exists');
                    }
                }
            }

            // Important - stats will now send display entity instead of displayId
            $stats = [
                'type' => $type,
                'statDate' => $now,
                'fromDt' => $fromDt,
                'toDt' => $toDt,
                'scheduleId' => $scheduleId,
                'display' => $this->display,
                'layoutId' => (int) $layoutId,
                'mediaId' => $mediaId,
                'tag' => $tag,
                'widgetId' => (int) $widgetId,
                'duration' => (int) $duration,
                'count' => $count,
                'engagements' => (count($engagements) > 0) ? $engagements : [],
                'parentCampaignId' => $parentCampaignId,
                'parentCampaign' => $parentCampaign,
            ];

            $this->getTimeSeriesStore()->addStat($stats);

            $statCount++;
        }

        // Insert stats
        if ($statCount > 0) {
            $this->getTimeSeriesStore()->addStatFinalize();
        } else {
            $this->getLog()->info('0 stats resolved from data package');
        }

        // Save ad campaign changes.
        foreach ($campaigns as $campaign) {
            if ($campaign->type === 'ad') {
                $campaign->saveIncrementPlays();
            }
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$SUBMITSTATS, strlen($statXml));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $inventory
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doMediaInventory($serverKey, $hardwareKey, $inventory)
    {
        $this->logProcessor->setRoute('MediaInventory');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);
        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        $this->getLog()->debug($inventory);

        // Check that the $inventory contains something
        if ($inventory == '') {
            throw new \SoapFault('Receiver', 'Inventory Cannot be Empty');
        }

        // Load the XML into a DOMDocument
        $document = new \DOMDocument('1.0');
        $document->loadXML($inventory);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new \DOMXPath($document);
        $fileNodes = $xpath->query('//file');

        foreach ($fileNodes as $node) {
            /* @var \DOMElement $node */

            // What type of file?
            try {
                $requiredFile = null;
                switch ($node->getAttribute('type')) {
                    case 'media':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia(
                            $this->display->displayId,
                            $node->getAttribute('id'),
                            $node->getAttribute('id') < 0 ? 'P' : 'M'
                        );
                        break;

                    case 'layout':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout(
                            $this->display->displayId,
                            $node->getAttribute('id')
                        );
                        break;

                    case 'resource':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndWidget(
                            $this->display->displayId,
                            $node->getAttribute('id')
                        );
                        break;

                    case 'dependency':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndDependency(
                            $this->display->displayId,
                            $node->getAttribute('fileType'),
                            $node->getAttribute('id')
                        );
                        break;

                    default:
                        $this->getLog()->debug(sprintf(
                            'Skipping unknown node in media inventory: %s - %s.',
                            $node->getAttribute('type'),
                            $node->getAttribute('id')
                        ));

                        // continue drops out the switch, continue again goes to the top of the foreach
                        continue 2;
                }

                // File complete?
                $complete = $node->getAttribute('complete');
                $requiredFile->complete = $complete;
                $requiredFile->save();

                // If this item is a 0 then set not complete
                if ($complete == 0) {
                    $mediaInventoryComplete = 2;
                }
            } catch (NotFoundException $e) {
                $this->getLog()->error('Unable to find file in media inventory: '
                    . $node->getAttribute('type') . '. ' . $node->getAttribute('id'));
            }
        }

        $this->display->mediaInventoryStatus = $mediaInventoryComplete;

        // Only call save if this property has actually changed.
        if ($this->display->hasPropertyChanged('mediaInventoryStatus')) {
            $this->getLog()->debug('Media Inventory status changed to ' . $this->display->mediaInventoryStatus);

            // If we are complete, then drop the player nonce cache
            if ($this->display->mediaInventoryStatus == 1) {
                $this->getLog()->debug('Media Inventory tells us that all downloads are complete');
            }

            $this->display->saveMediaInventoryStatus();
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$MEDIAINVENTORY, strlen($inventory));

        return true;
    }

    /**
     * Get Resource for XMDS v6 and lower
     *  outputs the HTML with data included
     * @param string $serverKey
     * @param string $hardwareKey
     * @param integer $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @param bool $isSupportsDataUrl Does the callee support data URLs in widgets?
     * @return mixed
     * @throws NotFoundException
     * @throws \SoapFault
     */
    protected function doGetResource(
        $serverKey,
        $hardwareKey,
        $layoutId,
        $regionId,
        $mediaId,
        bool $isSupportsDataUrl = false
    ) {
        $this->logProcessor->setRoute('GetResource');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'layoutId' => $layoutId,
            'regionId' => $regionId,
            'mediaId' => $mediaId
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');
        $layoutId = $sanitizer->getInt('layoutId');
        $regionId = $sanitizer->getString('regionId');
        $mediaId = $sanitizer->getString('mediaId');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // The MediaId is actually the widgetId
        try {
            $requiredFile = $this->requiredFileFactory->getByDisplayAndWidget(
                $this->display->displayId,
                $mediaId
            );

            $region = $this->regionFactory->getById($regionId);
            $widget = $this->widgetFactory->loadByWidgetId($mediaId);

            // If this is a canvas region we add all our widgets to this.
            if ($region->type === 'canvas') {
                // Render a canvas
                // ---------------
                // A canvas plays all widgets in the region at once.
                // none of them will be anything other than elements
                $widgets = $region->getPlaylist()->widgets;
            } else {
                // Render a widget in a region
                // ---------------------------
                // We have a widget
                $widgets = [$widget];
            }

            // Module is always the first widget
            $module = $this->moduleFactory->getByType($widget->type);

            // Get all templates
            $templates = $this->widgetFactory->getTemplatesForWidgets($module, $widgets);

            $renderer = $this->moduleFactory->createWidgetHtmlRenderer();
            $resource = $renderer->renderOrCache(
                $region,
                $widgets,
                $templates
            );

            // An array of media we have access to.
            // Get all linked media for this player and this widget.
            $media = [];
            $widgetIds = implode(',', array_map(function ($el) {
                return $el->widgetId;
            }, $widgets));

            $sql = '
                SELECT `media`.mediaId, `media`.storedAs 
                  FROM `media` 
                    INNER JOIN `lkwidgetmedia`
                    ON `lkwidgetmedia`.mediaId = `media`.mediaId
                 WHERE `lkwidgetmedia`.widgetId IN (' . $widgetIds . ')
                UNION ALL
                SELECT `media`.mediaId, `media`.storedAs 
                  FROM `media`
                    INNER JOIN `display_media`
                    ON `display_media`.mediaId = `media`.mediaId
                 WHERE `display_media`.displayId = :displayId
            ';

            // There isn't any point using a prepared statement because the widgetIds are substituted at runtime
            foreach ($this->getStore()->select($sql, [
                'displayId' => $this->display->displayId
            ]) as $row) {
                $media[$row['mediaId']] = $row['storedAs'];
            };

            // If this player doesn't support data URLs, then add the data to this response.
            $data = [];

            if (!$isSupportsDataUrl) {
                foreach ($widgets as $widget) {
                    $dataModule = $this->moduleFactory->getByType($widget->type);
                    if ($dataModule->isDataProviderExpected()) {
                        // We only ever return cache.
                        $dataProvider = $dataModule->createDataProvider($widget);
                        $dataProvider->setDisplayProperties(
                            $this->display->latitude ?: $this->getConfig()->getSetting('DEFAULT_LAT'),
                            $this->display->longitude ?: $this->getConfig()->getSetting('DEFAULT_LONG'),
                            $this->display->displayId
                        );

                        // Use the cache if we can.
                        try {
                            $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();
                            $cacheKey = $this->moduleFactory->determineCacheKey(
                                $dataModule,
                                $widget,
                                $this->display->displayId,
                                $dataProvider,
                                $dataModule->getWidgetProviderOrNull()
                            );

                            // We do not pass a modifiedDt in here because we always expect to be cached.
                            if (!$widgetDataProviderCache->decorateWithCache($dataProvider, $cacheKey, null, false)) {
                                throw new NotFoundException('Cache not ready');
                            }

                            $widgetData = $widgetDataProviderCache->decorateForPlayer(
                                $this->configService,
                                $this->display,
                                $this->configService->getApiKeyDetails()['encryptionKey'],
                                $dataProvider->getData(),
                                $media,
                            );
                        } catch (GeneralException $exception) {
                            // No data cached yet, exception
                            $this->getLog()->error('getResource: Failed to get data cache for widgetId '
                                . $widget->widgetId . ', e: ' . $exception->getMessage());
                            throw new \SoapFault('Receiver', 'Cache not ready');
                        }

                        $data[$widget->widgetId] = [
                            'data' => $widgetData,
                            'meta' => $dataProvider->getMeta()
                        ];
                    }
                }
            }

            // Decorate for the player
            $resource = $renderer->decorateForPlayer(
                $this->display,
                $resource,
                $media,
                $isSupportsDataUrl,
                $data,
                $this->moduleFactory->getAssetsFromTemplates($templates)
            );

            if ($resource == '') {
                throw new ControllerNotImplemented();
            }

            // Log bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->save();
        } catch (NotFoundException) {
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        } catch (\Exception $e) {
            // Pass soap faults straight through.
            if ($e instanceof \SoapFault) {
                throw $e;
            }

            $this->getLog()->error('Unknown error during getResource. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \SoapFault('Receiver', 'Unable to get the media resource');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }

    /**
     * Report anonymous usage statistics if they are switched on.
     */
    protected function phoneHome()
    {
        if ($this->getConfig()->getSetting('PHONE_HOME') == 1) {
            // If it's been > 1 day since last PHONE_HOME then send a new report
            $oneDayAgo = Carbon::now()->subDay()->format('U');
            if ($this->getConfig()->getSetting('PHONE_HOME_DATE') < $oneDayAgo) {
                $this->getLog()->debug('Phone Home required for displayId ' . $this->display->displayId);

                try {
                    // Make sure we have a key
                    $key = $this->getConfig()->getSetting('PHONE_HOME_KEY');
                    if (empty($key)) {
                        $this->getConfig()->changeSetting('PHONE_HOME_KEY', bin2hex(random_bytes(16)));
                    }

                    // Set PHONE_HOME_TIME to NOW.
                    $this->getConfig()->changeSetting('PHONE_HOME_DATE', Carbon::now()->format('U'));

                    // Patch
                    // Enhanced usage stats patch from 4.3, with modifications for v3.
                    // ---------------------------------------------------------------
                    $data = [
                        'id' => $key,
                        'version' => Environment::$WEBSITE_VERSION_NAME,
                        'accountId' => defined('ACCOUNT_ID') ? constant('ACCOUNT_ID') : null,
                    ];

                    // What type of install are we?
                    $data['installType'] = 'custom';
                    if (isset($_SERVER['INSTALL_TYPE'])) {
                        $data['installType'] = $_SERVER['INSTALL_TYPE'];
                    } else if ($this->getConfig()->getSetting('cloud_demo') !== null) {
                        $data['installType'] = 'cloud';
                    }

                    // General settings
                    $data['calendarType'] = strtolower($this->getConfig()->getSetting('CALENDAR_TYPE'));
                    $data['defaultLanguage'] = $this->getConfig()->getSetting('DEFAULT_LANGUAGE');
                    $data['isDetectLanguage'] = $this->getConfig()->getSetting('DETECT_LANGUAGE') == 1 ? 1 : 0;

                    // Connectors
                    $data['isSspConnector'] = $this->runQuery('SELECT `isEnabled` FROM `connectors` WHERE `className` = :name', [
                        'name' => '\\Xibo\\Connector\\XiboSspConnector'
                    ]) ?? 0;
                    $data['isDashboardConnector'] =
                        $this->runQuery('SELECT `isEnabled` FROM `connectors` WHERE `className` = :name', [
                            'name' => '\\Xibo\\Connector\\XiboDashboardConnector'
                        ]) ?? 0;

                    // Displays
                    $data = array_merge($data, $this->displayStats());
                    $data['countOfDisplays'] = $this->runQuery(
                        'SELECT COUNT(*) AS countOf FROM `display` WHERE `lastaccessed` > :recently',
                        [
                            'recently' => Carbon::now()->subDays(7)->format('U'),
                        ]
                    );
                    $data['countOfDisplaysTotal'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `display`');
                    $data['countOfDisplaysUnAuthorised'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `display` WHERE licensed = 0');
                    $data['countOfDisplayGroups'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `displaygroup` WHERE isDisplaySpecific = 0');

                    // Users
                    $data['countOfUsers'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `user`');
                    $data['countOfUsersActiveInLastTwentyFour'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE `lastAccessed` > :recently', [
                            'recently' => Carbon::now()->subHours(24)->format('Y-m-d H:i:s'),
                        ]);
                    $data['countOfUserGroups'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `group` WHERE isUserSpecific = 0');
                    $data['countOfUsersWithStatusDashboard'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'statusdashboard.view\'');
                    $data['countOfUsersWithIconDashboard'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'icondashboard.view\'');
                    $data['countOfUsersWithMediaDashboard'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'mediamanager.view\'');
                    $data['countOfUsersWithPlaylistDashboard'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'playlistdashboard.view\'');

                    // Other objects
                    $data['countOfFolders'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `folder`');
                    $data['countOfLayouts'] = $this->runQuery('
                        SELECT COUNT(*) AS countOf
                          FROM `campaign`
                         WHERE `isLayoutSpecific` = 1
                            AND `campaignId` NOT IN (
                                SELECT `lkcampaignlayout`.`campaignId`
                                  FROM `lkcampaignlayout`
                                    INNER JOIN `lktaglayout`
                                    ON `lktaglayout`.`layoutId` = `lkcampaignlayout`.`layoutId`
                                    INNER JOIN `tag`
                                    ON `lktaglayout`.tagId = `tag`.tagId
                                  WHERE `tag`.`tag` = \'template\'
                            )
                    ');
                    $data['countOfLayoutsWithPlaylists'] = $this->runQuery('
                        SELECT COUNT(DISTINCT `region`.`layoutId`) AS countOf 
                          FROM `widget`
                            INNER JOIN `playlist` ON `playlist`.`playlistId` = `widget`.`playlistId`
                            INNER JOIN `region` ON `playlist`.`regionId` = `region`.`regionId`
                         WHERE `widget`.`type` = \'subplaylist\'
                    ');
                    $data['countOfAdCampaigns'] =
                        $this->runQuery('
                        SELECT COUNT(*) AS countOf
                          FROM `campaign`
                        WHERE `type` = \'ad\'
                          AND `isLayoutSpecific` = 0
                    ');
                    $data['countOfListCampaigns'] =
                        $this->runQuery('
                        SELECT COUNT(*) AS countOf 
                          FROM `campaign`
                         WHERE `type` = \'list\'
                          AND `isLayoutSpecific` = 0
                    ');
                    $data['countOfMedia'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `media`');
                    $data['countOfPlaylists'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `playlist` WHERE `regionId` IS NULL');
                    $data['countOfDataSets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset`');
                    $data['countOfRemoteDataSets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset` WHERE `isRemote` = 1');
                    $data['countOfDataConnectorDataSets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset` WHERE `isRealTime` = 1');
                    $data['countOfApplications'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients`');
                    $data['countOfApplicationsUsingClientCredentials'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients` WHERE `clientCredentials` = 1');
                    $data['countOfApplicationsUsingUserCode'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients` WHERE `authCode` = 1');
                    $data['countOfScheduledReports'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `reportschedule`');
                    $data['countOfSavedReports'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `saved_report`');

                    // Widgets
                    $data['countOfImageWidgets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'image\'');
                    $data['countOfVideoWidgets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'video\'');
                    $data['countOfPdfWidgets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'pdf\'');
                    $data['countOfEmbeddedWidgets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'embedded\'');
                    $data['countOfCanvasWidgets'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'global\'');

                    // Schedules
                    $data['countOfSchedulesThisMonth'] = $this->runQuery('
                        SELECT COUNT(*) AS countOf 
                          FROM `schedule`
                         WHERE `fromDt` <= :toDt AND `toDt` > :fromDt
                    ', [
                        'fromDt' => Carbon::now()->startOfMonth()->unix(),
                        'toDt' => Carbon::now()->endOfMonth()->unix(),
                    ]);
                    $data['countOfSyncSchedulesThisMonth'] = $this->runQuery('
                        SELECT COUNT(*) AS countOf 
                          FROM `schedule`
                         WHERE `fromDt` <= :toDt AND `toDt` > :fromDt
                            AND `eventTypeId` = 9
                    ', [
                        'fromDt' => Carbon::now()->startOfMonth()->unix(),
                        'toDt' => Carbon::now()->endOfMonth()->unix(),
                    ]);
                    $data['countOfAlwaysSchedulesThisMonth'] = $this->runQuery('
                        SELECT COUNT(*) AS countOf 
                          FROM `schedule`
                            INNER JOIN `daypart` ON `daypart`.dayPartId = `schedule`.`dayPartId`
                         WHERE `daypart`.`isAlways` = 1
                    ');
                    $data['countOfRecurringSchedules'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `schedule` WHERE IFNULL(recurrence_type, \'\') <> \'\'');
                    $data['countOfSchedulesWithCriteria'] =
                        $this->runQuery('SELECT COUNT(DISTINCT `eventId`) AS countOf FROM `schedule_criteria`');
                    $data['countOfDayParts'] =
                        $this->runQuery('SELECT COUNT(*) AS countOf FROM `daypart`');

                    // Use Guzzle to phone home.
                    // we don't care about the response
                    (new Client())->post(
                        'https://xibosignage.com/api/stats/usage',
                        $this->getConfig()->getGuzzleProxy([
                            'json' => $data,
                        ])
                    );
                } catch (\Exception $e) {
                    $this->getLog()->error('Phone Home: ' . $e->getMessage());
                }
            }
        }
    }

    private function displayStats(): array
    {
        // Retrieve number of displays
        $stats = $this->store->select('
            SELECT client_type, COUNT(*) AS cnt
              FROM `display`
             WHERE licensed = 1
            GROUP BY client_type
        ', []);

        $counts = [
            'total' => 0,
            'android' => 0,
            'windows' => 0,
            'linux' => 0,
            'lg' => 0,
            'sssp' => 0,
            'chromeOS' => 0,
        ];
        foreach ($stats as $stat) {
            $counts['total'] += intval($stat['cnt']);
            $counts[$stat['client_type']] += intval($stat['cnt']);
        }

        return [
            'countOfDisplaysAuthorised' => $counts['total'],
            'countOfAndroid' => $counts['android'],
            'countOfLinux' => $counts['linux'],
            'countOfWebos' => $counts['lg'],
            'countOfWindows' => $counts['windows'],
            'countOfTizen' => $counts['sssp'],
            'countOfChromeOS' => $counts['chromeOS'],
        ];
    }

    /**
     * Run a query and return the value of a property
     * @param string $sql
     * @param string $property
     * @param array $params
     * @return string|null
     */
    private function runQuery($sql, $params = [], $property = 'countOf')
    {
        try {
            $record = $this->store->select($sql, $params);
            return $record[0][$property] ?? null;
        } catch (\PDOException $PDOException) {
            $this->getLog()->debug('runQuery: error returning specific stat, e: ' . $PDOException->getMessage());
            return null;
        }
    }

    /**
     * Authenticates the display
     * @param string $hardwareKey
     * @return bool
     */
    protected function authDisplay($hardwareKey)
    {
        try {
            $this->display = $this->displayFactory->getByLicence($hardwareKey);

            if ($this->display->licensed != 1) {
                return false;
            }

            // Configure our log processor
            $this->logProcessor->setDisplay($this->display->displayId, $this->display->isAuditing());

            return true;

        } catch (NotFoundException $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Alert Display Up
     *  assesses whether a notification is required to be sent for this display, and only does something if the
     *  display is currently marked as offline (i.e. it is coming back online again)
     *  this is only called in Register
     * @throws NotFoundException
     */
    protected function alertDisplayUp(): void
    {
        $maintenanceEnabled = $this->getConfig()->getSetting('MAINTENANCE_ENABLED');

        if ($this->display->loggedIn == 0 && !empty($this->display->displayId)) {
            $this->getLog()->info(sprintf('Display %s was down, now its up.', $this->display->display));

            // Log display up
            $this->displayEventFactory->createEmpty()->eventEnd($this->display->displayId);

            // Do we need to email?
            if ($this->display->emailAlert == 1
                && ($maintenanceEnabled == 'On' || $maintenanceEnabled == 'Protected')
                && $this->getConfig()->getSetting('MAINTENANCE_EMAIL_ALERTS') == 1
            ) {
                // Only send alerts during operating hours.
                if ($this->isInsideOperatingHours()) {
                    $subject = sprintf(__('Recovery for Display %s'), $this->display->display);
                    $body = sprintf(
                        __('Display ID %d is now back online %s'),
                        $this->display->displayId,
                        Carbon::now()->format(DateFormatHelper::getSystemFormat())
                    );

                    // Create a notification assigned to system-wide user groups
                    try {
                        $notification = $this->notificationFactory->createSystemNotification(
                            $subject,
                            $body,
                            Carbon::now(),
                            'display',
                        );

                        // Get groups which have been configured to receive notifications
                        foreach ($this->userGroupFactory
                                     ->getDisplayNotificationGroups($this->display->displayGroupId) as $group) {
                            $notification->assignUserGroup($group);
                        }

                        // Save the notification and insert the links, etc.
                        $notification->save();
                    } catch (\Exception) {
                        $this->getLog()->error(sprintf(
                            'Unable to send email alert for display %s with subject %s and body %s',
                            $this->display->display,
                            $subject,
                            $body
                        ));
                    }
                } else {
                    $this->getLog()->info('Not sending recovery email for Display - '
                        . $this->display->display . ' we are outside of its operating hours');
                }
            } else {
                $this->getLog()->debug(sprintf(
                    'No email required. Email Alert: %d, Enabled: %s, Email Enabled: %s.',
                    $this->display->emailAlert,
                    $maintenanceEnabled,
                    $this->getConfig()->getSetting('MAINTENANCE_EMAIL_ALERTS')
                ));
            }
        }
    }

    /**
     * Is the display currently inside operating hours?
     * @return bool
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function isInsideOperatingHours(): bool
    {
        $dayPartId = $this->display->getSetting('dayPartId', null, ['displayOverride' => true]);

        // If dayPart is configured, check operating hours
        if ($dayPartId !== null) {
            try {
                $dayPart = $this->dayPartFactory->getById($dayPartId);

                $startTimeArray = explode(':', $dayPart->startTime);
                $startTime = Carbon::now()->setTime(intval($startTimeArray[0]), intval($startTimeArray[1]));

                $endTimeArray = explode(':', $dayPart->endTime);
                $endTime = Carbon::now()->setTime(intval($endTimeArray[0]), intval($endTimeArray[1]));

                $now = Carbon::now();

                // handle exceptions
                foreach ($dayPart->exceptions as $exception) {
                    // check if we are on exception day and if so override the startTime and endTime accordingly
                    if ($exception['day'] == Carbon::now()->format('D')) {
                        // Parse the start/end times into the current day.
                        $exceptionsStartTime = explode(':', $exception['start']);
                        $startTime = Carbon::now()->setTime(
                            intval($exceptionsStartTime[0]),
                            intval($exceptionsStartTime[1])
                        );

                        $exceptionsEndTime = explode(':', $exception['end']);
                        $endTime = Carbon::now()->setTime(
                            intval($exceptionsEndTime[0]),
                            intval($exceptionsEndTime[1])
                        );
                    }
                }

                // check if we are inside the operating hours for this display - we use that flag to decide
                // if we need to create a notification and send an email.
                return ($now >= $startTime && $now <= $endTime);
            } catch (NotFoundException) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile for displayId '
                    . $this->display->displayId);
            }
        }

        // Otherwise, assume CMS is within operating hours.
        return true;
    }

    /**
     * Get the Client IP Address
     * @return string
     */
    protected function getIp()
    {
        $clientIp = '';

        $keys = array('X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP) !== false) {
                $clientIp = $_SERVER[$key];
                break;
            }
        }

        return $clientIp;
    }

    /**
     * Check we haven't exceeded the bandwidth limits
     *  - Note, display logging doesn't work in here, this is CMS level logging
     *
     * @param int $displayId The Display ID
     * @return bool true if the check passes, false if it fails
     * @throws NotFoundException
     */
    protected function checkBandwidth($displayId)
    {
        // Uncomment to enable auditing.
        //$this->logProcessor->setDisplay(0, 'debug');

        $this->display = $this->displayFactory->getById($displayId);

        $xmdsLimit = $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');
        $displayBandwidthLimit = $this->display->bandwidthLimit;

        try {
            $bandwidthUsage = 0;

            if ($this->bandwidthFactory->isBandwidthExceeded($xmdsLimit, $bandwidthUsage)) {
                // Bandwidth Exceeded
                // Create a notification if we don't already have one today for this display.
                $subject = __('Bandwidth allowance exceeded');
                $date = Carbon::now();
                $notifications = $this->notificationFactory->getBySubjectAndDate(
                    $subject,
                    $date->startOfDay()->format('U'),
                    $date->addDay()->startOfDay()->format('U')
                );

                if (count($notifications) <= 0) {
                    $body = __(
                        sprintf(
                            'Bandwidth allowance of %s exceeded. Used %s',
                            ByteFormatter::format($xmdsLimit * 1024),
                            ByteFormatter::format($bandwidthUsage)
                        )
                    );

                    $notification = $this->notificationFactory->createSystemNotification(
                        $subject,
                        $body,
                        Carbon::now(),
                        'library'
                    );

                    $notification->save();

                    $this->getLog()->critical($subject);
                }

                return false;

            } elseif ($this->bandwidthFactory->isBandwidthExceeded(
                $displayBandwidthLimit,
                $bandwidthUsage,
                $displayId
            )
            ) {
                // Bandwidth Exceeded
                // Create a notification if we don't already have one today for this display.
                $subject = __(sprintf('Display ID %d exceeded the bandwidth limit', $this->display->displayId));
                $date = Carbon::now();

                $notifications = $this->notificationFactory->getBySubjectAndDate(
                    $subject,
                    $date->startOfDay()->format('U'),
                    $date->addDay()->startOfDay()->format('U')
                );
                if (count($notifications) <= 0) {
                    $body = __(
                        sprintf(
                            'Display bandwidth limit %s exceeded. Used %s for Display Id %d',
                            ByteFormatter::format($displayBandwidthLimit * 1024),
                            ByteFormatter::format($bandwidthUsage),
                            $this->display->displayId
                        )
                    );

                    $notification = $this->notificationFactory->createSystemNotification(
                        $subject,
                        $body,
                        Carbon::now(),
                        'display'
                    );

                    // Add in any displayNotificationGroups, with permissions
                    foreach ($this->userGroupFactory->getDisplayNotificationGroups(
                        $this->display->displayGroupId
                    ) as $group) {
                        $notification->assignUserGroup($group);
                    }

                    $notification->save();

                    $this->getLog()->critical($subject);
                }

                return false;
            } else {
                // Bandwidth not exceeded.
                return true;
            }
        } catch (\Exception $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Log Bandwidth Usage
     * @param int $displayId
     * @param string $type
     * @param int $sizeInBytes
     */
    protected function logBandwidth($displayId, $type, $sizeInBytes)
    {
        $this->bandwidthFactory->createAndSave($type, $displayId, $sizeInBytes);
    }

    /**
     * Add a dependency to the provided DOM element
     * @param array $rfIds
     * @param \DOMDocument $requiredFilesXml
     * @param \DOMElement $fileElements
     * @param bool $httpDownloads
     * @param bool $isSupportsDependency
     * @param Dependency $dependency
     * @throws NotFoundException
     * @throws \DOMException
     */
    private function addDependency(
        array &$rfIds,
        \DOMDocument $requiredFilesXml,
        \DOMElement $fileElements,
        bool $httpDownloads,
        bool $isSupportsDependency,
        Dependency $dependency
    ): void {
        // Create a required file for this dependency
        $dependencyBasePath = basename($dependency->path);
        $rfId = $this->requiredFileFactory
            ->createForGetDependency(
                $this->display->displayId,
                $dependency->fileType,
                $dependency->legacyId,
                $dependency->id,
                $dependencyBasePath,
                $dependency->size,
                $isSupportsDependency
            )
            ->save()->rfId;

        // Make sure we do not already have it.
        if (in_array($rfId, $rfIds)) {
            $this->getLog()->debug('addDependency: ' . $dependency->id . ' already added to XML');
            return;
        }

        // Record this required file's ID as a new one.
        $rfIds[] = $rfId;

        // Add to RF XML
        $file = $requiredFilesXml->createElement('file');

        // HTTP downloads?
        // 3) some dependencies don't support HTTP downloads because they aren't in the library
        $httpFilePath = null;
        if ($httpDownloads && $dependency->isAvailableOverHttp) {
            $httpFilePath = LinkSigner::generateSignedLink(
                $this->display,
                $this->configService->getApiKeyDetails()['encryptionKey'],
                $this->configService->getSetting('CDN_URL'),
                RequiredFile::$TYPE_DEPENDENCY,
                $dependency->id,
                $dependencyBasePath,
                $dependency->fileType,
            );

            $file->setAttribute('download', 'http');
        } else {
            $file->setAttribute('download', 'xmds');
        }

        $file->setAttribute('size', $dependency->size);
        $file->setAttribute('md5', $dependency->md5);
        $file->setAttribute('saveAs', $dependencyBasePath);

        // 4) earlier versions of XMDS do not support GetDependency, and will therefore need to have their
        // dependencies added as media nodes.
        if ($isSupportsDependency) {
            // Soap7+: GetDependency supported
            $file->setAttribute('type', 'dependency');
            $file->setAttribute('fileType', $dependency->fileType);
            if ($httpFilePath !== null) {
                $file->setAttribute('path', $httpFilePath);
            } else {
                $file->setAttribute('path', $dependencyBasePath);
            }
            $file->setAttribute('saveAs', $dependencyBasePath);
            $file->setAttribute('id', $dependency->id);
        } else {
            // We have no choice but to pretend we're a media download.
            // Soap3/4 are modified to cater for this.
            // Soap3: players read, send and save using the `path`. Expects `id.ext`.
            // Soap4: we only have the ID, but we can use HTTP downloads.
            // Soap5,6,7: use Soap4
            $file->setAttribute('type', 'media');
            if ($httpFilePath !== null) {
                $file->setAttribute('path', $httpFilePath);
            } else {
                $file->setAttribute('path', $dependencyBasePath);
            }
            $file->setAttribute('id', $dependency->legacyId);
            $file->setAttribute('fileType', 'media');

            // We need an extra attribute so that we can retrieve the original asset type from cache.
            $file->setAttribute('realId', $dependency->id);
            $file->setAttribute('assetType', $dependency->fileType);
        }

        // Add our node
        $fileElements->appendChild($file);
    }

    /**
     * Set Date Filters
     */
    protected function setDateFilters()
    {
        // Hour to hour time bands for the query
        // Rf lookahead is the number of seconds ahead we should consider.
        // it may well be less than 1 hour, and if so we cannot do hour to hour time bands, we need to do
        // now, forwards.
        // Start with now:
        $fromFilter = Carbon::now();

        // If this Display is in a different timezone, then we need to set that here for these filter criteria
        if (!empty($this->display->timeZone)) {
            $fromFilter->setTimezone($this->display->timeZone);
        }

        // TODO use new sanitizer here
        //$rfLookAhead = $this->getSanitizer()->int($this->getConfig()->getSetting('REQUIRED_FILES_LOOKAHEAD'));
        $rfLookAhead = $this->getConfig()->getSetting('REQUIRED_FILES_LOOKAHEAD');
        if ($rfLookAhead >= 3600) {
            // Go from the top of this hour
            $fromFilter
                ->minute(0)
                ->second(0);
        }

        // If we're set to look ahead, then do so - otherwise grab only a 1 hour slice
        if ($this->getConfig()->getSetting('SCHEDULE_LOOKAHEAD') == 1) {
            $toFilter = $fromFilter->copy()->addSeconds($rfLookAhead);
        } else {
            $toFilter = $fromFilter->copy()->addHour();
        }

        // Make sure our filters are expressed in CMS time, so that when we run the query we don't lose the timezone
        $this->localFromFilter = $fromFilter;
        $this->localToFilter = $toFilter;
        $this->fromFilter = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $fromFilter);
        $this->toFilter = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $toFilter);

        $this->getLog()->debug(
            sprintf(
                'FromDT = %s [%d]. ToDt = %s [%d]',
                $fromFilter->toRssString(),
                $fromFilter->format('U'),
                $toFilter->toRssString(),
                $toFilter->format('U')
            )
        );
    }

    /**
     * Adjust the log date according to the Display timezone.
     * Return current date if we fail.
     * @param string $date
     * @param string $format
     * @return string
     */
    protected function adjustDisplayLogDate(string $date, string $format): string
    {
        // Get the display timezone to use when adjusting log dates.
        $defaultTimeZone = $this->getConfig()->getSetting('defaultTimezone');

        // Adjust the date according to the display timezone
        try {
            $date = ($this->display->timeZone != null)
                ? Carbon::createFromFormat(
                    DateFormatHelper::getSystemFormat(),
                    $date,
                    $this->display->timeZone
                )->tz($defaultTimeZone)
                : Carbon::createFromFormat(
                    DateFormatHelper::getSystemFormat(),
                    $date
                );
            $date = $date->format($format);
        } catch (\Exception $e) {
            // Protect against the date format being unreadable
            $this->getLog()->debug('Date format unreadable on log message: ' . $date);

            // Use now instead
            $date = Carbon::now()->format($format);
        }

        return $date;
    }

    private function createDisplayAlert(\DomElement $alertNode)
    {
        $date = $this->adjustDisplayLogDate($alertNode->getAttribute('date'), 'U');
        $eventType = '';
        $refId = '';
        $detail = '';
        $alertType = '';

        // Get the nodes we are expecting
        foreach ($alertNode->childNodes as $nodeElements) {
            if ($nodeElements->nodeName == 'eventType') {
                $eventType = $nodeElements->textContent;
            } else if ($nodeElements->nodeName == 'refId') {
                $refId = $nodeElements->textContent;
            } else if ($nodeElements->nodeName == 'message') {
                $detail = $nodeElements->textContent;
            } else if ($nodeElements->nodeName == 'alertType') {
                $alertType = $nodeElements->textContent;
            }
        }

        // if alerts should provide both start and end or just start
        if ($alertType == 'both' || $alertType == 'start') {
            $displayEvent = $this->displayEventFactory->createEmpty();

            // new record populated from the submitLog xml.
            $displayEvent->displayId = $this->display->displayId;
            $displayEvent->eventTypeId = $displayEvent->getEventIdFromString($eventType);
            $displayEvent->eventDate = $date;
            $displayEvent->start = $date;
            $displayEvent->end = $alertType == 'both' ? $date : null;
            $displayEvent->refId = empty($refId) ? null : $refId;
            $displayEvent->detail = $detail;

            $displayEvent->save();
        } else if ($alertType == 'end') {
            // if this event pertain only to end date for an existing event record,
            // then set the end date for this display and the specified eventType
            $displayEvent = $this->displayEventFactory->createEmpty();
            $eventTypeId = $displayEvent->getEventIdFromString($eventType);
            empty($refId)
                ? $displayEvent->eventEnd($this->display->displayId, $eventTypeId, $detail, $date)
                : $displayEvent->eventEndByReference($this->display->displayId, $eventTypeId, $refId, $detail);
        }
    }

    /**
     * Collection Interval with offset
     *  calculates an offset for the collection interval based on the displayId and returns it
     *  the offset is plus or minus 10 seconds and will always be the same when given the same displayId
     * @param int $collectionInterval
     * @return int
     */
    protected function collectionIntervalWithOffset(int $collectionInterval): int
    {
        if ($collectionInterval <= 60) {
            $offset = $this->display->displayId % 10;
            return $collectionInterval + ($offset <= 5 ? $offset * -1 : $offset - 5);
        } else {
            $offset = $this->display->displayId % 20;
            return $collectionInterval + ($offset <= 10 ? $offset * -1 : $offset - 10);
        }
    }
}
