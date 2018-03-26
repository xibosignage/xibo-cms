<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Config.php) is part of Xibo.
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
namespace Xibo\Service;

use Stash\Interfaces\PoolInterface;
use Xibo\Exception\ConfigurationException;
use Xibo\Helper\Environment;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ConfigService
 * @package Xibo\Service
 */
class ConfigService implements ConfigServiceInterface
{
    /**
     * @var StorageServiceInterface
     */
    public $store;

    /**
     * @var PoolInterface
     */
    public $pool;

    /** @var string Setting Cache Key */
    private $settingCacheKey = 'settings';

    /** @var bool Has the settings cache been dropped this request? */
    private $settingsCacheDropped = false;

    /** @var array */
    private $settings = null;

    /**
     * @var string
     */
    public $rootUri;

    public $envTested = false;
    public $envFault = false;
    public $envWarning = false;

    /**
     * Database Config
     * @var array
     */
    public static $dbConfig = [];

    //
    // Extra Settings
    //
    public $middleware = null;
    public $logHandlers = null;
    public $logProcessors = null;
    public $authentication = null;
    public $samlSettings = null;
    public $cacheDrivers = null;
    public $cacheNamespace = 'Xibo';

    /**
     * Theme Specific Config
     * @var array
     */
    public $themeConfig = [];
    /** @var bool Has a theme been loaded? */
    private $themeLoaded = false;

    /**
     * @inheritdoc
     */
    public function setDependencies($store, $rootUri)
    {
        if ($store == null)
            throw new \RuntimeException('ConfigService setDependencies called with null store');

        if ($rootUri == null)
            throw new \RuntimeException('ConfigService setDependencies called with null rootUri');

        $this->store = $store;
        $this->rootUri = $rootUri;
    }

    /**
     * @inheritdoc
     */
    public function setPool($pool)
    {
        $this->pool = $pool;
    }

    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    private function getPool()
    {
        return $this->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        if ($this->store == null)
            throw new \RuntimeException('Config Service called before setDependencies');

        return $this->store;
    }

    /**
     * @inheritdoc
     */
    public function getDatabaseConfig()
    {
        return self::$dbConfig;
    }

    /**
     * Get App Root URI
     * @return string
     */
    public function rootUri()
    {
        if ($this->rootUri == null)
            throw new \RuntimeException('Config Service called before setDependencies');

        return $this->rootUri;
    }

    /**
     * @inheritdoc
     */
    public function getCacheDrivers()
    {
        return $this->cacheDrivers;
    }

    /**
     * @inheritdoc
     */
    public function getCacheNamespace()
    {
        return $this->cacheNamespace;
    }

    /**
     * Loads the settings from file.
     *  DO NOT CALL ANY STORE() METHODS IN HERE
     * @param string $settings
     * @return ConfigServiceInterface
     */
    public static function Load($settings)
    {
        $config = new ConfigService();

        // Include the provided settings file.
        require ($settings);

        // Create a DB config
        self::$dbConfig = [
            'host' => $dbhost,
            'user' => $dbuser,
            'password' => $dbpass,
            'name' => $dbname
        ];

        // Pull in other settings

        // Log handlers
        if (isset($logHandlers))
            $config->logHandlers = $logHandlers;

        // Log Processors
        if (isset($logProcessors))
            $config->logProcessors = $logProcessors;

        // Middleware
        if (isset($middleware))
            $config->middleware = $middleware;

        // Authentication
        if (isset($authentication))
            $config->authentication = $authentication;

        // Saml settings
        if (isset($samlSettings))
            $config->samlSettings = $samlSettings;

        // Cache drivers
        if (isset($cacheDrivers))
            $config->cacheDrivers = $cacheDrivers;

        if (isset($cacheNamespace))
            $config->cacheNamespace = $cacheNamespace;

        // Set this as the global config
        return $config;
    }

    /**
     * Loads the theme
     * @param string[Optional] $themeName
     * @throws ConfigurationException
     */
    public function loadTheme($themeName = null)
    {
        // What is the currently selected theme?
        $globalTheme = ($themeName == NULL) ? $this->GetSetting('GLOBAL_THEME_NAME', 'default') : $themeName;

        // Is this theme valid?
        $systemTheme = (is_dir(PROJECT_ROOT . '/web/theme/' . $globalTheme) && file_exists(PROJECT_ROOT . '/web/theme/' . $globalTheme . '/config.php'));
        $customTheme = (is_dir(PROJECT_ROOT . '/web/theme/custom/' . $globalTheme) && file_exists(PROJECT_ROOT . '/web/theme/custom/' . $globalTheme . '/config.php'));

        if ($systemTheme) {
            require(PROJECT_ROOT . '/web/theme/' . $globalTheme . '/config.php');
            $themeFolder = 'theme/' . $globalTheme . '/';
        } elseif ($customTheme) {
            require(PROJECT_ROOT . '/web/theme/custom/' . $globalTheme . '/config.php');
            $themeFolder = 'theme/custom/' . $globalTheme . '/';
        } else
            throw new ConfigurationException(__('The theme "%s" does not exist', $globalTheme));

        $this->themeLoaded = true;
        $this->themeConfig = $config;
        $this->themeConfig['themeCode'] = $globalTheme;
        $this->themeConfig['themeFolder'] = $themeFolder;
    }

    /**
     * Get Theme Specific Settings
     * @param null $settingName
     * @param null $default
     * @return null
     */
    public function getThemeConfig($settingName = null, $default = null)
    {
        if ($settingName == null)
            return $this->themeConfig;

        if (isset($this->themeConfig[$settingName]))
            return $this->themeConfig[$settingName];
        else
            return $default;
    }

    /**
     * Get theme URI
     * @param string $uri
     * @param bool $local
     * @return string
     */
    public function uri($uri, $local = false)
    {
        $rootUri = ($local) ? '' : $this->rootUri();

        if (!$this->themeLoaded)
            return $rootUri . 'theme/default/' . $uri;

        // Serve the appropriate theme file
        if (is_dir(PROJECT_ROOT . '/web/' . $this->themeConfig['themeFolder'] . $uri)) {
            return $rootUri . $this->themeConfig['themeFolder'] . $uri;
        }
        else if (file_exists(PROJECT_ROOT . '/web/' . $this->themeConfig['themeFolder'] . $uri)) {
            return $rootUri . $this->themeConfig['themeFolder'] . $uri;
        }
        else {
            return $rootUri . 'theme/default/' . $uri;
        }
    }

    /** @inheritdoc */
    public function getSettings()
    {
        $item = null;

        if ($this->settings === null) {
            // We need to load in our settings
            if ($this->getPool() !== null) {
                // Try the cache
                $item = $this->getPool()->getItem($this->settingCacheKey);

                $data = $item->get();

                if ($item->isHit())
                    $this->settings = $data;
            }

            // Are we still null?
            if ($this->settings === null) {
                // Load from the database
                $results = $this->getStore()->select('SELECT `setting`, `value` FROM `setting`', []);

                foreach ($results as $setting) {
                    $this->settings[$setting['setting']] = $setting['value'];
                }
            }
        }

        // We should have our settings by now, so cache them if we can/need to
        if ($item !== null && $item->isMiss()) {
            $item->set($this->settings);

            // Do we have an elevated log level request? If so, then expire the cache sooner
            if (isset($this->settings['ELEVATE_LOG_UNTIL']) && intval($this->settings['ELEVATE_LOG_UNTIL']) > time())
                $item->expiresAfter(intval($this->settings['ELEVATE_LOG_UNTIL']));
            else
                $item->expiresAfter(60 * 5);

            $this->getPool()->saveDeferred($item);
        }

        return $this->settings;
    }

    /** @inheritdoc */
    public function GetSetting($setting, $default = NULL)
    {
        $this->getSettings();

        return (isset($this->settings[$setting])) ? $this->settings[$setting] : $default;
    }

    /** @inheritdoc */
    public function ChangeSetting($setting, $value)
    {
        $this->getSettings();

        if (isset($this->settings[$setting])) {
            // Update in memory cache
            $this->settings[$setting] = $value;

            // Update in database
            $this->getStore()->update('UPDATE `setting` SET `value` = :value WHERE `setting` = :setting', [
                'setting' => $setting, 'value' => $value
            ]);

            // Drop the cache if we've not already done so this time around
            if (!$this->settingsCacheDropped && $this->getPool() !== null) {
                $this->getPool()->deleteItem($this->settingCacheKey);
                $this->settingsCacheDropped = true;
            }
        }
    }

    /**
     * Defines the Version and returns it
     * @param $object string[optional]
     * @return array|string
     * @throws \Exception
     */
    public function Version($object = '')
    {
        try {

            $sth = $this->getStore()->getConnection()->prepare('SELECT app_ver, XlfVersion, XmdsVersion, DBVersion FROM version');
            $sth->execute();

            if (!$row = $sth->fetch(\PDO::FETCH_ASSOC))
                throw new \Exception('No results returned');

            $appVer = $row['app_ver'];
            $dbVer = intval($row['DBVersion']);

            if (!defined('VERSION'))
                define('VERSION', $appVer);

            if (!defined('DBVERSION'))
                define('DBVERSION', $dbVer);

            if ($object != '')
                return $row[$object];

            return $row;
        } catch (\Exception $e) {
            throw new \Exception(__('No Version information - please contact technical support'));
        }
    }

    /**
     * Is an upgrade pending?
     * @return bool
     */
    public function isUpgradePending()
    {
        return DBVERSION < Environment::$WEBSITE_VERSION;
    }

    /**
     * Should the host be considered a proxy exception
     * @param $host
     * @return bool
    */
    public function isProxyException($host)
    {
        $proxyExceptions = $this->GetSetting('PROXY_EXCEPTIONS');

        // If empty, cannot be an exception
        if (empty($proxyExceptions))
            return false;

        // Simple test
        if (stripos($host, $proxyExceptions) !== false)
            return true;

        // Host test
        $parsedHost = parse_url($host, PHP_URL_HOST);

        // Kick out extremely malformed hosts
        if ($parsedHost === false)
            return false;

        // Go through each exception and test against the host
        foreach (explode(',', $proxyExceptions) as $proxyException) {
            if (stripos($parsedHost, $proxyException) !== false)
                return true;
        }

        // If we've got here without returning, then we aren't an exception
        return false;
    }

    /**
     * Get Proxy Configuration
     * @param array $httpOptions
     * @return array
     */
    public function getGuzzleProxy($httpOptions = [])
    {
        // Proxy support
        if ($this->GetSetting('PROXY_HOST') != '') {

            $proxy = $this->GetSetting('PROXY_HOST') . ':' . $this->GetSetting('PROXY_PORT');

            if ($this->GetSetting('PROXY_AUTH') != '') {
                $scheme = explode('://', $proxy);

                $proxy = $scheme[0] . $this->GetSetting('PROXY_AUTH') . '@' . $scheme[1];
            }

            $httpOptions['proxy'] = [
                'http' => $proxy,
                'https' => $proxy
            ];

            if ($this->GetSetting('PROXY_EXCEPTIONS') != '') {
                $httpOptions['proxy']['no'] = explode(',', $this->GetSetting('PROXY_EXCEPTIONS'));
            }
        }

        return $httpOptions;
    }

    private function testItem(&$results, $item, $result, $advice, $fault = true)
    {
        // 1=OK, 0=Failure, 2=Warning
        $status = ($result) ? 1 : (($fault) ? 0 : 2);

        // Set fault flag
        if (!$result && $fault)
            $this->envFault = true;

        // Set warning flag
        if (!$result && !$fault)
            $this->envWarning = true;

        $results[] = [
            'item' => $item,
            'status' => $status,
            'advice' => $advice
        ];
    }

    /**
     * Checks the Environment and Determines if it is suitable
     * @return array
     */
    public function CheckEnvironment()
    {
        $rows = array();

        $this->testItem($rows, __('PHP Version'),
            Environment::checkPHP(),
            sprintf(__("PHP version %s or later required."), Environment::$VERSION_REQUIRED) . ' Detected ' . phpversion()
        );

        $this->testItem($rows, __('File System Permissions'),
            Environment::checkFsPermissions(),
            __('Write permissions are required for web/settings.php and cache/')
        );

        $this->testItem($rows, __('MySQL database (PDO MySql)'),
            Environment::checkPDO(),
            __('PDO support with MySQL drivers must be enabled in PHP.')
        );

        $this->testItem($rows, __('JSON Extension'),
            Environment::checkJson(),
            __('PHP JSON extension required to function.')
        );

        $this->testItem($rows, __('SOAP Extension'),
            Environment::checkSoap(),
            __('PHP SOAP extension required to function.')
        );

        $this->testItem($rows, __('GD Extension'),
            Environment::checkGd(),
            __('PHP GD extension required to function.')
        );

        $this->testItem($rows, __('Session'),
            Environment::checkGd(),
            __('PHP session support required to function.')
        );

        $this->testItem($rows, __('FileInfo'),
            Environment::checkFileInfo(),
            __('Requires PHP FileInfo support to function. If you are on Windows you need to enable the php_fileinfo.dll in your php.ini file.')
        );

        $this->testItem($rows, __('PCRE'),
            Environment::checkPCRE(),
            __('PHP PCRE support to function.')
        );

        $this->testItem($rows, __('Gettext'),
            Environment::checkPCRE(),
            __('PHP Gettext support to function.')
        );

        $this->testItem($rows, __('DOM Extension'),
            Environment::checkDom(),
            __('PHP DOM core functionality enabled.')
        );

        $this->testItem($rows, __('DOM XML Extension'),
            Environment::checkDomXml(),
            __('PHP DOM XML extension to function.')
        );

        $this->testItem($rows, __('Mcrypt Extension'),
            Environment::checkMcrypt(),
            __('PHP Mcrypt extension to function.')
        );

        $this->testItem($rows, __('Allow PHP to open external URLs'),
            Environment::checkAllowUrlFopen(),
            __('You must have allow_url_fopen = On in your PHP.ini file for RSS Feeds / Anonymous statistics gathering to function.'),
            false
        );

        $this->testItem($rows, __('DateTimeZone'),
            Environment::checkTimezoneIdentifiers(),
            __('This enables us to get a list of time zones supported by the hosting server.'),
            false
        );

        $this->testItem($rows, __('ZIP'),
            Environment::checkZip(),
            __('This enables import / export of layouts.')
        );

        $advice = __('Support for uploading large files is recommended.');
        $advice .= __('We suggest setting your PHP post_max_size and upload_max_filesize to at least 128M, and also increasing your max_execution_time to at least 120 seconds.');

        $this->testItem($rows, __('Large File Uploads'),
            Environment::checkPHPUploads(),
            $advice,
            false
        );

        $this->testItem($rows, __('cURL'),
            Environment::checkCurlInstalled(),
            __('cURL is used to fetch data from the Internet or Local Network')
        );

        $this->testItem($rows, __('ZeroMQ'),
            Environment::checkZmq(),
            __('ZeroMQ is used to send messages to XMR which allows push communications with player'),
            false
        );

        $this->testItem($rows, __('OpenSSL'),
            Environment::checkOpenSsl(),
            __('OpenSSL is used to seal and verify messages sent to XMR'),
            false
        );

        $this->testItem($rows, __('SimpleXML'),
            Environment::checkSimpleXml(),
            __('SimpleXML is used to parse RSS feeds and other XML data sources')
        );

        $this->envTested = true;

        return $rows;
    }

    /**
     * Is there an environment fault
     * @return bool
     */
    public function EnvironmentFault()
    {
        if (!$this->envTested) {
            $this->checkEnvironment();
        }

        return $this->envFault;
    }

    /**
     * Is there an environment warning
     * @return bool
     */
    public function EnvironmentWarning()
    {
        if (!$this->envTested) {
            $this->checkEnvironment();
        }

        return $this->envWarning;
    }

    /**
     * Check binlog format
     * @return bool
     */
    public function checkBinLogEnabled()
    {
        //TODO: move this into storage interface
        $results = $this->getStore()->select('show variables like \'log_bin\'', []);

        if (count($results) <= 0)
            return false;

        return ($results[0]['Value'] != 'OFF');
    }

    /**
     * Check binlog format
     * @return bool
     */
    public function checkBinLogFormat()
    {
        //TODO: move this into storage interface
        $results = $this->getStore()->select('show variables like \'binlog_format\'', []);

        if (count($results) <= 0)
            return false;

        return ($results[0]['Value'] != 'STATEMENT');
    }
}
