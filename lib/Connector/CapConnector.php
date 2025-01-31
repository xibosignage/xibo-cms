<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Location\Coordinate;
use Location\Polygon;
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
 * A connector to process Common Alerting Protocol (CAP) Data
 */
class CapConnector implements ConnectorInterface, EmergencyAlertInterface
{
    use ConnectorTrait;

    /** @var DOMDocument */
    protected DOMDocument $capXML;

    /** @var DOMElement */
    protected DOMElement $infoNode;

    /** @var DOMElement */
    protected DOMElement $areaNode;

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
        return 'cap-connector';
    }

    public function getTitle(): string
    {
        return 'CAP Connector';
    }

    public function getDescription(): string
    {
        return 'Common Alerting Protocol';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/xibo-cap.png';
    }

    public function getSettingsFormTwig(): string
    {
        return '';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        return [];
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
        if ($event->getDataProvider()->getDataSource() === 'emergency-alert') {
            $event->stopPropagation();

            try {
                // check if CAP URL is present
                if (empty($event->getDataProvider()->getProperty('emergencyAlertUri'))) {
                    $this->getLogger()->debug('onDataRequest: Emergency alert not configured.');
                    $event->getDataProvider()->addError(__('Missing CAP URL'));
                    return;
                }

                // Set cache expiry date to 3 minutes from now
                $cacheExpire = Carbon::now()->addMinutes(3);

                // Fetch the CAP XML content from the given URL
                $xmlContent = $this->fetchCapAlertFromUrl($event->getDataProvider(), $cacheExpire);

                if ($xmlContent) {
                    // Initialize DOMDocument and load the XML content
                    $this->capXML = new DOMDocument();
                    $this->capXML->loadXML($xmlContent);

                    // Process and initialize CAP data
                    $this->processCapData($event->getDataProvider());

                    // Initialize update interval
                    $updateIntervalMinute = $event->getDataProvider()->getProperty('updateInterval');

                    // Convert the $updateIntervalMinute to seconds
                    $updateInterval = $updateIntervalMinute * 60;

                    // If we've got data, then set our cache period.
                    $event->getDataProvider()->setCacheTtl($updateInterval);
                    $event->getDataProvider()->setIsHandled();

                    $capStatus = $this->getCapXmlData('status');
                    $category = $this->getCapXmlData('category');
                } else {
                    $capStatus = 'No Alerts';
                    $category = '';
                }

                // initialize status for schedule criteria push message
                if ($capStatus == 'Actual') {
                    $status = self::ACTUAL_ALERT;
                } elseif ($capStatus == 'No Alerts') {
                    $status = self::NO_ALERT;
                } else {
                    $status = self::TEST_ALERT;
                }

                $this->getLogger()->debug('Schedule criteria push message: status = ' . $status
                    . ', category = ' . $category);

                // Set schedule criteria update
                $action = new ScheduleCriteriaUpdateAction();
                $action->setCriteriaUpdates([
                    'emergency_alert_status' => $status,
                    'emergency_alert_category' => $category,
                ]);

                // Initialize the display
                $displayId = $event->getDataProvider()->getDisplayId();
                $display = $this->displayFactory->getById($displayId);

                // Criteria push message
                $this->getPlayerActionService()->sendAction($display, $action);
            } catch (Exception $exception) {
                $this->getLogger()
                    ->error('onDataRequest: Failed to get results. e = ' . $exception->getMessage());
                $event->getDataProvider()->addError(__('Unable to get Common Alerting Protocol (CAP) results.'));
            }
        }
    }

    /**
     * Get and process the CAP data
     *
     * @throws Exception
     */
    private function processCapData(DataProviderInterface $dataProvider): void
    {
        // Array to store configuration data
        $config = [];

        // Initialize configuration data
        $config['status'] = $dataProvider->getProperty('status');
        $config['msgType'] = $dataProvider->getProperty('msgType');
        $config['scope'] = $dataProvider->getProperty('scope');
        $config['category'] = $dataProvider->getProperty('category');
        $config['responseType'] = $dataProvider->getProperty('responseType');
        $config['urgency'] = $dataProvider->getProperty('urgency');
        $config['severity'] = $dataProvider->getProperty('severity');
        $config['certainty'] = $dataProvider->getProperty('certainty');
        $config['isAreaSpecific'] = $dataProvider->getProperty('isAreaSpecific');

        // Retrieve specific values from the CAP XML for filtering
        $status = $this->getCapXmlData('status');
        $msgType = $this->getCapXmlData('msgType');
        $scope = $this->getCapXmlData('scope');

        // Check if the retrieved CAP data matches the configuration filters
        if (!$this->matchesFilter($status, $config['status']) ||
            !$this->matchesFilter($msgType, $config['msgType']) ||
            !$this->matchesFilter($scope, $config['scope'])) {
            return;
        }

        // Array to store CAP values
        $cap = [];

        // Initialize CAP values
        $cap['source'] = $this->getCapXmlData('source');
        $cap['note'] = $this->getCapXmlData('note');

        // Get all <info> elements
        $infoNodes = $this->capXML->getElementsByTagName('info');

        foreach ($infoNodes as $infoNode) {
            $this->infoNode = $infoNode;

            // Extract values from the current <info> node for filtering
            $category = $this->getInfoData('category');
            $responseType = $this->getInfoData('responseType');
            $urgency = $this->getInfoData('urgency');
            $severity = $this->getInfoData('severity');
            $certainty = $this->getInfoData('certainty');

            // Check if the current <info> node matches all filters
            if (!$this->matchesFilter($category, $config['category']) ||
                !$this->matchesFilter($responseType, $config['responseType']) ||
                !$this->matchesFilter($urgency, $config['urgency']) ||
                !$this->matchesFilter($severity, $config['severity']) ||
                !$this->matchesFilter($certainty, $config['certainty'])) {
                continue;
            }

            // Initialize the rest of the CAP values
            $cap['event'] = $this->getInfoData('event');
            $cap['urgency'] = $this->getInfoData('urgency');
            $cap['severity'] = $this->getInfoData('severity');
            $cap['certainty'] = $this->getInfoData('certainty');
            $cap['dateTimeEffective'] = $this->getInfoData('effective');
            $cap['dateTimeOnset'] = $this->getInfoData('onset');
            $cap['dateTimeExpires'] = $this->getInfoData('expires');
            $cap['senderName'] = $this->getInfoData('senderName');
            $cap['headline'] = $this->getInfoData('headline');
            $cap['description'] = $this->getInfoData('description');
            $cap['instruction'] = $this->getInfoData('instruction');
            $cap['contact'] = $this->getInfoData('contact');

            // Retrieve all <area> elements within the current <info> element
            $areaNodes = $this->infoNode->getElementsByTagName('area');

            // Iterate through each <area> element
            foreach ($areaNodes as $areaNode) {
                $this->areaNode = $areaNode;

                $circle = $this->getAreaData('circle');
                $polygon = $this->getAreaData('polygon');
                $cap['areaDesc'] = $this->getAreaData('areaDesc');

                // Check if the area-specific filter is enabled
                if ($config['isAreaSpecific']) {
                    if ($circle || $polygon) {
                        // Get the current display coordinates
                        $displayLatitude = $dataProvider->getDisplayLatitude();
                        $displayLongitude = $dataProvider->getDisplayLongitude();

                        // Retrieve area coordinates (circle or polygon) from CAP XML
                        $areaCoordinates = $this->getAreaCoordinates();

                        // Check if display coordinates matches the CAP alert area
                        if ($this->isWithinArea($displayLatitude, $displayLongitude, $areaCoordinates)) {
                            $dataProvider->addItem($cap);
                        }
                    } else {
                        // Provide CAP data if no coordinate/s is provided
                        $dataProvider->addItem($cap);
                    }
                } else {
                    // Provide CAP data if area-specific filter is disabled
                    $dataProvider->addItem($cap);
                }
            }
        }
    }


    /**
     * Fetches the CAP (Common Alerting Protocol) XML data from the provided emergency alert URL.
     *
     * @param DataProviderInterface $dataProvider
     * @param Carbon $cacheExpiresAt
     *
     * @return string|null
     * @throws GuzzleException
     */
    private function fetchCapAlertFromUrl(DataProviderInterface $dataProvider, Carbon $cacheExpiresAt): string|null
    {
        $emergencyAlertUrl = $dataProvider->getProperty('emergencyAlertUri');

        $cache = $this->pool->getItem('/emergency-alert/cap/' . md5($emergencyAlertUrl));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $cache->lock();
            $this->getLogger()->debug('Getting CAP data from CAP Feed');

            $httpOptions = [
                'timeout' => 20, // Wait no more than 20 seconds
            ];

            try {
                // Make a GET request to the CAP URL using Guzzle HTTP client with defined options
                $response = $dataProvider
                    ->getGuzzleClient($httpOptions)
                    ->get($emergencyAlertUrl);

                $this->getLogger()->debug('CAP Feed: uri: ' . $emergencyAlertUrl . ' httpOptions: '
                        . json_encode($httpOptions));

                // Get the response body as a string
                $data = $response->getBody()->getContents();

                // Cache
                $cache->set($data);
                $cache->expiresAt($cacheExpiresAt);
                $this->pool->saveDeferred($cache);
            } catch (RequestException $e) {
                // Log the error with a message specific to CAP data fetching
                $this->getLogger()->error('Unable to reach the CAP feed URL: '
                    . $emergencyAlertUrl . ' Error: ' . $e->getMessage());

                // Throw a more specific exception message
                $dataProvider->addError(__('Failed to retrieve CAP data from the specified URL.'));
            }
        } else {
            $this->getLogger()->debug('Getting CAP data from cache');
        }

        return $data;
    }

    /**
     * Get the value of a specified tag from the CAP XML document.
     *
     * @param string $tagName
     * @return string|null
     */
    private function getCapXmlData(string $tagName): ?string
    {
        // Ensure the XML is loaded and the tag exists
        $node = $this->capXML->getElementsByTagName($tagName)->item(0);

        // Return the node value if the node exists, otherwise return an empty string
        return $node ? $node->nodeValue : '';
    }

    /**
     * Get the value of a specified tag from the current <info> node.
     *
     * @param string $tagName
     * @return string|null
     */
    private function getInfoData(string $tagName): ?string
    {
        // Ensure the tag exists within the provided <info> node
        $node = $this->infoNode->getElementsByTagName($tagName)->item(0);

        // Return the node value if the node exists, otherwise return an empty string
        return $node ? $node->nodeValue : '';
    }

    /**
     * Get the value of a specified tag from the current <area> node.
     *
     * @param string $tagName
     * @return string|null
     */
    private function getAreaData(string $tagName): ?string
    {
        // Ensure the tag exists within the provided <area> node
        $node = $this->areaNode->getElementsByTagName($tagName)->item(0);

        // Return the node value if the node exists, otherwise return an empty string
        return $node ? $node->nodeValue : '';
    }

    /**
     * Check if the value of a CAP XML element matches the expected filter value.
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
     * Get area coordinates from CAP XML data.
     *
     * Determines if the area is defined as a circle or polygon
     * and returns the relevant data.
     *
     * @return array An array with the area type and coordinates.
     */
    private function getAreaCoordinates(): array
    {
        // array to store coordinates data
        $area = [];

        // Check for a circle area element
        $circle = $this->getAreaData('circle');
        if ($circle) {
            // Split the circle data into center coordinates and radius
            $circleParts = explode(' ', $circle);
            $center = explode(',', $circleParts[0]);  // "latitude,longitude"
            $radius = $circleParts[1];

            $area['type'] = 'circle';
            $area['center'] = ['lat' => $center[0], 'lon' => $center[1]];
            $area['radius'] = $radius;
            return $area;
        }

        // Check for a polygon area element
        $polygon = $this->getAreaData('polygon');
        if ($polygon) {
            // Split the polygon data into multiple points ("lat1,lon1 lat2,lon2 ...")
            $points = explode(' ', $polygon);

            // Array to store multiple coordinates
            $polygonPoints = [];

            foreach ($points as $point) {
                $coords = explode(',', $point);
                $polygonPoints[] = ['lat' => $coords[0], 'lon' => $coords[1]];
            }

            $area['type'] = 'polygon';
            $area['points'] = $polygonPoints;
        }

        return $area;
    }

    /**
     * Checks if the provided display coordinates are inside a defined area (circle or polygon).
     * If no area coordinates are available, it returns false.
     *
     * @param float $displayLatitude
     * @param float $displayLongitude
     * @param array $areaCoordinates The coordinates defining the area (circle or polygon).
     *
     * @return bool
     */
    private function isWithinArea(float $displayLatitude, float $displayLongitude, array $areaCoordinates): bool
    {
        if (empty($areaCoordinates)) {
            // No area coordinates available
            return false;
        }

        // Initialize the display coordinate
        $displayCoordinate = new Coordinate($displayLatitude, $displayLongitude);

        if ($areaCoordinates['type'] == 'circle') {
            // Initialize the circle's coordinate and radius
            $centerCoordinate = new Coordinate($areaCoordinates['center']['lat'], $areaCoordinates['center']['lon']);
            $radius = $areaCoordinates['radius'];

            // Check if the display is within the specified radius of the center coordinate
            if ($centerCoordinate->hasSameLocation($displayCoordinate, $radius)) {
                return true;
            }
        } else {
            // Initialize a new polygon
            $geofence = new Polygon();

            // Add each point to the polygon
            foreach ($areaCoordinates['points'] as $point) {
                $geofence->addPoint(new Coordinate($point['lat'], $point['lon']));
            }

            // Check if the display is within the polygon
            if ($geofence->contains($displayCoordinate)) {
                return true;
            }
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
        $event->addType('emergency_alert', __('Emergency Alerts'))
                ->addMetric('status', __('Status'))
                    ->addCondition([
                        'eq' => __('Equal to')
                    ])
                    ->addValues('dropdown', [
                        self::ACTUAL_ALERT => __('Actual Alerts'),
                        self::TEST_ALERT => __('Test Alerts'),
                        self::NO_ALERT => __('No Alerts')
                    ])
                ->addMetric('category', __('Category'))
                    ->addCondition([
                        'eq' => __('Equal to')
                    ])
                    ->addValues('dropdown', [
                        'Geo' => __('Geo'),
                        'Met' => __('Met'),
                        'Safety' => __('Safety'),
                        'Security' => __('Security'),
                        'Rescue' => __('Rescue'),
                        'Fire' => __('Fire'),
                        'Health' => __('Health'),
                        'Env' => __('Env'),
                        'Transport' => __('Transport'),
                        'Infra' => __('Infra'),
                        'CBRNE' => __('CBRNE'),
                        'Other' => __('Other'),
                    ]);
    }
}
