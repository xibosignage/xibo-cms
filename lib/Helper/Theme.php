<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
use Exception;
use Slim\Slim;
use Xibo\Entity\Menu;
use Xibo\Factory\MenuFactory;


class Theme
{
    private static $instance = null;

    private $name = '';
    private $config = null;

    public function __construct($theme = NULL)
    {
        // What is the currently selected theme?
        $globalTheme = ($theme == NULL) ? Config::GetSetting('GLOBAL_THEME_NAME', 'default') : $theme;

        // Is this theme valid?
        if (!is_dir(PROJECT_ROOT . '/web/theme/' . $globalTheme))
            throw new Exception(__('The theme "%s" does not exist', $globalTheme));

        // Store the theme name for later
        $this->name = $globalTheme;

        // Get config
        if (!file_exists(PROJECT_ROOT . '/web/theme/' . $this->name . '/config.php'))
            throw new Exception(__('The theme "%s" config file does not exist', $globalTheme));

        require(PROJECT_ROOT . '/web/theme/' . $this->name . '/config.php');
        $this->config = $config;
        $this->config['themeCode'] = $this->name;

        self::$instance = $this;
    }

    /**
     * GetInstance of Theme
     */
    public static function getInstance()
    {
        if (!isset(self::$instance))
            self::$instance = new Theme();

        return self::$instance;
    }

    /**
     * Get Theme Specific Settings
     * @param null $settingName
     * @param null $default
     * @return null
     */
    public static function getConfig($settingName = null, $default = null)
    {
        $theme = Theme::getInstance();

        if ($settingName == null)
            return $theme->config;

        if (isset($theme->config[$settingName]))
            return $theme->config[$settingName];
        else
            return $default;
    }

    /**
     * Get theme URI
     * @param string $uri
     * @param bool $local
     * @return string
     */
    public static function uri($uri, $local = false)
    {
        $rootUri = ($local) ? '' : Slim::getInstance()->rootUri;

        // Serve the appropriate theme file
        if (is_dir(PROJECT_ROOT . '/web/theme/' . self::getInstance()->name . '/' . $uri)) {
            return $rootUri . 'theme/' . self::getInstance()->name . '/' . $uri;
        }
        else if (file_exists(PROJECT_ROOT . '/web/theme/' . self::getInstance()->name . '/' . $uri)) {
            return $rootUri . 'theme/' . self::getInstance()->name . '/' . $uri;
        }
        else {
            return $rootUri . 'theme/default/' . $uri;
        }
    }

    public static function rootUri()
    {
        return Slim::getInstance()->rootUri;
    }
}
