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

    /** @var string */
    private $formError;

    /** @var array */
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

    public function getFormError(): string
    {
        return $this->formError ?? __('Unknown error');
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        $existingApiKey = $this->getSetting('apiKey');
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }

        // Set partners.
        $partners = [];
        $available = $this->getAvailablePartners(false, $settings['apiKey']);

        // Pull in expected fields.
        foreach ($available as $partner) {
            $partners[] = [
                'name' => $params->getString($partner['name'] . '_name'),
                'enabled' => $params->getCheckbox($partner['name'] . '_enabled'),
                'currency' => $params->getString($partner['name'] . '_currency'),
                'key' => $params->getString($partner['name'] . '_key'),
                'sov' => $params->getInt($partner['name'] . '_sov'),
                'mediaTypesAllowed' => $params->getString($partner['name'] . '_mediaTypesAllowed'),
                'duration' => $params->getInt($partner['name'] . '_duration'),
                'minDuration' => $params->getInt($partner['name'] . '_minDuration'),
                'maxDuration' => $params->getInt($partner['name'] . '_maxDuration'),
            ];
        }

        // Update API config.
        $this->setPartners($settings['apiKey'], $partners);

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
                    return null;
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
                    $this->formError = empty($message)
                        ? __('Cannot contact SSP service, please try again shortly.')
                        : $message['message'];

                    throw new GeneralException($this->formError);
                } else {
                    return null;
                }
            } catch (\Exception $e) {
                $this->getLogger()->error('getAvailableServices: e = ' . $e->getMessage());

                $this->formError = __('Cannot contact SSP service, please try again shortly.');
                if ($isThrowError) {
                    throw new GeneralException($this->formError);
                } else {
                    return null;
                }
            }
        }

        return $this->partners['available'] ?? [];
    }

    /**
     * Get a setting for a partner
     * @param string $partnerKey
     * @param string $setting
     * @param $default
     * @return mixed|string|null
     */
    public function getPartnerSetting(string $partnerKey, string $setting, $default = null)
    {
        if (!is_array($this->partners) || !array_key_exists('partners', $this->partners)) {
            return $default;
        }

        foreach ($this->partners['partners'] as $partner) {
            if ($partner['name'] === $partnerKey) {
                return $partner[$setting] ?? $default;
            }
        }

        return $default;
    }

    /**
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function setPartners(string $apiKey, array $partners)
    {
        $this->getLogger()->debug('setPartners: updating');

        try {
            $this->getClient()->post($this->getServiceUrl() . '/configure', [
                'headers' => [
                    'X-API-KEY' => $apiKey
                ],
                'json' => [
                    'partners' => $partners
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
