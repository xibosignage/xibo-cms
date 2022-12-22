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
namespace Xibo\Connector;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\ConnectorReportEvent;
use Xibo\Event\ReportDataEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\GeneralException;
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
        return $this->getSetting('serviceUrl', 'https://exchange.xibo-adspace.com/api/audience');
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

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }
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

        // Get Watermark
        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/watermark', [
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey')
                ],
            ]);

            $body = $response->getBody()->getContents();
            $json = json_decode($body, true);
            $watermark = $json['watermark'];

            $params =   [
                'type' => 'layout',
                'start' => 1,
                'length' => 10000,
                'mustHaveParentCampaign' => true
            ];

            if (!empty($watermark)) {
                $params['statId'] = $watermark;
            }

            // Call the time series interface getStats
            $resultSet = $this->timeSeriesStore->getStats($params);

            // Array of campaigns for which we will update the total spend, impresssions, and plays
            $campaigns = [];
            $campaignCache = [];
            $displayCache = [];

            $rows = [];
            while ($row = $resultSet->getNextRow()) {
                $sanitizedRow = $this->sanitizer->getSanitizer($row);

                $parentCampaignId = $sanitizedRow->getInt('parentCampaignId', ['default' => 0]);
                $displayId = $sanitizedRow->getInt(('displayId'));

                if (empty($parentCampaignId) || empty($displayId)) {
                    continue;
                }

                $entry['parentCampaignId'] = $parentCampaignId;

                // Campaign list in array
                $campaigns[] = $parentCampaignId;

                // --------
                // Get Campaign
                // Campaign start and end date
                if (array_key_exists($parentCampaignId, $campaignCache)) {
                    $entry['campaignStart'] = $campaignCache[$parentCampaignId]['start'];
                    $entry['campaignEnd'] = $campaignCache[$parentCampaignId]['end'];
                } else {
                    $parentCampaign = $this->campaignFactory->getById($parentCampaignId);
                    $campaignCache[$parentCampaignId]['type'] = $parentCampaign->type;

                    $campaignCache[$parentCampaignId]['start'] =  $parentCampaign->getStartDt()->format(DateFormatHelper::getSystemFormat());
                    $campaignCache[$parentCampaignId]['end'] = $parentCampaign->getEndDt()->format(DateFormatHelper::getSystemFormat());
                    $entry['campaignStart'] = $campaignCache[$parentCampaignId]['start'];
                    $entry['campaignEnd'] = $campaignCache[$parentCampaignId]['end'];
                }

                // Skip list campaign stats, keep only ad campaign stats
                if ($campaignCache[$parentCampaignId]['type'] !== 'ad') {
                    continue;
                }

                // --------
                // Get Display
                // Cost per play and impressions per play
                $entry['displayId'] = $displayId;
                if (array_key_exists($displayId, $displayCache)) {
                    $entry['costPerPlay'] = $displayCache[$displayId]['costPerPlay'];
                    $entry['impressionsPerPlay'] = $displayCache[$displayId]['impressionsPerPlay'];
                } else {
                    $display = $this->displayFactory->getById($displayId);
                    $displayCache[$displayId]['costPerPlay'] =  $display->costPerPlay;
                    $displayCache[$displayId]['impressionsPerPlay'] = $display->impressionsPerPlay;
                    $entry['costPerPlay'] = $displayCache[$displayId]['costPerPlay'];
                    $entry['impressionsPerPlay'] = $displayCache[$displayId]['impressionsPerPlay'];
                }

                if ($this->timeSeriesStore->getEngine() == 'mongodb') {
                    $start = Carbon::createFromTimestamp($row['start']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                    $end = Carbon::createFromTimestamp($row['end']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                } else {
                    $start = Carbon::createFromTimestamp($row['start'])->format(DateFormatHelper::getSystemFormat());
                    $end = Carbon::createFromTimestamp($row['end'])->format(DateFormatHelper::getSystemFormat());
                }

                $entry['id'] = $resultSet->getIdFromRow($row);
                $entry['layoutId'] = $sanitizedRow->getInt('layoutId', ['default' => 0]);
                $entry['numberPlays'] = $sanitizedRow->getInt('count', ['default' => 0]);
                $entry['duration'] = $sanitizedRow->getInt('duration', ['default' => 0]);
                $entry['start'] = $start;
                $entry['end'] = $end;
                $entry['engagements'] = $resultSet->getEngagementsFromRow($row);

                $rows[] = $entry;
            }

            $this->getLogger()->debug('onRegularMaintenance: Records sent: ' . count($rows) . ', Watermark: ' . $watermark);

            $statusCode = 0;
            if (count($rows) > 0) {
                try {
                    $response = $this->getClient()->post($this->getServiceUrl() . '/receiveStats', [
                        'headers' => [
                            'X-API-KEY' => $this->getSetting('apiKey')
                        ],
                        'json' => $rows
                    ]);

                    $statusCode = $response->getStatusCode();
                } catch (RequestException $requestException) {
                    $event->addMessage(__('Error calling audience receiveStats'. $requestException->getMessage()));
                    $this->getLogger()->error('Audience receiveStats: failed e = ' . $requestException->getMessage());
                }

                // Get Campaign Total
                if ($statusCode == 204) {
                    try {
                        $response = $this->getClient()->get($this->getServiceUrl() . '/campaignTotal', [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => [
                                'campaigns' => $campaigns
                            ]
                        ]);

                        $body = $response->getBody()->getContents();
                        $results = json_decode($body, true);

                        foreach ($results as $item) {
                            // Save the total in the camapign
                            $campaign = $this->campaignFactory->getById($item['id']);

                            $campaign->spend = $item['spend'];
                            $campaign->impressions = $item['impressions'];

                            $campaign->save(['validate' => false]);
                        }
                    } catch (RequestException $requestException) {
                        $event->addMessage(__('Error getting campaign total:'. $requestException->getMessage()));
                        $this->getLogger()->error('Campaign total: e = ' . $requestException->getMessage());
                    }
                }
            }
        } catch (RequestException $requestException) {
            // If we cant get the watermark we stop
            $event->addMessage(__('Error getting watermark: '. $requestException->getMessage()));
            $this->getLogger()->error('Get Watermark: failed e = ' . $requestException->getMessage());
        }
    }

    /**
     * Request Report results from the audience report service
     * @throws GeneralException
     */
    public function onRequestReportData(ReportDataEvent $event)
    {
        $this->getLogger()->debug('onRequestReportData');

        $type = $event->getReportType();

        $typeUrl = [
            'campaignProofofplay' => $this->getServiceUrl() . '/campaign/proofofplay',
            'mobileProofofplay' => $this->getServiceUrl() . '/campaign/proofofplay/mobile',
            'displayAdPlay' => $this->getServiceUrl() . '/display/adplays'
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
//            [
//                'name'=> 'displayPlayedPercentageReport',
//                'description'=> 'Display played percentage',
//                'class'=> '\\Xibo\\Report\\DisplayPlayedPercentage',
//                'type'=> 'Report',
//                'output_type'=> 'table',
//                'color'=> 'green',
//                'fa_icon'=> 'fa-th',
//                'category'=> 'Connector Reports',
//                'feature'=> 'display-report',
//                'adminOnly'=> 0,
//                'sort_order' => 3
//            ],
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
}
