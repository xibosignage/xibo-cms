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
use MongoDB\Driver\Exception\RuntimeException;
use phpDocumentor\Reflection\DocBlock\Tags\Uses;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\LogServiceInterface;

/**
 * Class MongoDbTimeSeriesStore
 * @package Xibo\Storage
 */
class MongoDbTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var StorageServiceInterface */
    private $store;

    /** @var LogServiceInterface */
    private $log;

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
    public function setDependencies($log, $mediaFactory = null, $widgetFactory = null, $layoutFactory = null, $displayFactory = null, $displayGroupFactory = null)
    {
        $this->log = $log;
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
            if ($stat['mediaId'] != null) {
                $media = $this->mediaFactory->getById($stat['mediaId']);
                $mediaName = $media->name; //dont remove used later
                $statData[$k]['mediaName'] = $mediaName;
                $tagFilter['media']= explode(',', $media->tags);
                // $tagFilter['media']= explode(',', $this->mediaFactory->getById(323)->tags);

            }
            // Widget name
            if ($stat['widgetId'] != null) {
                $widget = $this->widgetFactory->getById($stat['widgetId']);
                $widget->load();
                $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);
                $statData[$k]['widgetName'] = $widgetName;
            }

            // Layout name
            $layout = $this->layoutFactory->getById($stat['layoutId']);
            $statData[$k]['layoutName'] = $layout->layout;

            // Layout tags
            // $tagFilter['layout']= explode(',', $this->layoutFactory->getById(602)->tags);
            $tagFilter['layout']= explode(',', $layout->tags);

            // Display name
            $display = $this->displayFactory->getById($stat['displayId']);
            $statData[$k]['displayName'] = $display->display;

            // Display tags
            $tagFilter['dg'] = explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tags);
            // $tagFilter['dg'] = explode(',', $this->displayGroupFactory->getById(339)->tags);

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
                'start' => [ '$lte' => $toDt],
                'displayId' => [
                    '$in' => $displayIds
                ]
            ]
        ];

        // Tag Filter
        if($tags != null) {

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
            $match['$match']['layoutId'] = [ '$in' => $layoutIds ];
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
            '_id.layout' => 'layout',
            '_id.media' => 'media',
            '_id.layoutId' => 'layoutId',
            '_id.widgetId' => 'widgetId',
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
        if($array != null) {
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
                'mediaId' =>  1,
                'mediaName'=> 1,
                'widgetId' =>  1,
                'widgetName' =>  1,
                'layoutId' =>  1,
                'layoutName' =>  1,
                'displayId' =>  1,
                'displayName' =>  1,
                'start' => 1,
                'end' => 1,
                'type' => 1,
                'duration' => [
                    '$divide' => [[
                        '$subtract' =>
                            [
                                '$endDate',
                                '$startDate'
                            ]], 1000
                    ]
                ],
                'total' => ['$sum' => 1],
            ]
        ];

        $group = [
            '$group' => [
                '_id' => [
                    'type' => '$type',
                    'mediaId'=> [ '$ifNull' => [ '$mediaId', 'Null' ] ],
                    'widgetId'=> [ '$ifNull' => [ '$widgetId', 'Null' ] ],
                    'layoutId'=> [ '$ifNull' => [ '$layoutId', 'Null' ] ],
                    'displayId'=> [ '$ifNull' => [ '$displayId', 'Null' ] ],
                    'display'=> '$displayName',
                    'layout'=> '$layoutName',
                    'media'=> [ '$ifNull' => [ '$mediaName', '$widgetName' ] ],

                ],
                'minStart' => ['$min' => '$start'],
                'maxEnd' => ['$max' => '$end'],
                'numberPlays' => ['$sum' => 1],
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

        if(count($resTotal) > 0) {
            $totalStats = $resTotal[0]['totals'];
        }

        $rows = [];

        foreach ($result as $row) {
            $entry = [];

            $entry['type'] = $row['_id']['type'];
            $entry['displayId'] = $row['_id']['displayId'];
            $entry['display'] = isset($row['_id']['display']) ? $row['_id']['display']: 'No display';
            $entry['layout'] = isset($row['_id']['layout']) ? $row['_id']['layout']: 'No layout';
            $entry['media'] = isset($row['_id']['media']) ? $row['_id']['media'] : 'No media' ;
            $entry['numberPlays'] = $row['numberPlays'];
            $entry['duration'] = $row['duration'];
            $entry['minStart'] = $row['minStart'];
            $entry['maxEnd'] = $row['maxEnd'];
            $entry['layoutId'] = $row['_id']['layoutId'];
            $entry['widgetId'] = $row['_id']['widgetId'];
            $entry['mediaId'] = $row['_id']['mediaId'];

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
        try{
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

            if($fromDt != null) {
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

}