<?php
/*
 * Copyright (c) 2023  Xibo Signage Ltd
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
 *
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
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Xmds\Wsdl;

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
            $services = $services['serviceType'] ?? [];

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
        // Always generate a token
        $tokenEvent = new XmdsConnectorTokenEvent();
        $tokenEvent->setTargets($event->getDataProvider()->getDisplayId(), $event->getDataProvider()->getWidgetId());
        $tokenEvent->setTtl(3600 * 24 * 2);
        $dispatcher->dispatch($tokenEvent, XmdsConnectorTokenEvent::$NAME);
        $token = $tokenEvent->getToken();

        if (empty($token)) {
            throw new ConfigurationException(__('No token returned'));
        }

        if ($event->getDataProvider()->isPreview()) {
            $url = $this->getLayoutPreviewUrl($token);
        } else {
            // This is fallback HTML for the player.
            // so output a link to the XMDS file request.
            $url = Wsdl::getRoot() . '?connector=true&token=' . $token;
        }

        $item = [];
        $item['url'] = $url;
        $item['token'] = $token;
        $item['isPreview'] = $event->getDataProvider()->isPreview();
        $item['spinner'] = $this->getSpinner();

        $event->getDataProvider()->addItem($item);
    }

    private function getSpinner(): string
    {
        return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQy
        AiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPgo8c3ZnIH
        dpZHRoPSI0MHB4IiBoZWlnaHQ9IjQwcHgiIHZpZXdCb3g9IjAgMCA0MCA0MCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcm
        cvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4bWw6c3BhY2U9InByZXNlcnZlIiBzdHlsZT0iZm
        lsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7c3Ryb2tlLWxpbmVqb2luOnJvdW5kO3N0cm9rZS1taXRlcmxpbWl0OjEuNDE0MjE7Ii
        B4PSIwcHgiIHk9IjBweCI+CiAgICA8ZGVmcz4KICAgICAgICA8c3R5bGUgdHlwZT0idGV4dC9jc3MiPjwhW0NEQVRBWwogICAgICAgICAgICBALX
        dlYmtpdC1rZXlmcmFtZXMgc3BpbiB7CiAgICAgICAgICAgICAgZnJvbSB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybTogcm90YX
        RlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKICAgICAgICAgICAgICAgIC13ZWJraXQtdHJhbnNmb3JtOiByb3RhdG
        UoLTM1OWRlZykKICAgICAgICAgICAgICB9CiAgICAgICAgICAgIH0KICAgICAgICAgICAgQGtleWZyYW1lcyBzcGluIHsKICAgICAgICAgICAgIC
        Bmcm9tIHsKICAgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKDBkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICAgIHRvIHsKIC
        AgICAgICAgICAgICAgIHRyYW5zZm9ybTogcm90YXRlKC0zNTlkZWcpCiAgICAgICAgICAgICAgfQogICAgICAgICAgICB9CiAgICAgICAgICAgIH
        N2ZyB7CiAgICAgICAgICAgICAgICAtd2Via2l0LXRyYW5zZm9ybS1vcmlnaW46IDUwJSA1MCU7CiAgICAgICAgICAgICAgICAtd2Via2l0LWFuaW
        1hdGlvbjogc3BpbiAxLjVzIGxpbmVhciBpbmZpbml0ZTsKICAgICAgICAgICAgICAgIC13ZWJraXQtYmFja2ZhY2UtdmlzaWJpbGl0eTogaGlkZG
        VuOwogICAgICAgICAgICAgICAgYW5pbWF0aW9uOiBzcGluIDEuNXMgbGluZWFyIGluZmluaXRlOwogICAgICAgICAgICB9CiAgICAgICAgXV0+PC
        9zdHlsZT4KICAgIDwvZGVmcz4KICAgIDxnIGlkPSJvdXRlciI+CiAgICAgICAgPGc+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0yMCwwQzIyLjIwNT
        gsMCAyMy45OTM5LDEuNzg4MTMgMjMuOTkzOSwzLjk5MzlDMjMuOTkzOSw2LjE5OTY4IDIyLjIwNTgsNy45ODc4MSAyMCw3Ljk4NzgxQzE3Ljc5ND
        IsNy45ODc4MSAxNi4wMDYxLDYuMTk5NjggMTYuMDA2MSwzLjk5MzlDMTYuMDA2MSwxLjc4ODEzIDE3Ljc5NDIsMCAyMCwwWiIgc3R5bGU9ImZpbG
        w6YmxhY2s7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNNS44NTc4Niw1Ljg1Nzg2QzcuNDE3NTgsNC
        4yOTgxNSA5Ljk0NjM4LDQuMjk4MTUgMTEuNTA2MSw1Ljg1Nzg2QzEzLjA2NTgsNy40MTc1OCAxMy4wNjU4LDkuOTQ2MzggMTEuNTA2MSwxMS41MD
        YxQzkuOTQ2MzgsMTMuMDY1OCA3LjQxNzU4LDEzLjA2NTggNS44NTc4NiwxMS41MDYxQzQuMjk4MTUsOS45NDYzOCA0LjI5ODE1LDcuNDE3NTggNS
        44NTc4Niw1Ljg1Nzg2WiIgc3R5bGU9ImZpbGw6cmdiKDIxMCwyMTAsMjEwKTsiLz4KICAgICAgICA8L2c+CiAgICAgICAgPGc+CiAgICAgICAgIC
        AgIDxwYXRoIGQ9Ik0yMCwzMi4wMTIyQzIyLjIwNTgsMzIuMDEyMiAyMy45OTM5LDMzLjgwMDMgMjMuOTkzOSwzNi4wMDYxQzIzLjk5MzksMzguMj
        ExOSAyMi4yMDU4LDQwIDIwLDQwQzE3Ljc5NDIsNDAgMTYuMDA2MSwzOC4yMTE5IDE2LjAwNjEsMzYuMDA2MUMxNi4wMDYxLDMzLjgwMDMgMTcuNz
        k0MiwzMi4wMTIyIDIwLDMyLjAxMjJaIiBzdHlsZT0iZmlsbDpyZ2IoMTMwLDEzMCwxMzApOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KIC
        AgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksMjguNDkzOUMzMC4wNTM2LDI2LjkzNDIgMzIuNTgyNCwyNi45MzQyIDM0LjE0MjEsMjguNDkzOU
        MzNS43MDE5LDMwLjA1MzYgMzUuNzAxOSwzMi41ODI0IDM0LjE0MjEsMzQuMTQyMUMzMi41ODI0LDM1LjcwMTkgMzAuMDUzNiwzNS43MDE5IDI4Lj
        Q5MzksMzQuMTQyMUMyNi45MzQyLDMyLjU4MjQgMjYuOTM0MiwzMC4wNTM2IDI4LjQ5MzksMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxMDEsMT
        AxLDEwMSk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogICAgICAgICAgICA8cGF0aCBkPSJNMy45OTM5LDE2LjAwNjFDNi4xOTk2OCwxNi
        4wMDYxIDcuOTg3ODEsMTcuNzk0MiA3Ljk4NzgxLDIwQzcuOTg3ODEsMjIuMjA1OCA2LjE5OTY4LDIzLjk5MzkgMy45OTM5LDIzLjk5MzlDMS43OD
        gxMywyMy45OTM5IDAsMjIuMjA1OCAwLDIwQzAsMTcuNzk0MiAxLjc4ODEzLDE2LjAwNjEgMy45OTM5LDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2
        IoMTg3LDE4NywxODcpOyIvPgogICAgICAgIDwvZz4KICAgICAgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTUuODU3ODYsMjguNDkzOUM3Lj
        QxNzU4LDI2LjkzNDIgOS45NDYzOCwyNi45MzQyIDExLjUwNjEsMjguNDkzOUMxMy4wNjU4LDMwLjA1MzYgMTMuMDY1OCwzMi41ODI0IDExLjUwNj
        EsMzQuMTQyMUM5Ljk0NjM4LDM1LjcwMTkgNy40MTc1OCwzNS43MDE5IDUuODU3ODYsMzQuMTQyMUM0LjI5ODE1LDMyLjU4MjQgNC4yOTgxNSwzMC
        4wNTM2IDUuODU3ODYsMjguNDkzOVoiIHN0eWxlPSJmaWxsOnJnYigxNjQsMTY0LDE2NCk7Ii8+CiAgICAgICAgPC9nPgogICAgICAgIDxnPgogIC
        AgICAgICAgICA8cGF0aCBkPSJNMzYuMDA2MSwxNi4wMDYxQzM4LjIxMTksMTYuMDA2MSA0MCwxNy43OTQyIDQwLDIwQzQwLDIyLjIwNTggMzguMj
        ExOSwyMy45OTM5IDM2LjAwNjEsMjMuOTkzOUMzMy44MDAzLDIzLjk5MzkgMzIuMDEyMiwyMi4yMDU4IDMyLjAxMjIsMjBDMzIuMDEyMiwxNy43OT
        QyIDMzLjgwMDMsMTYuMDA2MSAzNi4wMDYxLDE2LjAwNjFaIiBzdHlsZT0iZmlsbDpyZ2IoNzQsNzQsNzQpOyIvPgogICAgICAgIDwvZz4KICAgIC
        AgICA8Zz4KICAgICAgICAgICAgPHBhdGggZD0iTTI4LjQ5MzksNS44NTc4NkMzMC4wNTM2LDQuMjk4MTUgMzIuNTgyNCw0LjI5ODE1IDM0LjE0Mj
        EsNS44NTc4NkMzNS43MDE5LDcuNDE3NTggMzUuNzAxOSw5Ljk0NjM4IDM0LjE0MjEsMTEuNTA2MUMzMi41ODI0LDEzLjA2NTggMzAuMDUzNiwxMy
        4wNjU4IDI4LjQ5MzksMTEuNTA2MUMyNi45MzQyLDkuOTQ2MzggMjYuOTM0Miw3LjQxNzU4IDI4LjQ5MzksNS44NTc4NloiIHN0eWxlPSJmaWxsOn
        JnYig1MCw1MCw1MCk7Ii8+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4K';
    }
}
