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
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Event\WidgetEditOptionRequestEvent;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Xibo SSP Connector
 *  communicates with the Xibo Ad Exchange to register displays with connected SSPs and manage ad requests
 */
class XiboSspConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var string */
    private $formError;

    /** @var array */
    private $partners;

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

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
        $dispatcher->addListener(MaintenanceRegularEvent::$NAME, [$this, 'onRegularMaintenance']);
        $dispatcher->addListener(WidgetEditOptionRequestEvent::$NAME, [$this, 'onWidgetEditOption']);
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
        return 'theme/default/img/connectors/xibo-ssp.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'xibo-ssp-connector-form-settings';
    }

    public function getSettingsFormJavaScript(): string
    {
        return 'xibo-ssp-connector-form-javascript';
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

        $existingCmsUrl = $this->getSetting('cmsUrl');
        if (!$this->isProviderSetting('cmsUrl')) {
            $settings['cmsUrl'] = trim($params->getString('cmsUrl'), '/');

            if (empty($settings['cmsUrl']) || !Str::startsWith($settings['cmsUrl'], 'http')) {
                throw new InvalidArgumentException(
                    __('Please enter a CMS URL, including http(s)://'),
                    'cmsUrl'
                );
            }
        }

        // If our API key was empty, then do not set partners.
        if (empty($existingApiKey) || empty($settings['apiKey'])) {
            return $settings;
        }

        // Set partners.
        $partners = [];
        $available = $this->getAvailablePartners(true, $settings['apiKey']);

        // Pull in expected fields.
        foreach ($available as $partnerId => $partner) {
            $partners[] = [
                'name' => $partnerId,
                'enabled' => $params->getCheckbox($partnerId . '_enabled'),
                'isTest' => $params->getCheckbox($partnerId . '_isTest'),
                'isUseWidget' => $params->getCheckbox($partnerId . '_isUseWidget'),
                'currency' => $params->getString($partnerId . '_currency'),
                'key' => $params->getString($partnerId . '_key'),
                'sov' => $params->getInt($partnerId . '_sov'),
                'mediaTypesAllowed' => $params->getString($partnerId . '_mediaTypesAllowed'),
                'duration' => $params->getInt($partnerId . '_duration'),
                'minDuration' => $params->getInt($partnerId . '_minDuration'),
                'maxDuration' => $params->getInt($partnerId . '_maxDuration'),
            ];

            // Also grab the displayGroupId if one has been set.
            $displayGroupId = $params->getInt($partnerId . '_displayGroupId');
            if (empty($displayGroupId)) {
                unset($settings[$partnerId . '_displayGroupId']);
            } else {
                $settings[$partnerId . '_displayGroupId'] = $displayGroupId;
            }
            $settings[$partnerId . '_sspIdField'] = $params->getString($partnerId . '_sspIdField');
        }

        // Update API config.
        $this->setPartners($settings['apiKey'], $partners);

        try {
            // If the API key has changed during this request, clear out displays on the old API key
            if ($existingApiKey !== $settings['apiKey']) {
                // Clear all displays for this CMS on the existing key
                $this->setDisplays($existingApiKey, $existingCmsUrl, [], $settings);
            } else if (!empty($existingCmsUrl) && $existingCmsUrl !== $settings['cmsUrl']) {
                // Clear all displays for this CMS on the existing key
                $this->setDisplays($settings['apiKey'], $existingCmsUrl, [], $settings);
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('Failed to set displays '. $e->getMessage());
        }

        // Add displays on the new API key (maintenance also does this, but do it now).
        $this->setDisplays($settings['apiKey'], $settings['cmsUrl'], $partners, $settings);

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
                    $this->formError = __('Empty response from the dashboard service');
                    throw new InvalidArgumentException($this->formError);
                }

                $this->partners = $json;
            } catch (RequestException $e) {
                $this->getLogger()->error('getAvailablePartners: e = ' . $e->getMessage());

                if ($e->getResponse()->getStatusCode() === 401) {
                    $this->formError = __('API key not valid');
                    if ($isThrowError) {
                        throw new InvalidArgumentException($this->formError, 'apiKey');
                    } else {
                        return null;
                    }
                }

                $message = json_decode($e->getResponse()->getBody()->getContents(), true);

                $this->formError = empty($message)
                    ? __('Cannot contact SSP service, please try again shortly.')
                    : $message['message'];

                if ($isThrowError) {
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
     * Get the number of displays that are authorised by this API key.
     * @return int
     */
    public function getAuthorisedDisplayCount(): int
    {
        return intval($this->partners['displays'] ?? 0);
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
    private function setPartners(string $apiKey, array $partners)
    {
        $this->getLogger()->debug('setPartners: updating');
        $this->getLogger()->debug(json_encode($partners));

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
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function setDisplays(string $apiKey, string $cmsUrl, array $partners, array $settings)
    {
        $displays = [];
        foreach ($partners as $partner) {
            // If this partner is enabled?
            if (!$partner['enabled']) {
                continue;
            }

            // Get displays for this partner
            $partnerKey = $partner['name'];
            $sspIdField = $settings[$partnerKey . '_sspIdField'] ?? 'displayId';

            foreach ($this->displayFactory->query(null, [
                'disableUserCheck' => 1,
                'displayGroupId' => $settings[$partnerKey . '_displayGroupId'] ?? null,
                'authorised' => 1,
            ]) as $display) {
                if (!array_key_exists($display->displayId, $displays)) {
                    $resolution = explode('x', $display->resolution ?? '');
                    $displays[$display->displayId] = [
                        'displayId' => $display->displayId,
                        'hardwareKey' => $display->license,
                        'width' => trim($resolution[0] ?? 1920),
                        'height' => trim($resolution[1] ?? 1080),
                        'partners' => [],
                    ];
                }

                switch ($sspIdField) {
                    case 'customId':
                        $sspId = $display->customId;
                        break;

                    case 'ref1':
                        $sspId = $display->ref1;
                        break;

                    case 'ref2':
                        $sspId = $display->ref2;
                        break;

                    case 'ref3':
                        $sspId = $display->ref3;
                        break;

                    case 'ref4':
                        $sspId = $display->ref4;
                        break;

                    case 'ref5':
                        $sspId = $display->ref5;
                        break;

                    case 'displayId':
                    default:
                        $sspId = $display->displayId;
                }

                $displays[$display->displayId]['partners'][] = [
                    'name' => $partnerKey,
                    'sspId' => '' . $sspId,
                ];
            }
        }

        try {
            $this->getClient()->post($this->getServiceUrl() . '/displays', [
                'headers' => [
                    'X-API-KEY' => $apiKey,
                ],
                'json' => [
                    'cmsUrl' => $cmsUrl,
                    'displays' => array_values($displays),
                ],
            ]);
        } catch (RequestException $e) {
            $this->getLogger()->error('setDisplays: e = ' . $e->getMessage());
            $message = json_decode($e->getResponse()->getBody()->getContents(), true);

            throw new GeneralException(empty($message)
                ? __('Cannot contact SSP service, please try again shortly.')
                : $message['message']);
        } catch (\Exception $e) {
            $this->getLogger()->error('setDisplays: e = ' . $e->getMessage());
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

    // <editor-fold desc="Proxy methods">

    /**
     * Activity data
     */
    public function activity(SanitizerInterface $params): array
    {
        $fromDt = $params->getDate('activityFromDt', [
            'default' => Carbon::now()->startOfHour()
        ]);
        $toDt = $params->getDate('activityToDt', [
            'default' => $fromDt->addHour()
        ]);

        // Call the api (override the timeout)
        try {
            $response = $this->getClient()->get($this->getServiceUrl() . '/activity', [
                'timeout' => 120,
                'headers' => [
                    'X-API-KEY' => $this->getSetting('apiKey'),
                ],
                'query' => [
                    'cmsUrl' => $this->getSetting('cmsUrl'),
                    'fromDt' => $fromDt->toAtomString(),
                    'toDt' => $toDt->toAtomString(),
                    'displayId' => $params->getInt('displayId'),
                    'campaignId' => $params->getString('partnerId'),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body) {
                throw new GeneralException(__('No response'));
            }

            return $body;
        } catch (\Exception $e) {
            $this->getLogger()->error('activity: e = ' . $e->getMessage());
        }

        return [
            'data' => [],
            'recordsTotal' => 0,
        ];
    }

    /**
     * Available Partners
     */
    public function getAvailablePartnersFilter(SanitizerInterface $params): array
    {
        try {
            return $this->getAvailablePartners() ?? [];
        } catch (\Exception $e) {
            $this->getLogger()->error('activity: e = ' . $e->getMessage());
        }

        return [
            'data' => [],
            'recordsTotal' => 0,
        ];
    }
    // </editor-fold>

    // <editor-fold desc="Listeners">

    public function onRegularMaintenance(MaintenanceRegularEvent $event)
    {
        $this->getLogger()->debug('onRegularMaintenance');

        try {
            $this->getAvailablePartners();
            $partners = $this->partners['partners'] ?? [];

            if (count($partners) > 0) {
                $this->setDisplays(
                    $this->getSetting('apiKey'),
                    $this->getSetting('cmsUrl'),
                    $partners,
                    $this->settings
                );
            }

            $event->addMessage('SSP: done');
        } catch (\Exception $exception) {
            $this->getLogger()->error('SSP connector: ' . $exception->getMessage());
            $event->addMessage('Error processing SSP configuration.');
        }
    }

    /**
     * Connector is being deleted
     * @param \Xibo\Service\ConfigServiceInterface $configService
     * @return void
     */
    public function delete(ConfigServiceInterface $configService): void
    {
        $this->getLogger()->debug('delete');
        $configService->changeSetting('isAdspaceEnabled', 0);
    }

    /**
     * Connector is being enabled
     * @param \Xibo\Service\ConfigServiceInterface $configService
     * @return void
     */
    public function enable(ConfigServiceInterface $configService): void
    {
        $this->getLogger()->debug('enable');
        $configService->changeSetting('isAdspaceEnabled', 1);
    }

    /**
     * Connector is being disabled
     * @param \Xibo\Service\ConfigServiceInterface $configService
     * @return void
     */
    public function disable(ConfigServiceInterface $configService): void
    {
        $this->getLogger()->debug('disable');
        $configService->changeSetting('isAdspaceEnabled', 0);
    }

    public function onWidgetEditOption(WidgetEditOptionRequestEvent $event)
    {
        $this->getLogger()->debug('onWidgetEditOption');

        // Pull the widget we're working with.
        $widget = $event->getWidget();
        if ($widget === null) {
            throw new NotFoundException();
        }

        // We handle the dashboard widget and the property with id="type"
        if ($widget->type === 'ssp' && $event->getPropertyId() === 'partnerId') {
            // Pull in existing information
            $partnerFilter = $event->getPropertyValue();
            $options = $event->getOptions();

            foreach ($this->getAvailablePartners() as $partnerId => $partner) {
                if ((empty($partnerFilter) || $partnerId === $partnerFilter)
                    && $this->getPartnerSetting($partnerId, 'enabled') == 1
                ) {
                    $options[] = [
                        'id' => $partnerId,
                        'type' => $partnerId,
                        'name' => $partner['name'],
                    ];
                }
            }

            $event->setOptions($options);
        }
    }

    // </editor-fold>
}
