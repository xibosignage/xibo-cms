<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\ConnectorDeletingEvent;
use Xibo\Event\ConnectorEnabledChangeEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class XiboSspConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var mixed */
    private $partners;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(MaintenanceRegularEvent::$NAME, [$this, 'onRegularMaintenance']);
        $dispatcher->addListener(ConnectorDeletingEvent::$NAME, [$this, 'onDeleting']);
        $dispatcher->addListener(ConnectorEnabledChangeEvent::$NAME, [$this, 'onEnabledChange']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'xibo-ssp-connector';
    }

    public function getTitle(): string
    {
        return 'Xibo SSP Connector';
    }

    public function getDescription(): string
    {
        return 'Connect to world leading Supply Side Platforms (SSPs) and monetise your network.';
    }

    public function getThumbnail(): string
    {
        return '';
    }

    public function getSettingsFormTwig(): string
    {
        return 'xibo-ssp-connector-form-settings';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        $existingApiKey = $this->getSetting('apiKey');
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }

        // Set partners.
        $this->getAvailablePartners(false, $settings['apiKey']);



        // Update API config.
        $this->setPartners($settings['apiKey']);

        // If the API key has changed during this request, clear out displays on the old API key
        if ($existingApiKey !== $settings['apiKey']) {
            // TODO: We should clear all displays for this CMS on the existing key
        }

        // Add displays on the new API key (maintenance also does this, but do it now).


        return $settings;
    }

    /**
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function getAvailablePartners(bool $isThrowError = false, ?string $withApiKey = null)
    {
        if ($this->partners === null) {
            // Make a call to the API to see what we've currently got configured and what is available.
            if ($withApiKey) {
                $apiKey = $withApiKey;
            } else {
                $apiKey = $this->getSetting('apiKey');
                if (empty($apiKey)) {
                    return [];
                }
            }

            $this->getLogger()->debug('getAvailablePartners: Requesting available services.');

            try {
                $response = $this->getClient()->get($this->getServiceUrl() . '/configure', [
                    'headers' => [
                        'X-API-KEY' => $apiKey
                    ]
                ]);
                $body = $response->getBody()->getContents();

                $this->getLogger()->debug('getAvailablePartners: ' . $body);

                $json = json_decode($body, true);
                if (empty($json)) {
                    throw new InvalidArgumentException(__('Empty response from the dashboard service'));
                }

                $this->partners = $json;
            } catch (RequestException $e) {
                $this->getLogger()->error('getAvailablePartners: e = ' . $e->getMessage());
                $message = json_decode($e->getResponse()->getBody()->getContents(), true);

                if ($isThrowError) {
                    throw new GeneralException(empty($message)
                        ? __('Cannot contact SSP service, please try again shortly.')
                        : $message['message']);
                } else {
                    return [];
                }
            } catch (\Exception $e) {
                $this->getLogger()->error('getAvailableServices: e = ' . $e->getMessage());

                if ($isThrowError) {
                    throw new GeneralException(__('Cannot contact SSP service, please try again shortly.'));
                } else {
                    return [];
                }
            }
        }

        return $this->partners;
    }

    /**
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function setPartners(string $apiKey)
    {
        $this->getLogger()->debug('setPartners: updating');

        try {
            $this->getClient()->post($this->getServiceUrl() . '/configure', [
                'headers' => [
                    'X-API-KEY' => $apiKey
                ],
                'json' => [
                    'partners' => $this->partners
                ]
            ]);
        } catch (RequestException $e) {
            $this->getLogger()->error('setPartners: e = ' . $e->getMessage());
            $message = json_decode($e->getResponse()->getBody()->getContents(), true);

            throw new GeneralException(empty($message)
                ? __('Cannot contact SSP service, please try again shortly.')
                : $message['message']);
        } catch (\Exception $e) {
            $this->getLogger()->error('setPartners: e = ' . $e->getMessage());
            throw new GeneralException(__('Cannot contact SSP service, please try again shortly.'));
        }
    }

    /**
     * Get the service url, either from settings or a default
     * @return string
     */
    private function getServiceUrl(): string
    {
        return $this->getSetting('serviceUrl', 'https://exchange.xibo-adspace.com/api');
    }

    // <editor-fold desc="Listeners">

    public function onRegularMaintenance(MaintenanceRegularEvent $event)
    {
        $this->getLogger()->debug('onRegularMaintenance');

        // TODO: send displays.

    }

    public function onDeleting(ConnectorDeletingEvent $event)
    {
        $this->getLogger()->debug('onDeleting');
        $event->getConfigService()->changeSetting('isAdspaceEnabled', 0);
    }

    public function onEnabledChange(ConnectorEnabledChangeEvent $event)
    {
        $this->getLogger()->debug('onEnabledChange');
        $event->getConfigService()->changeSetting('isAdspaceEnabled', $event->getConnector()->isEnabled);
    }

    // </editor-fold>
}
