<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
	
	private $db;
	private $user;
	private $helpManager;
	private $dateManager;

	private $name = '';
	private $pageName = '';
	private $vars = null;
	
	public function __construct(database $db, user $user) {

		// Store some things for the Theme engine to use
		$this->db =& $db;
		$this->user =& $user;
		$this->help = new HelpManager($db, $user);
		$this->dateManager = new DateManager($db);

		// TODO: Perhaps we also allow the user to configure their own theme for their session?

		// What is the currently selected theme?
		$globalTheme = Config::GetSetting($db, 'GLOBAL_THEME_NAME');

		// Is this theme valid?
		if (!is_dir('theme/' . $globalTheme))
			throw new Exception(__('The theme "%s" does not exist', $globalTheme));
		
		// Store the theme name for later
		$this->name = $globalTheme;

		static::$instance = $this;
	}

	/**
	 * GetInstance of Theme
	 */
	private static function GetInstance() {
		if (!isset(static::$instance))
			throw new Exception(__("Theme not initialised"));

		return static::$instance;
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
		// If not, then use the default folder
		elseif (file_exists('theme/default/html/' . $item . '.php')) {
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
	 */
	public static function Image($item) {

		$theme = Theme::GetInstance();
		
		// See if we have the requested file in the theme folder
		if (file_exists('theme/' . $theme->name . '/img/' . $item)) {
			return '<img src="theme/' . $theme->name . '/img/' . $item . '" />';
		}
		// If not, then use the default folder
		elseif (file_exists('theme/default/img/' . $item)) {
			return '<img src="theme/default/img/' . $item . '" />';
		}
		else
			throw new Exception(__('The requested theme item does not exist. [%s, %s]', array($item, $theme->name)));
	}

	/**
	 * Translate a string into the user language
	 * @param string $string The String to Translate
	 * @param array $args   Variables to insert (will replace %d %s in order)
	 */
	public static function Translate($string, $args = null) {
		return __($string, $args);
	}

	public static function Set($key, $value) {
		$theme = Theme::GetInstance();

		$theme->vars[$key] = $value;
	}

	public static function Get($key) {
		$theme = Theme::GetInstance();

		if (!isset($theme->vars[$key]))			
			return null;
		else
			return $theme->vars[$key];
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

	/**
	 * Get Menu
	 * @param string $menu The Name of the Menu
	 * @return array Array containing menu items (page, args, class, title, link, li)
	 */
	public static function GetMenu($menu) {

		$theme = Theme::GetInstance();
		$array = null;

		if (!$menu = new MenuManager($theme->db, $theme->user, $menu))
			trigger_error($menu->message, E_USER_ERROR);
					
		while ($menuItem = $menu->GetNextMenuItem()) {
			$item = array();
			$item['page'] = Kit::ValidateParam($menuItem['name'], _WORD);
			$item['args'] = Kit::ValidateParam($menuItem['Args'], _STRING);
			$item['class'] = Kit::ValidateParam($menuItem['Class'], _WORD);
			$item['title'] = __(Kit::ValidateParam($menuItem['Text'], _STRING));
			$item['selected'] = ($item['page'] == $theme->pageName);
			$item['link'] = 'index.php?p=' . $item['page'] . '&' . $item['args'];
			$item['li'] = '<li class="' . $item['class'] . '"><a href="' . $item['link'] . '" class="' . $item['class'] . (($item['selected']) ? ' current' : '') . '">' . $item['title'] . '</a></li>';

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
    public static function SelectList($listName, $listValues, $idColumn, $nameColumn, $selectedId = '', $callBack = '', $classColumn = '')
    {
        $list = '<select name="' . $listName . '" id="' . $listName . '"' . $callBack . '>';

        foreach ($listValues as $listItem)
        {
            $class = ($classColumn == '') ? '' : 'class="' . $listItem[$classColumn] . '"';
            $list .= '<option ' . $class . ' value="' . $listItem[$idColumn] . '" ' . (($listItem[$idColumn] == $selectedId) ? 'selected' : '') . '>' . $listItem[$nameColumn] . '</option>';
        }

        $list .= '</select>';

        return $list;
    }
}
?>