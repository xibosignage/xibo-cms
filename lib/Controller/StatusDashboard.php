<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\MediaService;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class StatusDashboard
 * @package Xibo\Controller
 */
class StatusDashboard extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param UserFactory $userFactory
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($store, $pool, $userFactory, $displayFactory, $displayGroupFactory, $mediaFactory)
    {
        $this->store = $store;
        $this->pool = $pool;
        $this->userFactory = $userFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Displays
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function displays(Request $request, Response $response)
    {
        $parsedRequestParams = $this->getSanitizer($request->getParams());
        // Get a list of displays
        $displays = $this->displayFactory->query($this->gridRenderSort($parsedRequestParams), $this->gridRenderFilter([], $parsedRequestParams));

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayFactory->countLast();
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    /**
     * View
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $data = [];
        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        try {
            // Get some data for a bandwidth chart
            $dbh = $this->store->getConnection();
            $params = ['month' => Carbon::now()->subSeconds(86400 * 365)->format('U')];

            $sql = '
                SELECT month,
                      SUM(size) AS size
                  FROM (
                      SELECT MAX(FROM_UNIXTIME(month)) AS month,
                          IFNULL(SUM(Size), 0) AS size,
                          MIN(month) AS month_order
                        FROM `bandwidth`
                          INNER JOIN `lkdisplaydg`
                          ON lkdisplaydg.displayID = bandwidth.displayId
                          INNER JOIN `displaygroup`
                          ON displaygroup.DisplayGroupID = lkdisplaydg.DisplayGroupID
                            AND displaygroup.isDisplaySpecific = 1
                       WHERE month > :month 
            ';

            // Permissions
            $this->displayFactory->viewPermissionSql('Xibo\Entity\DisplayGroup', $sql, $params, '`lkdisplaydg`.displayGroupId');

            $sql .= ' GROUP BY MONTH(FROM_UNIXTIME(month)) ';

            // Include deleted displays?
            if ($this->getUser()->isSuperAdmin()) {
                $sql .= '
                    UNION ALL
                    SELECT MAX(FROM_UNIXTIME(month)) AS month,
                        IFNULL(SUM(Size), 0) AS size,
                        MIN(month) AS month_order
                     FROM `bandwidth`
                     WHERE bandwidth.displayId NOT IN (SELECT displayId FROM `display`)
                        AND month > :month
                    GROUP BY MONTH(FROM_UNIXTIME(month))
                ';
            }

            $sql .= '
                    ) grp
                GROUP BY month
                ORDER BY MIN(month_order)
            ';

            // Run the SQL
            $results = $this->store->select($sql, $params);

            // Monthly bandwidth - optionally tested against limits
            $xmdsLimit = $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

            $maxSize = 0;
            foreach ($results as $row) {
                $maxSize = ($row['size'] > $maxSize) ? $row['size'] : $maxSize;
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            if ($xmdsLimit > 0) {
                // Convert to appropriate size (xmds limit is in KB)
                $xmdsLimit = ($xmdsLimit * 1024) / (pow(1024, $base));
                $data['xmdsLimit'] = round($xmdsLimit, 2) . ' ' . $suffixes[$base];
            }

            $labels = [];
            $usage = [];
            $limit = [];

            foreach ($results as $row) {
                $sanitizedRow = $this->getSanitizer($row);
                $labels[] = Carbon::createFromTimeString($sanitizedRow->getString('month'))->format('F');

                $size = ((double)$row['size']) / (pow(1024, $base));
                $usage[] = round($size, 2);

                $limit[] = round($xmdsLimit - $size, 2);
            }

            // What if we are empty?
            if (count($results) == 0) {
                $labels[] = Carbon::now()->format('F');
                $usage[] = 0;
                $limit[] = 0;
            }

            // Organise our datasets
            $dataSets = [
                [
                    'label' => __('Used'),
                    'backgroundColor' => ($xmdsLimit > 0) ? 'rgb(255, 0, 0)' : 'rgb(11, 98, 164)',
                    'data' => $usage
                ]
            ];

            if ($xmdsLimit > 0) {
                $dataSets[] = [
                    'label' => __('Available'),
                    'backgroundColor' => 'rgb(0, 204, 0)',
                    'data' => $limit
                ];
            }

            // Set the data
            $data['xmdsLimitSet'] = ($xmdsLimit > 0);
            $data['bandwidthSuffix'] = $suffixes[$base];
            $data['bandwidthWidget'] = json_encode([
                'labels' => $labels,
                'datasets' => $dataSets
            ]);

            // We would also like a library usage pie chart!
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId', [], 'media.permissionsFolderId');
            $sql .= ' GROUP BY type ';

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();
            // add any dependencies fonts, player software etc to the results
            $event = new \Xibo\Event\DependencyFileSizeEvent($results);
            $this->getDispatcher()->dispatch($event, $event::$NAME);
            $results = $event->getResults();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $data['libraryLimitSet'] = ($libraryLimit > 0);
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidgetLabels'] = json_encode($libraryLabels);
            $data['libraryWidgetData'] = json_encode($libraryUsage);

            // Get a count of users
            $data['countUsers'] = $this->userFactory->count();

            // Get a count of active layouts, only for display groups we have permission for
            $params = ['now' => Carbon::now()->format('U')];

            $sql = '
              SELECT IFNULL(COUNT(*), 0) AS count_scheduled 
                FROM `schedule` 
               WHERE (
                  :now BETWEEN FromDT AND ToDT
                  OR `schedule`.recurrence_range >= :now 
                  OR (
                    IFNULL(`schedule`.recurrence_range, 0) = 0 AND IFNULL(`schedule`.recurrence_type, \'\') <> \'\' 
                  )
                )
                AND eventId IN (
                  SELECT eventId 
                    FROM `lkscheduledisplaygroup` 
                   WHERE 1 = 1
            ';

            $this->displayFactory->viewPermissionSql('Xibo\Entity\DisplayGroup', $sql, $params, '`lkscheduledisplaygroup`.displayGroupId');

            $sql .= ' ) ';

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            $data['nowShowing'] = $sth->fetchColumn(0);

            // Latest news
            if ($this->getConfig()->getSetting('DASHBOARD_LATEST_NEWS_ENABLED') == 1
                && !empty($this->getConfig()->getSetting('LATEST_NEWS_URL'))
            ) {
                // Make sure we have the cache location configured
                MediaService::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

                try {
                    $feedUrl = $this->getConfig()->getSetting('LATEST_NEWS_URL');
                    $cache = $this->pool->getItem('rss/' . md5($feedUrl));

                    $latestNews = $cache->get();

                    // Check the cache
                    if ($cache->isMiss()) {
                        // Create a Guzzle Client to get the Feed XML
                        $client = new Client();
                        $responseGuzzle = $client->get($feedUrl, $this->getConfig()->getGuzzleProxy());

                        // Pull out the content type and body
                        $result = explode('charset=', $responseGuzzle->getHeaderLine('Content-Type'));
                        $document['encoding'] = $result[1] ?? '';
                        $document['xml'] = $responseGuzzle->getBody();

                        $this->getLog()->debug($document['xml']);

                        // Get the feed parser
                        $reader = new Reader();
                        $parser = $reader->getParser($feedUrl, $document['xml'], $document['encoding']);

                        // Get a feed object
                        $feed = $parser->execute();

                        // Parse the items in the feed
                        $latestNews = [];

                        foreach ($feed->getItems() as $item) {
                            // Try to get the description tag
                            $desc = $item->getTag('description');
                            if (!$desc) {
                                // use content with tags stripped
                                $content = strip_tags($item->getContent());
                            } else {
                                // use description
                                $content = ($desc[0] ?? strip_tags($item->getContent()));
                            }

                            $latestNews[] = [
                                'title' => $item->getTitle(),
                                'description' => $content,
                                'link' => $item->getUrl(),
                                'date' => Carbon::instance($item->getDate())->format(DateFormatHelper::getSystemFormat()),
                            ];
                        }

                        // Store in the cache for 1 day
                        $cache->set($latestNews);
                        $cache->expiresAfter(86400);

                        $this->pool->saveDeferred($cache);
                    }

                    $data['latestNews'] = $latestNews;
                } catch (PicoFeedException $e) {
                    $this->getLog()->error('Unable to get feed: %s', $e->getMessage());
                    $this->getLog()->debug($e->getTraceAsString());

                    $data['latestNews'] = array(array('title' => __('Latest news not available.'), 'description' => '', 'link' => ''));
                }
            } else {
                $data['latestNews'] = array(array('title' => __('Latest news not enabled.'), 'description' => '', 'link' => ''));
            }

            // Display Status and Media Inventory data - Level one
            $displays = $this->displayFactory->query();
            $displayLoggedIn = [];
            $displayMediaStatus = [];
            $displaysOnline = 0;
            $displaysOffline = 0;
            $displaysMediaUpToDate = 0;
            $displaysMediaNotUpToDate = 0;

            foreach ($displays as $display) {
                $displayLoggedIn[] = $display->loggedIn;
                $displayMediaStatus[] = $display->mediaInventoryStatus;
            }

            foreach ($displayLoggedIn as $status) {
                if ($status == 1) {
                    $displaysOnline++;
                } else {
                    $displaysOffline++;
                }
            }

            foreach ($displayMediaStatus as $statusMedia) {
                if ($statusMedia == 1) {
                    $displaysMediaUpToDate++;
                } else {
                    $displaysMediaNotUpToDate++;
                }
            }

            $data['displayStatus'] = json_encode([$displaysOnline, $displaysOffline]);
            $data['displayMediaStatus'] = json_encode([$displaysMediaUpToDate, $displaysMediaNotUpToDate]);
        } catch (Exception $e) {
            $this->getLog()->error($e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());

            // Show the error in place of the bandwidth chart
            $data['widget-error'] = 'Unable to get widget details';
        }

        // Do we have an embedded widget?
        $data['embeddedWidget'] = html_entity_decode($this->getConfig()->getSetting('EMBEDDED_STATUS_WIDGET'));

        // Render the Theme and output
        $this->getState()->template = 'dashboard-status-page';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayGroups(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());
        $status = null;
        $inventoryStatus = null;
        $params = [];
        $label = $parsedQueryParams->getString('status');
        $labelContent = $parsedQueryParams->getString('inventoryStatus');

        $displayGroupIds = [];
        $displayGroupNames = [];
        $displaysAssigned = [];
        $data = [];

        if (isset($label)) {
            if ($label == 'Online') {
                $status = 1;
            } else {
                $status = 0;
            }
        }

        if (isset($labelContent)) {
            if ($labelContent == 'Up to Date') {
                $inventoryStatus = 1;
            } else {
                $inventoryStatus = -1;
            }
        }

        try {
            $sql = 'SELECT DISTINCT displaygroup.DisplayGroupID, displaygroup.displayGroup 
                      FROM displaygroup 
                        INNER JOIN `lkdisplaydg`
                          ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID
                        INNER JOIN `display`
                          ON display.displayid = lkdisplaydg.DisplayID
                      WHERE
                          displaygroup.IsDisplaySpecific = 0 ';

            if ($status !== null) {
                $sql .= ' AND display.loggedIn = :status ';
                $params = ['status' => $status];
            }

            if ($inventoryStatus != null) {
                if ($inventoryStatus === -1) {
                    $sql .= ' AND display.MediaInventoryStatus <> 1';
                } else {
                    $sql .= ' AND display.MediaInventoryStatus = :inventoryStatus';
                    $params = ['inventoryStatus' => $inventoryStatus];
                }
            }

            $this->displayFactory->viewPermissionSql('Xibo\Entity\DisplayGroup', $sql, $params, '`lkdisplaydg`.displayGroupId', null, [], 'permissionsFolderId');

            $sql .= ' ORDER BY displaygroup.DisplayGroup ';

            $results = $this->store->select($sql, $params);

            foreach ($results as $row) {
                $displayGroupNames[] = $row['displayGroup'];
                $displayGroupIds[] = $row['DisplayGroupID'];
                $displaysAssigned[] = count($this->displayFactory->query(['displayGroup'], ['displayGroupId' => $row['DisplayGroupID'], 'mediaInventoryStatus' => $inventoryStatus, 'loggedIn' => $status]));
            }

            $data['displayGroupNames'] = json_encode($displayGroupNames);
            $data['displayGroupIds'] = json_encode($displayGroupIds);
            $data['displayGroupMembers'] = json_encode($displaysAssigned);

            $this->getState()->setData($data);
        } catch (Exception $e) {
            $this->getLog()->error($e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
        }
        return $this->render($request, $response);
    }
}
