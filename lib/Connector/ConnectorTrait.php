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

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\PoolInterface;
use Xibo\Service\JwtServiceInterface;

/**
 * Connector trait to assist with basic scaffolding and utility methods.
 *  we recommend all connectors use this trait.
 */
trait ConnectorTrait
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var array */
    private $settings = [];

    /** @var array The keys for all provider settings */
    private $providerSettings = [];

    /** @var PoolInterface|null */
    private $pool;

    /** @var array */
    private $httpOptions = [];

    /** @var array */
    private $keys = [];

    /** @var JwtServiceInterface */
    private $jwtService;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function useLogger(LoggerInterface $logger): ConnectorInterface
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return \Psr\Log\LoggerInterface|\Psr\Log\NullLogger
     */
    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            return new NullLogger();
        }
        return $this->logger;
    }

    /**
     * @param array $settings
     * @param bool $provider
     * @return ConnectorInterface
     */
    public function useSettings(array $settings, bool $provider = false): ConnectorInterface
    {
        if ($provider) {
            $this->providerSettings = array_keys($settings);
        }

        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    /**
     * @param $setting
     * @return bool
     */
    public function isProviderSetting($setting): bool
    {
        return in_array($setting, $this->providerSettings);
    }

    /**
     * @param $setting
     * @param null $default
     * @return string|null
     */
    public function getSetting($setting, $default = null)
    {
        $this->logger->debug('getSetting: ' . $setting);
        if (!array_key_exists($setting, $this->settings)) {
            $this->logger->debug('getSetting: ' . $setting . ' not present.');
            return $default;
        }

        return $this->settings[$setting] ?: $default;
    }

    /**
     * @param \Stash\Interfaces\PoolInterface $pool
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function usePool(PoolInterface $pool): ConnectorInterface
    {
        $this->pool = $pool;
        return $this;
    }

    /**
     * @return \Stash\Interfaces\PoolInterface
     */
    private function getPool(): PoolInterface
    {
        return $this->pool;
    }

    /**
     * @param array $options
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function useHttpOptions(array $options): ConnectorInterface
    {
        $this->httpOptions = $options;
        return $this;
    }

    public function useJwtService(JwtServiceInterface $jwtService): ConnectorInterface
    {
        $this->jwtService = $jwtService;
        return $this;
    }

    protected function getJwtService(): JwtServiceInterface
    {
        return $this->jwtService;
    }

    public function setFactories($container): ConnectorInterface
    {
        return $this;
    }

    public function getSettingsFormJavaScript(): string
    {
        return '';
    }

    /**
     * Get an HTTP client with the default proxy settings, etc
     * @return \GuzzleHttp\Client
     */
    public function getClient(): Client
    {
        return new Client($this->httpOptions);
    }
}
