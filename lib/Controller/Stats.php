<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
namespace Xibo\Controller;

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\ConnectorReportEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Helper\SendFile;
use Xibo\Service\ReportServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Stats
 * @package Xibo\Controller
 */
class Stats extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param ReportServiceInterface $reportService
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $timeSeriesStore, $reportService, $displayFactory)
    {
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->reportService = $reportService;
        $this->displayFactory = $displayFactory;
    }

    /**
     * Report page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function displayReportPage(Request $request, Response $response)
    {
        // ------------
        // Dispatch an event to get connector reports
        $event = new ConnectorReportEvent();
        $this->getDispatcher()->dispatch($event, ConnectorReportEvent::$NAME);

        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => Carbon::now()->subSeconds(86400 * 35)->format(DateFormatHelper::getSystemFormat()),
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'availableReports' => $this->reportService->listReports(),
                'connectorReports' => $event->getReports()
            ]
        ];

        $this->getState()->template = 'report-page';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Definition(
     *  definition="StatisticsData",
     *  @SWG\Property(
     *      property="type",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="display",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="displayId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="layout",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="layoutId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="media",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="mediaId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="widgetId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="scheduleId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="numberPlays",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="duration",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="start",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="end",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="statDate",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="tag",
     *      type="string"
     *  )
     * )
     *
     *
     * Stats API
     *
     * @SWG\Get(
     *  path="/stats",
     *  operationId="statsSearch",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="type",
     *      in="query",
     *      description="The type of stat to return. Layout|Media|Widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="query",
     *      description="The start date for the filter. Default = 24 hours ago",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="query",
     *      description="The end date for the filter. Default = now.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="statDate",
     *      in="query",
     *      description="The statDate filter returns records that are greater than or equal a particular date",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="statId",
     *      in="query",
     *      description="The statId filter returns records that are greater than a particular statId",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="displayIds",
     *      description="An optional array of display Id to filter",
     *      in="query",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="layoutId",
     *      description="An optional array of layout Id to filter",
     *      in="query",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="parentCampaignId",
     *      description="An optional Parent Campaign ID to filter",
     *      in="query",
     *      required=false,
     *      type="integer",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="mediaId",
     *      description="An optional array of media Id to filter",
     *      in="query",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="campaignId",
     *      in="query",
     *      description="An optional Campaign Id to filter",
     *      type="integer",
     *      required=false
     *  ),
     *   @SWG\Parameter(
     *      name="returnDisplayLocalTime",
     *      in="query",
     *      description="true/1/On if the results should be in display local time, otherwise CMS time",
     *      type="boolean",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="returnDateFormat",
     *      in="query",
     *      description="A PHP formatted date format for how the dates in this call should be returned.",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Should the return embed additional data, options are layoutTags,displayTags and mediaTags",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(
     *              ref="#/definitions/StatisticsData"
     *          )
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $fromDt = $sanitizedQueryParams->getDate('fromDt', ['default' => Carbon::now()->subDay()]);
        $toDt = $sanitizedQueryParams->getDate('toDt', ['default' => Carbon::now()]);
        $type = strtolower($sanitizedQueryParams->getString('type'));

        $displayId = $sanitizedQueryParams->getInt('displayId');
        $displays = $sanitizedQueryParams->getIntArray('displayIds', ['default' => []]);
        $layoutIds = $sanitizedQueryParams->getIntArray('layoutId', ['default' => []]);
        $mediaIds = $sanitizedQueryParams->getIntArray('mediaId', ['default' => []]);
        $statDate = $sanitizedQueryParams->getDate('statDate');
        $statDateLessThan = $sanitizedQueryParams->getDate('statDateLessThan');
        $statId = $sanitizedQueryParams->getString('statId');
        $campaignId = $sanitizedQueryParams->getInt('campaignId');
        $parentCampaignId = $sanitizedQueryParams->getInt('parentCampaignId');
        $eventTag = $sanitizedQueryParams->getString('eventTag');

        // Return formatting
        $returnDisplayLocalTime = $sanitizedQueryParams->getCheckbox('returnDisplayLocalTime');
        $returnDateFormat = $sanitizedQueryParams->getString('returnDateFormat', ['default' => DateFormatHelper::getSystemFormat()]);

        // Embed Tags
        $embed = explode(',', $sanitizedQueryParams->getString('embed', ['default' => '']));

        // CMS timezone
        $defaultTimezone = $this->getConfig()->getSetting('defaultTimezone');

        // Paging
        $start = $sanitizedQueryParams->getInt('start', ['default' => 0]);
        $length = $sanitizedQueryParams->getInt('length', ['default' => 10]);

        // Merge displayId and displayIds
        if ($displayId != 0) {
            $displays = array_unique(array_merge($displays, [$displayId]));
        }

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $timeZoneCache = [];
        $displayIds = $this->authoriseDisplayIds($displays, $timeZoneCache);

        // Call the time series interface getStats
        $resultSet = $this->timeSeriesStore->getStats(
            [
                'fromDt'=> $fromDt,
                'toDt'=> $toDt,
                'type' => $type,
                'displayIds' => $displayIds,
                'layoutIds' => $layoutIds,
                'mediaIds' => $mediaIds,
                'statDate' => $statDate,
                'statDateLessThan' => $statDateLessThan,
                'statId' => $statId,
                'campaignId' => $campaignId,
                'parentCampaignId' => $parentCampaignId,
                'eventTag' => $eventTag,
                'displayTags' => in_array('displayTags', $embed),
                'layoutTags' => in_array('layoutTags', $embed),
                'mediaTags' => in_array('mediaTags', $embed),
                'start' => $start,
                'length' => $length,
            ]);

        $rows = [];
        foreach ($resultSet->getArray() as $row) {
            $entry = [];

            // Load my row into the sanitizer
            $sanitizedRow = $this->getSanitizer($row);

            // Core details
            $entry['id'] = $resultSet->getIdFromRow($row);
            $entry['type'] = strtolower($sanitizedRow->getString('type'));
            $entry['displayId'] = $sanitizedRow->getInt(('displayId'));

            // Get the start/end date
            $start = $resultSet->getDateFromValue($row['start']);
            $end = $resultSet->getDateFromValue($row['end']);

            if ($returnDisplayLocalTime) {
                // Convert the dates to the display timezone.
                if (!array_key_exists($entry['displayId'], $timeZoneCache)) {
                    try {
                        $display = $this->displayFactory->getById($entry['displayId']);
                        $timeZoneCache[$entry['displayId']] = (empty($display->timeZone)) ? $defaultTimezone : $display->timeZone;
                    } catch (\Xibo\Support\Exception\NotFoundException $e) {
                        $timeZoneCache[$entry['displayId']] = $defaultTimezone;
                    }
                }
                $start = $start->tz($timeZoneCache[$entry['displayId']]);
                $end = $end->tz($timeZoneCache[$entry['displayId']]);
            }

            $widgetId = $sanitizedRow->getInt('widgetId', ['default' => 0]);
            $widgetName = $sanitizedRow->getString('media');
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;

            $entry['display'] = $sanitizedRow->getString('display', ['default' => __('Not Found')]);
            $entry['layout'] = $sanitizedRow->getString('layout', ['default' => __('Not Found')]);
            $entry['media'] = $widgetName;
            $entry['numberPlays'] = $sanitizedRow->getInt('count');
            $entry['duration'] = $sanitizedRow->getInt('duration');
            $entry['start'] = $start->format($returnDateFormat);
            $entry['end'] = $end->format($returnDateFormat);
            $entry['layoutId'] = $sanitizedRow->getInt('layoutId', ['default' => 0]);
            $entry['campaignId'] = $sanitizedRow->getInt('campaignId', ['default' => 0]);
            $entry['widgetId'] = $widgetId;
            $entry['mediaId'] = $sanitizedRow->getInt('mediaId', ['default' => 0]);
            $entry['scheduleId'] = $sanitizedRow->getInt('scheduleId', ['default' => 0]);
            $entry['tag'] = $sanitizedRow->getString('tag');
            $entry['statDate'] = isset($row['statDate']) ? $resultSet->getDateFromValue($row['statDate'])->format(DateFormatHelper::getSystemFormat()) : '';
            $entry['engagements'] = $resultSet->getEngagementsFromRow($row);

            // Tags
            // ----
            // Display tags
            $tagFilter = $resultSet->getTagFilterFromRow($row);
            if (in_array('displayTags', $embed)) {
                $entry['displayTags'] = $tagFilter['dg'] ?? [];
            }

            // Layout tags
            if (in_array('layoutTags', $embed)) {
                $entry['layoutTags'] = $tagFilter['layout'] ?? [];
            }

            // Media tags
            if (in_array('mediaTags', $embed)) {
                $entry['mediaTags'] = $tagFilter['media'] ?? [];
            }

            $rows[] = $entry;
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $resultSet->getTotalCount();
        $this->getState()->setData($rows);

        return $this->render($request, $response);
    }

    /**
     * Bandwidth Data
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function bandwidthData(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $fromDt = $sanitizedParams->getDate('fromDt', ['default' => $sanitizedParams->getDate('bandwidthFromDt')]);
        $toDt = $sanitizedParams->getDate('toDt', ['default' => $sanitizedParams->getDate('bandwidthToDt')]);

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query(null, []) as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
        }

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $displayId = $sanitizedParams->getInt('displayId');
        $params = array(
            'month' => $fromDt->setDateTime($fromDt->year, $fromDt->month, 1, 0, 0)->format('U'),
            'month2' => $toDt->addMonth()->setDateTime($toDt->year, $toDt->month, 1, 0, 0)->format('U')
        );

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        // For user with limited access, return only data for displays this user has permissions to.
        $joinType = ($this->getUser()->isSuperAdmin()) ? 'LEFT OUTER JOIN' : 'INNER JOIN';

        $SQL .= ' FROM `bandwidth` ' .
                $joinType . ' `display`
                ON display.displayid = bandwidth.displayid AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2 ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';

        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        $maxSize = 0;
        foreach ($results as $library) {
            $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
        }

        // Decide what our units are going to be, based on the size
        $base = floor(log($maxSize) / log(1024));

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $labels[] = $row['type'];
            } else {
                $labels[] = $row['display'] === null ? __('Deleted Displays') : $row['display'];
            }
            $backgroundColor[] = ($row['display'] === null) ? 'rgb(255,0,0)' : 'rgb(11, 98, 164)';
            $data[] = round((double)$row['size'] / (pow(1024, $base)), 2);
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'labels' => $labels,
            'data' => $data,
            'backgroundColor' => $backgroundColor,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];

        return $this->render($request, $response);
    }

    /**
     * Output CSV Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function exportForm(Request $request, Response $response)
    {
        $this->getState()->template = 'statistics-form-export';

        return $this->render($request, $response);
    }

    /**
     * Total count of stats
     *
     * @SWG\Get(
     *  path="/stats/getExportStatsCount",
     *  operationId="getExportStatsCount",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="query",
     *      description="The start date for the filter. Default = 24 hours ago",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="query",
     *      description="The end date for the filter. Default = now.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getExportStatsCount(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // We are expecting some parameters
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');
        $displayId = $sanitizedParams->getInt('displayId');

        if ($fromDt != null) {
            $fromDt->startOfDay();
        }

        if ($toDt != null) {
            $toDt->addDay()->startOfDay();
        }

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt != null && $toDt != null && $fromDt == $toDt) {
            $toDt->addDay();
        }

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        // Call the time series interface getStats
        $resultSet =  $this->timeSeriesStore->getExportStatsCount(
            [
                'fromDt'=> $fromDt,
                'toDt'=> $toDt,
                'displayIds' => $displayIds
            ]);

        $data = [
            'total' => $resultSet
        ];

        $this->getState()->template = 'statistics-form-export';
        $this->getState()->recordsTotal = $resultSet;
        $this->getState()->setData($data);

        return $this->render($request, $response);

    }

    /**
     * Outputs a CSV of stats
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function export(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // We are expecting some parameters
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');
        $displayId = $sanitizedParams->getInt('displayId');
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/stats_' . Random::generateString();
        $isOutputUtc = $sanitizedParams->getCheckbox('isOutputUtc');

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0) {
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
            }

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        if ($fromDt == null || $toDt == null) {
            throw new InvalidArgumentException(__('Both fromDt/toDt should be provided'), 'fromDt/toDt');
        }

        $fromDt->startOfDay();
        $toDt->addDay()->startOfDay();

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay();
        }

        // Get result set
        $resultSet =  $this->timeSeriesStore->getStats([
            'fromDt'=> $fromDt,
            'toDt'=> $toDt,
            'displayIds' => $displayIds,
        ]);

        $out = fopen($tempFileName, 'w');
        fputcsv($out, ['Stat Date', 'Type', 'FromDT', 'ToDT', 'Layout', 'Campaign', 'Display', 'Media', 'Tag', 'Duration', 'Count', 'Engagements']);

        $defaultTimezone = $this->getConfig()->getSetting('defaultTimezone');

        while ($row = $resultSet->getNextRow() ) {
            $sanitizedRow = $this->getSanitizer($row);
            $sanitizedRow->setDefaultOptions(['defaultIfNotExists' => true]);

            // Read the columns
            $type = strtolower($sanitizedRow->getString('type'));
            $statDate = isset($row['statDate']) ? $resultSet->getDateFromValue($row['statDate']) : null;
            $fromDt = $resultSet->getDateFromValue($row['start']);
            $toDt = $resultSet->getDateFromValue($row['end']);
            // MySQL stores dates in the timezone of the CMS,
            // while MongoDB converts those dates to UTC before storing them.

            // If we choose to retrieve the dates in UTC:
            // MongoDB: We don't need to convert the dates as they are "already" in UTC
            // MySQL: We need to convert the dates to UTC as they are in CMS Local Time

            // If we choose to retrieve the dates in CMS Local Time:
            // MongoDB: We need to convert the dates to CMS Local Time
            // MySQL: We don't need to convert the dates, as they are "already" in CMS Local Time

            // For MySQL, dates are already in CMS Local Time
            // For MongoDB, dates are in UTC
            if ($isOutputUtc) {
                if ($this->timeSeriesStore->getEngine() == 'mysql') {
                    $fromDt = $fromDt->setTimezone('UTC');
                    $toDt = $toDt->setTimezone('UTC');
                    $statDate = isset($statDate) ? $statDate->setTimezone('UTC') : null;
                }
            } else {
                if ($this->timeSeriesStore->getEngine() == 'mongodb') {
                    $fromDt = $fromDt->setTimezone($defaultTimezone);
                    $toDt = $toDt->setTimezone($defaultTimezone);
                    $statDate = isset($statDate) ? $statDate->setTimezone($defaultTimezone) : null;
                }
            }

            $statDate = isset($statDate) ? $statDate->format(DateFormatHelper::getSystemFormat()) : null;
            $fromDt = $fromDt->format(DateFormatHelper::getSystemFormat());
            $toDt = $toDt->format(DateFormatHelper::getSystemFormat());

            $engagements = $resultSet->getEngagementsFromRow($row, false);
            $layout = $sanitizedRow->getString('layout', ['default' => __('Not Found')]);
            $parentCampaign = $sanitizedRow->getString('parentCampaign', ['default' => '']);
            $display = $sanitizedRow->getString('display', ['default' => __('Not Found')]);
            $media = $sanitizedRow->getString('media', ['default' => '']);
            $tag = $sanitizedRow->getString('tag', ['default' => '']);
            $duration = $sanitizedRow->getInt('duration', ['default' => 0]);
            $count = $sanitizedRow->getInt('count', ['default' => 0]);

            fputcsv($out, [$statDate, $type, $fromDt, $toDt, $layout, $parentCampaign, $display, $media, $tag, $duration, $count, $engagements]);
        }

        fclose($out);

        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            'stats.csv'
        )->withHeader('Content-Type', 'text/csv'));
    }

    /**
     * @SWG\Definition(
     *  definition="TimeDisconnectedData",
     *  @SWG\Property(
     *      property="display",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="displayId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="duration",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="start",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="end",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="isFinished",
     *      type="boolean"
     *  )
     * )
     *
     * @SWG\Get(
     *  path="/stats/timeDisconnected",
     *  operationId="timeDisconnectedSearch",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="query",
     *      description="The start date for the filter.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="query",
     *      description="The end date for the filter.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="displayIds",
     *      description="An optional array of display Id to filter",
     *      in="query",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="returnDisplayLocalTime",
     *      in="query",
     *      description="true/1/On if the results should be in display local time, otherwise CMS time",
     *      type="boolean",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="returnDateFormat",
     *      in="query",
     *      description="A PHP formatted date format for how the dates in this call should be returned.",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(
     *              ref="#/definitions/TimeDisconnectedData"
     *          )
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function gridTimeDisconnected(Request $request, Response $response): Response
    {
        // CMS timezone
        $defaultTimezone = $this->getConfig()->getSetting('defaultTimezone');

        $params = $this->getSanitizer($request->getParams());
        $fromDt = $params->getDate('fromDt');
        $toDt = $params->getDate('toDt');
        $displayId = $params->getInt('displayId');
        $displays = $params->getIntArray('displayIds');
        $returnDisplayLocalTime = $params->getCheckbox('returnDisplayLocalTime');
        $returnDateFormat = $params->getString('returnDateFormat', 'Y-m-d H:i:s');

        // Merge displayId and displayIds
        if ($displayId != 0) {
            $displays = array_unique(array_merge($displays, [$displayId]));
        }

        $timeZoneCache = [];
        $displayIds = $this->authoriseDisplayIds($displays, $timeZoneCache);

        $params = [];
        $select = '
            SELECT displayevent.eventDate, 
                    display.displayId, 
                    display.display, 
                    displayevent.start, 
                    displayevent.end
        ';
        $body = '
              FROM displayevent
                INNER JOIN display 
                ON displayevent.displayId = display.displayId
             WHERE 1 = 1 
        ';

        if (count($displays) > 0) {
            $body .= ' AND display.displayId IN (' . implode(',', $displayIds) . ') ';
        }

        if ($fromDt != null) {
            $body .= ' AND displayevent.start >= :start ';
            $params['start'] = $fromDt->format('U');
        }

        if ($toDt != null) {
            $body .= ' AND displayevent.end < :end ';
            $params['end'] = $toDt->format('U');
        }

        // Sorting?
        $filterBy = $this->gridRenderFilter([], $params);
        $sortOrder = $this->gridRenderSort($params);

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';

        // Paging
        $filterBy = $this->getSanitizer($filterBy);
        if ($filterBy !== null && $filterBy->hasParam('start') && $filterBy->hasParam('length')) {
            $limit = ' LIMIT ' . intval($filterBy->getInt('start', ['default' => 0])) . ', '
                . $filterBy->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        // Run the main query
        $rows = [];
        foreach ($this->store->select($sql, $params) as $row) {
            // Load my row into the sanitizer
            $sanitizedRow = $this->getSanitizer($row);

            $entry = [];
            $entry['displayId'] = $sanitizedRow->getInt('displayId');
            $entry['display'] = $sanitizedRow->getString('display');
            $entry['isFinished'] = $row['end'] !== null;

            // Get the start/end date
            $start = Carbon::createFromTimestamp($row['start']);
            $end = Carbon::createFromTimestamp($row['end']);

            if ($returnDisplayLocalTime) {
                // Convert the dates to the display timezone.
                if (!array_key_exists($entry['displayId'], $timeZoneCache)) {
                    try {
                        $display = $this->displayFactory->getById($entry['displayId']);
                        $timeZoneCache[$entry['displayId']] = (empty($display->timeZone)) ? $defaultTimezone : $display->timeZone;
                    } catch (NotFoundException $e) {
                        $timeZoneCache[$entry['displayId']] = $defaultTimezone;
                    }
                }
                $start = $start->tz($timeZoneCache[$entry['displayId']]);
                $end = $end->tz($timeZoneCache[$entry['displayId']]);
            }
            $entry['start'] = $start->format($returnDateFormat);
            $entry['end'] = $end->format($returnDateFormat);
            $entry['duration'] = $end->diffInSeconds($start);
            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select($select . $body, $params);
            $this->getState()->recordsTotal = count($results);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
        return $this->render($request, $response);
    }

    /**
     * @param $displays
     * @param $timeZoneCache
     * @return array|int[]
     * @throws \Xibo\Support\Exception\InvalidArgumentException|\Xibo\Support\Exception\NotFoundException
     */
    private function authoriseDisplayIds($displays, &$timeZoneCache)
    {
        $displayIds = [];
        $displaysAccessible = [];

        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displaysAccessible[] = $display->displayId;

                // Cache the display timezone.
                $timeZoneCache[$display->displayId] = $display->timeZone;
            }

            if (count($displaysAccessible) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if (count($displays) <= 0) {
                $displayIds = $displaysAccessible;
            } else {
                foreach ($displays as $key => $id) {
                    if (!in_array($id, $displaysAccessible)) {
                        unset($displays[$key]);
                    } else {
                        $displayIds[] = $id;
                    }
                }

                if (count($displays) <= 0 ) {
                    $displayIds = [-1];
                }
            }
        } else {
            $displayIds = $displays;
        }

        return $displayIds;
    }
}
