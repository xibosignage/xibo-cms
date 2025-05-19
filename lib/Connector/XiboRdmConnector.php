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
        // TODO: Implement registerWithDispatcher() method.
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

        $this->getLogger()->debug('getAvailableDevices: Requesting available devices.');

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
    public function getRdmDevices(SanitizerInterface $params): ?array
    {
        $cmsPsk = $this->getSetting('cmsPsk');

        if (empty($cmsPsk)) {
            throw new InvalidArgumentException(__('Connector Key cannot be empty'), 'cmsPsk');
        }

        $this->getLogger()->debug('getRdmDevices: Requesting available devices.');

        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/rdm', [
                'headers' => [
                    'X-CMS-PSK-KEY' => $cmsPsk
                ],
                'query' => [
                    'cmsConnected' => $params->getInt('cmsConnected'),
                    'deviceName' => $params->getString('deviceName'),
                    'id' => $params->getInt('deviceId'),
                    'type' => $params->getString('deviceType'),
                ],
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
     * @return mixed
     * @throws GeneralException
     * @throws GuzzleException
     */
    public function setRdmDevice(SanitizerInterface $params): mixed
    {
        try {
            $display = [
                'displayId' => $params->getInt('displayId'),
                'rdmDeviceId' => $params->getString('rdmDeviceId'),
                'displayName' => $params->getString('displayName'),
                'macAddress' => $params->getString('macAddress')
            ];

            $response = $this->getClient()->post($this->getServiceUrl() . '/rdm/cms/connect/manual', [
                'headers' => [
                    'X-CMS-PSK-KEY' => $this->getSetting('cmsPsk')
                ],
                'query' => [
                    'display' => $display
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $device = json_decode($data, true);

            // Save the rdmDeviceId of the displays
            $cmsDisplay = $this->displayFactory->query(null, ['displayId' => $device['displayId']])[0];
            $cmsDisplay->rdmDeviceId = $device['rdmDeviceId'];
            $cmsDisplay->save();

            return $data;
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
     * Get the list of connected devices
     * @throws NotFoundException
     */
    public function getConnectedDevices(SanitizerInterface $params): array
    {

    }

    /**
     * Get the service url
     * @return string
     */
    private function getServiceUrl(): string
    {
        return $this->getSetting('serviceUrl', 'http://xibosignage.com');
    }
}
