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

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\RdmConnectorSendCommandEvent;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Xibo RDM Connector
 *  Communicates with the Xibo Portal - My Account to perform remote device management
 */
class XiboRdmConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var string */
    private $formError;

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

    /** @var array|array[] */
    private array $devices;

    /**
     * @var array|array[]
     */
    private array $displays;

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function setFactories(ContainerInterface $container): ConnectorInterface
    {
        $this->displayFactory = $container->get('displayFactory');

        return $this;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(RdmConnectorSendCommandEvent::$NAME, [$this, 'onSendCommand']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'xibo-rdm-connector';
    }

    public function getTitle(): string
    {
        return 'Xibo Remote Device Management';
    }

    public function getDescription(): string
    {
        return 'Connect your CMS to Xibo Portal - My Account for remote device management.';
    }

    public function getThumbnail(): string
    {
        return '';
    }

    public function getSettingsFormTwig(): string
    {
        return 'xibo-rdm-connector-form-settings';
    }

    public function getSettingsFormJavaScript(): string
    {
        return 'xibo-rdm-connector-form-javascript';
    }

    /**
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws GeneralException
     */
    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('cmsPsk')) {
            $settings['cmsPsk'] = $params->getString('cmsPsk');
        }

        $rdmDisplays = $this->getRdmDisplays();

        $this->devices = $this->setRdmDevices($rdmDisplays, $settings['cmsPsk']);

        return $settings;
    }

    /**
     * Auto match - Make a call to the My Account Portal and set the RDM devices
     * @param array $displays
     * @param string|null $withCmsPsk
     * @return array|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function setRdmDevices(array $displays, ?string $withCmsPsk): ?array
    {
        $cmsPsk = !empty($withCmsPsk) ? $withCmsPsk : $this->getSetting('cmsPsk');

        if (empty($cmsPsk)) {
            throw new InvalidArgumentException(__('Connector Key cannot be empty'), 'cmsPsk');
        }

        $this->getLogger()->info('getAvailableDevices: Requesting available devices.');

        try {
            $request = $this->getClient()->post($this->getServiceUrl() . '/rdm/cms/connect', [
                'headers' => [
                    'X-CMS-PSK-KEY' => $cmsPsk
                ],
                'query' => [
                    'displays' => $displays
                ]
            ]);

            $devices = json_decode($request->getBody()->getContents(), true);

            // Save the rdmDeviceId of the displays
            foreach ($devices as $device) {
                $cmsDisplay = $this->displayFactory->query(null, ['displayId' => $device['displayId']])[0];
                $cmsDisplay->rdmDeviceId = $device['rdmDeviceId'];
                $cmsDisplay->save();
            }
        } catch (RequestException $e) {
            $this->getLogger()->error('getAvailableDevices: e = ' . $e->getMessage());

            if ($e->getResponse()->getStatusCode() === 401) {
                $this->formError = __('Invalid Connector Key');

                throw new InvalidArgumentException($this->formError, 'cmsPsk');
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('getAvailableDevices: e = ' . $e->getMessage());

            $this->formError = __('Cannot contact the Xibo Portal, please try again shortly.');

            throw new GeneralException($this->formError);
        }

        return $devices ?? [];
    }

    /**
     * Manual match - get the list of RDM devices from the Portal
     * @param SanitizerInterface $params
     * @return array|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getRdmDevices(SanitizerInterface $params, $enableSearch = true): ?array
    {
        $cmsPsk = $this->getSetting('cmsPsk');

        if (empty($cmsPsk)) {
            throw new InvalidArgumentException(__('Connector Key cannot be empty'), 'cmsPsk');
        }

        $this->getLogger()->info('getRdmDevices: Requesting available devices.');

        try {
            $query = [
                'cmsConnected' => $params->getInt('cmsConnected'),
            ];

            if ($enableSearch) {
                $searchParams = [
                    'deviceName' => $params->getString('deviceName'),
                    'id' => $params->getInt('id'),
                    'type' => $params->getString('type'),
                ];

                $query = array_merge($query, $searchParams);
            }

            $response = $this->getClient()->get($this->getServiceUrl() . '/rdm', [
                'headers' => [
                    'X-CMS-PSK-KEY' => $cmsPsk
                ],
                'query' => $query
            ]);

            $this->devices = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->getLogger()->error('getRdmDevices: e = ' . $e->getMessage());

            if ($e->getResponse()->getStatusCode() === 401) {
                $this->formError = __('Invalid Connector Key');

                throw new InvalidArgumentException($this->formError, 'cmsPsk');
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('getRdmDevices: e = ' . $e->getMessage());

            $this->formError = __('Cannot contact the Xibo Portal, please try again shortly.');

            throw new GeneralException($this->formError);
        }

        return $this->devices ?? [];
    }

    /**
     * Manual match between CMS display and Portal device
     * @param SanitizerInterface $params
     * @throws GeneralException
     * @throws GuzzleException
     */
    public function setRdmDevice(SanitizerInterface $params): void
    {
        try {
            $this->getLogger()->info('setRdmDevice: ' . json_encode($params));

            $display = [
                'displayId' => $params->getInt('displayId'),
                'rdmDeviceId' => $params->getInt('rdmDeviceId'),
            ];

            $response = $this->getClient()->post($this->getServiceUrl() . '/rdm/cms/connect/manual', [
                'headers' => [
                    'X-CMS-PSK-KEY' => $this->getSetting('cmsPsk')
                ],
                'json' => [
                    'display' => $display
                ]
            ]);

            if ($response->getStatusCode() === 204) {
                // Save the rdmDeviceId of the displays
                $cmsDisplay = $this->displayFactory->query(null, ['displayId' => $display['displayId']])[0];
                $cmsDisplay->rdmDeviceId = $display['rdmDeviceId'];
                $cmsDisplay->save();

                $this->getLogger()->info('setRdmDevice: Linked device ID ' . $display['displayId'] .
                    ' with display ID ' . $display['rdmDeviceId']);
            }
        } catch (RequestException $e) {
            $this->getLogger()->error('setDevices: e = ' . $e->getMessage());
            $message = json_decode($e->getResponse()->getBody()->getContents(), true);

            throw new GeneralException(empty($message)
                ? __('Cannot contact the Xibo Portal, please try again shortly.')
                : $message['message']);
        } catch (\Exception $e) {
            $this->getLogger()->error('setDevices: e = ' . $e->getMessage());
            throw new GeneralException(__('Cannot contact the Xibo Portal, please try again shortly.'));
        }
    }

    /**
     * Get the list of saved RDM displays in CMS for auto-connection
     * @throws NotFoundException
     */
    public function getRdmDisplays(): array
    {
        $displays = array_map(function ($display) {
            return [
                'displayId' => $display->displayId,
                'macAddress' => $display->macAddress,
            ];
        }, $this->displayFactory->query(null, ['cmsConnected' => 0]));

        $this->displays = $displays;

        $this->getLogger()->info('getRdmDisplays: ' . json_encode($displays));

        return $this->displays;
    }

    /**
     * Get linked displays and devices
     * @param SanitizerInterface $params
     * @return array
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getDisplaysAndDevices(SanitizerInterface $params): array
    {
        $source = $params->getString('sourceFilter');
        $cmsDisplays = [];
        $devices = [];

        // Fetch CMS displays or devices based on the source
        if ($source === 'cmsDisplay') {
            $cmsDisplays = $this->displayFactory->query(null, [
                'cmsConnected' => 1,
                'displayId' => $params->getInt('displayId'),
                'display' => $params->getString('display'),
                'displayType' => $params->getString('type')
            ]);

            $this->getLogger()->info('getDisplaysAndDevices: CMS Displays - ' . json_encode($cmsDisplays));

            // Return immediately if no CMS displays found
            if (empty($cmsDisplays)) {
                return [
                    'data' => [],
                    'draw' => 0,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0
                ];
            }

            $devices = $this->getRdmDevices($params, false);
        } else {
            $devices = $this->getRdmDevices($params);

            $this->getLogger()->info('getDisplaysAndDevices: Devices - ' . json_encode($devices));

            // Return immediately if no devices found
            if (empty($devices)) {
                return $devices;
            }

            $cmsDisplays = $this->displayFactory->query(null, ['cmsConnected' => 1]);
        }

        // Prepare a mapping of CMS displays for quick lookup
        $cmsDisplayMap = [];

        foreach ($cmsDisplays as $cmsDisplay) {
            $cmsDisplayMap[$cmsDisplay->displayId] = $cmsDisplay;
        }

        // Process and decorate devices with CMS display data
        foreach ($devices['data'] as &$device) {
            $matchingCmsDisplay = $cmsDisplayMap[$device['cmsDisplayId']] ?? null;

            $device['display'] = $matchingCmsDisplay->display ?? '';
            $device['manufacturer'] = $matchingCmsDisplay->manufacturer ?? '';

            $this->getLogger()->info('getDisplaysAndDevices: Processed Device - ' . json_encode($device));
        }

        return $devices;
    }

    /**
     * Send RDM command
     * @param $deviceId
     * @param $command
     * @param $params
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function sendRdmCommand($deviceId, $command, $params)
    {
        if (isset($params['commandId'])) {
            unset($params['commandId']);
        }

        $url = $this->getServiceUrl() . '/rdm/' . $deviceId . '/' . strtolower($command);

        try {
            $response = $this->getClient()->post($url, [
                'headers' => [
                    'X-CMS-PSK-KEY' => $this->getSetting('cmsPsk')
                ],
                'json' => $params
            ]);

            $status = $response->getStatusCode();
            $body   = $response->getBody();

            $this->getLogger()->info('sendRdmCommand: ' . $command . ': ' . json_encode($params));

            if ($status >= 400) {
                throw new GeneralException('sendRdmCommand: ' . $status . ': ' . $body);
            }
        } catch (RequestException $e) {
            $this->getLogger()->error('sendRdmCommand: e = ' . $e->getMessage());

            if ($e->getResponse()->getStatusCode() === 401) {
                $this->formError = __('Invalid Connector Key');

                throw new InvalidArgumentException($this->formError, 'cmsPsk');
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('sendRdmCommand: e = ' . $e->getMessage());

            $this->formError = __('Cannot contact the Xibo Portal, please try again shortly.');

            throw new GeneralException($this->formError);
        }
    }

    /**
     * Get the current device screen ON/OFF settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getScreen(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'screen');
    }

    /**
     * Get the current device screen rotation settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getRotation(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'rotate');
    }

    /**
     * Get the current device mute settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getMute(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'mute');
    }

    /**
     * Get the current device pro-mode settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getProMode(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'pro-mode');
    }

    /**
     * Get the current device brightness settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getBrightness(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'brightness');
    }

    /**
     * Get the current device power schedule settings
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getPowerSchedule(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'power-schedule');
    }

    /**
     * Get screenshot
     * @param int $deviceId
     * @return mixed|null
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function getScreenshot(int $deviceId)
    {
        return $this->fetchSetting($deviceId, 'screenshot');
    }

    /**
     * Helper function to fetch the device settings
     * @param int $deviceId
     * @param string $setting
     * @param null $defaultValue
     * @return mixed|void
     * @throws GeneralException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function fetchSetting(int $deviceId, string $setting, $defaultValue = null)
    {
        $cmsPsk = $this->getSetting('cmsPsk');

        if (empty($cmsPsk)) {
            throw new InvalidArgumentException(__('Connector Key cannot be empty'), 'cmsPsk');
        }

        $this->getLogger()->info('getSetting: Requesting ' . $setting . ' setting.');

        try {
            $response = $this->getClient()->get(
                $this->getServiceUrl() . '/rdm/{$deviceId}/{$setting}',
                [
                    'headers' => [
                        'X-CMS-PSK-KEY' => $cmsPsk
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['value'] ?? $defaultValue;
        } catch (RequestException $e) {
            $this->getLogger()->error('getSetting: e = ' . $e->getMessage());

            if ($e->getResponse()->getStatusCode() === 401) {
                $this->formError = __('Invalid Connector Key');

                throw new InvalidArgumentException($this->formError, 'cmsPsk');
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('getSetting: e = ' . $e->getMessage());

            $this->formError = __('Cannot contact the Xibo Portal, please try again shortly.');

            throw new GeneralException($this->formError);
        }
    }

    /**
     * Get the service url
     * @return string
     */
    private function getServiceUrl(): string
    {
        return $this->getSetting('serviceUrl', 'https://xibosignage.com');
    }

    /**
     * @param RdmConnectorSendCommandEvent $event
     * @param $name
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public function onSendCommand(RdmConnectorSendCommandEvent $event, $name, EventDispatcherInterface $dispatcher)
    {
        $this->sendRdmCommand($event->getDisplayId(), $event->getCommand(), $event->getParams());
    }
}
