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
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Connector\ConnectorInterface;
use Xibo\Connector\ConnectorTrait;
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
        $settings['apiKey'] = $params->getString('apiKey');

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
                foreach ($this->getCredentials() as $credential) {
                    $this->getClient()->delete(
                        $this->getServiceUrl() . '/services/' . $credential['type'] . '/' . $credential['id'],
                        [
                            'headers' => [
                                'X-API-KEY' => $existingApiKey
                            ]
                        ]
                    );
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
            $password = $params->getString($service['type'] . '_password');
            $twoFactorSecret = $params->getString($service['type'] . '_twoFactorSecret');
            $isUrl = isset($service['isUrl']);
            $url = ($isUrl) ? $params->getString($service['type' ]. '_url') : '';

            if (!empty($id) && $isMarkedForRemoval) {
                // Existing credential marked for removal
                $this->getClient()->delete($this->getServiceUrl() . '/services/' . $service['type'] . '/' . $id, [
                    'headers' => [
                        'X-API-KEY' => $this->getSetting('apiKey')
                    ]
                ]);
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
            $this->getLogger()->debug('getAvailableServices: Requesting available services.' . $apiKey);
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
            // Generate a new token
            $token = $this->getJwtService()->generateJwt(
                $this->getTitle(),
                $this->getSourceName(),
                $event->getWidgetId(),
                $event->getDisplayId(),
                $event->getTtl()
            );

            $event->setToken($token);
        } else {
            // Validate the token we've been given
            try {
                $token = $this->getJwtService()->validateJwt($event->getToken());
                if ($token === null) {
                    throw new NotFoundException(__('Cannot decode token'));
                }

                if ($this->getSourceName() === $token->claims()->get('aud')) {
                    $this->getLogger()->debug('Token not for this connector');
                    return;
                }

                // Configure the event with details from this token
                $displayId = intval($token->claims()->get('sub'));
                $widgetId = intval($token->claims()->get('jti'));
                $event->setTargets($displayId, $widgetId);

                $this->getLogger()->debug('Configured event with displayId: ' . $displayId
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
        $widget = $event->getWidget();
        // get any options we have in this event
        $options = $event->getOptions();

        if ($widget === null) {
            throw new NotFoundException();
        }

        // only care about dashboard type Widgets here
        if ($widget->type === 'dashboard') {
            // get available services
            $services = $this->getAvailableServices(true, $this->getSetting('apiKey'));

            // add services to our options array and set options on the event.
            $options['serviceType'] = $services;
            $event->setOptions($options);
        }
    }
}
