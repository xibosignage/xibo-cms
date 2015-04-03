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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Theme {
	private static $instance = null;
	
	private $user;
	private $helpManager;
	private $dateManager;

	private $name = '';
	private $pageName = '';
	private $vars = null;
	private $config = null;
	
	public function __construct(user $user, $theme = NULL) {

		// Store some things for the Theme engine to use
		$this->user =& $user;
		$this->help = new HelpManager();
		$this->dateManager = new DateManager();

		// What is the currently selected theme?
		$globalTheme = ($theme == NULL) ? Config::GetSetting('GLOBAL_THEME_NAME', 'default') : $theme;

		// Is this theme valid?
		if (!is_dir('theme/' . $globalTheme))
			throw new Exception(__('The theme "%s" does not exist', $globalTheme));
		
		// Store the theme name for later
		$this->name = $globalTheme;

		// Get config
		if (!file_exists('theme/' . $this->name . '/config.php'))
			throw new Exception(__('The theme "%s" config file does not exist', $globalTheme));

		require('theme/' . $this->name . '/config.php');
		$this->config = $config;

		self::$instance = $this;
	}

	/**
	 * GetInstance of Theme
	 */
	private static function GetInstance() {
		if (!isset(self::$instance))
			throw new Exception(__("Theme not initialised"));

		return self::$instance;
	}

	/**
	 * Render Item
	 * @param string $item Item to Render
	 */
	public static function Render($item) {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/html/' . $item . '.php')) {
			include('theme/' . $theme->name . '/html/' . $item . '.php');
		}
		// Check the module theme folder
		else if (file_exists('modules/theme/' . $item . '.php')) {
			include('modules/theme/' . $item . '.php');	
		}
		// If not, then use the default folder
		else if (file_exists('theme/default/html/' . $item . '.php')) {
			include('theme/default/html/' . $item . '.php');
		}
		else
			throw new Exception(__('The requested theme item does not exist. [%s, %s]', array($item, $theme->name)));
	}

	/**
	 * Render Item but return the value as a string
	 * @param string $item Item to Render
	 */
	public static function RenderReturn($item) {

		ob_start();

		Theme::Render($item);

		$output = ob_get_contents();

		ob_end_clean();

		return $output;
	}

	/**
	 * Get an image from the Theme
	 * @param string $item The image filename
	 * @param string $class The class to apply [optional]
	 */
	public static function Image($item, $class = '') {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/img/' . $item)) {
			return '<img ' . (($class != '') ? 'class="' . $class . '"' : '') . ' src="theme/' . $theme->name . '/img/' . $item . '" />';
		}
		// If not, then use the default folder
		elseif (file_exists('theme/default/img/' . $item)) {
			return '<img ' . (($class != '') ? 'class="' . $class . '"' : '') . ' src="theme/default/img/' . $item . '" />';
		}
		else
			return '';
	}

	/**
	 * Get an image URL
	 * @param [string] $item the image
	 */
	public static function ImageUrl($item) {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/img/' . $item)) {
			return 'theme/' . $theme->name . '/img/' . $item;
		}
		// If not, then use the default folder
		elseif (file_exists('theme/default/img/' . $item)) {
			return 'theme/default/img/' . $item;
		}
		else
			return '';
	}

	/**
	 * Get Item Path
	 * @param string $item The Item required
	 */
	public static function ItemPath($item) {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/' . $item)) {
			return 'theme/' . $theme->name . '/' . $item;
		}
		// If not, then use the default folder
		elseif (file_exists('theme/default/' . $item)) {
			return 'theme/default/' . $item;
		}
		else
			return '';
	}

	/**
	 * Get Item Path
	 * @param string $item The Item required
	 */
	public static function Script($item) {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/' . $item)) {
			return '<script src="theme/' . $theme->name . '/' . $item . '"></script>';
		}
		// If not, then use the default folder
		elseif (file_exists('theme/default/' . $item)) {
			return '<script src="theme/default/' . $item . '"></script>';
		}
		else
			return '';
	}

	/**
	 * Translate a string into the user language
	 * @param string $string The String to Translate
	 * @param array $args   Variables to insert (will replace %d %s in order)
	 */
	public static function Translate($string) {
		return call_user_func_array('__', func_get_args());
	}

	public static function Set($key, $value) {
		$theme = Theme::GetInstance();

		$theme->vars[$key] = $value;
	}

	public static function Get($key) {
		$theme = Theme::GetInstance();

		if (!isset($theme->vars[$key]))			
			$return = null;
		else
			$return = $theme->vars[$key];
		
		if ($key == 'form_meta') {
			// Append a token to the end
			$return = $return . Kit::Token();
		}
		return $return;
	}

	public static function SetTranslation($key, $value) {
		// Get existing translations
		$translations = Theme::Get('translations');

		if ($translations == '') {
			$translations = array();
		}
		else {
			$translations = json_decode($translations, true);
		}

		$translations[$key] = $value;

		Theme::Set('translations', json_encode($translations));
	}

	public static function Prepare($string) {
		return htmlspecialchars($string);
	}

	public static function SetPagename($pageName) {
		Theme::GetInstance()->pageName = $pageName;
	}

	public static function GetPagename() {
		return Theme::GetInstance()->pageName;
	}

	public static function GetUsername() {
		return Theme::GetInstance()->user->userName;
	}

	public static function GetUserHomeLink() {
		return 'index.php?p=' . Theme::GetInstance()->user->homePage;
	}

	public static function GetPageHelpLink() {
		return HelpManager::Link();
	}

	public static function GetClock() {
		return Theme::GetInstance()->dateManager->GetClock();
	}

	public static function ApplicationName() {
		return Theme::GetInstance()->config['app_name'];
	}

	public static function ThemeName() {
		return Theme::GetInstance()->config['theme_name'];
	}

	public static function SourceLink() {
		return (isset(Theme::GetInstance()->config['cms_source_url']) ? Theme::GetInstance()->config['cms_source_url'] : 'https://launchpad.net/xibo/1.7');
	}

	public static function ThemeFolder() {
		return Theme::GetInstance()->name;
	}

	public static function GetConfig($settingName, $default = null) {
		$theme = Theme::GetInstance();

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
	public static function GetMenu($menu) {

		$theme = Theme::GetInstance();
		$array = array();

		if (!$menu = new MenuManager($theme->user, $menu))
			trigger_error($menu->message, E_USER_ERROR);
					
		while ($menuItem = $menu->GetNextMenuItem()) {
			$item = array();
			$item['page'] = Kit::ValidateParam($menuItem['name'], _WORD);
			$item['args'] = Kit::ValidateParam($menuItem['Args'], _STRING);
			$item['class'] = Kit::ValidateParam($menuItem['Class'], _WORD);
			$item['title'] = __(Kit::ValidateParam($menuItem['Text'], _STRING));
			$item['img'] = Kit::ValidateParam($menuItem['Img'], _STRING);
			$item['external'] = Kit::ValidateParam($menuItem['External'], _INT);

			$item['selected'] = ($item['page'] == $theme->pageName);

			if ($item['external'] == 0) {
				$item['link'] = 'index.php?p=' . $item['page'] . '&' . $item['args'];
			}
			else {
				$item['link'] = $item['args'];
			}

			$item['li'] = '<li class="' . $item['class'] . (($item['selected']) ? ' active' : '') . '"><a href="' . $item['link'] . '" class="' . $item['class'] . (($item['selected']) ? ' active' : '') . '">' . $item['title'] . '</a></li>';

			$array[] = $item;
		}

		return $array;
	}

	/**
     * Generate a select list
     * @param string Select list name
     * @param array Array of Values
     * @param string Key for item id
     * @param string Key for item name
     * @param string ID value for selected item
     * @param string Extra attributes to put on the list
     * @param string Key for item class
     * @return string
     */
    public static function SelectList($listName, $listValues, $idColumn, $nameColumn, $selectedId = null, $callBack = '', $classColumn = '', $styleColumn = '')
    {
    	$list = '<select class="form-control" name="' . $listName . '" id="' . $listName . '"' . $callBack . '>';

        foreach ($listValues as $listItem)
        {
            $class = ($classColumn == '') ? '' : 'class="' . $listItem[$classColumn] . '"';
            $style = ($styleColumn == '') ? '' : 'style="' . $listItem[$styleColumn] . '"';
            $list .= '<option ' . $style . ' ' . $class . ' value="' . $listItem[$idColumn] . '" ' . (($listItem[$idColumn] == $selectedId) ? 'selected' : '') . '>' . $listItem[$nameColumn] . '</option>';
        }

        $list .= '</select>';

        return $list;
    }
}
?>
