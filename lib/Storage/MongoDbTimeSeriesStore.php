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

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Xibo\Exception\GeneralException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
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

    /** @var array */
    private $config;

    /** @var \MongoDB\Client */
    private $client;

    private $table = 'stat';

    private $periodTable = 'period';

    // Keep all stats in this array after processing
    private $stats = [];

    private $mediaItems = [];
    private $widgets = [];
    private $layouts = [];
    private $displayGroups = [];
    private $layoutsNotFound = [];
    private $mediaItemsNotFound = [];

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

    /** @var  CampaignFactory */
    protected $campaignFactory;

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
    public function setDependencies($log, $date, $layoutFactory = null, $campaignFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null, $displayGroupFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->campaignFactory = $campaignFactory;

        try {
            $uri = isset($this->config['uri']) ? $this->config['uri'] : 'mongodb://' . $this->config['host'] . ':' . $this->config['port'];
            $this->client = new Client($uri, [
                'username' => $this->config['username'],
                'password' => $this->config['password']
            ], (array_key_exists('driverOptions', $this->config) ? $this->config['driverOptions'] : []));
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->critical($e->getMessage());
        }

        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {

        // We need to transform string date to UTC date
        $statData['statDate'] = new UTCDateTime($statData['statDate']->format('U') * 1000);

        // In mongo collection we save fromDt/toDt as start/end
        // and tag as eventName
        // so we unset fromDt/toDt/tag from individual stats array
        $statData['start'] = new UTCDateTime($statData['fromDt']->format('U') * 1000);
        $statData['end'] = new UTCDateTime($statData['toDt']->format('U') * 1000);
        $statData['eventName'] = $statData['tag'];
        unset($statData['fromDt']);
        unset($statData['toDt']);
        unset($statData['tag']);

        // Make an empty array to collect layout/media/display tags into
        $tagFilter = [];

        // Media name
        $mediaName = null;
        if (!empty($statData['mediaId'])) {

            if (array_key_exists($statData['mediaId'], $this->mediaItems)) {

                $media = $this->mediaItems[$statData['mediaId']];

            } else {

                try {

                    // Media exists in not found cache
                    if (in_array($statData['mediaId'], $this->mediaItemsNotFound)) {
                        return;
                    }

                    $media = $this->mediaFactory->getById($statData['mediaId']);

                    // Cache media
                    $this->mediaItems[$statData['mediaId']] = $media;

                } catch (NotFoundException $error) {

                    // Cache Media not found, ignore and log the stat
                    if (!in_array($statData['mediaId'], $this->mediaItemsNotFound)) {
                        $this->mediaItemsNotFound[] = $statData['mediaId'];
                        $this->log->error('Media not found. Media Id: '. $statData['mediaId']);
                    }

                    return;
                }
            }

            $mediaName = $media->name; //dont remove used later
            $mediaTags = $media->tags;
            $statData['mediaName'] = $mediaName;

            if (isset($mediaTags)) {
                $arrayOfTags = explode(',', $mediaTags);
                for ($i=0; $i<count($arrayOfTags); $i++) {
                    if (isset($arrayOfTags[$i]) ) {

                        if (!empty($arrayOfTags[$i]))
                            $tagFilter['media'][$i]['tag'] = $arrayOfTags[$i];
                    }
                }
            }
        }

        // Widget name
        if (!empty($statData['widgetId'])) {

            if (array_key_exists($statData['widgetId'], $this->widgets)) {

                $widget = $this->widgets[$statData['widgetId']];

            } else {

                // We are already doing getWidgetForStat is XMDS,
                // checking widgetId not found does not require
                // We should always be able to get the widget
                try {
                    $widget = $this->widgetFactory->getById($statData['widgetId']);

                    // Cache widget
                    $this->widgets[$statData['widgetId']] = $widget;

                } catch (\Exception $error) {
                    // Widget not found, ignore and log the stat
                    $this->log->error('Widget not found. Widget Id: '. $statData['widgetId']);

                    return;
                }
            }

            if($widget != null) {
                $widget->load();
                $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);

                // SET widgetName
                $statData['widgetName'] = $widgetName;
            }

        }

        // Layout data
        $layoutName = null;
        $layoutTags = null;

        // For a type "event" we have layoutid 0 so is campaignId
        // otherwise we should try and resolve the campaignId
        $campaignId = 0;
        if ($statData['type'] != 'event') {
            if (array_key_exists($statData['layoutId'], $this->layouts)) {
                $layout = $this->layouts[$statData['layoutId']];
            } else {

                try {

                    // Layout exists in not found cache
                    if (in_array($statData['layoutId'], $this->layoutsNotFound)) {
                        return;
                    }

                    // Get the layout campaignId - this can give us a campaignId of a layoutId which id was replaced when draft to published
                    $layout = $this->layoutFactory->getByLayoutHistory($statData['layoutId']);

                    $this->log->debug('Found layout : '. $statData['layoutId']);

                    // Cache layout
                    $this->layouts[$statData['layoutId']] = $layout;

                } catch (NotFoundException $error) {
                    // All we can do here is log
                    // we shouldn't ever get in this situation because the campaignId we used above will have
                    // already been looked up in the layouthistory table.

                    // Cache layouts not found
                    if (!in_array($statData['layoutId'], $this->layoutsNotFound)) {
                        $this->layoutsNotFound[] = $statData['layoutId'];
                        $this->log->alert('Error processing statistic into MongoDB. Layout not found. Layout Id: ' . $statData['layoutId']);
                    }

                    return;
                } catch (XiboException $error) {

                    // Cache layouts not found
                    if (!in_array($statData['layoutId'], $this->layoutsNotFound)) {
                        $this->layoutsNotFound[] = $statData['layoutId'];
                        $this->log->error('Layout not found. Layout Id: '. $statData['layoutId']);
                    }

                    return;
                }
            }

            $campaignId = (int) $layout->campaignId;
            $layoutName = $layout->layout;
            $layoutTags = $layout->tags;

        }

        // Get layout Campaign ID
        $statData['campaignId'] = $campaignId;

        $statData['layoutName'] = $layoutName;

        // Layout tags
        if (isset($layoutTags)) {
            $arrayOfTags = explode(',', $layoutTags);
            for ($i=0; $i<count($arrayOfTags); $i++) {
                if (isset($arrayOfTags[$i]) ) {

                    if (!empty($arrayOfTags[$i]))
                        $tagFilter['layout'][$i]['tag'] = $arrayOfTags[$i];
                }
            }
        }


        // Display
        $display = $statData['display'];

        // Display ID
        $statData['displayId'] = $display->displayId;
        unset($statData['display']);

        // Display name
        $statData['displayName'] = $display->display;

        $arrayOfTags = array_filter(explode(',', $display->tags));
        $arrayOfTagValues = array_filter(explode(',', $display->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                $tagFilter['dg'][$i]['val'] = $arrayOfTagValues[$i];
            } else {
                $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
            }
        }

        // Display tags
        if (array_key_exists($display->displayGroupId, $this->displayGroups)) {
            $displayGroup = $this->displayGroups[$display->displayGroupId];
        } else {

            try {

                $displayGroup = $this->displayGroupFactory->getById($display->displayGroupId);

                // Cache displaygroup
                $this->displayGroups[$display->displayGroupId] = $displayGroup;

            } catch (NotFoundException $notFoundException) {
                $this->log->error('Display group not found');
                return;
            }

        }

        $arrayOfTags = array_filter(explode(',', $displayGroup->tags));
        $arrayOfTagValues = array_filter(explode(',', $displayGroup->tagValues));
        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                $tagFilter['dg'][$i]['val'] = $arrayOfTagValues[$i];
            } else {
                $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
            }
        }

        // TagFilter array
        $statData['tagFilter'] = $tagFilter;

        $this->stats[] = $statData;

    }

    /** @inheritdoc */
    public function addStatFinalize()
    {
        // Insert statistics
        if (count($this->stats) > 0) {
            $collection = $this->client->selectCollection($this->config['database'], $this->table);

            try {
                $collection->insertMany($this->stats);

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
                throw new \MongoDB\Exception\RuntimeException($e->getMessage());
            }
        }

        // Create a period collection if it doesnot exist
        $collectionPeriod = $this->client->selectCollection($this->config['database'], $this->periodTable);

        try {
            $cursor = $collectionPeriod->findOne(['name' => 'period']);

            if (count($cursor) <= 0 ) {
                $this->log->error('Period collection does not exist in Mongo DB.');
                // Period collection created
                $collectionPeriod->insertOne(['name' => 'period']);
                $this->log->debug('Period collection created.');

            }

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

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
                        'minDate' => ['$min' => '$statDate'],
                    ]
                ]
            ])->toArray();

            if(count($earliestDate) > 0) {
                return [
                    'minDate' => $earliestDate[0]['minDate']->toDateTime()->format('U')
                ];
            }

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return [];
    }

    /** @inheritdoc */
    public function getStats($filterBy = [])
    {
        // do we consider that the fromDt and toDt will always be provided?
        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $statDate = isset($filterBy['statDate']) ? $filterBy['statDate'] : null;

        // In the case of user switches from mysql to mongo - laststatId were saved as integer
        if (isset($filterBy['statId'])) {
            if (is_numeric($filterBy['statId'])) {
                throw new InvalidArgumentException(__('Invalid statId provided'), 'statId');
            }
            else {
                $statId = $filterBy['statId'];
            }
        } else {
            $statId = null;
        }

        $type = isset($filterBy['type']) ? $filterBy['type'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];
        $layoutIds = isset($filterBy['layoutIds']) ? $filterBy['layoutIds'] : [];
        $mediaIds = isset($filterBy['mediaIds']) ? $filterBy['mediaIds'] : [];
        $campaignId = isset($filterBy['campaignId']) ? $filterBy['campaignId'] : null;
        $eventTag = isset($filterBy['eventTag']) ? $filterBy['eventTag'] : null;

        // Limit
        $start = isset($filterBy['start']) ? $filterBy['start'] : null;
        $length = isset($filterBy['length']) ? $filterBy['length'] : null;

        // Match query
        $match = [];

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $fromDt = new UTCDateTime($fromDt->format('U')*1000);
            $match['$match']['end'] = [ '$gt' => $fromDt];

            $toDt = new UTCDateTime($toDt->format('U')*1000);
            $match['$match']['start'] = [ '$lte' => $toDt];
        }

        // statDate Filter
        // get the next stats from the given date
        if ($statDate != null) {
            $statDate = new UTCDateTime($statDate->format('U')*1000);
            $match['$match']['statDate'] = [ '$gte' => $statDate];
        }

        if (!empty($statId)) {
            $match['$match']['_id'] = [ '$gt' => new ObjectId($statId)];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        // Campaign Filter
        // ---------------
        // Use the Layout Factory to get all Layouts linked to the provided CampaignId
        if ($campaignId != null) {
            $campaignIds = [];
            try {
                $layouts = $this->layoutFactory->getByCampaignId($campaignId, false);
                if (count($layouts) > 0) {
                    foreach ($layouts as $layout) {
                        $campaignIds[] = $layout->campaignId;
                    }

                    // Add to our match
                    $match['$match']['campaignId'] = ['$in' => $campaignIds];
                }
            } catch (NotFoundException $ignored) {}
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
        }

        // Event Tag Filter
        if ($eventTag != null) {
            $match['$match']['eventName'] = $eventTag;
        }

        // Layout Filter
        if (count($layoutIds) != 0) {
            // Get campaignIds for selected layoutIds
            $campaignIds = [];
            foreach ($layoutIds as $layoutId) {
                try {
                    $campaignIds[] = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
                } catch (XiboException $ignored) {
                    // TODO: this is quite inefficient and could be reworked to return an empty TimeSeriesResults
                    $campaignIds[] = -1;
                }
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);


        // Get total
        try {
            $totalQuery = [
                $match,
                [
                    '$group' => [
                        '_id'=> null,
                        'count' => ['$sum' => 1],
                    ]
                ],
            ];
            $totalCursor = $collection->aggregate($totalQuery, ['allowDiskUse' => true]);

            $totalCount = $totalCursor->toArray();
            $total = (count($totalCount) > 0) ? $totalCount[0]['count'] : 0;

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
        }

        try {
            $query = [
                $match,
                [
                    '$project' => [
                        'id'=> '$_id',
                        'type'=> 1,
                        'start'=> 1,
                        'end'=> 1,
                        'layout'=> '$layoutName',
                        'display'=> '$displayName',
                        'media'=> '$mediaName',
                        'tag'=> '$eventName',
                        'duration'=> '$duration',
                        'count'=> '$count',
                        'displayId'=> 1,
                        'layoutId'=> 1,
                        'widgetId'=> 1,
                        'mediaId'=> 1,
                        'campaignId'=> 1,
                        'statDate'=> 1,
                        'engagements'=> 1,
                        'tagFilter' => 1
                    ]
                ],
            ];

            // Sort by id (statId) - we must sort before we do pagination as mongo stat has descending order indexing on start/end
            $query[]['$sort'] = ['id'=> 1];

            if ($start !== null && $length !== null) {
                $query[]['$skip'] =  $start;
                $query[]['$limit'] = $length;
            }

            $cursor = $collection->aggregate($query, ['allowDiskUse' => true]);

            $result = new TimeSeriesMongoDbResults($cursor);

            // Total
            $result->totalCount = $total;

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
        }

        return $result;
    }

    /** @inheritdoc */
    public function getExportStatsCount($filterBy = [])
    {
        // do we consider that the fromDt and toDt will always be provided?
        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];

        // Match query
        $match = [];

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $fromDt = new UTCDateTime($fromDt->format('U')*1000);
            $match['$match']['end'] = [ '$gt' => $fromDt];

            $toDt = new UTCDateTime($toDt->format('U')*1000);
            $match['$match']['start'] = [ '$lte' => $toDt];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);

        // Get total
        try {
            $totalQuery = [
                $match,
                [
                    '$group' => [
                        '_id'=> null,
                        'count' => ['$sum' => 1],
                    ]
                ],
            ];
            $totalCursor = $collection->aggregate($totalQuery, ['allowDiskUse' => true]);

            $totalCount = $totalCursor->toArray();
            $total = (count($totalCount) > 0) ? $totalCount[0]['count'] : 0;

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting total number of Proof of Play data, please consult your administrator'));
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting total number of Proof of Play data, please consult your administrator'));
        }

        return $total;
    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {
        // Set default options
        $options = array_merge([
            'maxAttempts' => 10,
            'statsDeleteSleep' => 3,
            'limit' => 1000,
        ], $options);

        // we dont use $options['limit'] anymore.
        // we delete all the records at once based on filter criteria (no-limit approach)

        $toDt = new UTCDateTime($maxage->format('U')*1000);

        $collection = $this->client->selectCollection($this->config['database'], $this->table);

        $rows = 1;
        $count = 0;

        if ($fromDt != null) {

            $start = new UTCDateTime($fromDt->format('U')*1000);
            $filter =  [
                'start' => ['$lte' => $toDt],
                'end' => ['$gt' => $start]
            ];

        } else {

            $filter =  [
                'start' => ['$lte' => $toDt]
            ];
        }

        try {
            $deleteResult = $collection->deleteMany(
                $filter
            );
            $rows = $deleteResult->getDeletedCount();


        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $count += $rows;

        // Give MongoDB time to recover
        if ($rows > 0) {
            $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
            sleep($options['statsDeleteSleep']);
        }


        return $count;

    }

    /** @inheritdoc */
    public function getEngine()
    {
        return 'mongodb';
    }

    /** @inheritdoc */
    public function executeQuery($options = [])
    {
        $this->log->debug('Execute MongoDB query.');

        $options = array_merge([
            'allowDiskUse' => true
        ], $options);

        // Aggregate command options
        $aggregateConfig['allowDiskUse'] = $options['allowDiskUse'];
        if (!empty($options['maxTimeMS'])) {
            $aggregateConfig['maxTimeMS']= $options['maxTimeMS'];
        }

        $collection = $this->client->selectCollection($this->config['database'], $options['collection']);
        try {
            $cursor = $collection->aggregate($options['query'], $aggregateConfig);

            // log query
            $this->log->debug($cursor);

            $results = $cursor->toArray();

        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException($e->getMessage());
        }

        return $results;

    }
}