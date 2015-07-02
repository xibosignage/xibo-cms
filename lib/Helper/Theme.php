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
use Extra;
use ID;
use Key;
use MenuManager;
use Select;
use Slim\Slim;
use Xibo\Entity\Menu;
use Xibo\Factory\MenuFactory;


class Theme
{
    private static $instance = null;

    private $dateManager;

    private $name = '';
    private $pageName = '';
    private $config = null;

    public function __construct($theme = NULL)
    {
        // Store some things for the Theme engine to use
        $this->help = new Help();
        $this->dateManager = new Date();

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
     * Get Menu
     * @param string $menu The Name of the Menu
     * @return array Array containing menu items (page, args, class, title, link, li)
     */
    public static function getMenu($menu)
    {
        $theme = Theme::getInstance();
        $array = array();

        foreach (MenuFactory::getByMenu($menu) as $menuItem) {
            /* @var Menu $menuItem */
            $item = array();
            $item['page'] = $menuItem->page;
            $item['args'] = $menuItem->args;
            $item['class'] = $menuItem->class;
            $item['title'] = __($menuItem->title);
            $item['img'] = $menuItem->img;
            $item['external'] = $menuItem->external;

            $item['selected'] = ($item['page'] == $theme->pageName);

            if ($item['external'] == 0) {
                $item['link'] = $item['page'] . '.view';
            } else {
                $item['link'] = $item['args'];
            }

            $array[] = $item;
        }

        return $array;
    }

    /**
     * Get a consolidated menu for the side bar
     * @return array
     */
    public static function getConsolidatedMenu()
    {
        $menus = [];
        $menus['top'] = Theme::getMenu('Top Nav');
        $menus['design'] = Theme::getMenu('Design Menu');
        $menus['library'] = Theme::getMenu('Library Menu');
        $menus['display'] = Theme::getMenu('Display Menu');
        $menus['admin'] = Theme::getMenu('Administration Menu');
        $menus['advanced'] = Theme::getMenu('Advanced Menu');

        return $menus;
    }

    /**
     * Get theme URI
     * @param string $uri
     * @param bool $local
     * @return string
     */
    public static function uri($uri, $local = false)
    {
        $app = Slim::getInstance();

        $rootUri = '';

        if (!$local) {
            $rootUri = $app->request->getScriptName();

            switch ($app->getName()) {

                case 'install':
                    $rootUri = str_replace('/install', '', $rootUri);
                    break;

                case 'api':
                    $rootUri = str_replace('/api', '', $rootUri);
                    break;

                case 'auth':
                    $rootUri = str_replace('/api/authorize', '', $rootUri);
                    break;

                case 'maintenance':
                    $rootUri = str_replace('/maintenance', '', $rootUri);
                    break;
            }
        }

        if (file_exists(PROJECT_ROOT . '/web/theme.' . self::getInstance()->name . '/' . $uri)) {
            return $rootUri . '/theme/' . self::getInstance()->name . '/' . $uri;
        }
        else {
            return $rootUri . '/theme/default/' . $uri;
        }
    }
}
