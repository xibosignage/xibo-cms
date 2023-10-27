<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Carbon\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Xibo\Entity\Campaign;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class MongoDbTimeSeriesStore
 * @package Xibo\Storage
 */
class MongoDbTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var LogServiceInterface */
    private $log;

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
    public function setDependencies($log, $layoutFactory, $campaignFactory, $mediaFactory, $widgetFactory, $displayFactory, $displayGroupFactory)
    {
        $this->log = $log;
        $this->layoutFactory = $layoutFactory;
        $this->campaignFactory = $campaignFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        return $this;
    }

    /**
     * @param \Xibo\Storage\StorageServiceInterface $store
     * @return $this|\Xibo\Storage\MongoDbTimeSeriesStore
     */
    public function setStore($store)
    {
        return $this;
    }

    /**
     * Set Client in the event you want to completely replace the configuration options and roll your own client.
     * @param \MongoDB\Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * Get a MongoDB client to use.
     * @return \MongoDB\Client
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getClient()
    {
        if ($this->client === null) {
            try {
                $uri = isset($this->config['uri']) ? $this->config['uri'] : 'mongodb://' . $this->config['host'] . ':' . $this->config['port'];
                $this->client = new Client($uri, [
                    'username' => $this->config['username'],
                    'password' => $this->config['password']
                ], (array_key_exists('driverOptions', $this->config) ? $this->config['driverOptions'] : []));
            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error('Unable to connect to MongoDB: ' . $e->getMessage());
                $this->log->debug($e->getTraceAsString());
                throw new GeneralException('Connection to Time Series Database failed, please try again.');
            }
        }

        return $this->client;
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
            $statData['mediaName'] = $mediaName;

            $i = 0;
            foreach ($media->tags as $tagLink) {
                $tagFilter['media'][$i]['tag'] = $tagLink->tag;
                if (isset($tagLink->value)) {
                    $tagFilter['media'][$i]['val'] = $tagLink->value;
                }
                $i++;
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

            if ($widget != null) {
                $widget->load();
                $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);

                // SET widgetName
                $statData['widgetName'] = $widgetName;
            }
        }

        // Layout data
        $layoutName = null;

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
                } catch (GeneralException $error) {
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

            $i = 0;
            foreach ($layout->tags as $tagLink) {
                $tagFilter['layout'][$i]['tag'] = $tagLink->tag;
                if (isset($tagLink->value)) {
                    $tagFilter['layout'][$i]['val'] = $tagLink->value;
                }
                $i++;
            }
        }

        // Get layout Campaign ID
        $statData['campaignId'] = $campaignId;

        $statData['layoutName'] = $layoutName;


        // Display
        $display = $statData['display'];

        // Display ID
        $statData['displayId'] = $display->displayId;
        unset($statData['display']);

        // Display name
        $statData['displayName'] = $display->display;

        $i = 0;
        foreach ($display->tags as $tagLink) {
            $tagFilter['dg'][$i]['tag'] = $tagLink->tag;
            if (isset($tagLink->value)) {
                $tagFilter['dg'][$i]['val'] = $tagLink->value;
            }
            $i++;
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

        $i = 0;
        foreach ($displayGroup->tags as $tagLink) {
            $tagFilter['dg'][$i]['tag'] = $tagLink->tag;
            if (isset($tagLink->value)) {
                $tagFilter['dg'][$i]['val'] = $tagLink->value;
            }
            $i++;
        }

        // TagFilter array
        $statData['tagFilter'] = $tagFilter;

        // Parent Campaign
        if (array_key_exists('parentCampaign', $statData)) {
            if ($statData['parentCampaign'] instanceof Campaign) {
                $statData['parentCampaign'] = $statData['parentCampaign']->campaign;
            } else {
                $statData['parentCampaign'] = '';
            }
        }

        $this->stats[] = $statData;
    }

    /**
     * @inheritdoc
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addStatFinalize()
    {
        // Insert statistics
        if (count($this->stats) > 0) {
            $collection = $this->getClient()->selectCollection($this->config['database'], $this->table);

            try {
                $collection->insertMany($this->stats);

                // Reset
                $this->stats = [];
            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
                throw new \MongoDB\Exception\RuntimeException($e->getMessage());
            }
        }

        // Create a period collection if it doesnot exist
        $collectionPeriod = $this->getClient()->selectCollection($this->config['database'], $this->periodTable);

        try {
            $cursor = $collectionPeriod->findOne(['name' => 'period']);

            if ($cursor === null) {
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
        $collection = $this->getClient()->selectCollection($this->config['database'], $this->table);
        try {
            // _id is the same as statDate for the purposes of sorting (stat date being the date/time of stat insert)
            $earliestDate = $collection->find([], [
                'limit' => 1,
                'sort' => ['start' => 1]
            ])->toArray();

            if (count($earliestDate) > 0) {
                return Carbon::instance($earliestDate[0]['start']->toDateTime());
            }
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getStats($filterBy = [], $isBufferedQuery = false)
    {
        // do we consider that the fromDt and toDt will always be provided?
        $fromDt = $filterBy['fromDt'] ?? null;
        $toDt = $filterBy['toDt'] ?? null;
        $statDate = $filterBy['statDate'] ?? null;
        $statDateLessThan = $filterBy['statDateLessThan'] ?? null;

        // In the case of user switches from mysql to mongo - laststatId were saved as integer
        if (isset($filterBy['statId'])) {
            try {
                $statId = new ObjectID($filterBy['statId']);
            } catch (\Exception $e) {
                throw new InvalidArgumentException(__('Invalid statId provided'), 'statId');
            }
        } else {
            $statId = null;
        }

        $type = $filterBy['type'] ?? null;
        $displayIds = $filterBy['displayIds'] ?? [];
        $layoutIds = $filterBy['layoutIds'] ?? [];
        $mediaIds = $filterBy['mediaIds'] ?? [];
        $campaignId = $filterBy['campaignId'] ?? null;
        $parentCampaignId = $filterBy['parentCampaignId'] ?? null;
        $mustHaveParentCampaign = $filterBy['mustHaveParentCampaign'] ?? false;
        $eventTag = $filterBy['eventTag'] ?? null;

        // Limit
        $start = $filterBy['start'] ?? null;
        $length = $filterBy['length'] ?? null;

        // Match query
        $match = [];

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $fromDt = new UTCDateTime($fromDt->format('U')*1000);
            $match['$match']['end'] = ['$gt' => $fromDt];

            $toDt = new UTCDateTime($toDt->format('U')*1000);
            $match['$match']['start'] = ['$lte' => $toDt];
        } elseif (($fromDt != null) && ($toDt == null)) {
            $fromDt = new UTCDateTime($fromDt->format('U') * 1000);
            $match['$match']['start'] = ['$gte' => $fromDt];
        }

        // statDate and statDateLessThan Filter
        // get the next stats from the given date
        $statDateQuery = [];
        if ($statDate != null) {
            $statDate = new UTCDateTime($statDate->format('U')*1000);
            $statDateQuery['$gte'] = $statDate;
        }

        if ($statDateLessThan != null) {
            $statDateLessThan = new UTCDateTime($statDateLessThan->format('U')*1000);
            $statDateQuery['$lt'] = $statDateLessThan;
        }

        if (count($statDateQuery) > 0) {
            $match['$match']['statDate'] = $statDateQuery;
        }

        if ($statId !== null) {
            $match['$match']['_id'] = ['$gt' => new ObjectId($statId)];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = ['$in' => $displayIds];
        }

        // Campaign/Layout Filter
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
            } catch (NotFoundException $ignored) {
            }
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = new Regex($type, 'i');
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
                } catch (NotFoundException $notFoundException) {
                    // Ignore the missing one
                    $this->log->debug('Filter for Layout without Layout History Record, layoutId is ' . $layoutId);
                }
            }
            $match['$match']['campaignId'] = ['$in' => $campaignIds];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $match['$match']['mediaId'] = ['$in' => $mediaIds];
        }

        // Parent Campaign Filter
        if ($parentCampaignId != null) {
            $match['$match']['parentCampaignId'] = $parentCampaignId;
        }

        // Has Parent Campaign Filter
        if ($mustHaveParentCampaign) {
            $match['$match']['parentCampaignId'] = ['$exists' => true, '$ne' => 0];
        }

        // Select collection
        $collection = $this->getClient()->selectCollection($this->config['database'], $this->table);

        // Paging
        // ------
        // Check whether or not we've requested a page, if we have then we need a count of records total for paging
        // if we haven't then we don't bother getting a count
        $total = 0;
        if ($start !== null && $length !== null) {
            // We add a group pipeline to get a total count of records
            $group = [
                '$group' => [
                    '_id' => null,
                    'count' => ['$sum' => 1],
                ]
            ];

            if (count($match) > 0) {
                $totalQuery = [
                    $match,
                    $group,
                ];
            } else {
                $totalQuery = [
                    $group,
                ];
            }

            // Get total
            try {
                $totalCursor = $collection->aggregate($totalQuery, ['allowDiskUse' => true]);

                $totalCount = $totalCursor->toArray();
                $total = (count($totalCount) > 0) ? $totalCount[0]['count'] : 0;
            } catch (\Exception $e) {
                $this->log->error('Error: Total Count. ' . $e->getMessage());
                throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
            }
        }

        try {
            $project = [
                '$project' => [
                    'id'=> '$_id',
                    'type'=> 1,
                    'start'=> 1,
                    'end'=> 1,
                    'layout'=> '$layoutName',
                    'display'=> '$displayName',
                    'media'=> '$mediaName',
                    'tag'=> '$eventName',
                    'duration'=> ['$toInt' => '$duration'],
                    'count'=> ['$toInt' => '$count'],
                    'displayId'=> 1,
                    'layoutId'=> 1,
                    'widgetId'=> 1,
                    'mediaId'=> 1,
                    'campaignId'=> 1,
                    'parentCampaign'=> 1,
                    'parentCampaignId'=> 1,
                    'campaignStart'=> 1,
                    'campaignEnd'=> 1,
                    'statDate'=> 1,
                    'engagements'=> 1,
                    'tagFilter' => 1
                ]
            ];

            if (count($match) > 0) {
                $query = [
                    $match,
                    $project,
                ];
            } else {
                $query = [
                    $project,
                ];
            }

            // Paging
            if ($start !== null && $length !== null) {
                // Sort by id (statId) - we must sort before we do pagination as mongo stat has descending order indexing on start/end
                $query[]['$sort'] = ['id'=> 1];
                $query[]['$skip'] =  $start;
                $query[]['$limit'] = $length;
            }

            $cursor = $collection->aggregate($query, ['allowDiskUse' => true]);

            $result = new TimeSeriesMongoDbResults($cursor);

            // Total (we have worked this out above if we have paging enabled, otherwise its 0)
            $result->totalCount = $total;
        } catch (\Exception $e) {
            $this->log->error('Error: Get total. '. $e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting Proof of Play data, please consult your administrator'));
        }

        return $result;
    }

    /** @inheritdoc */
    public function getExportStatsCount($filterBy = [])
    {
        // do we consider that the fromDt and toDt will always be provided?
        $fromDt = $filterBy['fromDt'] ?? null;
        $toDt = $filterBy['toDt'] ?? null;
        $displayIds = $filterBy['displayIds'] ?? [];

        // Match query
        $match = [];

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $fromDt = new UTCDateTime($fromDt->format('U')*1000);
            $match['$match']['end'] = ['$gt' => $fromDt];

            $toDt = new UTCDateTime($toDt->format('U')*1000);
            $match['$match']['start'] = ['$lte' => $toDt];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = ['$in' => $displayIds];
        }

        $collection = $this->getClient()->selectCollection($this->config['database'], $this->table);

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
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException(__('Sorry we encountered an error getting total number of Proof of Play data, please consult your administrator'));
        }

        return $total;
    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {
        // Filter the records we want to delete.
        // we dont use $options['limit'] anymore.
        // we delete all the records at once based on filter criteria (no-limit approach)
        $filter = [
            'start' => ['$lte' => new UTCDateTime($maxage->format('U')*1000)],
        ];

        // Do we also limit the from date?
        if ($fromDt !== null) {
            $filter['end'] = ['$gt' => new UTCDateTime($fromDt->format('U')*1000)];
        }

        // Run the delete and return the number of records we deleted.
        try {
            $deleteResult = $this->getClient()
                ->selectCollection($this->config['database'], $this->table)
                ->deleteMany($filter);

            return $deleteResult->getDeletedCount();
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException('Stats cannot be deleted.');
        }
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

        $collection = $this->getClient()->selectCollection($this->config['database'], $options['collection']);
        try {
            $cursor = $collection->aggregate($options['query'], $aggregateConfig);

            // log query
            $this->log->debug(json_encode($options['query']));

            $results = $cursor->toArray();
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            $this->log->debug($e->getTraceAsString());
            throw new GeneralException($e->getMessage());
        }

        return $results;
    }
}
