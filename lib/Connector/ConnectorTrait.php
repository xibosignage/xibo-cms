<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\PoolInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Connector trait to assit with basic scaffolding and utility methods.
 *  we recommend all connectors use this trait.
 */
trait ConnectorTrait
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var SanitizerInterface|null */
    private $settings;

    /** @var PoolInterface|null */
    private $pool;

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
    private function getLogger(): LoggerInterface {
        if ($this->logger === null) {
            return new NullLogger();
        }
        return $this->logger;
    }

    /**
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $settings
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function useSettings(SanitizerInterface $settings): ConnectorInterface
    {
        $this->settings = $settings;
        return $this;
    }

    /**
     * @param $setting
     * @param null $default
     * @return string|null
     */
    private function getSetting($setting, $default = null)
    {
        $this->logger->debug('getSetting: ' . $setting);
        if ($this->settings === null) {
            $this->logger->debug('getSetting: settings null');
            return $default;
        } else if (!$this->settings->hasParam($setting)) {
            $this->logger->debug('getSetting: setting not present. ' . var_export($this->settings, true));
            return $default;
        }

        return $this->settings->getString($setting, ['default' => $default]);
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
}
