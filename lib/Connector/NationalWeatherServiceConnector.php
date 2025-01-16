<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

namespace Xibo\Connector;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\ScheduleCriteriaRequestEvent;
use Xibo\Event\ScheduleCriteriaRequestInterface;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\XMR\ScheduleCriteriaUpdateAction;

/**
 * A connector to process National Weather Alert (NWS) - Atom feed data
 */
class NationalWeatherServiceConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var DOMDocument */
    protected DOMDocument $atomFeedXML;

    /** @var DOMElement */
    protected DOMElement $feedNode;

    /** @var DOMElement */
    protected DOMElement $entryNode;

    /** @var DisplayFactory */
    private DisplayFactory $displayFactory;

    /**
     * @param ContainerInterface $container
     * @return ConnectorInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setFactories(ContainerInterface $container): ConnectorInterface
    {
        $this->displayFactory = $container->get('displayFactory');
        return $this;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(WidgetDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        $dispatcher->addListener(ScheduleCriteriaRequestEvent::$NAME, [$this, 'onScheduleCriteriaRequest']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'national-weather-service-connector';
    }

    public function getTitle(): string
    {
        return 'National Weather Service Connector';
    }

    public function getDescription(): string
    {
        return 'National Weather Service (NWS)';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/xibo-nws.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'national-weather-service-form-settings';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('atomFeedUri')) {
            $settings['atomFeedUri'] = $params->getString('atomFeedUri');
        }
        return $settings;
    }

    /**
     * If the requested dataSource is emergency-alert, get the data, process it and add to dataProvider
     *
     * @param WidgetDataRequestEvent $event
     * @return void
     * @throws GuzzleException
     */
    public function onDataRequest(WidgetDataRequestEvent $event): void
    {
        if ($event->getDataProvider()->getDataSource() === 'national-weather-service') {
            if (empty($this->getSetting('atomFeedUri'))) {
                $this->getLogger()->debug('onDataRequest: National Weather Service Connector not configured.');
                return;
            }

            $event->stopPropagation();

            try {
                // Process and initialize Atom Feed data
                $this->processAtomFeedData($event->getDataProvider());

                // Initialize update interval
                $updateIntervalMinute = $event->getDataProvider()->getProperty('updateInterval');

                // Convert the $updateIntervalMinute to seconds
                $updateInterval = $updateIntervalMinute * 60;

                // If we've got data, then set our cache period.
                $event->getDataProvider()->setCacheTtl($updateInterval);
                $event->getDataProvider()->setIsHandled();

                // Get all <entry> nodes within the <feed> element
                $entryNodes = $this->feedNode->getElementsByTagName('entry');

                // Define priority arrays for status and msgType (higher priority = lower index)
                $statusPriority = ['Actual', 'Exercise', 'System', 'Test', 'Draft'];
                $msgTypePriority = ['Alert', 'Update', 'Cancel', 'Ack', 'Error'];

                // Initialize placeholders for the highest-priority status and msgType
                $highestStatus = null;
                $highestMsgType = null;

                // Iterate through each <entry> node to find the highest-priority status and msgType
                foreach ($entryNodes as $entryNode) {
                    $this->entryNode = $entryNode;

                    // Get the status and msgType for the current entry
                    $status = $this->getEntryData('status');
                    $msgType = $this->getEntryData('msgType');

                    // Check if the current status has a higher priority
                    if ($status !== null && (
                            $highestStatus === null ||
                            array_search($status, $statusPriority) < array_search($highestStatus, $statusPriority)
                        )) {
                        $highestStatus = $status;
                    }

                    // Check if the current msgType has a higher priority
                    if ($msgType !== null && (
                            $highestMsgType === null ||
                            array_search($msgType, $msgTypePriority) < array_search($highestMsgType, $msgTypePriority)
                        )) {
                        $highestMsgType = $msgType;
                    }
                }

                // Ensure default values if no valid status/msgType is found
                $highestStatus = $highestStatus ?? '';
                $highestMsgType = $highestMsgType ?? '';

                If ($highestStatus || $highestMsgType) {
                    $this->getLogger()->debug(
                        'Schedule criteria push message: status=' . $highestStatus .
                        ', msgType=' . $highestMsgType,
                        ['status' => $highestStatus, 'msgType' => $highestMsgType]
                    );

                    // Set schedule criteria update
                    $action = new ScheduleCriteriaUpdateAction();
                    $action->setCriteriaUpdates([
                        'isNwsActive' => 1,
                        'status' => $highestStatus,
                        'msgType' => $highestMsgType
                    ]);

                    // Initialize the display
                    $displayId = $event->getDataProvider()->getDisplayId();
                    $display = $this->displayFactory->getById($displayId);

                    // Criteria push message
                    $this->getPlayerActionService()->sendAction($display, $action);
                }
            } catch (Exception $exception) {
                $this->getLogger()
                    ->error('onDataRequest: Failed to get results. e = ' . $exception->getMessage());
                $event->getDataProvider()->addError(__('Unable to get National Weather Service results.'));
            }
        }
    }

    /**
     * Get and process the NWS Atom Feed data
     *
     * @throws GuzzleException
     * @throws Exception
     */
    private function processAtomFeedData(DataProviderInterface $dataProvider): void
    {
        // Array to store configuration data
        $config = [];

        // Initialize configuration data
        $config['status'] = $dataProvider->getProperty('status');
        $config['msgType'] = $dataProvider->getProperty('msgType');
        $config['urgency'] = $dataProvider->getProperty('urgency');
        $config['severity'] = $dataProvider->getProperty('severity');
        $config['certainty'] = $dataProvider->getProperty('certainty');

        // Set cache expiry date to 3 minutes from now
        $cacheExpire = Carbon::now()->addMinutes(3);

        // Fetch the Atom Feed XML content
        $xmlContent = $this->getFeedFromUrl($dataProvider, $cacheExpire);

        // Initialize DOMDocument and load the XML content
        $this->atomFeedXML = new DOMDocument();
        $this->atomFeedXML->loadXML($xmlContent);

        // Ensure the root element is <feed>
        $feedNode = $this->atomFeedXML->getElementsByTagName('feed')->item(0);
        if ($feedNode instanceof DOMElement) {
            $this->feedNode = $feedNode;
        } else {
            throw new \Exception('The root <feed> element is missing.');
        }

        // Get all <entry> nodes within the <feed> element
        $entryNodes = $this->feedNode->getElementsByTagName('entry');

        // Iterate through each <entry> node
        foreach ($entryNodes as $entryNode) {
            $this->entryNode = $entryNode;

            // Retrieve specific values from the CAP XML for filtering
            $status = $this->getEntryData('status');
            $msgType = $this->getEntryData('msgType');

            // Check if the retrieved CAP data matches the configuration filters
            if (!$this->matchesFilter($status, $config['status']) ||
                !$this->matchesFilter($msgType, $config['msgType'])) {
                continue;
            }

            // Array to store CAP values
            $cap = [];

            // Initialize CAP values
            $cap['source'] = $this->getEntryData('source');
            $cap['note'] = $this->getEntryData('note');

            // Extract values from the current <info> node for filtering
            $urgency = $this->getEntryData('urgency');
            $severity = $this->getEntryData('severity');
            $certainty = $this->getEntryData('certainty');

            // Check if the current <info> node matches all filters
            if (!$this->matchesFilter($urgency, $config['urgency']) ||
                !$this->matchesFilter($severity, $config['severity']) ||
                !$this->matchesFilter($certainty, $config['certainty'])) {
                continue;
            }

            // Initialize the rest of the CAP values
            $cap['event'] = $this->getEntryData('event');
            $cap['urgency'] = $this->getEntryData('urgency');
            $cap['severity'] = $this->getEntryData('severity');
            $cap['certainty'] = $this->getEntryData('certainty');
            $cap['dateTimeEffective'] = $this->getEntryData('effective');
            $cap['dateTimeOnset'] = $this->getEntryData('onset');
            $cap['dateTimeExpires'] = $this->getEntryData('expires');
            $cap['headline'] = $this->getEntryData('headline');
            $cap['description'] = $this->getEntryData('summary');
            $cap['instruction'] = $this->getEntryData('instruction');
            $cap['contact'] = $this->getEntryData('contact');
            $cap['areaDesc'] = $this->getEntryData('areaDesc');

            // Extract <author> node from the entry
            $authorNode = $this->getEntryData('author');

            // Provide CAP data if area-specific filter is disabled
            $dataProvider->addItem($cap);

        }
    }


    /**
     * Fetches the National Weather Service's Atom Feed XML data from the Atom Feed URL provided by the connector.
     *
     * @param DataProviderInterface $dataProvider
     * @param Carbon $cacheExpiresAt
     *
     * @return string
     * @throws GuzzleException
     */
    private function getFeedFromUrl(DataProviderInterface $dataProvider, Carbon $cacheExpiresAt): string
    {
        $atomFeedUri = $this->getSetting('atomFeedUri');
        $area = $dataProvider->getProperty('area');

        // Construct the Atom feed url
        if (empty($area)) {
            $url = $atomFeedUri;
        } else {
            $url = $atomFeedUri . '?area=' . $area;
        }

        $cache = $this->pool->getItem('/national-weather-service/alerts/' . md5($url));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $cache->lock();
            $this->getLogger()->debug('Getting alerts from National Weather Service Atom feed');

            $httpOptions = [
                'timeout' => 20, // Wait no more than 20 seconds
            ];

            try {
                // Make a GET request to the Atom Feed URL using Guzzle HTTP client with defined options
                $response = $dataProvider
                    ->getGuzzleClient($httpOptions)
                    ->get($url);

                $this->getLogger()->debug('NWS Atom Feed uri: ' . $url . ' httpOptions: '
                    . json_encode($httpOptions));

                // Get the response body as a string
                $data = $response->getBody()->getContents();

                // Cache
                $cache->set($data);
                $cache->expiresAt($cacheExpiresAt);
                $this->pool->saveDeferred($cache);
            } catch (RequestException $e) {
                // Log the error with a message specific to NWS Alert data fetching
                $this->getLogger()->error('Unable to reach the NWS Atom feed URL: '
                    . $url . ' Error: ' . $e->getMessage());

                // Throw a more specific exception message
                $dataProvider->addError(__('Failed to retrieve NWS alerts from specified Atom Feed URL.'));
            }
        } else {
            $this->getLogger()->debug('Getting NWS Alert data from cache');
        }

        return $data;
    }

    /**
     * Get the value of a specified tag from the current <info> node.
     *
     * @param string $tagName
     * @return string|null
     */
    private function getEntryData(string $tagName): ?string
    {
        // Ensure the tag exists within the provided <info> node
        $node = $this->entryNode->getElementsByTagName($tagName)->item(0);

        // Return the node value if the node exists, otherwise return an empty string
        return $node ? $node->nodeValue : '';
    }

    /**
     * Check if the value of XML element matches the expected filter value.
     *
     * @param string $actualValue
     * @param string $expectedValue
     *
     * @return bool
     */
    private function matchesFilter(string $actualValue, string $expectedValue): bool
    {
        // If the expected value is 'Any' (empty string) or matches the actual value, the filter passes
        if (empty($expectedValue) || $expectedValue == $actualValue) {
            return true;
        }

        return false;
    }

    /**
     * @param ScheduleCriteriaRequestInterface $event
     * @return void
     * @throws ConfigurationException
     */
    public function onScheduleCriteriaRequest(ScheduleCriteriaRequestInterface $event): void
    {
        // Initialize Emergency Alerts schedule criteria parameters
        $event->addType('nws_alerts', __('National Weather Service Alerts'))
            ->addMetric('isNwsActive', __('Is NWS alert active?'))
                ->addValues('dropdown', [
                    '1' => __('Yes'),
                    '0' => __('No'),
                ])
            ->addMetric('emergency_alert_status', __('Status'))
                ->addValues('dropdown', [
                    'Actual' => __('Actual'),
                    'Exercise' => __('Exercise'),
                    'System' => __('System'),
                    'Test' => __('Test'),
                    'Draft' => __('Draft')
                ])
            ->addMetric('msgType', __('Message Type'))
                ->addValues('dropdown', [
                    'Alert' => __('Alert'),
                    'Update' => __('Update'),
                    'Cancel' => __('Cancel'),
                    'Ack' => __('Ack'),
                    'Error' => __('Error')
                ]);
    }
}
