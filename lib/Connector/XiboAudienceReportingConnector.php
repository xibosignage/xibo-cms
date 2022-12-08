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
use Xibo\Event\ReportDataEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

class XiboAudienceReportingConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var StorageServiceInterface */
    private $store;

    /** @var TimeSeriesStoreInterface */
    private $timeSeriesStore;

    /** @var  SanitizerService */
    private $sanitizer;

    /** @var CampaignFactory */
    private $campaignFactory;

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function setFactories(ContainerInterface $container): ConnectorInterface
    {
        $this->store = $container->get('store');
        $this->timeSeriesStore = $container->get('timeSeriesStore');
        $this->sanitizer = $container->get('sanitizerService');
        $this->campaignFactory = $container->get('campaignFactory');

        return $this;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(MaintenanceRegularEvent::$NAME, [$this, 'onRegularMaintenance']);
        $dispatcher->addListener(ReportDataEvent::$NAME, [$this, 'onAudienceReport']);

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
        // TODO: Implement processSettingsForm() method.
        return $settings;
    }

    public function onRegularMaintenance(MaintenanceRegularEvent $event)
    {
        $watermark = null;
        // Get Watermark
        try {
            $response = $this->getClient()->get('https://test-exchange.xibo-adspace.com/api/audience/watermark', [
                    'headers' => [
                        'X-API-KEY' => $this->getSetting('apiKey')
                    ],
                ]
            );

            $body = $response->getBody()->getContents();
            $json = json_decode($body, true);
            $watermark = $json['watermark'];
        } catch (RequestException $requestException) {
            $event->addMessage(__('Error getting watermark: '. $requestException->getMessage()));
            $this->getLogger()->error('Get Watermark: failed e = ' . $requestException->getMessage());
        }

        $params =   [
            'type' => 'layout',
            'start' => 1,
            'length' => 10000,
        ];

        if (!empty($watermark)) {
            $params['statId'] = $watermark;
        }

        // Call the time series interface getStats
        $resultSet = $this->timeSeriesStore->getStats($params);

        // Array of campaigns for which we will update the total spend, impresssions, and plays
        $campaigns = [];

        $rows = [];
        while ($row = $resultSet->getNextRow()) {
            $sanitizedRow = $this->sanitizer->getSanitizer($row);

            if ($this->timeSeriesStore->getEngine() == 'mongodb') {
                $start = Carbon::createFromTimestamp($row['start']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
            } else {
                $start = Carbon::createFromTimestamp($row['start'])->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end'])->format(DateFormatHelper::getSystemFormat());
            }
            $entry['id'] = $resultSet->getIdFromRow($row);

            $entry['parentCampaignId'] = $sanitizedRow->getInt('parentCampaignId', ['default' => 0]);
            $entry['displayId'] = $sanitizedRow->getInt(('displayId'));
            $entry['layoutId'] = $sanitizedRow->getInt('layoutId', ['default' => 0]);

            $entry['numberPlays'] = $sanitizedRow->getInt('count');
            $entry['duration'] = $sanitizedRow->getInt('duration');
            $entry['start'] = $start;
            $entry['end'] = $end;
            $entry['engagements'] = $resultSet->getEngagementsFromRow($row);

            $rows[] = $entry;
            $campaigns[] = $entry['parentCampaignId'];
        }

        $statusCode = 0;
        if (count($rows) > 0) {
            try {
                $response = $this->getClient()->post('https://test-exchange.xibo-adspace.com/api/audience/receiveStats', [
                        'headers' => [
                            'X-API-KEY' => $this->getSetting('apiKey')
                        ],
                        'json' => $rows
                    ]
                );

                $statusCode = $response->getStatusCode();

            } catch (RequestException $requestException) {
                $event->addMessage(__('Error calling audience receiveStats'. $requestException->getMessage()));
                $this->getLogger()->error('Audience receiveStats: failed e = ' . $requestException->getMessage());
            }

            // Get Campaign Total
            if ($statusCode == 204) {
                try {
                    $response = $this->getClient()->get('https://test-exchange.xibo-adspace.com/api/audience/campaignTotal', [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'query' => [
                                'campaigns' => $campaigns
                            ]
                        ]
                    );

                    $body = $response->getBody()->getContents();
                    $results = json_decode($body, true);
                    foreach ($results as $result) {
                        // Save the total in the camapign
                        $campaign = $this->campaignFactory->getById($result['id']);

                        $campaign->spend = $result['spend'];
                        $campaign->impressions = $result['impressions'];
                        $campaign->plays = $result['adPlays'];

                        $campaign->save();
                    }
                } catch (RequestException $requestException) {
                    $event->addMessage(__('Error getting campaign total:'. $requestException->getMessage()));
                    $this->getLogger()->error('Campaign total: e = ' . $requestException->getMessage());
                }

            }
        }
    }

    public function onAudienceReport(ReportDataEvent $event)
    {
        $type = $event->getReportType();

        $typeUrl = [
          'proofofplay' => 'https://test-exchange.xibo-adspace.com/api/audience/campaign/proofofplay'
        ];

        if (array_key_exists($type, $typeUrl)) {

            $json = [];
            switch ($type) {
                case 'proofofplay':

                    // Get audience proofofplay result
                    try {
                        $response = $this->getClient()->get($typeUrl[$type], [
                                'headers' => [
                                    'X-API-KEY' => $this->getSetting('apiKey')
                                ],
                                'query' => $event->getParams()
                            ]
                        );

                        $body = $response->getBody()->getContents();
                        $json = json_decode($body, true);

                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('Get '. $type.': failed. e = ' . $requestException->getMessage());
                    }
                    break;

                default:
                    $this->getLogger()->error('Report type not found ');
            }

            $event->setResults($json);

        }
    }
}
