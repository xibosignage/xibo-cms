<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
namespace Xibo\Connector;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\ConnectorReportEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Event\ReportDataEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class XiboAudienceReportingConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var User */
    private $user;

    /** @var TimeSeriesStoreInterface */
    private $timeSeriesStore;

    /** @var  SanitizerService */
    private $sanitizer;

    /** @var ConfigServiceInterface */
    private $config;

    /** @var CampaignFactory */
    private $campaignFactory;

    /** @var DisplayFactory */
    private $displayFactory;

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function setFactories(ContainerInterface $container): ConnectorInterface
    {
        $this->user = $container->get('user');
        $this->timeSeriesStore = $container->get('timeSeriesStore');
        $this->sanitizer = $container->get('sanitizerService');
        $this->config = $container->get('configService');
        $this->campaignFactory = $container->get('campaignFactory');
        $this->displayFactory = $container->get('displayFactory');

        return $this;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(MaintenanceRegularEvent::$NAME, [$this, 'onRegularMaintenance']);
        $dispatcher->addListener(ReportDataEvent::$NAME, [$this, 'onRequestReportData']);
        $dispatcher->addListener(ConnectorReportEvent::$NAME, [$this, 'onListReports']);

        return $this;
    }

    public function getSourceName(): string
    {
        return 'xibo-audience-reporting-connector';
    }

    public function getTitle(): string
    {
        return 'Xibo Audience Reporting Connector';
    }

    /**
     * Get the service url, either from settings or a default
     * @return string
     */
    private function getServiceUrl(): string
    {
        return $this->getSetting('serviceUrl', 'https://exchange.xibo-adspace.com/api');
    }

    public function getDescription(): string
    {
        return 'Enhance your reporting with audience data, impressions and more.';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/xibo-audience-reporting.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'xibo-audience-connector-form-settings';
    }

    public function getSettingsFormJavaScript(): string
    {
        return 'xibo-audience-connector-form-javascript';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }

        // Get this connector settings, etc.
        $this->getOptionsFromAxe($settings['apiKey'], true);

        return $settings;
    }

    // <editor-fold desc="Listeners">

    /**
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function onRegularMaintenance(MaintenanceRegularEvent $event)
    {
        // We should only do this if the connector is enabled and if we have an API key
        $apiKey = $this->getSetting('apiKey');
        if (empty($apiKey)) {
            $this->getLogger()->debug('onRegularMaintenance: No api key');
            return;
        }

        $event->addMessage('## Audience Connector');

        // Set displays on DMAs
        foreach ($this->dmaSearch($this->sanitizer->getSanitizer([]))['data'] as $dma) {
            if ($dma['displayGroupId'] !== null) {
                $this->setDisplaysForDma($dma['_id'], $dma['displayGroupId']);
            }
        }

        // Handle sending stats to the audience connector service API
        try {
            $defaultTimezone = $this->config->getSetting('defaultTimezone');

            // Get Watermark (might be null - start from beginning)
            $watermark = $this->getWatermark();

            // Loop over 5000 stat records
            // Only interested in layout stats which belong to a parent campaign
            $params = [
                'type' => 'layout',
                'start' => 0,
                'length' => $this->getSetting('batchSize', 5000),
                'mustHaveParentCampaign' => true,
            ];

            // If the watermark is not empty, we go from this point
            if (!empty($watermark)) {
                $params['statId'] = $watermark;
            }

            $this->getLogger()->debug('onRegularMaintenance: Processing batch of stats with params: '
                . json_encode($params));

            // Call the time series interface getStats
            $resultSet = $this->timeSeriesStore->getStats($params, true);

            // Array of campaigns for which we will update the total spend, impresssions, and plays
            $campaigns = [];
            $adCampaignCache = [];
            $listCampaignCache = [];
            $displayCache = [];
            $displayIdsDeleted = [];
            $erroredCampaign = [];
            $rows = [];

            $updateWatermark = null;

            // Process the stats one by one
            while ($row = $resultSet->getNextRow()) {
                try {
                    $sanitizedRow = $this->sanitizer->getSanitizer($row);

                    $parentCampaignId = $sanitizedRow->getInt('parentCampaignId', ['default' => 0]);
                    $displayId = $sanitizedRow->getInt('displayId');
                    $statId = $resultSet->getIdFromRow($row);

                    // Keep this watermark, so we update it later
                    $updateWatermark = $statId;

                    // Skip records we're not interested in, or records that have already been discounted before.
                    if (empty($parentCampaignId)
                        || empty($displayId)
                        || in_array($displayId, $displayIdsDeleted)
                        || array_key_exists($parentCampaignId, $erroredCampaign)
                        || array_key_exists($parentCampaignId, $listCampaignCache)
                    ) {
                        // Comment out this log to save recording messages unless we need to troubleshoot in dev
                        //$this->getLogger()->debug('onRegularMaintenance: Campaign is a list campaign '
                        //    . $parentCampaignId);
                        continue;
                    }

                    // Build an array to represent the row we want to send.
                    $entry = [
                        'id' => $statId,
                        'parentCampaignId' => $parentCampaignId,
                        'displayId' => $displayId,
                    ];

                    // --------
                    // Get Campaign
                    // Campaign start and end date
                    if (array_key_exists($parentCampaignId, $adCampaignCache)) {
                        $entry['campaignStart'] = $adCampaignCache[$parentCampaignId]['start'];
                        $entry['campaignEnd'] = $adCampaignCache[$parentCampaignId]['end'];
                    } else {
                        // Get Campaign
                        try {
                            $parentCampaign = $this->campaignFactory->getById($parentCampaignId);
                        } catch (\Exception $exception) {
                            $this->getLogger()->error('onRegularMaintenance: campaign with ID '
                                . $parentCampaignId . ' not found');

                            $erroredCampaign[$parentCampaignId] = $entry['id']; // first stat id
                            continue;
                        }

                        if ($parentCampaign->type == 'ad') {
                            $adCampaignCache[$parentCampaignId]['type'] = $parentCampaign->type;
                        } else {
                            $this->getLogger()->debug('onRegularMaintenance: campaign is a list '
                                . $parentCampaignId);
                            $listCampaignCache[$parentCampaignId] = $parentCampaignId;
                            continue;
                        }

                        if (!empty($parentCampaign->getStartDt()) && !empty($parentCampaign->getEndDt())) {
                            $adCampaignCache[$parentCampaignId]['start'] = $parentCampaign->getStartDt()
                                ->format(DateFormatHelper::getSystemFormat());
                            $adCampaignCache[$parentCampaignId]['end'] = $parentCampaign->getEndDt()
                                ->format(DateFormatHelper::getSystemFormat());

                            $entry['campaignStart'] = $adCampaignCache[$parentCampaignId]['start'];
                            $entry['campaignEnd'] = $adCampaignCache[$parentCampaignId]['end'];
                        } else {
                            $this->getLogger()->error('onRegularMaintenance: campaign without dates '
                                . $parentCampaignId);

                            $erroredCampaign[$parentCampaignId] = $entry['id']; // first stat id
                            continue;
                        }
                    }

                    // Get Display
                    // -----------
                    // Cost per play and impressions per play
                    if (!array_key_exists($displayId, $displayCache)) {
                        try {
                            $display = $this->displayFactory->getById($displayId);
                            $displayCache[$displayId]['costPerPlay'] = $display->costPerPlay;
                            $displayCache[$displayId]['impressionsPerPlay'] = $display->impressionsPerPlay;
                            $displayCache[$displayId]['timeZone'] = empty($display->timeZone) ? $defaultTimezone : $display->timeZone;
                        } catch (NotFoundException $notFoundException) {
                            $this->getLogger()->error('onRegularMaintenance: display not found with ID: '
                                . $displayId);
                            $displayIdsDeleted[] = $displayId;
                            continue;
                        }
                    }
                    $entry['costPerPlay'] = $displayCache[$displayId]['costPerPlay'];
                    $entry['impressionsPerPlay'] = $displayCache[$displayId]['impressionsPerPlay'];

                    // Converting the date into the format expected by the API

                    // --------
                    // We know that player's local dates were stored in the CMS's configured timezone
                    // Dates were saved in Unix timestamps in MySQL
                    // Dates were saved in UTC format in MongoDB
                    // The main difference is that MySQL stores dates in the timezone of the CMS,
                    // while MongoDB converts those dates to UTC before storing them.

                    // -----MySQL
                    // Carbon::createFromTimestamp() always applies the CMS timezone

                    // ------MongoDB
                    // $date->toDateTime() returns a PHP DateTime object from MongoDB BSON Date type (UTC)
                    // Carbon::instance() keeps the timezone as UTC
                    try {
                        $start = $resultSet->getDateFromValue($row['start']);
                        $end = $resultSet->getDateFromValue($row['end']);
                    } catch (\Exception $exception) {
                        $this->getLogger()->error('onRegularMaintenance: Date convert failed for ID '
                            . $entry['id'] . ' with error: '. $exception->getMessage());
                        continue;
                    }

                    // Convert dates to display timezone
                    $entry['start'] = $start->timezone($displayCache[$displayId]['timeZone'])->format(DateFormatHelper::getSystemFormat());
                    $entry['end'] = $end->timezone($displayCache[$displayId]['timeZone'])->format(DateFormatHelper::getSystemFormat());

                    $entry['layoutId'] = $sanitizedRow->getInt('layoutId', ['default' => 0]);
                    $entry['numberPlays'] = $sanitizedRow->getInt('count', ['default' => 0]);
                    $entry['duration'] = $sanitizedRow->getInt('duration', ['default' => 0]);
                    $entry['engagements'] = $resultSet->getEngagementsFromRow($row);

                    $rows[] = $entry;

                    // Campaign list in array
                    if (!in_array($parentCampaignId, $campaigns)) {
                        $campaigns[] = $parentCampaignId;
                    }
                } catch (\Exception $exception) {
                    $this->getLogger()->error('onRegularMaintenance: unexpected exception processing row '
                        . ($entry['id'] ?? null) . ', e: ' . $exception->getMessage());
                }
            }

            if (count($erroredCampaign) > 0) {
                $event->addMessage(sprintf(
                    __('There were %d campaigns which failed. A summary is in the error log.'),
                    count($erroredCampaign)
                ));

                $this->getLogger()->error('onRegularMaintenance: Failure summary of campaignId and first statId:'
                    . json_encode($erroredCampaign));
            }

            $this->getLogger()->debug('onRegularMaintenance: Records to send: ' . count($rows)
                . ', Watermark: ' . $watermark);
            $this->getLogger()->debug('onRegularMaintenance: Campaigns: ' . json_encode($campaigns));

            // If we have rows, send them.
            if (count($rows) > 0) {
                // All outcomes from here are either a break; or an exception to stop the loop.
                try {
                    $response = $this->getClient()->post($this->getServiceUrl() . '/audience/receiveStats', [
                        'timeout' => $this->getSetting('receiveStatsTimeout', 300), // 5 minutes
                        'headers' => [
                            'X-API-KEY' => $this->getSetting('apiKey')
                        ],
                        'json' => $rows
                    ]);

                    $statusCode = $response->getStatusCode();

                    $this->getLogger()->debug('onRegularMaintenance: Receive Stats StatusCode: ' . $statusCode);

                    // Get Campaign Total
                    if ($statusCode == 204) {
                        $this->getAndUpdateCampaignTotal($campaigns);
                    }

                    $event->addMessage('Added ' . count($rows) . ' to audience API');
                } catch (RequestException $requestException) {
                    // If a request fails completely, we should stop and log the error.
                    $this->getLogger()->error('onRegularMaintenance: Audience receiveStats: failed e = '
                        . $requestException->getMessage());

                    throw new GeneralException(__('Failed to send stats to audience API'));
                }
            }

            // Update the last statId of the block as the watermark
            if (!empty($updateWatermark)) {
                $this->setWatermark($updateWatermark);
            }
        } catch (GeneralException $exception) {
            // We should have recorded in the error log already, so we just append to the event message for task
            // last run status.
            $event->addMessage($exception->getMessage());
        }
    }

    /**
     * Get the watermark representing how far we've processed already
     * @return mixed|null
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getWatermark()
    {
        // If the watermark request fails, we should error.
        try {
            $this->getLogger()->debug('onRegularMaintenance: Get Watermark');
            $response = $this->getClient()->get($this->getServiceUrl() . '/audience/watermark', [
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey')
                ],
            ]);

            $body = $response->getBody()->getContents();
            $json = json_decode($body, true);
            return $json['watermark'] ?? null;
        } catch (RequestException $requestException) {
            $this->getLogger()->error('getWatermark: failed e = ' . $requestException->getMessage());
            throw new GeneralException(__('Cannot get watermark'));
        }
    }

    /**
     * Set the watermark representing how far we've processed already
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function setWatermark($watermark)
    {
        // If the watermark set fails, we should error.
        try {
            $this->getLogger()->debug('onRegularMaintenance: Set Watermark ' . $watermark);
            $this->getClient()->post($this->getServiceUrl() . '/audience/watermark', [
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey')
                ],
                'json' => ['watermark' => $watermark]
            ]);
        } catch (RequestException $requestException) {
            $this->getLogger()->error('setWatermark: failed e = ' . $requestException->getMessage());
            throw new GeneralException(__('Cannot set watermark'));
        }
    }

    /**
     * @param array $campaigns
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getAndUpdateCampaignTotal(array $campaigns)
    {
        $this->getLogger()->debug('onRegularMaintenance: Get Campaign Total');

        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/audience/campaignTotal', [
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey')
                ],
                'query' => [
                    'campaigns' => $campaigns
                ]
            ]);

            $body = $response->getBody()->getContents();
            $results = json_decode($body, true);
            $this->getLogger()->debug('onRegularMaintenance: Campaign Total Results: ' . json_encode($results));

            foreach ($results as $item) {
                try {
                    // Save the total in the campaign
                    $campaign = $this->campaignFactory->getById($item['id']);
                    $this->getLogger()->debug('onRegularMaintenance: Campaign Id: ' . $item['id']
                        . ' Spend: ' . $campaign->spend . ' Impressions: ' . $campaign->impressions);

                    $campaign->spend = $item['spend'];
                    $campaign->impressions = $item['impressions'];

                    $campaign->overwritePlays();

                    $this->getLogger()->debug('onRegularMaintenance: Campaign Id: ' . $item['id']
                        . ' Spend(U): ' . $campaign->spend . ' Impressions(U): ' . $campaign->impressions);
                } catch (NotFoundException $notFoundException) {
                    $this->getLogger()->error('onRegularMaintenance: campaignId '
                        . $item['id'].  '  should have existed, but did not.');

                    throw new GeneralException(sprintf(__('Cannot update campaign status for %d'), $item['id']));
                }
            }
        } catch (RequestException $requestException) {
            $this->getLogger()->error('Campaign total: e = ' . $requestException->getMessage());

            throw new GeneralException(__('Failed to update campaign totals.'));
        }
    }

    /**
     * Request Report results from the audience report service
     */
    public function onRequestReportData(ReportDataEvent $event)
    {
        $this->getLogger()->debug('onRequestReportData');

        $type = $event->getReportType();

        $typeUrl = [
            'campaignProofofplay' => $this->getServiceUrl() . '/audience/campaign/proofofplay',
            'mobileProofofplay' => $this->getServiceUrl() . '/audience/campaign/proofofplay/mobile',
            'displayAdPlay' => $this->getServiceUrl() . '/audience/display/adplays',
            'displayPercentage' => $this->getServiceUrl() . '/audience/display/percentage'
        ];

        if (array_key_exists($type, $typeUrl)) {
            $json = [];
            switch ($type) {
                case 'campaignProofofplay':
                    // Get campaign proofofplay result
                    try {
                        $response = $this->getClient()->get($typeUrl[$type], [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => $event->getParams()
                        ]);

                        $body = $response->getBody()->getContents();
                        $json = json_decode($body, true);
                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('Get '. $type.': failed. e = ' . $requestException->getMessage());
                        $error = 'Failed to get campaign proofofplay result: '.$requestException->getMessage();
                    }
                    break;

                case 'mobileProofofplay':
                    // Get mobile proofofplay result
                    try {
                        $response = $this->getClient()->get($typeUrl[$type], [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => $event->getParams()
                        ]);

                        $body = $response->getBody()->getContents();
                        $json = json_decode($body, true);
                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('Get '. $type.': failed. e = ' . $requestException->getMessage());
                        $error = 'Failed to get mobile proofofplay result: '.$requestException->getMessage();
                    }
                    break;

                case 'displayAdPlay':
                    // Get display adplays result
                    try {
                        $response = $this->getClient()->get($typeUrl[$type], [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => $event->getParams()
                        ]);

                        $body = $response->getBody()->getContents();
                        $json = json_decode($body, true);
                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('Get '. $type.': failed. e = ' . $requestException->getMessage());
                        $error = 'Failed to get display adplays result: '.$requestException->getMessage();
                    }
                    break;

                case 'displayPercentage':
                    // Get display played percentage result
                    try {
                        $response = $this->getClient()->get($typeUrl[$type], [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => $event->getParams()
                        ]);

                        $body = $response->getBody()->getContents();
                        $json = json_decode($body, true);
                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('Get '. $type.': failed. e = ' . $requestException->getMessage());
                        $error = 'Failed to get display played percentage result: '.$requestException->getMessage();
                    }
                    break;

                default:
                    $this->getLogger()->error('Connector Report not found ');
            }

            $event->setResults([
                'json' => $json,
                'error' => $error ?? null
            ]);
        }
    }

    /**
     * Get this connector reports
     * @param ConnectorReportEvent $event
     * @return void
     */
    public function onListReports(ConnectorReportEvent $event)
    {
        $this->getLogger()->debug('onListReports');

        $connectorReports = [
            [
                'name'=> 'campaignProofOfPlay',
                'description'=> 'Campaign Proof of Play',
                'class'=> '\\Xibo\\Report\\CampaignProofOfPlay',
                'type'=> 'Report',
                'output_type'=> 'table',
                'color'=> 'gray',
                'fa_icon'=> 'fa-th',
                'category'=> 'Connector Reports',
                'feature'=> 'campaign-proof-of-play',
                'adminOnly'=> 0,
                'sort_order' => 1
            ],
            [
                'name'=> 'mobileProofOfPlay',
                'description'=> 'Mobile Proof of Play',
                'class'=> '\\Xibo\\Report\\MobileProofOfPlay',
                'type'=> 'Report',
                'output_type'=> 'table',
                'color'=> 'green',
                'fa_icon'=> 'fa-th',
                'category'=> 'Connector Reports',
                'feature'=> 'mobile-proof-of-play',
                'adminOnly'=> 0,
                'sort_order' => 2
            ],
            [
                'name'=> 'displayPercentage',
                'description'=> 'Display Played Percentage',
                'class'=> '\\Xibo\\Report\\DisplayPercentage',
                'type'=> 'Chart',
                'output_type'=> 'both',
                'color'=> 'blue',
                'fa_icon'=> 'fa-pie-chart',
                'category'=> 'Connector Reports',
                'feature'=> 'display-report',
                'adminOnly'=> 0,
                'sort_order' => 3
            ],
//            [
//                'name'=> 'revenueByDisplayReport',
//                'description'=> 'Revenue by Display',
//                'class'=> '\\Xibo\\Report\\RevenueByDisplay',
//                'type'=> 'Report',
//                'output_type'=> 'table',
//                'color'=> 'green',
//                'fa_icon'=> 'fa-th',
//                'category'=> 'Connector Reports',
//                'feature'=> 'display-report',
//                'adminOnly'=> 0,
//                'sort_order' => 4
//            ],
            [
                'name'=> 'displayAdPlay',
                'description'=> 'Display Ad Plays',
                'class'=> '\\Xibo\\Report\\DisplayAdPlay',
                'type'=> 'Chart',
                'output_type'=> 'both',
                'color'=> 'red',
                'fa_icon'=> 'fa-bar-chart',
                'category'=> 'Connector Reports',
                'feature'=> 'display-report',
                'adminOnly'=> 0,
                'sort_order' => 5
            ],
        ];

        $reports = [];
        foreach ($connectorReports as $connectorReport) {
            // Compatibility check
            if (!isset($connectorReport['feature']) || !isset($connectorReport['category'])) {
                continue;
            }

            // Check if only allowed for admin
            if ($this->user->userTypeId != 1) {
                if (isset($connectorReport['adminOnly']) && !empty($connectorReport['adminOnly'])) {
                    continue;
                }
            }

            $reports[$connectorReport['category']][] = (object) $connectorReport;
        }

        if (count($reports) > 0) {
            $event->addReports($reports);
        }
    }

    // </editor-fold>

    // <editor-fold desc="Proxy methods">

    public function dmaSearch(SanitizerInterface $params): array
    {
        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/dma', [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey'),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body) {
                throw new GeneralException(__('No response'));
            }

            return [
                'data' => $body,
                'recordsTotal' => count($body),
            ];
        } catch (\Exception $e) {
            $this->getLogger()->error('activity: e = ' . $e->getMessage());
        }

        return [
            'data' => [],
            'recordsTotal' => 0,
        ];
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function dmaAdd(SanitizerInterface $params): array
    {
        $startDate = $params->getDate('startDate');
        if ($startDate !== null) {
            $startDate = $startDate->format('Y-m-d');
        }

        $endDate = $params->getDate('endDate');
        if ($endDate !== null) {
            $endDate = $endDate->format('Y-m-d');
        }

        try {
            $response = $this->getClient()->post($this->getServiceUrl() . '/dma', [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey'),
                ],
                'json' => [
                    'name' => $params->getString('name'),
                    'costPerPlay' => $params->getDouble('costPerPlay'),
                    'impressionSource' => $params->getString('impressionSource'),
                    'impressionsPerPlay' => $params->getDouble('impressionsPerPlay'),
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'daysOfWeek' => $params->getIntArray('daysOfWeek'),
                    'startTime' => $params->getString('startTime'),
                    'endTime' => $params->getString('endTime'),
                    'geoFence' => json_decode($params->getString('geoFence'), true),
                    'priority' => $params->getInt('priority'),
                    'displayGroupId' => $params->getInt('displayGroupId'),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body) {
                throw new GeneralException(__('No response'));
            }

            // Set the displays
            $this->setDisplaysForDma($body['_id'], $params->getInt('displayGroupId'));

            return $body;
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function dmaEdit(SanitizerInterface $params): array
    {
        $startDate = $params->getDate('startDate');
        if ($startDate !== null) {
            $startDate = $startDate->format('Y-m-d');
        }

        $endDate = $params->getDate('endDate');
        if ($endDate !== null) {
            $endDate = $endDate->format('Y-m-d');
        }

        try {
            $response = $this->getClient()->put($this->getServiceUrl() . '/dma/' . $params->getString('_id'), [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey'),
                ],
                'json' => [
                    'name' => $params->getString('name'),
                    'costPerPlay' => $params->getDouble('costPerPlay'),
                    'impressionSource' => $params->getString('impressionSource'),
                    'impressionsPerPlay' => $params->getDouble('impressionsPerPlay'),
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'daysOfWeek' => $params->getIntArray('daysOfWeek'),
                    'startTime' => $params->getString('startTime'),
                    'endTime' => $params->getString('endTime'),
                    'geoFence' => json_decode($params->getString('geoFence'), true),
                    'priority' => $params->getInt('priority'),
                    'displayGroupId' => $params->getInt('displayGroupId'),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body) {
                throw new GeneralException(__('No response'));
            }

            // Set the displays
            $this->setDisplaysForDma($body['_id'], $params->getInt('displayGroupId'));

            return $body;
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function dmaDelete(SanitizerInterface $params)
    {
        try {
            $this->getClient()->delete($this->getServiceUrl() . '/dma/' . $params->getString('_id'), [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey'),
                ],
            ]);

            return null;
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    // </editor-fold>

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getOptionsFromAxe($apiKey = null, $throw = false)
    {
        $apiKey = $apiKey ?? $this->getSetting('apiKey');
        if (empty($apiKey)) {
            if ($throw) {
                throw new InvalidArgumentException(__('Please provide an API key'));
            } else {
                return [
                    'error' => true,
                    'message' => __('Please provide an API key'),
                ];
            }
        }

        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/options', [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $apiKey,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            try {
                $this->handleException($e);
            } catch (\Exception $exception) {
                if ($throw) {
                    throw $exception;
                } else {
                    return [
                        'error' => true,
                        'message' => $exception->getMessage() ?: __('Unknown Error'),
                    ];
                }
            }
        }
    }

    private function setDisplaysForDma($dmaId, $displayGroupId)
    {
        // Get displays
        $displayIds = [];
        foreach ($this->displayFactory->getByDisplayGroupId($displayGroupId) as $display) {
            $displayIds[] = $display->displayId;
        }

        // Make a blind call to update this DMA.
        try {
            $this->getClient()->post($this->getServiceUrl() . '/dma/' . $dmaId . '/displays', [
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey')
                ],
                'json' => [
                    'displays' => $displayIds,
                ]
            ]);
        } catch (\Exception $e) {
            $this->getLogger()->error('Exception updating Displays for dmaId: ' . $dmaId
                . ', e: ' . $e->getMessage());
        }
    }

    /**
     * @param \Exception $exception
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function handleException($exception)
    {
        $this->getLogger()->debug('handleException: ' . $exception->getMessage());
        $this->getLogger()->debug('handleException: ' . $exception->getTraceAsString());

        if ($exception instanceof ClientException) {
            if ($exception->hasResponse()) {
                $body = $exception->getResponse()->getBody() ?? null;
                if (!empty($body)) {
                    $decodedBody = json_decode($body, true);
                    $message = $decodedBody['message'] ?? $body;
                } else {
                    $message = __('An unknown error has occurred.');
                }

                switch ($exception->getResponse()->getStatusCode()) {
                    case 422:
                        throw new InvalidArgumentException($message);

                    case 404:
                        throw new NotFoundException($message);

                    case 401:
                        throw new AccessDeniedException(__('Access denied, please check your API key'));

                    default:
                        throw new GeneralException(sprintf(
                            __('Unknown client exception processing your request, error code is %s'),
                            $exception->getResponse()->getStatusCode()
                        ));
                }
            } else {
                throw new InvalidArgumentException(__('Invalid request'));
            }
        } elseif ($exception instanceof ServerException) {
            $this->getLogger()->error('handleException:' . $exception->getMessage());
            throw new GeneralException(__('There was a problem processing your request, please try again'));
        } else {
            throw new GeneralException(__('Unknown Error'));
        }
    }
}
