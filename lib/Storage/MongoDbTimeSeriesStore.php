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

namespace Xibo\Storage;

use MongoDB\Client;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class MongoDbTimeSeriesStore
 * @package Xibo\Storage
 */
class MongoDbTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var LogServiceInterface */
    private $log;

    /** @var DateServiceInterface */
    private $dateService;

    private $config;

    private $client;

    private $table = 'stat';

    /** @var  MediaFactory */
    protected $mediaFactory;

    /** @var  WidgetFactory */
    protected $widgetFactory;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  DisplayFactory */
    protected $displayFactory;

    /** @var  DisplayGroupFactory */
    protected $displayGroupFactory;

    /**
     * @inheritdoc
     */
    public function __construct($config = null)
    {

        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function setDependencies($log, $date, $layoutFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null, $displayGroupFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;

        try {
            $this->client = new Client('mongodb://'.$this->config['host'].':'. $this->config['port'],
                [
                    'username' => $this->config['username'],
                    'password' => $this->config['password']
                ]);
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {
        foreach ($statData as $k => $stat) {

            $statData[$k]['start'] = $statData[$k]['fromDt'];
            unset($statData[$k]['fromDt']);

            $statData[$k]['end'] = $statData[$k]['toDt'];
            unset($statData[$k]['toDt']);

            $statData[$k]['eventName'] = $statData[$k]['tag'];
            unset($statData[$k]['tag']);

            unset($statData[$k]['scheduleId']);
            unset($statData[$k]['statDate']);

            // Media name
            $mediaName = null;
            if ($stat['mediaId'] != null) {
                $media = $this->mediaFactory->getById($stat['mediaId']);
                $mediaName = $media->name; //dont remove used later
                $statData[$k]['mediaName'] = $mediaName;
                $tagFilter['media'] = explode(',', $media->tags);
            }

            // Widget name
            if ($stat['widgetId'] != null) {
                $widget = $this->widgetFactory->getById($stat['widgetId']);
                if($widget != null) {
                    $widget->load();
                    $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);
                    $statData[$k]['widgetName'] = $widgetName;
                }
            }

            // Layout data
            $layoutName = null;
            $layoutTags = null;

            try {
                $layout = $this->layoutFactory->getById($stat['layoutId']);

                $this->log->debug('Found layout : '. $stat['layoutId']);

                $campaignId = $layout->campaignId;
                $layoutName = $layout->layout;
                $layoutTags = $layout->tags;

            } catch (NotFoundException $error) {

                $this->log->debug('Layout not Found. Search in layout history for latest layout.');

                // an old layout which has been deleted still plays on the player
                // so we will get layout not found
                // Hence, get the latest layout

                $campaignId = $this->layoutFactory->getCampaignId($stat['layoutId']);

                if ($campaignId !== null) {

//                    $this->log->debug('CampaignId is not NULL.');

                    $latestLayoutId = $this->layoutFactory->getLatestLayoutId($campaignId);

                    $this->log->debug('Latest layoutId: '.$latestLayoutId);

                    // Latest layout
                    $layout = $this->layoutFactory->getById($latestLayoutId);
                    $layoutName = $layout->layout;
                    $layoutTags = $layout->tags;

                }

            }

            $statData[$k]['layoutName'] = $layoutName;

            // Get layout Campaign ID
            $statData[$k]['campaignId'] = (int) $campaignId;

            // Layout tags
            $tagFilter['layout'] = explode(',', $layoutTags);

            // Display name
            $display = $this->displayFactory->getById($stat['displayId']);
            $statData[$k]['displayName'] = $display->display;

            // Display tags
            $tagFilter['dg'] = explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tags);

            // TagFilter array
            $statData[$k]['tagFilter'] = $tagFilter;
        }

        // Insert statistics
        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $collection->insertMany($statData);
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }
    }

    /** @inheritdoc */
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start = null, $length = null)
    {
        $fromDt = date(DATE_ISO8601, strtotime($fromDt));
        $toDt = date(DATE_ISO8601, strtotime($toDt));

        // Filters the documents to pass only the documents that
        // match the specified condition(s) to the next pipeline stage.
        $match =  [
            '$match' => [
                'end' => [ '$gt' => $fromDt],
                'start' => [ '$lte' => $toDt]
            ]
        ];

        // Display Filter
        if (count($displayIds) > 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
        }

        // Tag Filter
        if ($tags != null) {

            $tagsArray = explode(',', $tags);

            // When exact match is not desired
            if ($exactTags != 1) {
                $tagsArray = array_map(function ($tag) { return new \MongoDB\BSON\Regex('.*'.$tag. '.*', 'i'); }, $tagsArray);
            }

            if (count($tagsArray) > 0) {
                $match['$match']['tagFilter.' . $tagsType] = [
                    '$exists' => true,
                    '$in' => $tagsArray
                ];
            }
        }

        // Layout Filter
        if (count($layoutIds) != 0) {
            $this->log->debug($layoutIds, JSON_PRETTY_PRINT);
            // Get campaignIds for selected layoutIds
            $campaignIds = [];
            foreach ($layoutIds as $layoutId) {
                $campaignIds[] = $this->layoutFactory->getCampaignId($layoutId);
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $this->log->debug($mediaIds, JSON_PRETTY_PRINT);
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        // For sorting
        // The selected column has a key
        $temp = [
            '_id.type' => 'type',
            '_id.display' => 'display',
            'layout' => 'layout',
            'media' => 'media',
            'eventName' => 'eventName',
            'layoutId' => 'layoutId',
            'widgetId' => 'widgetId',
            '_id.displayId' => 'displayId',
            'numberPlays' => 'numberPlays',
            'minStart' => 'minStart',
            'maxEnd' => 'maxEnd',
            'duration' => 'duration',
        ];

        // Remove ` and DESC from the array strings
        $cols = [];
        foreach ($columns as $column) {
            $str = str_replace("`","",str_replace(" DESC","",$column));
            if (\strpos($column, 'DESC') !== false) {
                $cols[$str] = -1;
            } else {
                $cols[$str] = 1;

            }
        }

        // The selected column key gets stored in an array with value 1 or -1 (for DESC)
        $array = [];
        foreach ($cols as $k => $v) {
            if (array_search($k, $temp))
                $array[array_search($k, $temp)] = $v;
        }

        $order = ['_id.type'=> 1]; // default sorting by type
        if ($array != null) {
            $order = $array;
        }

        $addFields = [
            '$addFields' => [
                'startDate' => [
                    '$dateFromString' => ['dateString' =>'$start']
                ],
                'endDate' => [
                    '$dateFromString' => ['dateString' =>'$end']
                ]
            ]
        ];

        $project = [
            '$project' => [
                'campaignId' =>  1,
                'mediaId' =>  1,
                'mediaName'=> 1,
                'media'=> [ '$ifNull' => [ '$mediaName', '$widgetName' ] ],
                'eventName' => 1,
                'widgetId' =>  1,
                'widgetName' =>  1,
                'layoutId' =>  1,
                'layoutName' =>  1,
                'displayId' =>  1,
                'displayName' =>  1,
                'start' => 1,
                'end' => 1,
                'type' => 1,
                'duration' => 1,
                'count' => 1,
                'total' => ['$sum' => 1],
            ]
        ];

        $group = [
            '$group' => [
                '_id' => [
                    'type' => '$type',
                    'campaignId'=> [ '$ifNull' => [ '$campaignId', '$layoutId' ] ],
                    'mediaorwidget'=> [ '$ifNull' => [ '$mediaId', '$widgetId' ] ],
                    'displayId'=> [ '$ifNull' => [ '$displayId', null ] ],
                    'display'=> '$displayName',
                    // we don't need to group by media name and widget name

                ],

                'media'=> [ '$first' => '$media'],
                'eventName'=> [ '$first' => '$eventName'],
                'mediaId' => ['$first' => '$mediaId'],
                'widgetId' => ['$first' => '$widgetId' ],

                'layout' => ['$first' => '$layoutName'],

                // use the last layoutId to say that is the latest layoutId
                'layoutId' => ['$last' => '$layoutId'],

                'minStart' => ['$min' => '$start'],
                'maxEnd' => ['$max' => '$end'],
                'numberPlays' => ['$sum' => '$count'],
                'duration' => ['$sum' => '$duration'],
                'total' => ['$max' => '$total'],
            ],
        ];

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $cursor = $collection->aggregate([
                // Filters the documents to pass only the documents that
                // match the specified condition(s) to the next pipeline stage.
                $match,
                $addFields,
                $project,
                $group,
                ['$skip' => $start],
                ['$limit' => $length],
                ['$sort' => $order],
            ]);

            $result = $cursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $this->log->debug($cursor, JSON_PRETTY_PRINT);
        $this->log->debug($result, JSON_PRETTY_PRINT);

        $totalStats = 0;
        try {
            $totalStatCursor = $collection->aggregate([
                $match,
                $project,
                $group,
                [
                    '$group' => [
                        '_id' => [],
                        'totals' => ['$sum' => '$total'],

                    ],
                ]
            ]);

            $resTotal = $totalStatCursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        if (count($resTotal) > 0) {
            $totalStats = $resTotal[0]['totals'];
        }

        $rows = [];

        foreach ($result as $row) {
            $entry = [];

            $entry['type'] = $row['_id']['type'];
            $entry['displayId'] = $row['_id']['displayId'];
            $entry['display'] = isset($row['_id']['display']) ? $row['_id']['display']: 'No display';
            $entry['layout'] = isset($row['layout']) ? $row['layout']: 'No layout';
            $entry['media'] = isset($row['media']) ? $row['media'] : 'No media' ;
            $entry['numberPlays'] = $row['numberPlays'];
            $entry['duration'] = $row['duration'];
            $entry['minStart'] = $row['minStart'];
            $entry['maxEnd'] = $row['maxEnd'];
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];
            $entry['tag'] = $row['eventName'];

            $rows[] = $entry;
        }

        return [
            'statData' => $rows,
            'count' => count($rows),
            'totalStats' => $totalStats
        ];
    }

    /** @inheritdoc */
    public function getEarliestDate()
    {
        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $earliestDate = $collection->aggregate([
                [
                    '$group' => [
                        '_id' => [],
                        'minDate' => ['$min' => '$start'],
                    ]
                ]
            ])->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $earliestDate;

    }

    /** @inheritdoc */
    public function getStats($fromDt, $toDt, $displayIds = null)
    {
        $fromDt = date(DATE_ISO8601, strtotime($fromDt));
        $toDt = date(DATE_ISO8601, strtotime($toDt));

        $match =  [
            '$match' => [
                'end' => [ '$gt' => $fromDt],
                'start' => [ '$lte' => $toDt]
            ]
        ];

        if (count($displayIds) != 0) {
            $this->log->debug($displayIds, JSON_PRETTY_PRINT);
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $cursor = $collection->aggregate([
                $match,
                [
                    '$project' => [
                        'type'=> 1,
                        'start'=> 1,
                        'end'=> 1,
                        'layout'=> '$layoutName',
                        'display'=> '$displayName',
                        'media'=> '$mediaName',
                        'tag'=> '$eventName',
                        'displayId'=> 1,
                        'layoutId'=> 1,
                        'widgetId'=> 1,
                        'mediaId'=> 1,

                    ]
                ]
            ]);
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $result = new TimeSeriesMongoDbResults($cursor);

        return $result;

    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {

        $fromDt = date(DATE_ISO8601, strtotime($fromDt));
        $toDt = date(DATE_ISO8601, strtotime($maxage));

        $collection = $this->client->selectCollection($this->config['database'], $this->table);

        $i = 0;
        $rows = 1;
        $options = array_merge([
            'maxAttempts' => 10,
            'statsDeleteSleep' => 3,
            'limit' => 10000,
        ], $options);

        $count = 0;
        while ($rows > 0) {

            $i++;

            if ($fromDt != null) {
                $query = [
                    'end' => ['$gt' => $fromDt],
                    'start' => ['$lte' => $toDt]
                ];
            } else {
                $query = [
                    'start' => ['$lte' => $toDt]
                ];
            }

            try{
                $findResult = $collection->find($query, ['limit' => $options['limit']])->toArray();
            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
            }

            $idsArray = array_map(function ($res) { return $res['_id']; }, $findResult);

            try {
                $deleteResult = $collection->deleteMany([
                    '_id' => ['$in' => $idsArray]
                ]);

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
                throw new \RuntimeException('Stats cannot be deleted.');
            }

            $rows = $deleteResult->getDeletedCount();
            $count += $rows;

            // Give MongoDB time to recover
            if ($rows > 0) {
                $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
                sleep($options['statsDeleteSleep']);
            }

            // Break if we've exceeded the maximum attempts.
            if ($i >= $options['maxAttempts'])
                break;
        }

        return $count;

    }

    /** @inheritdoc */
    public function getDailySummaryReport($displayIds, $diffInDays, $type, $layoutId, $mediaId, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('Y-m-d H:i:s');;
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('Y-m-d H:i:s'); // added a day

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('Y-m-d H:i:s');
            $today = $this->dateService->parse()->startOfDay()->format('Y-m-d H:i:s');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $firstdaythisweek = $this->dateService->parse()->startOfWeek()->format('Y-m-d H:i:s');
            $lastdaythisweek = $this->dateService->parse()->endOfWeek()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastweek = $this->dateService->parse()->startOfWeek()->subWeek()->format('Y-m-d H:i:s');
            $lastdaylastweek = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('Y-m-d H:i:s'); // added a second

            $firstdaythismonth = $this->dateService->parse()->startOfMonth()->format('Y-m-d H:i:s');
            $lastdaythismonth = $this->dateService->parse()->endOfMonth()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastmonth = $this->dateService->parse()->startOfMonth()->subMonth()->format('Y-m-d H:i:s');
            $lastdaylastmonth = $this->dateService->parse()->endOfMonth()->addSecond()->subMonth()->format('Y-m-d H:i:s');

            $firstdaythisyear = $this->dateService->parse()->startOfYear()->format('Y-m-d H:i:s');
            $lastdaythisyear = $this->dateService->parse()->endOfYear()->addSecond()->format('Y-m-d H:i:s');

            $firstdaylastyear = $this->dateService->parse()->startOfYear()->subYear()->format('Y-m-d H:i:s');
            $lastdaylastyear = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('Y-m-d H:i:s');

            if ($reportFilter == '') {

                $hour = 24;
                $input = range(0, $diffInDays);

                $periodStart = $fromDt;
                $periodEnd = $toDt;
            }

            // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'today')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = $today;
                $periodEnd = $nextday;
            }

            // where start is less than last hour of the day + 1 hour (i.e., today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'yesterday')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = $yesterday;
                $periodEnd = $today;
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'thisweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = $firstdaythisweek;
                $periodEnd = $lastdaythisweek;
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'lastweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = $firstdaylastweek;
                $periodEnd = $lastdaylastweek;
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'thismonth')) {

                $hour = 24;
                $input = range(0, 30);
                $periodStart = $firstdaythismonth;
                $periodEnd = $lastdaythismonth;
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'lastmonth')) {

                $hour = 24;
                $input = range(0, 30);
                $periodStart = $firstdaylastmonth;
                $periodEnd = $lastdaylastmonth;
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'thisyear')) {

                $hour = 24;
                $input = range(0, 365);
                $periodStart = $firstdaythisyear;
                $periodEnd = $lastdaythisyear;
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'lastyear')) {

                $hour = 24;
                $input = range(0, 365);
                $periodStart = $firstdaylastyear;
                $periodEnd = $lastdaylastyear;
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {

                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignId($layoutId);

                $matchType = [
                    '$eq' => [ '$type', 'layout' ]
                ];
                $matchId = [
                    '$eq' => [ '$campaignId', $campaignId ]
                ];
            } elseif (($type == 'media') && ($mediaId != '')) {
                $matchType = [
                    '$eq' => [ '$type', 'media' ]
                ];
                $matchId = [
                    '$eq' => [ '$mediaId', $mediaId ]
                ];
            }

            // GROUP BY
            if ($groupByFilter == 'byweek') {
                $groupBy = [
                    'yearWeek' => '$yearWeek',
                ];
                $sort =  [ 'periodStartDate' => 1 ];

            } elseif ($groupByFilter == 'bymonth') {

                if (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $groupBy = [
                        'monthNo' => '$monthNo'
                    ];
                    $sort =  [ '_id' => 1 ];

                } else {
                    $groupBy = [
                        'yearDate' => '$yearDate',
                        'monthNo' => '$monthNo'
                    ];
                    $sort =  [ '_id' => 1 ];

                }

            } else {
                $groupBy = [
                    'periodStart' => '$periodStart'
                ];
                $sort =  [ '_id' => 1 ];
            }

            // AGGREGATION QUERY
            $collection = $this->client->selectCollection($this->config['database'], $this->table);

            try {
                $cursor = $collection->aggregate([

                    // STEP 1: GENERATE PERIOD START AND END
                    // we add a temporary field (i.e., tempField) then we perform group by to get only one matched result.
                    // we add a range of periods with this only result

                    // reason to add tempField: if we start with project then we would end up adding period start and
                    // period end for each record of the collection (which we want to avoid)
                    [
                        '$addFields' => [
                            'tempField' => null
                        ]
                    ],

                    // then group by the temp field
                    [
                        '$group' => [
                            '_id' => [
                                'tempField'=> '$tempField',
                            ],
                        ],
                    ],

                    // here we generate the periods for a given range (i.e. $input)
                    [
                        '$project' => [
                            'periods' =>  [
                                '$map' => [
                                    'input' => $input, // this is an array that we will use to map and generate period dates
                                    'as' => 'number',
                                    'in' => [
                                        'numberId' => '$$number',
                                        'start' => [
                                            '$add' => [
                                                ['$dateFromString' => ['dateString'=> $periodStart]],
                                                [
                                                    '$multiply' => [
                                                        $hour*3600000,
                                                        '$$number'
                                                    ]
                                                ]
                                            ]
                                        ],
                                        'end' => [
                                            '$add' => [
                                                [
                                                    '$add' => [
                                                        ['$dateFromString' => ['dateString'=> $periodStart]],
                                                        [
                                                            '$multiply' => [
                                                                $hour*3600000,
                                                                '$$number'
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                                , $hour*3600000
                                            ]
                                        ],
                                    ]
                                ]
                            ]
                        ]
                    ],

                    // periods needs to be unwind to merge next
                    [
                        '$unwind' => '$periods'
                    ],

                    // replace the root to eliminate _id and get only periods
                    [
                        '$replaceRoot' => [
                            'newRoot' => '$periods'
                        ]
                    ],

                    // project periodStart and periodEnd
                    [
                        '$project' => [
                            'periodStart' => '$start',
                            'periodEnd' => '$end'
                        ]
                    ],

                    // format periodStart and periodEnd as string
                    // get month number, year and week to group by later
                    [
                        '$project' => [
                            'periodStart' => [
                                '$dateToString' => [
                                    'format' => '%Y-%m-%d %H:%M:%S',
                                    'date' => '$periodStart'
                                ]
                            ],
                            'periodEnd' => [
                                '$dateToString' => [
                                    'format' => '%Y-%m-%d %H:%M:%S',
                                    'date' => '$periodEnd'
                                ]
                            ],

                            // group by
                            'monthNo' => ['$month' => '$periodStart'],
                            'yearDate' => ['$isoWeekYear' => '$periodStart'],
                            'yearWeek' => ['$isoWeek' => '$periodStart']
                        ]
                    ],

                    // where periods start is greater than or equal given start period and
                    // periods end is less than or equal given end period

                    // to prevent any exceeding period
                    // for a month 31 records are generated if a month has 28 days we eliminate the rest
                    // for a year 366 records are generated
                    [
                        '$match' => [
                            'periodStart' =>  [
                                '$gte' => $periodStart
                            ],
                            'periodEnd' => [
                                '$lte' => $periodEnd
                            ],
                        ]
                    ],

                    // upto this point we have generated all our required periods

                    // STEP 2: ADD STATDATA ARRAY WITH GENERATED PERIOD START AND END

                    // match stat records that lies within each period with $lookup pipeline
                    // "statdata" array holds matched stat records
                    [
                        '$lookup' => [
                            'from' => 'stat',
                            'let' => [
                                'periodStart' => [
                                    '$dateFromString' => [
                                        'dateString' => '$periodStart'
                                    ]
                                ],
                                'periodEnd' => [
                                    '$dateFromString' => [
                                        'dateString' => '$periodEnd'
                                    ]
                                ]
                            ],
                            'pipeline' => [
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$and' => [

                                                // match media id is 926
                                                // stat.start < $periodEnd AND stat.end > $periodStart
                                                $matchType,
                                                $matchId,

                                                // display ids
                                                [
                                                    '$in' => [ '$displayId', $displayIds ]
                                                ],

                                                // for example, when report filter 'today' is selected
                                                // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
                                                // and end is greater than or equal first hour of the day
                                                [
                                                    '$lt' => [ [
                                                        '$dateFromString' => [
                                                            'dateString' => '$start'
                                                        ]
                                                    ], ['$dateFromString' => ['dateString'=> $periodEnd]] ]
                                                ],
                                                [
                                                    '$gt' => [ [
                                                        '$dateFromString' => [
                                                            'dateString' => '$end'
                                                        ]
                                                    ], ['$dateFromString' => ['dateString'=> $periodStart]]  ]
                                                ],

                                                // records that are matched with the period data
                                                [
                                                    '$lt' => [ [
                                                        '$dateFromString' => [
                                                            'dateString' => '$start'
                                                        ]
                                                    ], '$$periodEnd' ]
                                                ],
                                                [
                                                    '$gt' => [ [
                                                        '$dateFromString' => [
                                                            'dateString' => '$end'
                                                        ]
                                                    ], '$$periodStart' ]
                                                ]
                                            ]
                                        ]
                                    ]

                                ],

                                // convert stat collection start and end as date
                                [
                                    '$project' => [
                                        'count' => '$count',
                                        'duration' => '$duration',
                                        'start' => [
                                            '$dateFromString' => [
                                                'dateString' => '$start'
                                            ]
                                        ],
                                        'end' => [
                                            '$dateFromString' => [
                                                'dateString' => '$end'
                                            ]
                                        ]
                                    ]

                                ],

                                // we need this project so that we can use:
                                // $start to find actualStart using $max
                                // $end to find actualEnd using $min
                                [
                                    '$project' => [
                                        '_id' => 1,
                                        'count' => 1,
                                        'duration' => 1,
                                        'stat_start' => '$start',
                                        'stat_end' => '$end',
                                        'actualStart' => [
                                            '$max' => [ '$start', '$$periodStart' ]
                                        ],
                                        'actualEnd' => [
                                            '$min' => [ '$end', '$$periodEnd' ]
                                        ],
                                        'actualDiff' => [
                                            '$min' => [
                                                '$duration',
                                                [
                                                    '$divide' => [
                                                        [
                                                            '$subtract' => [
                                                                ['$min' => [ '$end', '$$periodEnd' ]],
                                                                ['$max' => [ '$start', '$$periodStart' ]]
                                                            ]
                                                        ], 1000
                                                    ]
                                                ]
                                            ]
                                        ],

                                    ]

                                ]

                            ],
                            'as' => 'statdata'
                        ]
                    ],

                    // STEP 3: GROUP BY
                    [
                        '$group' => [
                            '_id' => $groupBy,

                            // keep periodStart which is a string
                            'start' => ['$first' => '$periodStart'],

                            // reason for double sum
                            // a single stage pipeline version of an aggregate
                            // operation with an extra field that holds the sum expression before the group pipeline then
                            // calling that field as the $sum operator in the group.
                            'NumberPlays' => ['$sum' => ['$sum' => '$statdata.count']],
                            'Duration' => ['$sum' => ['$sum' => '$statdata.actualDiff']],

                            // convert periodStart as date that will be used later to get month number, year and week
                            'periodStartDate' => [
                                '$first' => [
                                    '$dateFromString' => [
                                        'dateString' => '$periodStart'
                                    ]
                                ]
                            ],

                        ]
                    ],

                    // STEP 4: SORT BY
                    [
                        '$sort' => $sort
                    ],

                    // STEP 5: FINAL PROJECT
                    [
                        '$project' => [
                            'start' => 1,
                            'NumberPlays' => 1,
                            'Duration' => 1,
                            'monthNo' => [
                                '$month' =>  '$periodStartDate'
                            ],
                            'yearDate' => [
                                '$isoWeekYear' =>  '$periodStartDate'
                            ],
                            'weekStart' => [
                                '$dateToString' => [
                                    'format' => '%Y-%m-%d 00:00:00',
                                    'date' => [
                                        '$subtract' => [
                                            '$periodStartDate',
                                            [
                                                '$multiply' => [
                                                    [
                                                        '$subtract' => [
                                                            [
                                                                '$isoDayOfWeek' => '$periodStartDate'
                                                            ], 1

                                                        ]
                                                    ], 86400000
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            'weekEnd' => [
                                '$dateToString' => [
                                    'format' => '%Y-%m-%d 00:00:00',
                                    'date' => [
                                        '$add' => [
                                            [
                                                '$subtract' => [
                                                    '$periodStartDate',
                                                    [
                                                        '$multiply' => [
                                                            [
                                                                '$subtract' => [
                                                                    [
                                                                        '$isoDayOfWeek' => '$periodStartDate'
                                                                    ], 1

                                                                ]
                                                            ], 86400000
                                                        ]
                                                    ]
                                                ]
                                            ], 518400000 // add 6 days (86400000 * 6) to get weekEnd date. e.g. weekStart is 2019-03-11 then weekEnd is 2019-03-17
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],

                    // mongodb doesnot have monthname
                    // so we use addFields to add month name (shortMonth) in a $let aggregation (map monthNo with monthString)
                    [
                        '$addFields' => [
                            'shortMonth' => [
                                '$let' => [
                                    'vars' => [
                                        'monthString' => ['NA', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                                    ],
                                    'in' => [
                                        '$arrayElemAt' => [
                                            '$$monthString', '$monthNo'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]);

                // log query
                $this->log->debug($cursor);

                $result = $cursor->toArray();

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
            }

            return $result;

        } else {
            return [];
        }
    }
}