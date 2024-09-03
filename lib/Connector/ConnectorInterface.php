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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\JwtServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Connector Interface
 */
interface ConnectorInterface
{
    public function setFactories(ContainerInterface $container): ConnectorInterface;
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface;
    public function useLogger(LoggerInterface $logger): ConnectorInterface;
    public function useSettings(array $settings, bool $isProvider = true): ConnectorInterface;
    public function usePool(PoolInterface $pool): ConnectorInterface;
    public function useHttpOptions(array $httpOptions): ConnectorInterface;
    public function useJwtService(JwtServiceInterface $jwtService): ConnectorInterface;
    public function usePlayerActionService(PlayerActionServiceInterface $playerActionService): ConnectorInterface;
    public function getClient(): Client;
    public function getSourceName(): string;
    public function getTitle(): string;
    public function getDescription(): string;
    public function getThumbnail(): string;
    public function getSetting($setting, $default = null);
    public function isProviderSetting($setting): bool;
    public function getSettingsFormTwig(): string;
    public function getSettingsFormJavaScript(): string;
    public function processSettingsForm(SanitizerInterface $params, array $settings): array;
}
