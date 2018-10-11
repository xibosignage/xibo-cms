<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ConfigServiceInterface.php)
 */


namespace Xibo\Service;


use Stash\Interfaces\PoolInterface;
use Xibo\Exception\ConfigurationException;
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
     * Gets the requested setting from the DB object given
     * @param $setting string
     * @param string[optional] $default
     * @return string
     */
    public function getSetting($setting, $default = NULL);

    /**
     * Change Setting
     * @param string $setting
     * @param mixed $value
     */
    public function changeSetting($setting, $value);

    /**
     * Should the host be considered a proxy exception
     * @param $host
     * @return bool
     */
    public function isProxyException($host);

    /**
     * Get Proxy Configuration
     * @param array $httpOptions
     * @return array
     */
    public function getGuzzleProxy($httpOptions = []);

    /**
     * Checks the Environment and Determines if it is suitable
     * @return string
     */
    public function checkEnvironment();

    /**
     * Loads the theme
     * @param string[Optional] $themeName
     * @throws ConfigurationException
     */
    public function loadTheme($themeName = null);

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
     * Get the cache namespace
     * @return string
     */
    public function getCacheNamespace();
}