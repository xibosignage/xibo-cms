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
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ConfigService
 * @package Xibo\Service
 */
class ConfigService implements ConfigServiceInterface
{
    public static $WEBSITE_VERSION_NAME = '1.8.0-rc1';
    public static $WEBSITE_VERSION = 129;
    public static $VERSION_REQUIRED = '5.5';

    /**
     * @var StorageServiceInterface
     */
    public $store;

    /**
     * @var PoolInterface
     */
    public $pool;

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

    /**
     * Gets the requested setting from the DB object given
     * @param $setting string
     * @param string[optional] $default
     * @return string
     */
    public function GetSetting($setting, $default = NULL)
    {
        $item = null;

        if ($this->getPool() != null) {
            $item = $this->getPool()->getItem('config/' . $setting);

            $data = $item->get();

            if ($item->isHit()) {
                return $data;
            }
        }

        $sth = $this->getStore()->getConnection()->prepare('SELECT `value` FROM `setting` WHERE `setting` = :setting');
        $sth->execute(array('setting' => $setting));

        if (!$result = $sth->fetch())
            $data = $default;
        else
            $data = $result['value'];

        if ($this->getPool() != null) {

            if ($setting == 'ELEVATE_LOG_UNTIL' && intval($data) > time())
                $item->expiresAfter(intval($data) - time());

            $this->getPool()->saveDeferred($item->set($data));
        }

        return $data;
    }

    /**
     * Change Setting
     * @param string $setting
     * @param mixed $value
     */
    public function ChangeSetting($setting, $value)
    {
        $sth = $this->getStore()->getConnection()->prepare('UPDATE `setting` SET `value` = :value WHERE `setting` = :setting');
        $sth->execute(array('setting' => $setting, 'value' => $value));

        if (self::getPool() != null) {
            $item = self::getPool()->getItem('config/' . $setting);
            self::getPool()->saveDeferred($item->set($value));
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
            trigger_error($e->getMessage());
            throw new \Exception(__('No Version information - please contact technical support'));
        }
    }

    /**
     * Is an upgrade pending?
     * @return bool
     */
    public function isUpgradePending()
    {
        return DBVERSION < ConfigService::$WEBSITE_VERSION;
    }

    /**
     * Should the host be considered a proxy exception
     * @param $host
     * @return bool
    */
    public function isProxyException($host)
    {
        $proxyException = $this->GetSetting('PROXY_EXCEPTIONS');
        return ($proxyException != '' && stripos($host, $proxyException) > -1);
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

    /**
     * Get PicoFeed Proxy
     * @param string $feedUrl
     * @return null|\PicoFeed\Config\Config
     */
    public function getPicoFeedProxy($feedUrl)
    {
        $clientOptions = null;

        if ($this->GetSetting('PROXY_HOST') != '' && !$this->isProxyException($feedUrl)) {
            $clientOptions = new \PicoFeed\Config\Config();
            $clientOptions->setProxyHostname($this->GetSetting('PROXY_HOST'));
            $clientOptions->setProxyPort($this->GetSetting('PROXY_PORT'));

            $proxyAuth = $this->GetSetting('PROXY_AUTH');
            if ($proxyAuth != '') {
                $proxyAuth = explode(':', $proxyAuth);
                $clientOptions->setProxyUsername($proxyAuth[0]);
                $clientOptions->setProxyPassword($proxyAuth[1]);
            }
        }

        return $clientOptions;
    }

    /**
     * Checks the Environment and Determines if it is suitable
     * @return string
     */
    public function CheckEnvironment()
    {
        $rows = array();

        // Check for PHP version
        $advice = sprintf(__("PHP version %s or later required."), ConfigService::$VERSION_REQUIRED) . ' Detected ' . phpversion();
        if ($this->CheckPHP()) {
            $status = 1;
        } else {
            $this->envFault = true;
            $status = 0;
        }

        $rows[] = array(
            'item' => __('PHP Version'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for file system permissions
        $advice = __("Write access required for settings.php and install.php");
        if ($this->CheckFsPermissions()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('File System Permissions'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for PDO
        $advice = __('PDO support with MySQL drivers must be enabled in PHP.');
        if ($this->CheckPDO()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('MySQL database (PDO MySql)'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for JSON
        $advice = __('PHP JSON extension required to function.');
        if ($this->CheckJson()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('JSON Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for SOAP
        $advice = __('PHP SOAP extension required to function.');
        if ($this->CheckSoap()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('SOAP Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for GD (graphics)
        $advice = __('PHP GD extension to function.');
        if ($this->CheckGd()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('GD Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for PHP Session
        $advice = __('PHP session support to function.');
        if ($this->CheckSession()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('Session'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for PHP FileInfo
        $advice = __('Requires PHP FileInfo support to function. If you are on Windows you need to enable the php_fileinfo.dll in your php.ini file.');
        if ($this->CheckFileInfo()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('FileInfo'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for PHP PCRE
        $advice = __('PHP PCRE support to function.');
        if ($this->CheckPCRE()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('PCRE'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for PHP Gettext
        $advice = __('PHP Gettext support to function.');
        if ($this->CheckGettext()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('Gettext'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for Calendar
        $advice = __('PHP Calendar extension to function.');
        if ($this->CheckCal()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('Calendar Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for DOM
        $advice = __('PHP DOM core functionality enabled.');
        if ($this->CheckDom()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('DOM Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for DOM XML
        $advice = __('PHP DOM XML extension to function.');
        if ($this->CheckDomXml()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('DOM XML Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check for Mcrypt
        $advice = __('PHP Mcrypt extension to function.');
        if ($this->CheckMcrypt()) {
            $status = 1;
        } else {
            $this->envFault = true;

            $status = 0;
        }

        $rows[] = array(
            'item' => __('Mcrypt Extension'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if we are allowed to open remote URLs (home call will not work otherwise)
        $advice = __('You must have allow_url_fopen = On in your PHP.ini file for RSS Feeds / Anonymous statistics gathering to function.');
        if (ini_get('allow_url_fopen')) {
            $status = 1;
        } else {
            // Not a fault as this will not block installation / upgrade. Informational.
            $this->envWarning = true;
            $status = 2;
        }

        $rows[] = array(
            'item' => __('Allow PHP to open external URLs'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if timezone_identifiers_list exists
        $advice = __('This enables us to get a list of time zones supported by the hosting server.');
        if (function_exists('timezone_identifiers_list')) {
            $status = 1;
        } else {
            $status = 2;
            $this->envWarning = true;
        }

        $rows[] = array(
            'item' => __('DateTimeZone'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if Zip support exists
        $advice = __('This enables import / export of layouts.');
        if ($this->CheckZip()) {
            $status = 1;
        } else {
            $status = 0;
            $this->envFault = true;
        }

        $rows[] = array(
            'item' => __('ZIP'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if large file uploads enabled
        $advice = __('Support for uploading large files is recommended.');
        if ($this->CheckPHPUploads()) {
            $status = 1;
        } else {
            $this->envWarning = true;
            $status = 2;
            $advice = __('You probably want to allow larger files to be uploaded than is currently available with your PHP configuration.');
            $advice .= __('We suggest setting your PHP post_max_size and upload_max_filesize to at least 128M, and also increasing your max_execution_time to at least 120 seconds.');
        }

        $rows[] = array(
            'item' => __('Large File Uploads'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if cURL is installed
        $advice = __('cURL is used to fetch data from the Internet or Local Network');
        if ($this->checkCurlInstalled()) {
            $status = 1;
        } else {
            $this->envFault = true;
            $status = 0;
            $advice .= __(' and is required.');
        }

        $rows[] = array(
            'item' => __('cURL'),
            'status' => $status,
            'advice' => $advice
        );

        // Check to see if ZMQ is installed
        $advice = __('ZeroMQ is used to send messages to XMR which allows push communications with player');
        if ($this->checkZmq()) {
            $status = 1;
        } else {
            $this->envWarning = true;
            $status = 2;
            $advice .= __(' and is recommended.');
        }

        $rows[] = array(
            'item' => __('ZeroMQ'),
            'status' => $status,
            'advice' => $advice
        );

        $this->envTested = true;

        return $rows;
    }

    /**
     * Is there an environment fault
     * @return
     */
    public function EnvironmentFault()
    {
        if (!$this->envTested) {
            $this->CheckEnvironment();
        }

        return $this->envFault;
    }

    /**
     * Is there an environment warning
     * @return
     */
    public function EnvironmentWarning()
    {
        if (!$this->envTested) {
            $this->CheckEnvironment();
        }

        return $this->envWarning;
    }


    /**
     * Check FileSystem Permissions
     * @return
     */
    function CheckFsPermissions()
    {
        return (is_writable("install.php") && (is_writable("settings.php")) || is_writable("."));
    }

    /**
     * Check PHP version > 5
     * @return
     */
    function CheckPHP()
    {
        return (version_compare(phpversion(), ConfigService::$VERSION_REQUIRED) != -1);
    }

    /**
     * Check PHP has the PDO module installed (with MySQL driver)
     */
    function CheckPDO()
    {
        return extension_loaded("pdo_mysql");
    }

    /**
     * Check PHP has the GetText module installed
     * @return
     */
    function CheckGettext()
    {
        return extension_loaded("gettext");
    }

    /**
     * Check PHP has JSON module installed
     * @return
     */
    function CheckJson()
    {
        return extension_loaded("json");
    }

    /**
     *
     * Check PHP has SOAP module installed
     * @return
     */
    function CheckSoap()
    {
        return extension_loaded("soap");
    }

    /**
     * Check PHP has JSON module installed
     * @return
     */
    function CheckGd()
    {
        return extension_loaded("gd");
    }

    /**
     * Check PHP has JSON module installed
     * @return
     */
    function CheckCal()
    {
        return extension_loaded("calendar");
    }

    /**
     * Check PHP has the DOM XML functionality installed
     * @return
     */
    function CheckDomXml()
    {
        return extension_loaded("dom");
    }

    /**
     * Check PHP has the Mcrypt functionality installed
     * @return
     */
    function CheckMcrypt()
    {
        return extension_loaded("mcrypt");
    }

    /**
     * Check PHP has the DOM functionality installed
     * @return
     */
    function CheckDom()
    {
        return class_exists("DOMDocument");
    }

    /**
     * Check PHP has session functionality installed
     * @return
     */
    function CheckSession()
    {
        return extension_loaded("session");
    }

    /**
     * Check PHP has PCRE functionality installed
     * @return
     */
    function CheckPCRE()
    {
        return extension_loaded("pcre");
    }

    /**
     * Check PHP has FileInfo functionality installed
     * @return
     */
    function CheckFileInfo()
    {
        return extension_loaded("fileinfo");
    }

    function CheckZip()
    {
        return extension_loaded('zip');
    }

    static function CheckIntlDateFormat()
    {
        return class_exists('IntlDateFormatter');
    }


     /**
      * Check to see if curl is installed
      */
     static function checkCurlInstalled()
     {
         return function_exists('curl_version');
     }

    /**
     * Check PHP is setup for large file uploads
     * @return
     */
    function CheckPHPUploads()
    {
        # Consider 0 - 128M warning / < 120 seconds
        # Variables to check:
        #    post_max_size
        #    upload_max_filesize
        #    max_execution_time

        $minSize = $this->return_bytes('128M');

        if ($this->return_bytes(ini_get('post_max_size')) < $minSize)
            return false;

        if ($this->return_bytes(ini_get('upload_max_filesize')) < $minSize)
            return false;

        if (ini_get('max_execution_time') < 120)
            return false;

        // All passed
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function checkZmq()
    {
        return class_exists('ZMQSocket');
    }

    /**
     * Helper function to convert strings like 8M or 3G into bytes
     * by Stas Trefilov. Assumed Public Domain.
     * Taken from the PHP Manual (http://www.php.net/manual/en/function.ini-get.php#96996)
     * @return
     */
    function return_bytes($size_str)
    {
        switch (substr($size_str, -1)) {
            case 'M':
            case 'm':
                return (int)$size_str * 1048576;
            case 'K':
            case 'k':
                return (int)$size_str * 1024;
            case 'G':
            case 'g':
                return (int)$size_str * 1073741824;
            default:
                return $size_str;
        }
    }

    public static function getMaxUploadSize()
    {
        return ini_get('upload_max_filesize');
    }

    /**
     * Check binlog format
     * @return bool
     */
    public function checkBinLogEnabled()
    {
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
        $results = $this->getStore()->select('show variables like \'binlog_format\'', []);

        if (count($results) <= 0)
            return false;

        return ($results[0]['Value'] != 'STATEMENT');
    }
}
