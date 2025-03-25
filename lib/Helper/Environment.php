<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
    public static $WEBSITE_VERSION_NAME = '4.2.2';
    public static $XMDS_VERSION = '7';
    public static $XLF_VERSION = 4;
    public static $VERSION_REQUIRED = '8.1.0';
    public static $VERSION_UNSUPPORTED = '9.0';
    public static $PLAYER_SUPPORT = 300;

    /** @var null cache migration status for the whole request */
    private static $migrationStatus = null;

    /** @var string the git commit ref */
    private static $gitCommit = null;

    /**
     * Is there a migration pending?
     * @return bool
     */
    public static function migrationPending()
    {
        // Allow missing migrations in dev mode.
        if (self::isDevMode()) {
            return self::getMigrationStatus() > 2;
        } else {
            return self::getMigrationStatus() != 0;
        }
    }

    /**
     * Get Migration Status
     * @return int
     */
    private static function getMigrationStatus()
    {
        if (self::$migrationStatus === null) {
            // Use a Phinx text wrapper to work out what the current status is
            // make sure this does not output anything to our output buffer
            ob_start();
            $phinx = new TextWrapper(new PhinxApplication(), ['configuration' => PROJECT_ROOT . '/phinx.php']);
            $phinx->getStatus();

            self::$migrationStatus = $phinx->getExitCode();
            ob_end_clean();
        }

        return self::$migrationStatus;
    }

    /**
     * Get Git Commit
     * @return string
     */
    public static function getGitCommit()
    {
        if (self::$gitCommit === null) {
            if (isset($_SERVER['GIT_COMMIT']) && $_SERVER['GIT_COMMIT'] === 'dev') {
                $out = [];
                exec('cat /var/www/cms/.git/$(cat /var/www/cms/.git/HEAD | cut -d\' \' -f2)', $out);
                self::$gitCommit = $out[0] ?? null;
            } else {
                self::$gitCommit = $_SERVER['GIT_COMMIT'] ?? null;
            }

            if (self::$gitCommit === null && file_exists(PROJECT_ROOT . '/commit.sha')) {
                self::$gitCommit = trim(file_get_contents(PROJECT_ROOT . '/commit.sha'));
            }
        }

        return self::$gitCommit ?? 'unknown';
    }

    /**
     * Check FileSystem Permissions
     * @return bool
     */
    public static function checkSettingsFileSystemPermissions()
    {
        $settingsPath = PROJECT_ROOT . '/web/settings.php';
        return (file_exists($settingsPath)) ? is_writable($settingsPath) : is_writable(PROJECT_ROOT . '/web');
    }

    /**
     * Check FileSystem Permissions
     * @return bool
     */
    public static function checkCacheFileSystemPermissions()
    {
        return is_writable(PROJECT_ROOT . '/cache');
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
    public static function checkCurl()
    {
        return extension_loaded('curl');
    }

    /**
     * @return bool
     */
    public static function checkSimpleXml()
    {
        return extension_loaded('simplexml');
    }

    /**
     * @return bool
     */
    public static function checkGnu()
    {
        return extension_loaded('gnupg');
    }

    /**
     * @param $url
     * @return bool
     */
    public static function checkUrl($url)
    {
        return (stripos($url, '/web/') === false);
    }

    /**
     * Is the CMS in DEV mode?
     * @return bool
     */
    public static function isDevMode()
    {
        return (isset($_SERVER['CMS_DEV_MODE']) && $_SERVER['CMS_DEV_MODE'] === 'true');
    }

    /**
     * Is debugging forced ON for this request?
     * @return bool
     */
    public static function isForceDebugging()
    {
        return (isset($_SERVER['CMS_FORCE_DEBUG']) && $_SERVER['CMS_FORCE_DEBUG'] === 'true');
    }
}
