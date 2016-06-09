<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Soap.php)
 */


namespace Xibo\Xmds;

define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\RequiredFile;
use Xibo\Entity\Schedule;
use Xibo\Entity\Stat;
use Xibo\Entity\UserGroup;
use Xibo\Entity\Widget;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Random;
use Xibo\Service\ConfigService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogService;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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

    /**
     * @var LogProcessor
     */
    protected $logProcessor;

    /** @var  PoolInterface */
    private $pool;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $logService;

    /** @var  DateServiceInterface */
    private $dateService;

    /** @var  SanitizerServiceInterface */
    private $sanitizerService;

    /** @var  ConfigServiceInterface */
    private $configService;

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

    /**
     * Soap constructor.
     * @param LogProcessor $logProcessor
     * @param PoolInterface $pool
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
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
     */
    public function __construct($logProcessor, $pool, $store, $log, $date, $sanitizer, $config, $requiredFileFactory, $moduleFactory, $layoutFactory, $dataSetFactory, $displayFactory, $userGroupFactory, $bandwidthFactory, $mediaFactory, $widgetFactory, $regionFactory, $notificationFactory, $displayEventFactory)
    {
        $this->logProcessor = $logProcessor;
        $this->pool = $pool;
        $this->store = $store;
        $this->logService = $log;
        $this->dateService = $date;
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
     * Get Log
     * @return LogService
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * Get Config
     * @return ConfigService
     */
    protected function getConfig()
    {
        return $this->configService;
    }

    /**
     * Get Required Files (common)
     * @param $serverKey
     * @param $hardwareKey
     * @param bool $httpDownloads
     * @return string
     * @throws \SoapFault
     */
    protected function doRequiredFiles($serverKey, $hardwareKey, $httpDownloads)
    {
        $this->logProcessor->setRoute('RequiredFiles');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $rfLookAhead = $this->getSanitizer()->int($this->getConfig()->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        $libraryLocation = $this->getConfig()->GetSetting("LIBRARY_LOCATION");

        // auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Sender', 'This display is not licensed.');

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/requiredFiles');

        $output = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->info('Returning required files from Cache for display %s', $this->display->display);

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

            return $output;
        }

        // Generate a new Request Key which we will sign our Required Files with
        $requestKey = Random::generateString(10);

        // Build a new RF
        $requiredFilesXml = new \DOMDocument("1.0");
        $fileElements = $requiredFilesXml->createElement("files");
        $requiredFilesXml->appendChild($fileElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf look ahead
        $rfLookAhead = $fromFilter + 3600 + $rfLookAhead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);
        $toFilter = $rfLookAhead - ($rfLookAhead % 3600);

        if ($this->display->isAuditing())
            $this->getLog()->debug(sprintf('Required files date criteria. FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)));

        try {
            $dbh = $this->getStore()->getConnection();

            // Get a list of all layout ids in the schedule right now
            // including any layouts that have been associated to our Display Group
            $SQL = '
                SELECT layout.layoutID, schedule.DisplayOrder, lkcampaignlayout.DisplayOrder AS LayoutDisplayOrder, schedule_detail.eventId
                  FROM `campaign`
                    INNER JOIN `schedule`
                    ON `schedule`.CampaignID = campaign.CampaignID
                    INNER JOIN schedule_detail
                    ON schedule_detail.eventID = `schedule`.eventID
                    INNER JOIN `lkscheduledisplaygroup`
                    ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                    INNER JOIN `lkcampaignlayout`
                    ON lkcampaignlayout.CampaignID = campaign.CampaignID
                    INNER JOIN `layout`
                    ON lkcampaignlayout.LayoutID = layout.LayoutID
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                    AND schedule_detail.FromDT < :fromdt
                    AND schedule_detail.ToDT > :todt
                    AND layout.retired = 0
                UNION
                SELECT `lklayoutdisplaygroup`.layoutId, 0 AS DisplayOrder, 0 AS LayoutDisplayOrder, 0 AS eventId
                  FROM `lklayoutdisplaygroup`
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                ORDER BY DisplayOrder, LayoutDisplayOrder, eventId
            ';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                'displayId' => $this->display->displayId,
                'fromdt' => $toFilter,
                'todt' => $fromFilter
            ));

            // Our layout list will always include the default layout
            $layouts = array();
            $layouts[] = $this->display->defaultLayoutId;

            // Build up the other layouts into an array
            foreach ($sth->fetchAll() as $row)
                $layouts[] = $this->getSanitizer()->int($row['layoutID']);

        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of layouts. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get a list of layouts');
        }

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        try {
            $dbh = $this->getStore()->getConnection();

            // Run a query to get all required files for this display.
            // Include the following:
            // DownloadOrder:
            //  1 - Module System Files and fonts
            //  2 - Media Linked to Displays
            //  3 - Media Linked to Widgets in the Scheduled Layouts
            //  4 - Background Images for all Scheduled Layouts
            $SQL = "
                SELECT 1 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                   FROM `media`
                 WHERE media.type = 'font'
                    OR (media.type = 'module' AND media.moduleSystemFile = 1)
                UNION ALL
                SELECT 2 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                   FROM `media`
                    INNER JOIN `lkmediadisplaygroup`
                    ON lkmediadisplaygroup.mediaid = media.MediaID
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkmediadisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                 WHERE lkdisplaydg.DisplayID = :displayId
                UNION ALL
                SELECT 3 AS DownloadOrder, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize
                  FROM media
                   INNER JOIN `lkwidgetmedia`
                   ON `lkwidgetmedia`.mediaID = media.MediaID
                   INNER JOIN `widget`
                   ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                   INNER JOIN `lkregionplaylist`
                   ON `lkregionplaylist`.playlistId = `widget`.playlistId
                   INNER JOIN `region`
                   ON `region`.regionId = `lkregionplaylist`.regionId
                   INNER JOIN layout
                   ON layout.LayoutID = region.layoutId
                 WHERE layout.layoutId IN (%s)
                UNION ALL
                SELECT 4 AS DownloadOrder, storedAs AS path, media.mediaId AS id, media.`MD5`, media.FileSize
                  FROM `media`
                 WHERE `media`.mediaID IN (
                    SELECT backgroundImageId
                      FROM `layout`
                     WHERE layoutId IN (%s)
                 )
                ORDER BY DownloadOrder
            ";

            $sth = $dbh->prepare(sprintf($SQL, $layoutIdList, $layoutIdList));
            $sth->execute(array(
                'displayId' => $this->display->displayId
            ));

            // Prepare a SQL statement in case we need to update the MD5 and FileSize on media nodes.
            $mediaSth = $dbh->prepare('UPDATE media SET `MD5` = :md5, FileSize = :size WHERE MediaID = :mediaid');

            // Keep a list of path names added to RF to prevent duplicates
            $pathsAdded = array();

            foreach ($sth->fetchAll() as $row) {
                // Media
                $path = $this->getSanitizer()->string($row['path']);
                $id = $this->getSanitizer()->string($row['id']);
                $md5 = $row['MD5'];
                $fileSize = $this->getSanitizer()->int($row['FileSize']);

                // Check we haven't added this before
                if (in_array($path, $pathsAdded))
                    continue;

                // Do we need to calculate a new MD5?
                // If they are empty calculate them and save them back to the media.
                if ($md5 == '' || $fileSize == 0) {

                    $md5 = md5_file($libraryLocation . $path);
                    $fileSize = filesize($libraryLocation . $path);

                    // Update the media record with this information
                    $mediaSth->execute(array('md5' => $md5, 'size' => $fileSize, 'mediaid' => $id));
                }

                // Add nonce
                $mediaNonce = $this->requiredFileFactory->createForMedia($this->display->displayId, $requestKey, $id, $fileSize, $path);
                $mediaNonce->save();

                // Add the file node
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("type", 'media');
                $file->setAttribute("id", $id);
                $file->setAttribute("size", $fileSize);
                $file->setAttribute("md5", $md5);

                if ($httpDownloads) {
                    // Serve a link instead (standard HTTP link)
                    $file->setAttribute("path", $this->generateRequiredFileDownloadPath($mediaNonce->nonce));
                    $file->setAttribute("saveAs", $path);
                    $file->setAttribute("download", 'http');
                }
                else {
                    $file->setAttribute("download", 'xmds');
                    $file->setAttribute("path", $path);
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
        $modules = $this->moduleFactory->get();

        // Reset the paths added array to start again with layouts
        $pathsAdded = [];

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {

            // Check we haven't added this before
            if (in_array($layoutId, $pathsAdded))
                continue;

            // Load this layout
            $layout = $this->layoutFactory->loadById($layoutId);
            $layout->loadPlaylists();

            // Make sure its XLF is up to date
            $path = $layout->xlfToDisk(['notify' => false]);

            // For layouts the MD5 column is the layout xml
            $fileSize = filesize($path);
            $md5 = md5_file($path);
            $fileName = basename($path);

            // Log
            if ($this->display->isAuditing())
                $this->getLog()->debug('MD5 for layoutid ' . $layoutId . ' is: [' . $md5 . ']');

            // Add nonce
            $layoutNonce = $this->requiredFileFactory->createForLayout($this->display->displayId, $requestKey, $layoutId, $fileSize, $fileName);
            $layoutNonce->save();

            // Add the Layout file element
            $file = $requiredFilesXml->createElement("file");
            $file->setAttribute("type", 'layout');
            $file->setAttribute("id", $layoutId);
            $file->setAttribute("size", $fileSize);
            $file->setAttribute("md5", $md5);

            $supportsHttpLayouts = ($this->display->clientType == 'android' || ($this->display->clientType == 'windows' && $this->display->clientCode > 120));

            if ($httpDownloads && $supportsHttpLayouts) {
                // Serve a link instead (standard HTTP link)
                $file->setAttribute("path", $this->generateRequiredFileDownloadPath($layoutNonce->nonce));
                $file->setAttribute("saveAs", $fileName);
                $file->setAttribute("download", 'http');
            }
            else {
                $file->setAttribute("download", 'xmds');
                $file->setAttribute("path", $layoutId);
            }

            $fileElements->appendChild($file);

            // Get the Layout Modified Date
            $layoutModifiedDt = new \DateTime($layout->modifiedDt);

            // Load the layout XML and work out if we have any ticker / text / dataset media items
            foreach ($layout->regions as $region) {
                /* @var Region $region */
                foreach ($region->playlists as $playlist) {
                    /* @var Playlist $playlist */
                    foreach ($playlist->widgets as $widget) {
                        /* @var Widget $widget */
                        if ($widget->type == 'ticker' ||
                            $widget->type == 'text' ||
                            $widget->type == 'datasetview' ||
                            $widget->type == 'webpage' ||
                            $widget->type == 'embedded' ||
                            $modules[$widget->type]->renderAs == 'html'
                        ) {
                            // Add nonce
                            $this->requiredFileFactory->createForGetResource($this->display->displayId, $requestKey, $layoutId, $region->regionId, $widget->widgetId)->save();

                            // Does the media provide a modified Date?
                            $widgetModifiedDt = $layoutModifiedDt->getTimestamp();

                            if ($widget->type == 'datasetview' || $widget->type == 'ticker') {
                                try {
                                    $dataSetId = $widget->getOption('dataSetId');
                                    $dataSet = $this->dataSetFactory->getById($dataSetId);
                                    $widgetModifiedDt = $dataSet->lastDataEdit;
                                }
                                catch (NotFoundException $e) {
                                    // Widget doesn't have a dataSet associated to it
                                    // This is perfectly valid, so ignore it.
                                }
                            }

                            // Append this item to required files
                            $file = $requiredFilesXml->createElement("file");
                            $file->setAttribute('type', 'resource');
                            $file->setAttribute('id', $widget->widgetId);
                            $file->setAttribute('layoutid', $layoutId);
                            $file->setAttribute('regionid', $region->regionId);
                            $file->setAttribute('mediaid', $widget->widgetId);
                            $file->setAttribute('updated', $widgetModifiedDt);
                            $fileElements->appendChild($file);
                        }
                    }
                }
            }

            // Add to paths added
            $pathsAdded[] = $layoutId;
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        try {
            $dbh = $this->getStore()->getConnection();

            $sth = $dbh->prepare('SELECT MediaID FROM blacklist WHERE DisplayID = :displayid AND isIgnored = 0');
            $sth->execute(array(
                'displayid' => $this->display->displayId
            ));

            // Add a black list element for each file
            foreach ($sth->fetchAll() as $row) {
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("id", $row['MediaID']);

                $blackList->appendChild($file);
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to get a list of blacklisted files. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Phone Home?
        $this->phoneHome();

        if ($this->display->isAuditing())
            $this->getLog()->debug($requiredFilesXml->saveXML());

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Remove unused required files
        RequiredFile::removeUnusedForDisplay($this->getStore(), $this->display->displayId, $requestKey);

        // Cache
        $cache->set($output);

        // Nonces expire after 86400 seconds.
        //$cache->expiresAt($this->getDate()->parse($toFilter, 'U'));
        $cache->expiresAfter(86400);
        $this->getPool()->saveDeferred($cache);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$RF, strlen($output));

        return $output;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param array $options
     * @return mixed
     * @throws \SoapFault
     */
    protected function doSchedule($serverKey, $hardwareKey, $options = [])
    {
        $this->logProcessor->setRoute('Schedule');

        $options = array_merge(['dependentsAsNodes' => false, 'includeOverlays' => false], $options);

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $rfLookAhead = $this->getSanitizer()->int($this->getConfig()->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Sender', "This display client is not licensed");

        // Check the cache
        $cache = $this->getPool()->getItem($this->display->getCacheKey() . '/schedule');

        $output = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->info('Returning Schedule from Cache for display %s. Options %s.', $this->display->display, json_encode($options));

            // Log Bandwidth
            $this->logBandwidth($this->display->displayId, Bandwidth::$SCHEDULE, strlen($output));

            return $output;
        }

        // Generate the Schedule XML
        $scheduleXml = new \DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf lookahead
        $rfLookAhead = $fromFilter + 3600 + $rfLookAhead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);

        if ($this->getConfig()->GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
            $toFilter = $rfLookAhead - ($rfLookAhead % 3600);
        else
            $toFilter = ($fromFilter + 3600) - (($fromFilter + 3600) % 3600);

        if ($this->display->isAuditing())
            $this->getLog()->debug(sprintf('FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)));

        try {
            $dbh = $this->getStore()->getConnection();

            // Get all the module dependants
            $sth = $dbh->prepare("SELECT DISTINCT StoredAs FROM `media` WHERE media.type = 'font' OR (media.type = 'module' AND media.moduleSystemFile = 1) ");
            $sth->execute(array());
            $rows = $sth->fetchAll();
            $moduleDependents = array();

            foreach ($rows as $dependent) {
                $moduleDependents[] = $dependent['StoredAs'];
            }

            // Add file nodes to the $fileElements
            // Firstly get all the scheduled layouts
            $SQL = '
                SELECT `schedule`.eventTypeId, layout.layoutId, `layout`.status, `command`.code, schedule_detail.fromDt, schedule_detail.toDt, schedule.eventId, schedule.is_priority
            ';

            if (!$options['dependentsAsNodes']) {
                // Pull in the dependents using GROUP_CONCAT
                $SQL .= ' ,
                  (
                    SELECT GROUP_CONCAT(DISTINCT StoredAs)
                      FROM `media`
                        INNER JOIN `lkwidgetmedia`
                        ON `lkwidgetmedia`.MediaID = `media`.MediaID
                        INNER JOIN `widget`
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                        INNER JOIN `lkregionplaylist`
                        ON `lkregionplaylist`.playlistId = `widget`.playlistId
                        INNER JOIN `region`
                        ON `region`.regionId = `lkregionplaylist`.regionId
                     WHERE `region`.layoutId = `layout`.layoutId
                      AND media.type <> \'module\'
                    GROUP BY `region`.layoutId
                  ) AS Dependents
                ';
            }

            $SQL .= '
                   FROM `schedule`
                    INNER JOIN schedule_detail
                    ON schedule_detail.eventID = `schedule`.eventID
                    INNER JOIN `lkscheduledisplaygroup`
                    ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lkscheduledisplaygroup`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                    LEFT OUTER JOIN `campaign`
                    ON `schedule`.CampaignID = campaign.CampaignID
                    LEFT OUTER JOIN `lkcampaignlayout`
                    ON lkcampaignlayout.CampaignID = campaign.CampaignID
                    LEFT OUTER JOIN `layout`
                    ON lkcampaignlayout.LayoutID = layout.LayoutID
                      AND layout.retired = 0
                    LEFT OUTER JOIN `command`
                    ON `command`.commandId = `schedule`.commandId
                 WHERE lkdisplaydg.DisplayID = :displayId
                    AND schedule_detail.FromDT < :todt
                    AND IFNULL(schedule_detail.ToDT, schedule_detail.FromDT) > :fromdt
                ORDER BY schedule.DisplayOrder, IFNULL(lkcampaignlayout.DisplayOrder, 0), schedule_detail.FromDT
            ';

            $params = array(
                'displayId' => $this->display->displayId,
                'todt' => $toFilter,
                'fromdt' => $fromFilter
            );

            if ($this->display->isAuditing())
                $this->getLog()->sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            $events = $sth->fetchAll(\PDO::FETCH_ASSOC);

            // If our dependents are nodes, then build a list of layouts we can use to query for nodes
            $layoutDependents = [];

            if ($options['dependentsAsNodes']) {

                // Layouts (pop in the default)
                $layoutIds = [$this->display->defaultLayoutId];

                foreach ($events as $event) {
                    if ($event['layoutId'] != null && !in_array($event['layoutId'], $layoutIds))
                        $layoutIds[] = $event['layoutId'];
                }

                $SQL = '
                    SELECT DISTINCT `region`.layoutId, `media`.storedAs
                      FROM `media`
                        INNER JOIN `lkwidgetmedia`
                        ON `lkwidgetmedia`.MediaID = `media`.MediaID
                        INNER JOIN `widget`
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                        INNER JOIN `lkregionplaylist`
                        ON `lkregionplaylist`.playlistId = `widget`.playlistId
                        INNER JOIN `region`
                        ON `region`.regionId = `lkregionplaylist`.regionId
                     WHERE `region`.layoutId IN (' . implode(',', $layoutIds) . ')
                      AND media.type <> \'module\'
                ';

                foreach ($this->getStore()->select($SQL, []) as $row) {
                    if (!array_key_exists($row['layoutId'], $layoutDependents))
                        $layoutDependents[$row['layoutId']] = [];

                    $layoutDependents[$row['layoutId']][] = $row['storedAs'];
                }

                $this->getLog()->debug('Resolved dependents for Schedule: %s.', json_encode($layoutDependents, JSON_PRETTY_PRINT));
            }

            $overlayNodes = null;

            // We must have some results in here by this point
            foreach ($events as $row) {
                $eventTypeId = $row['eventTypeId'];
                $layoutId = $row['layoutId'];
                $commandCode = $row['code'];
                $fromDt = date('Y-m-d H:i:s', $row['fromDt']);
                $toDt = date('Y-m-d H:i:s', $row['toDt']);
                $scheduleId = $row['eventId'];
                $is_priority = $this->getSanitizer()->int($row['is_priority']);

                if ($eventTypeId == Schedule::$LAYOUT_EVENT) {
                    // Check the layout status
                    // https://github.com/xibosignage/xibo/issues/743
                    if (intval($row['status']) > 3) {
                        $this->getLog()->error('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId);
                        continue;
                    }

                    // Add a layout node to the schedule
                    $layout = $scheduleXml->createElement("layout");
                    $layout->setAttribute("file", $layoutId);
                    $layout->setAttribute("fromdt", $fromDt);
                    $layout->setAttribute("todt", $toDt);
                    $layout->setAttribute("scheduleid", $scheduleId);
                    $layout->setAttribute("priority", $is_priority);

                    if (!$options['dependentsAsNodes']) {
                        $dependents = $this->getSanitizer()->string($row['Dependents']);
                        $layout->setAttribute("dependents", $dependents);
                    }
                    else if (array_key_exists($layoutId, $layoutDependents)) {
                        $dependentNode = $scheduleXml->createElement("dependents");

                        foreach ($layoutDependents[$layoutId] as $storedAs) {
                            $fileNode = $scheduleXml->createElement("file", $storedAs);

                            $dependentNode->appendChild($fileNode);
                        }

                        $layout->appendChild($dependentNode);
                    }

                    $layoutElements->appendChild($layout);

                } else if ($eventTypeId == Schedule::$COMMAND_EVENT) {
                    // Add a command node to the schedule
                    $command = $scheduleXml->createElement("command");
                    $command->setAttribute("date", $fromDt);
                    $command->setAttribute("scheduleid", $scheduleId);
                    $command->setAttribute('code', $commandCode);
                    $layoutElements->appendChild($command);
                } else if ($eventTypeId == Schedule::$OVERLAY_EVENT && $options['includeOverlays']) {

                    // Check the layout status
                    // https://github.com/xibosignage/xibo/issues/743
                    if (intval($row['status']) > 3) {
                        $this->getLog()->error('Player has invalid layout scheduled. Display = %s, LayoutId = %d', $this->display->display, $layoutId);
                        continue;
                    }

                    if ($overlayNodes == null) {
                        $overlayNodes = $scheduleXml->createElement('overlays');
                    }

                    $overlay = $scheduleXml->createElement('overlay');
                    $overlay->setAttribute("file", $layoutId);
                    $overlay->setAttribute("fromdt", $fromDt);
                    $overlay->setAttribute("todt", $toDt);
                    $overlay->setAttribute("scheduleid", $scheduleId);
                    $overlay->setAttribute("priority", $is_priority);

                    // Add to the overlays node list
                    $overlayNodes->appendChild($overlay);
                }
            }

            // Add the overlay nodes if we had any
            if ($overlayNodes != null)
                $layoutElements->appendChild($overlayNodes);

        } catch (\Exception $e) {
            $this->getLog()->error('Error getting a list of layouts for the schedule. ' . $e->getMessage());
            return new \SoapFault('Sender', 'Unable to get A list of layouts for the schedule');
        }

        // Are we interleaving the default?
        if ($this->display->incSchedule == 1) {
            // Add as a node at the end of the schedule.
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $this->display->defaultLayoutId);
            $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
            $layout->setAttribute("todt", '2030-01-19 00:00:00');
            $layout->setAttribute("scheduleid", 0);
            $layout->setAttribute("priority", 0);

            if ($options['dependentsAsNodes'] && array_key_exists($this->display->defaultLayoutId, $layoutDependents)) {
                $dependentNode = $scheduleXml->createElement("dependents");

                foreach ($layoutDependents[$this->display->defaultLayoutId] as $storedAs) {
                    $fileNode = $scheduleXml->createElement("file", $storedAs);

                    $dependentNode->appendChild($fileNode);
                }

                $layout->appendChild($dependentNode);
            }

            $layoutElements->appendChild($layout);
        }

        // Add on the default layout node
        $default = $scheduleXml->createElement("default");
        $default->setAttribute("file", $this->display->defaultLayoutId);

        if ($options['dependentsAsNodes'] && array_key_exists($this->display->defaultLayoutId, $layoutDependents)) {
            $dependentNode = $scheduleXml->createElement("dependents");

            foreach ($layoutDependents[$this->display->defaultLayoutId] as $storedAs) {
                $fileNode = $scheduleXml->createElement("file", $storedAs);

                $dependentNode->appendChild($fileNode);
            }

            $default->appendChild($dependentNode);
        }

        $layoutElements->appendChild($default);

        // Add on a list of global dependants
        $globalDependents = $scheduleXml->createElement("dependants");

        foreach ($moduleDependents as $dep) {
            $dependent = $scheduleXml->createElement("file", $dep);
            $globalDependents->appendChild($dependent);
        }
        $layoutElements->appendChild($globalDependents);

        // Format the output
        $scheduleXml->formatOutput = true;

        if ($this->display->isAuditing())
            $this->getLog()->debug($scheduleXml->saveXML());

        $output = $scheduleXml->saveXML();

        // Cache
        $cache->set($output);
        $cache->expiresAt($this->getDate()->parse($toFilter, 'U'));
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
     * @throws \SoapFault
     */
    protected function doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason)
    {
        $this->logProcessor->setRoute('BlackList');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $mediaId = $this->getSanitizer()->string($mediaId);
        $type = $this->getSanitizer()->string($type);
        $reason = $this->getSanitizer()->string($reason);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed", $hardwareKey);

        if ($this->display->isAuditing())
            $this->getLog()->debug('Blacklisting ' . $mediaId . ' for ' . $reason);

        try {
            $dbh = $this->getStore()->getConnection();

            // Check to see if this media / display is already blacklisted (and not ignored)
            $sth = $dbh->prepare('SELECT BlackListID FROM blacklist WHERE MediaID = :mediaid AND isIgnored = 0 AND DisplayID = :displayid');
            $sth->execute(array(
                'mediaid' => $mediaId,
                'displayid' => $this->display->displayId
            ));

            $results = $sth->fetchAll();

            if (count($results) == 0) {

                $insertSth = $dbh->prepare('
                        INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES (:mediaid, :displayid, :reportingdisplayid, :reason)
                    ');

                // Insert the black list record
                if ($type == BLACKLIST_SINGLE) {
                    $insertSth->execute(array(
                        'mediaid' => $mediaId,
                        'displayid' => $this->display->displayId,
                        'reportingdisplayid' => $this->display->displayId,
                        'reason' => $reason
                    ));
                } else {
                    $displaySth = $dbh->prepare('SELECT displayID FROM `display`');
                    $displaySth->execute();

                    foreach ($displaySth->fetchAll() as $row) {

                        $insertSth->execute(array(
                            'mediaid' => $mediaId,
                            'displayid' => $row['displayID'],
                            'reportingdisplayid' => $this->display->displayId,
                            'reason' => $reason
                        ));
                    }
                }
            } else {
                if ($this->display->isAuditing())
                    $this->getLog()->debug($mediaId . ' already black listed');
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Unable to query for Blacklist records. ' . $e->getMessage());
            return new \SoapFault('Sender', "Unable to query for BlackList records.");
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$BLACKLIST, strlen($reason));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $logXml
     * @return bool
     * @throws \SoapFault
     */
    protected function doSubmitLog($serverKey, $hardwareKey, $logXml)
    {
        $this->logProcessor->setRoute('SubmitLog');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Sender', 'This display client is not licensed.');

        if ($this->display->isAuditing())
            $this->getLog()->debug('XML log: ' . $logXml);

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");

        if (!$document->loadXML($logXml)) {
            $this->getLog()->error('Malformed XML from Player, this will be discarded. The Raw XML String provided is: ' . $logXml);
            return true;
        }

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE)
                continue;

            // Zero out the common vars
            $scheduleId = "";
            $layoutId = "";
            $mediaId = "";
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

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements) {

                if ($nodeElements->nodeName == "scheduleID") {
                    $scheduleId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "layoutID") {
                    $layoutId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "mediaID") {
                    $mediaId = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "type") {
                    $type = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "method") {
                    $method = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "message") {
                    $message = $nodeElements->textContent;
                } else if ($nodeElements->nodeName == "thread") {
                    if ($nodeElements->textContent != '')
                        $thread = '[' . $nodeElements->textContent . '] ';
                }
            }

            // If the message is still empty, take the entire node content
            if ($message == '')
                $message = $node->textContent;

            // We should have enough information to log this now.
            $logType = ($cat == 'error') ? 'error' : 'audit';

            $this->getLog()->notice('%s,%s,%s,%s,%s,%s,%s,%s', $logType, $message, 'Client', $thread . $method . $type, $date, $scheduleId, $layoutId, $mediaId);
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$SUBMITLOG, strlen($logXml));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $statXml
     * @return bool
     * @throws \SoapFault
     */
    protected function doSubmitStats($serverKey, $hardwareKey, $statXml)
    {
        $this->logProcessor->setRoute('SubmitStats');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing())
            $this->getLog()->debug('Received XML. ' . $statXml);

        if ($statXml == "")
            throw new \SoapFault('Receiver', "Stat XML is empty.");

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node) {
            /* @var \DOMElement $node */
            // Make sure we don't consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE)
                continue;

            // Each element should have these attributes
            $fromdt = $node->getAttribute('fromdt');
            $todt = $node->getAttribute('todt');
            $type = $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '') {
                $this->getLog()->error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID = $node->getAttribute('scheduleid');
            $layoutID = $node->getAttribute('layoutid');
            
            // Slightly confusing behaviour here to support old players without introducting a different call in 
            // xmds v=5.
            // MediaId is actually the widgetId (since 1.8) and the mediaId is looked up by this service
            $widgetId = $node->getAttribute('mediaid');
            
            // Lookup the mediaId
            $media = $this->mediaFactory->getByLayoutAndWidget($layoutID, $widgetId);

            if (count($media) <= 0) {
                // Non-media widget
                $mediaId = 0;
            } else {
                $mediaId = $media[0]->mediaId;
            }
            
            $tag = $node->getAttribute('tag');

            // Write the stat record with the information we have available to us.
            try {
                $stat = new Stat($this->getStore(), $this->getLog());
                $stat->type = $type;
                $stat->fromDt = $fromdt;
                $stat->toDt = $todt;
                $stat->scheduleId = $scheduleID;
                $stat->displayId = $this->display->displayId;
                $stat->layoutId = $layoutID;
                $stat->mediaId = $mediaId;
                $stat->widgetId = $widgetId;
                $stat->tag = $tag;
                $stat->save();
            }
            catch (\PDOException $e) {
                $this->getLog()->error('Stat Add failed with error: %s', $e->getMessage());
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
     * @throws \SoapFault
     */
    protected function doMediaInventory($serverKey, $hardwareKey, $inventory)
    {
        $this->logProcessor->setRoute('MediaInventory');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing())
            $this->getLog()->debug($inventory);

        // Check that the $inventory contains something
        if ($inventory == '')
            throw new \SoapFault('Receiver', 'Inventory Cannot be Empty');

        // Load the XML into a DOMDocument
        $document = new \DOMDocument("1.0");
        $document->loadXML($inventory);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new \DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node) {
            /* @var \DOMElement $node */

            // What type of file?
            try {
                switch ($node->getAttribute('type')) {

                    case 'media':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia($this->display->displayId, $node->getAttribute('id'));
                        break;

                    case 'layout':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $node->getAttribute('id'));
                        break;

                    case 'resource':
                        $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia($this->display->displayId, $node->getAttribute('id'));
                        break;

                    default:
                        $this->getLog()->debug('Skipping unknown node in media inventory: %s - %s.', $node->getAttribute('type'), $node->getAttribute('id'));
                        continue;
                }
            }
            catch (NotFoundException $e) {
                $this->getLog()->info('Unable to find file in media inventory: %s', $node->getAttribute('type'), $node->getAttribute('id'));
                continue;
            }

            // File complete?
            $complete = $node->getAttribute('complete');
            $requiredFile->complete = $complete;
            $requiredFile->save(['refreshNonce' => false]);

            // If this item is a 0 then set not complete
            if ($complete == 0)
                $mediaInventoryComplete = 2;
        }

        $this->display->mediaInventoryStatus = $mediaInventoryComplete;
        $this->display->save(['validate' => false, 'audit' => false]);

        $this->logBandwidth($this->display->displayId, Bandwidth::$MEDIAINVENTORY, strlen($inventory));

        return true;
    }

    /**
     * @param $serverKey
     * @param $hardwareKey
     * @param $layoutId
     * @param $regionId
     * @param $mediaId
     * @return mixed
     * @throws \SoapFault
     */
    protected function doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        $this->logProcessor->setRoute('GetResource');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $layoutId = $this->getSanitizer()->int($layoutId);
        $regionId = $this->getSanitizer()->string($regionId);
        $mediaId = $this->getSanitizer()->string($mediaId);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed");

        // Update the last accessed date/logged in
        $this->touchDisplay();

        // The MediaId is actually the widgetId
        try {
            $requiredFile = $this->requiredFileFactory->getByDisplayAndResource($this->display->displayId, $layoutId, $regionId, $mediaId);

            $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($mediaId), $this->regionFactory->getById($regionId));
            $resource = $module->getResource($this->display->displayId);

            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->markUsed();

            if ($resource == '')
                throw new ControllerNotImplemented();
        }
        catch (NotFoundException $notEx) {
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        }
        catch (ControllerNotImplemented $e) {
            throw new \SoapFault('Receiver', 'Unable to get the media resource');
        }

        // Commit the touch
        $this->display->save(Display::$saveOptionsMinimum);

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }

    /**
     * PHONE_HOME if required
     */
    protected function phoneHome()
    {
        if ($this->getConfig()->GetSetting('PHONE_HOME') == 'On') {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if ($this->getConfig()->GetSetting('PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28))) {

                try {
                    $dbh = $this->getStore()->getConnection();

                    // Retrieve number of displays
                    $sth = $dbh->prepare('SELECT COUNT(*) AS Cnt FROM `display` WHERE `licensed` = 1');
                    $sth->execute();

                    $PHONE_HOME_CLIENTS = $sth->fetchColumn();

                    // Retrieve version number
                    $PHONE_HOME_VERSION = $this->getConfig()->Version('app_ver');

                    $PHONE_HOME_URL = $this->getConfig()->GetSetting('PHONE_HOME_URL') . "?id=" . urlencode($this->getConfig()->GetSetting('PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);

                    if ($this->display->isAuditing())
                        $this->getLog()->notice("audit", "PHONE_HOME_URL " . $PHONE_HOME_URL, "xmds", "RequiredFiles");

                    // Set PHONE_HOME_TIME to NOW.
                    $sth = $dbh->prepare('UPDATE `setting` SET `value` = :time WHERE `setting`.`setting` = :setting LIMIT 1');
                    $sth->execute(array(
                        'time' => time(),
                        'setting' => 'PHONE_HOME_DATE'
                    ));

                    @file_get_contents($PHONE_HOME_URL);

                    if ($this->display->isAuditing())
                        $this->getLog()->notice("audit", "PHONE_HOME [OUT]", "xmds", "RequiredFiles");

                } catch (\Exception $e) {

                    $this->getLog()->error($e->getMessage());

                    return false;
                }
            }
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

            if ($this->display->licensed != 1)
                return false;

            // Configure our log processor
            $this->logProcessor->setDisplay($this->display->displayId, ($this->display->isAuditing()));

            return true;

        } catch (NotFoundException $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Touch Display
     */
    protected function touchDisplay()
    {
        // Last accessed date on the display
        $this->display->lastAccessed = time();
        $this->display->loggedIn = 1;
        $this->display->clientAddress = $this->getIp();
    }

    /**
     * Alert Display Up
     * @throws \phpmailerException
     */
    protected function alertDisplayUp()
    {
        $maintenanceEnabled = $this->getConfig()->GetSetting('MAINTENANCE_ENABLED');

        if ($this->display->loggedIn == 0) {

            $this->getLog()->info('Display %s was down, now its up.', $this->display->display);

            // Log display up
            $this->displayEventFactory->createEmpty()->displayUp($this->display->displayId);

            // Do we need to email?
            if ($this->display->emailAlert == 1 && ($maintenanceEnabled == 'On' || $maintenanceEnabled == 'Protected')
                && $this->getConfig()->GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'On'
            ) {

                $subject = sprintf(__("Recovery for Display %s"), $this->display->display);
                $body = sprintf(__("Display %s with ID %d is now back online."), $this->display->display, $this->display->displayId);

                $notification = $this->notificationFactory->createEmpty();
                $notification->subject = $subject;
                $notification->body = $body;
                $notification->createdDt = $this->getDate()->getLocalDate(null, 'U');
                $notification->releaseDt = $this->getDate()->getLocalDate(null, 'U');
                $notification->isEmail = 1;
                $notification->isInterrupt = 0;
                $notification->userId = 0;
                $notification->isSystem = 1;

                // Add the system notifications group - if there is one.
                foreach ($this->userGroupFactory->getSystemNotificationGroups() as $group) {
                    /* @var UserGroup $group */
                    $notification->assignUserGroup($group);
                }

                // Get a list of people that have view access to the display?
                if ($this->getConfig()->GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1) {

                    foreach ($this->userGroupFactory->getByDisplayGroupId($this->display->displayGroupId) as $group) {
                        /* @var UserGroup $group */
                        $notification->assignUserGroup($group);
                    }
                }

                try {
                    $notification->save();
                } catch (\Exception $e) {
                    $this->getLog()->error('Unable to send email alert for display %s with subject %s and body %s', $this->display->display, $subject, $body);
                }
            } else {
                $this->getLog()->debug('No email required. Email Alert: %d, Enabled: %s, Email Enabled: %s.', $this->display->emailAlert, $maintenanceEnabled, $this->getConfig()->GetSetting('MAINTENANCE_EMAIL_ALERTS'));
            }
        }
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
            if (isset($_SERVER[$key])) {
                $clientIp = $_SERVER[$key];
                break;
            }
        }

        return $clientIp;
    }

    /**
     * Check we haven't exceeded the bandwidth limits
     */
    protected function checkBandwidth()
    {
        $xmdsLimit = $this->getConfig()->GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

        if ($xmdsLimit <= 0)
            return true;

        try {
            $dbh = $this->getStore()->getConnection();

            // Test bandwidth for the current month
            $sth = $dbh->prepare('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = :month');
            $sth->execute(array(
                'month' => strtotime(date('m') . '/02/' . date('Y') . ' 00:00:00')
            ));

            $bandwidthUsage = $sth->fetchColumn(0);

            return ($bandwidthUsage >= ($xmdsLimit * 1024)) ? false : true;

        } catch (\Exception $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Log Bandwidth Usage
     * @param <type> $displayId
     * @param <type> $type
     * @param <type> $sizeInBytes
     */
    protected function logBandwidth($displayId, $type, $sizeInBytes)
    {
        $this->bandwidthFactory->createAndSave($type, $displayId, $sizeInBytes);
    }

    /**
     * Generate a file download path for HTTP downloads, taking into account the precence of a CDN.
     * @param $nonce
     * @return string
     */
    protected function generateRequiredFileDownloadPath($nonce)
    {
        $saveAsPath = Wsdl::getRoot() . '?file=' . $nonce;
        // CDN?
        $cdnUrl = $this->configService->GetSetting('CDN_URL');
        if ($cdnUrl != '') {
            // Serve a link to the CDN
            return 'http' . ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? 's' : '') . '://' . $cdnUrl . urlencode($saveAsPath);
        } else {
            // Serve a HTTP link to XMDS
            return $saveAsPath;
        }
    }
}