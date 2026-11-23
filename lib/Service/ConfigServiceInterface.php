<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Service;

use Stash\Interfaces\PoolInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface ConfigServiceInterface
 * @package Xibo\Service
 */
interface ConfigServiceInterface
{
    /**
     * Set Service Dependencies
     * @param StorageServiceInterface $store
     * @param string $rootUri
     */
    public function setDependencies($store, $rootUri);

    /**
     * Get Cache Pool
     * @param PoolInterface $pool
     * @return mixed
     */
    public function setPool($pool);

    /**
     * Get Database Config
     * @return array
     */
    public function getDatabaseConfig();

    /**
     * Get settings
     * @return array|mixed|null
     */
    public function getSettings();

    /**
     * Gets the requested setting from the DB object given
     * @param $setting string
     * @param string[optional] $default
     * @param bool[optional] $full
     * @return string
     */
    public function getSetting($setting, $default = NULL, $full = false);

    /**
     * Change Setting
     * @param string $setting
     * @param mixed $value
     * @param int $userChange
     */
    public function changeSetting($setting, $value, $userChange = 0);

    /**
     * Get all settings with metadata (value, userSee, userChange)
     * @return array
     */
    public function getAllSettingsWithMeta();

    /**
     * Is the provided setting visible
     * @param string $setting
     * @return bool
     */
    public function isSettingVisible($setting);

    /**
     * Is the provided setting editable
     * @param string $setting
     * @return bool
     */
    public function isSettingEditable($setting);

    /**
     * Should the host be considered a proxy exception
     * @param $host
     * @return bool
     */
    public function isProxyException($host);

    /**
     * Is SAML Single Logout usable - i.e. explicitly enabled in workflow settings
     * and the IdP has a singleLogoutService endpoint configured.
     * @return bool
     */
    public function isSamlSloSupported();

    /**
     * Is SAML the active authentication ($authentication is SAMLAuthentication) with $samlSettings configured?
     * Its /saml/* routes only exist when it is.
     * @return bool
     */
    public function isSamlEnabled(): bool;

    /**
     * Is CAS the active authentication ($authentication is CASAuthentication) with $casSettings configured?
     * Its /cas/* routes only exist when it is.
     * @return bool
     */
    public function isCasEnabled(): bool;

    /**
     * Should the login page be shown (with a SAML button) rather than redirecting straight to the IdP?
     * Opt-in via $samlSettings['workflow']['showLoginPage'] (true, 1, 'true', 'yes' or 'on'), and SAML is enabled.
     * @return bool
     */
    public function isSamlLoginPageShown(): bool;

    /**
     * Should the full login page be shown (with a CAS button) rather than a CAS-only page?
     * Opt-in via $casSettings['showLoginPage'] (true, 1, 'true', 'yes' or 'on'), and CAS is enabled.
     * @return bool
     */
    public function isCasLoginPageShown(): bool;

    /**
     * Get Proxy Configuration
     * @param array $httpOptions
     * @return array
     */
    public function getGuzzleProxy($httpOptions = []);

    /**
     * Get API key details from Configuration
     * @return array
     */
    public function getApiKeyDetails();

    /**
     * Checks the Environment and Determines if it is suitable
     * @return string
     */
    public function checkEnvironment();

    /**
     * Loads the theme, overlaying the brand's names and texts when a brand service is given.
     */
    public function loadTheme(?BrandServiceInterface $brandService = null);

    /**
     * Get Theme Specific Settings
     * @param null $settingName
     * @param null $default
     * @return null
     */
    public function getThemeConfig($settingName = null, $default = null);

    /**
     * Get theme URI
     * @param string $uri
     * @param bool $local
     * @return string
     */
    public function uri($uri, $local = false);

    /**
     * Check a theme file exists
     * @param string $uri
     * @return bool
     */
    public function themeFileExists($uri);

    /**
     * Check a web file exists
     * @param string $uri
     * @return bool
     */
    public function fileExists($uri);

    /**
     * Get App Root URI
     * @return mixed
     */
    public function rootUri();

    /**
     * Get cache drivers
     * @return array
     */
    public function getCacheDrivers();

    /**
     * Get time series store settings
     * @return array
     */
    public function getTimeSeriesStore();

    /**
     * Get the cache namespace
     * @return string
     */
    public function getCacheNamespace();

    /**
     * Get Connector settings from the file based settings
     *  this acts as an override for settings stored in the database
     * @param string $connector The connector to return settings for.
     * @return array
     */
    public function getConnectorSettings(string $connector): array;

    /**
     * Get the operator-supplied Host-header allow-list (comma-separated). Empty when unset.
     * Deployment-time only — sourced from web/settings.php, not the DB.
     * @return string
     */
    public function getWhitelistHosts(): string;

    /**
     * Get the operator-supplied trusted reverse-proxy IP/CIDR list (comma-separated).
     * Empty when unset. Deployment-time only — sourced from web/settings.php, not the DB.
     * @return string
     */
    public function getTrustedProxyIps(): string;

    /**
     * Get the parsed, deduped trusted reverse-proxy IP/CIDR/wildcard list — the settings.php-only
     * getTrustedProxyIps(), and nothing else. This is the list used to authorize trusting
     * X-Forwarded-For for client-IP resolution (login/2FA/password-reset rate limiting,
     * session/display audit logs) — deliberately excludes the admin-editable
     * WHITELIST_LOAD_BALANCERS DB setting (see "Why WHITELIST_LOAD_BALANCERS is HTTPS-detection
     * only" in SECURITY.md). Match entries against an address with Xibo\Helper\IpTrust::isTrusted().
     * @return string[]
     */
    public function getTrustedProxyIpList(): array;

    /**
     * Get the combined, deduped list of trusted reverse-proxy IPs/CIDRs/wildcards used ONLY for
     * HTTPS/HSTS detection (HttpsDetect::isShouldIssueSts() / isHttpsTrusted()) — the union of
     * getTrustedProxyIpList() and the admin-editable WHITELIST_LOAD_BALANCERS DB setting. Requires
     * setDependencies() to have already been called. Do not use this for anything that grants a
     * standing capability to bypass a security control (rate limiting, audit-IP resolution) — use
     * getTrustedProxyIpList() for those.
     * @return string[]
     */
    public function getHttpsDetectionTrustedProxyIpList(): array;
}
