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

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
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
        foreach ($statData as $k => $stat) {

            $statData[$k]['statDate'] = new UTCDateTime($statData[$k]['statDate']->format('U') * 1000);
            $statData[$k]['start'] = new UTCDateTime($statData[$k]['fromDt']->format('U') * 1000);
            unset($statData[$k]['fromDt']);

            $statData[$k]['end'] = new UTCDateTime($statData[$k]['toDt']->format('U') * 1000);
            unset($statData[$k]['toDt']);

            $statData[$k]['eventName'] = $statData[$k]['tag'];
            unset($statData[$k]['tag']);

            unset($statData[$k]['scheduleId']);

            // Make an empty array to collect tags into
            $tagFilter = [];

            // Media name
            $mediaName = null;
            if (!empty($stat['mediaId'])) {
                try {
                    $media = $this->mediaFactory->getById($stat['mediaId']);
                } catch (NotFoundException $error) {
                    // Media not found ignore and log the stat
                    $this->log->error('Media not found. Media Id: '. $stat['mediaId'] .',Layout Id: '. $stat['layoutId']
                        .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                        .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                    // unset($statData[$k]);
                    continue;
                }
                $mediaName = $media->name; //dont remove used later
                $statData[$k]['mediaName'] = $mediaName;
                $tagFilter['media'] = explode(',', $media->tags);
            }

            // Widget name
            if (!empty($stat['widgetId'])) {
                try {
                    $widget = $this->widgetFactory->getById($stat['widgetId']);
                } catch (NotFoundException $error) {
                    // Widget not found, ignore and log the stat
                    $this->log->error('Widget not found. Widget Id: '. $stat['widgetId'] .',Layout Id: '. $stat['layoutId']
                        .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                        .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                    // unset($statData[$k]);
                    continue;
                }

                if($widget != null) {
                    $widget->load();
                    $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);
                    $statData[$k]['widgetName'] = $widgetName;
                }
            }

            // Display name
            try {
                $display = $this->displayFactory->getById($stat['displayId']); //TODO what if you dont find the displayId
            } catch (NotFoundException $error) {
                // Display not found, ignore and log the stat
                $this->log->error('Display not found. Display Id: '. $stat['displayId'] .',Layout Id: '. $stat['layoutId']
                    .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                    .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                // unset($statData[$k]);
                continue;
            }

            $statData[$k]['displayName'] = $display->display;

            // Layout data
            $layoutName = null;
            $layoutTags = null;

            if ($stat['type'] != 'event') {

                try {
                    $layout = $this->layoutFactory->getByParentCampaignId($stat['campaignId']);

                    $this->log->debug('Found layout : '. $stat['layoutId']);

                    $campaignId = $layout->campaignId;
                    $layoutName = $layout->layout;
                    $layoutTags = $layout->tags;

                    // Get layout Campaign ID
                    $statData[$k]['campaignId'] = (int) $campaignId;

                    // Layout tags
                    $tagFilter['layout'] = $layoutTags;

                } catch (NotFoundException $error) {
                    // All we can do here is log
                    // we shouldn't ever get in this situation because the campaignId we used above will have
                    // already been looked up in the layouthistory table.
                    $this->log->alert('Error processing statistic into MongoDB. Layout not found. Stat is: ' . json_encode($stat));
                }

            }

            $statData[$k]['layoutName'] = $layoutName;

            // Display tags
            try {
                $arrayOfTags = array_filter(explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tags));
                $arrayOfTagValues = array_filter(explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tagValues));

                for ($i=0; $i<count($arrayOfTags); $i++) {
                    if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                        $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                        $tagFilter['dg'][$i]['val'] = $arrayOfTagValues[$i];
                    } else {
                        $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                    }
                }
            } catch (NotFoundException $notFoundException) {
                $this->log->alert('Error processing statistic into MongoDB. Display not found. Stat is: ' . json_encode($stat));
                // TODO: need to remove the record?
                continue;
            }

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

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        if(count($earliestDate) > 0) {
            return [
                'minDate' => $earliestDate[0]['minDate']->toDateTime()->format('U')
            ];
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

        if ($statDate == null) {

            // Check whether fromDt and toDt are provided
            if (($fromDt == null) && ($toDt == null)) {
                throw new InvalidArgumentException(__("Either fromDt/toDt or statDate should be provided"), 'fromDt/toDt/statDate');
            }

            if ($fromDt == null) {
                throw new InvalidArgumentException(__("Fromdt cannot be null"), 'fromDt');
            }

            if ($toDt == null) {
                throw new InvalidArgumentException(__("Todt cannot be null"), 'toDt');
            }
        } else {
            if (($fromDt != null) || ($toDt != null)) {
                throw new InvalidArgumentException(__("Either fromDt/toDt or statDate should be provided"), 'fromDt/toDt/statDate');
            }
        }

        $type = isset($filterBy['type']) ? $filterBy['type'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];
        $layoutIds = isset($filterBy['layoutIds']) ? $filterBy['layoutIds'] : [];
        $mediaIds = isset($filterBy['mediaIds']) ? $filterBy['mediaIds'] : [];
        $campaignId = isset($filterBy['campaignId']) ? $filterBy['campaignId'] : null;

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
        } else { // statDate Filter
            $statDate = new UTCDateTime($statDate->format('U')*1000);
            $match['$match']['statDate'] = [ '$gte' => $statDate];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        // Campaign selection
        // ------------------
        // Get all the layouts of that campaign.
        // Then get all the campaigns of the layouts
        $campaignIds = [];
        if ($campaignId != null) {
            try {
                $campaign = $this->campaignFactory->getById($campaignId);
                $layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId);
                if (count($layouts) > 0) {
                    foreach ($layouts as $layout) {
                        $campaignIds[] = $layout->campaignId;
                    }
                }
            } catch (NotFoundException $notFoundException) {
                $this->log->error('Empty campaignIds.');
            }
        }

        // Campaign Filter
        if ($campaignId != null) {
            if (count($campaignIds) != 0) {
                $match['$match']['campaignId'] = ['$in' => $campaignIds];
            } else {
                // we wont get any match as we store layoutspecific campaignid in stat
                $match['$match']['campaignId'] = ['$eq' => $campaignId];
            }
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
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
                    $this->getLog()->debug('Filter for Layout without Layout History Record, layoutId is ' . $layoutId);
                }
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
           $query = [
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
                       'duration'=> '$duration',
                       'count'=> '$count',
                       'displayId'=> 1,
                       'layoutId'=> 1,
                       'widgetId'=> 1,
                       'mediaId'=> 1,
                       'statDate'=> 1,
                   ]
               ],
           ];

           if ($start !== null && $length !== null) {
               $query[]['$skip'] =  $start;
               $query[]['$limit'] = $length;
           }

           $cursor = $collection->aggregate($query);

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $result = new TimeSeriesMongoDbResults($cursor);

        return $result;

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

        $collection = $this->client->selectCollection($this->config['database'], $options['collection']);
        try {
            $cursor = $collection->aggregate($options['query'], ['allowDiskUse' => $options['allowDiskUse']]);

            // log query
            $this->log->debug($cursor);

            $results = $cursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $results;

    }
}