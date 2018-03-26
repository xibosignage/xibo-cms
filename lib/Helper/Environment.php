<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (Environment.php)
 */


namespace Xibo\Helper;
use Phinx\Console\PhinxApplication;
use Phinx\Wrapper\TextWrapper;

/**
 * Class Environment
 * @package Xibo\Helper
 */
class Environment
{
    public static $WEBSITE_VERSION_NAME = '2.0.0-alpha1';
    public static $XMDS_VERSION = '5';
    public static $XLF_VERSION = '2';
    public static $VERSION_REQUIRED = '5.5';
    public static $VERSION_UNSUPPORTED = '8.0';

    /**
     * Is there a migration pending
     * @return bool
     */
    public static function migrationPending()
    {
        // Use a Phinx text wrapper to work out what the current status is
        $phinx = new TextWrapper(new PhinxApplication(), ['configuration' => PROJECT_ROOT . '/phinx.php']);
        $phinx->getStatus();

        return ($phinx->getExitCode() != 0);
    }

    /**
     * Check FileSystem Permissions
     * @return bool
     */
    public static function checkFsPermissions()
    {
        return (is_writable(PROJECT_ROOT . "/web/settings.php") && is_writable(PROJECT_ROOT . "/cache"));
    }

    /**
     * Check PHP version is within the preset parameters
     * @return bool
     */
    public static function checkPHP()
    {
        return (version_compare(phpversion(), self::$VERSION_REQUIRED) != -1) && (version_compare(phpversion(), self::$VERSION_UNSUPPORTED) != 1);
    }

    /**
     * Check PHP has the PDO module installed (with MySQL driver)
     */
    public static function checkPDO()
    {
        return extension_loaded("pdo_mysql");
    }

    /**
     * Check PHP has the GetText module installed
     * @return bool
     */
    public static function checkGettext()
    {
        return extension_loaded("gettext");
    }

    /**
     * Check PHP has JSON module installed
     * @return bool
     */
    public static function checkJson()
    {
        return extension_loaded("json");
    }

    /**
     *
     * Check PHP has SOAP module installed
     * @return bool
     */
    public static function checkSoap()
    {
        return extension_loaded("soap");
    }

    /**
     * Check PHP has GD module installed
     * @return bool
     */
    public static function checkGd()
    {
        return extension_loaded("gd");
    }

    /**
     * Check PHP has the DOM XML functionality installed
     * @return bool
     */
    public static function checkDomXml()
    {
        return extension_loaded("dom");
    }

    /**
     * Check PHP has the Mcrypt functionality installed
     * @return bool
     */
    public static function checkMcrypt()
    {
        return extension_loaded("mcrypt");
    }

    /**
     * Check PHP has the DOM functionality installed
     * @return bool
     */
    public static function checkDom()
    {
        return class_exists("DOMDocument");
    }

    /**
     * Check PHP has session functionality installed
     * @return bool
     */
    public static function checkSession()
    {
        return extension_loaded("session");
    }

    /**
     * Check PHP has PCRE functionality installed
     * @return bool
     */
    public static function checkPCRE()
    {
        return extension_loaded("pcre");
    }

    /**
     * Check PHP has FileInfo functionality installed
     * @return bool
     */
    public static function checkFileInfo()
    {
        return extension_loaded("fileinfo");
    }

    public static function checkZip()
    {
        return extension_loaded('zip');
    }

    public static function checkIntlDateFormat()
    {
        return class_exists('IntlDateFormatter');
    }


    /**
     * Check to see if curl is installed
     */
    public static function checkCurlInstalled()
    {
        return function_exists('curl_version');
    }

    /**
     * Check PHP is setup for large file uploads
     * @return bool
     */
    public static function checkPHPUploads()
    {
        # Consider 0 - 128M warning / < 120 seconds
        # Variables to check:
        #    post_max_size
        #    upload_max_filesize
        #    max_execution_time

        $minSize = ByteFormatter::toBytes('128M');

        if (ByteFormatter::toBytes(ini_get('post_max_size')) < $minSize)
            return false;

        if (ByteFormatter::toBytes(ini_get('upload_max_filesize')) < $minSize)
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


    public static function getMaxUploadSize()
    {
        return ini_get('upload_max_filesize');
    }

    /**
     * Check open ssl is available
     * @return bool
     */
    public static function checkOpenSsl()
    {
        return extension_loaded('openssl');
    }

    /**
     * @inheritdoc
     * https://stackoverflow.com/a/45767760
     */
    public static function getMemoryLimitBytes()
    {
        return intval(str_replace(array('G', 'M', 'K'), array('000000000', '000000', '000'), ini_get('memory_limit')));
    }

    /**
     * @return bool
     */
    public static function checkTimezoneIdentifiers()
    {
        return function_exists('timezone_identifiers_list');
    }

    /**
     * @return bool
     */
    public static function checkAllowUrlFopen()
    {
        return ini_get('allow_url_fopen');
    }

    /**
     * @return bool
     */
    public static function checkSimpleXml()
    {
        return extension_loaded('simplexml');
    }

    /**
     * @param $url
     * @return bool
     */
    public static function checkUrl($url)
    {
        return (stripos($url, '/web/') === false);
    }
}