<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use GuzzleHttp\Exception\RequestException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\DashboardDataRequestEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Event\WidgetEditOptionRequestEvent;
use Xibo\Event\XmdsConnectorFileEvent;
use Xibo\Event\XmdsConnectorTokenEvent;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Xibo Dashboard Service connector.
 *   This connector collects credentials and sends them off to the dashboard service
 */
class XiboDashboardConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /** @var float|int The token TTL */
    const TOKEN_TTL_SECONDS = 3600 * 24 * 2;

    /** @var string Used when rendering the form */
    private $errorMessage;

    /** @var array Cache of available services */
    private $availableServices = null;

    /** @var string Cache key for credential states */
    private $cacheKey = 'connector/xibo_dashboard_connector_statuses';

    /** @var array Cache of error types */
    private $cachedErrorTypes = null;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(MaintenanceRegularEvent::$NAME, [$this, 'onRegularMaintenance']);
        $dispatcher->addListener(XmdsConnectorFileEvent::$NAME, [$this, 'onXmdsFile']);
        $dispatcher->addListener(XmdsConnectorTokenEvent::$NAME, [$this, 'onXmdsToken']);
        $dispatcher->addListener(WidgetEditOptionRequestEvent::$NAME, [$this, 'onWidgetEditOption']);
        $dispatcher->addListener(DashboardDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'xibo-dashboard-connector';
    }

    public function getTitle(): string
    {
        return 'Xibo Dashboard Service';
    }

    public function getDescription(): string
    {
        return 'Add your dashboard credentials for use in the Dashboard widget.';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/xibo-dashboards.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'xibo-dashboard-form-settings';
    }

    /**
     * Get the service url, either from settings or a default
     * @return string
     */
    public function getServiceUrl(): string
    {
        return $this->getSetting('serviceUrl', 'https://api.dashboards.xibosignage.com');
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        // Remember the old service URL
        $existingApiKey = $this->getSetting('apiKey');

        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
        }

        // What if the user changes their API key?
        // Handle existing credentials
        if ($existingApiKey !== $settings['apiKey']) {
            // Test the new API key.
            $services = $this->getAvailableServices(true, $settings['apiKey']);
            if (!is_array($services)) {
                throw new InvalidArgumentException($services);
            }

            // The new key is valid, clear out the old key's credentials.
            if (!empty($existingApiKey)) {
                foreach ($this->getCredentials() as $type => $credential) {
                    try {
                        $this->getClient()->delete(
                            $this->getServiceUrl() . '/services/' . $type . '/' . $credential['id'],
                            [
                                'headers' => [
                                    'X-API-KEY' => $existingApiKey
                                ]
                            ]
                        );
                    } catch (RequestException $requestException) {
                        $this->getLogger()->error('getAvailableServices: delete failed. e = '
                            . $requestException->getMessage());
                    }
                }
            }
            $credentials = [];
        } else {
            $credentials = $this->getCredentials();
        }

        $this->getLogger()->debug('Processing credentials');

        foreach ($this->getAvailableServices(false, $settings['apiKey']) as $service) {
            // Pull in the parameters for this service.
            $id = $params->getString($service['type'] . '_id');
            $isMarkedForRemoval = $params->getCheckbox($service['type'] . '_remove') == 1;

            if (empty($id)) {
                $userName = $params->getString($service['type'] . '_userName');
            } else {
                $userName = $credentials[$service['type']]['userName'] ?? null;

                // This shouldn't happen because we had it when the form opened.
                if ($userName === null) {
                    $isMarkedForRemoval = true;
                }
            }
            $password = $params->getParam($service['type'] . '_password');
            $twoFactorSecret = $params->getString($service['type'] . '_twoFactorSecret');
            $isUrl = isset($service['isUrl']);
            $url = ($isUrl) ? $params->getString($service['type' ]. '_url') : '';

            if (!empty($id) && $isMarkedForRemoval) {
                // Existing credential marked for removal
                try {
                    $this->getClient()->delete($this->getServiceUrl() . '/services/' . $service['type'] . '/' . $id, [
                        'headers' => [
                            'X-API-KEY' => $this->getSetting('apiKey')
                        ]
                    ]);
                } catch (RequestException $requestException) {
                    $this->getLogger()->error('getAvailableServices: delete failed. e = '
                        . $requestException->getMessage());
                }
                unset($credentials[$service['type']]);
            } else if (!empty($userName) && !empty($password)) {
                // A new service or an existing service with a changed password.
                // Make a request to our service URL.
                try {
                    $response = $this->getClient()->post(
                        $this->getServiceUrl() . '/services/' . $service['type'],
                        [
                            'headers' => [
                                'X-API-KEY' => $this->getSetting('apiKey')
                            ],
                            'json' => [
                                'username' => $userName,
                                'password' => $password,
                                'totp' => $twoFactorSecret,
                                'url' => $url
                            ],
                            'timeout' => 120
                        ]
                    );

                    $json = json_decode($response->getBody()->getContents(), true);
                    if (empty($json)) {
                        throw new InvalidArgumentException(__('Empty response from the dashboard service'), $service['type']);
                    }
                    $credentialId = $json['id'];

                    $credentials[$service['type']] = [
                        'userName' => $userName,
                        'id' => $credentialId,
                        'status' => true
                    ];
                } catch (RequestException $requestException) {
                    $this->getLogger()->error('getAvailableServices: e = ' . $requestException->getMessage());
                    throw new InvalidArgumentException(__('Cannot register those credentials.'), $service['type']);
                }
            }
        }

        // Set the credentials
        $settings['credentials'] = $credentials;
        return $settings;
    }

    public function getCredentialForType(string $type)
    {
        return $this->settings['credentials'][$type] ?? null;
    }

    public function getCredentials(): array
    {
        return $this->settings['credentials'] ?? [];
    }

    /**
     * Used by the Twig template
     * @param string $type
     * @return bool
     */
    public function isCredentialInErrorState(string $type): bool
    {
        if ($this->cachedErrorTypes === null) {
            $item = $this->getPool()->getItem($this->cacheKey);
            if ($item->isHit()) {
                $this->cachedErrorTypes = $item->get();
            } else {
                $this->cachedErrorTypes = [];
            }
        }

        return in_array($type, $this->cachedErrorTypes);
    }

    /**
     * @return array|mixed|string|null
     */
    public function getAvailableServices(bool $isReturnError = true, ?string $withApiKey = null)
    {
        if ($withApiKey) {
            $apiKey = $withApiKey;
        } else {
            $apiKey = $this->getSetting('apiKey');
            if (empty($apiKey)) {
                return [];
            }
        }

        if ($this->availableServices === null) {
            $this->getLogger()->debug('getAvailableServices: Requesting available services.');
            try {
                $response = $this->getClient()->get($this->getServiceUrl() . '/services', [
                    'headers' => [
                        'X-API-KEY' => $apiKey
                    ]
                ]);
                $body = $response->getBody()->getContents();

                $this->getLogger()->debug('getAvailableServices: ' . $body);

                $json = json_decode($body, true);
                if (empty($json)) {
                    throw new InvalidArgumentException(__('Empty response from the dashboard service'));
                }

                $this->availableServices = $json;
            } catch (RequestException $e) {
                $this->getLogger()->error('getAvailableServices: e = ' . $e->getMessage());
                $message = json_decode($e->getResponse()->getBody()->getContents(), true);

                if ($isReturnError) {
                    return empty($message)
                        ? __('Cannot contact dashboard service, please try again shortly.')
                        : $message['message'];
                } else {
                    return [];
                }
            } catch (\Exception $e) {
                $this->getLogger()->error('getAvailableServices: e = ' . $e->getMessage());

                if ($isReturnError) {
                    return __('Cannot contact dashboard service, please try again shortly.');
                } else {
                    return [];
                }
            }
        }

        return $this->availableServices;
    }

    public function onRegularMaintenance(MaintenanceRegularEvent $event)
    {
        $this->getLogger()->debug('onRegularMaintenance');

        $credentials = $this->getCredentials();
        if (count($credentials) <= 0) {
            $this->getLogger()->debug('onRegularMaintenance: No credentials configured, nothing to do.');
            return;
        }

        $services = [];
        foreach ($credentials as $credential) {
            // Build up a request to ping the service.
            $services[] = $credential['id'];
        }

        try {
            $response = $this->getClient()->post(
                $this->getServiceUrl() . '/services',
                [
                    'headers' => [
                        'X-API-KEY' => $this->getSetting('apiKey')
                    ],
                    'json' => $services
                ]
            );

            $body = $response->getBody()->getContents();
            if (empty($body)) {
                throw new NotFoundException('Empty response');
            }

            $json = json_decode($body, true);
            if (!is_array($json)) {
                throw new GeneralException('Invalid response body: ' . $body);
            }

            // Parse the response and activate/deactivate services accordingly.
            $erroredTypes = [];
            foreach ($credentials as $type => $credential) {
                // Get this service from the response.
                foreach ($json as $item) {
                    if ($item['id'] === $credential['id']) {
                        if ($item['status'] !== true) {
                            $this->getLogger()->error($type . ' credential is in error state');
                            $erroredTypes[] = $type;
                        }
                        continue 2;
                    }
                }
                $erroredTypes[] = $type;
                $this->getLogger()->error($type . ' credential is not present');
            }

            // Cache the errored types.
            if (count($erroredTypes) > 0) {
                $item = $this->getPool()->getItem($this->cacheKey);
                $item->set($erroredTypes);
                $item->expiresAfter(3600 * 4);
                $this->getPool()->save($item);
            } else {
                $this->getPool()->deleteItem($this->cacheKey);
            }
        } catch (\Exception $e) {
            $event->addMessage(__('Error calling Dashboard service'));
            $this->getLogger()->error('onRegularMaintenance: dashboard service e = ' . $e->getMessage());
        }
    }

    public function onXmdsToken(XmdsConnectorTokenEvent $event)
    {
        $this->getLogger()->debug('onXmdsToken');

        // We are either generating a new token, or verifying an old one.
        if (empty($event->getToken())) {
            $this->getLogger()->debug('onXmdsToken: empty token, generate a new one');

            // Generate a new token
            $token = $this->getJwtService()->generateJwt(
                $this->getTitle(),
                $this->getSourceName(),
                $event->getWidgetId(),
                $event->getDisplayId(),
                $event->getTtl()
            );

            $event->setToken($token->toString());
        } else {
            $this->getLogger()->debug('onXmdsToken: Validate the token weve been given');

            try {
                $token = $this->getJwtService()->validateJwt($event->getToken());
                if ($token === null) {
                    throw new NotFoundException(__('Cannot decode token'));
                }

                if ($this->getSourceName() === $token->claims()->get('aud')) {
                    $this->getLogger()->debug('onXmdsToken: Token not for this connector');
                    return;
                }

                // Configure the event with details from this token
                $displayId = intval($token->claims()->get('sub'));
                $widgetId = intval($token->claims()->get('jti'));
                $event->setTargets($displayId, $widgetId);

                $this->getLogger()->debug('onXmdsToken: Configured event with displayId: ' . $displayId
                    . ', widgetId: ' . $widgetId);
            } catch (\Exception $exception) {
                $this->getLogger()->error('onXmdsToken: Invalid token, e = ' . $exception->getMessage());
            }
        }
    }

    public function onXmdsFile(XmdsConnectorFileEvent $event)
    {
        $this->getLogger()->debug('onXmdsFile');

        try {
            // Get the widget
            $widget = $event->getWidget();
            if ($widget === null) {
                throw new NotFoundException();
            }

            // We want options, so load the widget
            $widget->load();

            $type = $widget->getOptionValue('type', 'powerbi');

            // Get the credentials for this type.
            $credentials = $this->getCredentialForType($type);
            if ($credentials === null) {
                throw new NotFoundException(sprintf(__('No credentials logged for %s'), $type));
            }

            // Add headers
            $headers = [
                'X-API-KEY' => $this->getSetting('apiKey')
            ];

            $response = $this->getClient()->get($this->getServiceUrl() . '/services/' . $type, [
                'headers' => $headers,
                'query' => [
                    'credentialId' => $credentials['id'],
                    'url' => $widget->getOptionValue('url', ''),
                    'interval' => $widget->getOptionValue('updateInterval', 60) * 60,
                    'debug' => $event->isDebug()
                ]
            ]);

            // Create a response
            $factory = new Psr17Factory();
            $event->setResponse(
                $factory->createResponse(200)
                    ->withHeader('Content-Type', $response->getHeader('Content-Type'))
                    ->withHeader('Cache-Control', $response->getHeader('Cache-Control'))
                    ->withHeader('Last-Modified', $response->getHeader('Last-Modified'))
                    ->withBody($response->getBody())
            );
        } catch (\Exception $exception) {
            // We log any error and return empty
            $this->getLogger()->error('onXmdsFile: unknown error: ' . $exception->getMessage());
        }
    }

    public function onWidgetEditOption(WidgetEditOptionRequestEvent $event)
    {
        $this->getLogger()->debug('onWidgetEditOption');

        // Pull the widget we're working with.
        $widget = $event->getWidget();
        if ($widget === null) {
            throw new NotFoundException();
        }

        // Pull in existing information
        $existingType = $event->getPropertyValue();
        $options = $event->getOptions();

        // We handle the dashboard widget and the property with id="type"
        if ($widget->type === 'dashboard' && $event->getPropertyId() === 'type') {
            // get available services
            $services = $this->getAvailableServices(true, $this->getSetting('apiKey'));

            foreach ($services as $option) {
                // Filter the list of options by the property value provided (if there is one).
                if (empty($existingType) || $option['type'] === $existingType) {
                    $options[] = $option;
                }
            }

            // Set these options on the event.
            $event->setOptions($options);
        }
    }

    public function onDataRequest(DashboardDataRequestEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->getLogger()->debug('onDataRequest');

        // Validate that we're configured.
        if (empty($this->getSetting('apiKey'))) {
            $event->getDataProvider()->addError(__('Dashboard Connector not configured'));
            return;
        }

        // Always generate a token
        try {
            $tokenEvent = new XmdsConnectorTokenEvent();
            $tokenEvent->setTargets($event->getDataProvider()->getDisplayId(), $event->getDataProvider()->getWidgetId());
            $tokenEvent->setTtl(self::TOKEN_TTL_SECONDS);
            $dispatcher->dispatch($tokenEvent, XmdsConnectorTokenEvent::$NAME);
            $token = $tokenEvent->getToken();

            if (empty($token)) {
                $event->getDataProvider()->addError(__('No token returned'));
                return;
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('onDataRequest: Failed to get token. e = ' . $e->getMessage());
            $event->getDataProvider()->addError(__('No token returned'));
            return;
        }

        // We return a single data item which contains our URL, token and whether we're a preview
        $item = [];
        $item['url'] = $this->getTokenUrl($token);
        $item['token'] = $token;
        $item['isPreview'] = $event->getDataProvider()->isPreview();

        // We make sure our data cache expires shortly before the token itself expires (so that we have a new token
        // generated for it).
        $event->getDataProvider()->setCacheTtl(self::TOKEN_TTL_SECONDS - 3600);

        // Add our item and set handled
        $event->getDataProvider()->addItem($item);
        $event->getDataProvider()->setIsHandled();
    }
}
