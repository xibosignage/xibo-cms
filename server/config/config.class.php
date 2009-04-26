<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
 
class Config 
{
	private $db;
	private $extensions;
	private $envFault;
	
	public function __construct(database $db)
	{
		$this->db			=& $db;
		
		// Populate an array of loaded extensions just in case we need it for something.
		$this->extensions 	= get_loaded_extensions();
		
		// Assume the environment is OK
		$this->envFault		= false;
		
		return;
	}
	
	/**
	 * Loads the settings from file.
	 * @return 
	 */
	static function Load() 
	{
		include("settings.php");
	}
	
	/**
	 * Gets the requested setting from the DB object given
	 * @return 
	 * @param $db Object
	 * @param $setting Object[optional]
	 */
	static function GetSetting(database $db, $setting = "") 
	{		
		$SQL = "";
		$SQL.= sprintf("SELECT value FROM setting WHERE setting='%s'", $setting);
		
		if(!$results = $db->query($SQL, true))
		{
			trigger_error($db->error());
			trigger_error('Unable to get setting: ' . $setting, E_USER_WARNING);			
		} 
		
		if($db->num_rows($results)==0) 
		{
			return false;
		}
		else 
		{
			$row = $db->get_row($results);
			return $row[0];
		}
	}
	
	/**
	 * Defines the Version and returns it
	 * @return 
	 * @param $db Object
	 * @param $object String [optional]
	 */
	static function Version(database $db, $object = '') 
	{
		if (!$results = $db->query("SELECT app_ver, XlfVersion, XmdsVersion, DBVersion FROM version")) 
		{
			trigger_error("No Version information - please contact Xibo support", E_USER_WARNING);
		}
		
		$row 		= $db->get_assoc_row($results);
		
		$appVer		= Kit::ValidateParam($row['app_ver'], _STRING);
		$xlfVer		= Kit::ValidateParam($row['XlfVersion'], _INT);
		$xmdsVer	= Kit::ValidateParam($row['XmdsVersion'], _INT);
	
		if (!defined('VERSION')) define('VERSION', $appVer);
		
		if ($object != '')
		{
			return Kit::GetParam($object, $row, _STRING, false);
		}
		
		return $row;
	}
	
	/**
	 * Checks the Environment and Determines if it is suitable for Xibo
	 * @return 
	 */
	public function CheckEnvironment()
	{
		$db 	 =& $this->db;
		
		$output  = '';
		$imgGood = '<img src="install/dot_green.gif"> ';
		$imgBad  = '<img src="install/dot_red.gif"> ';
		
		$output .= '<div class="checks">';
		
		// Check for PHP version
		$message = __('PHP Version 5.0.2 or later');

		if ($this->CheckPHP()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"> <p>' . __("Xibo requires PHP version 5.0.2 or later.") . '</p></div>';
		}
		
		// Check for file system permissions
		$message = __('Filesystem Permissions');

		if ($this->CheckFsPermissions()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __("Xibo needs to be able to write to the following:");
			$output .= <<<END
      			<ul>
        			<li> settings.php
        			<li> install.php
				<li> upgrade.php
      			</ul>
END;
      			$output .= __('Please fix this, and retest.') . '</p></div>';
		}
		
		// Check for MySQL
		$message = __('MySQL Extension');

		if ($this->CheckMySQL()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo requires a MySQL database. Ensure PHP MySQL client extension is installed') . '</p></div>';
		}
		
		// Check for JSON
		$message = __('JSON Extension');

		if ($this->CheckJson()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo needs the PHP JSON extension to function.') . '</p></div>';
		}
		
		// Check for GD (graphics)
		$message = __('GD Extension');

		if ($this->CheckGd()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo needs the PHP GD extension to function.') . '</p></div>';
		}
		
		
		// Check for Calendar
		$message = __('Calendar Extension');

		if ($this->CheckCal()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo needs the PHP Calendar extension to function.') . '</p></div>';
		}
		
		// Check for DOM
		$message = __('DOM Extension');

		if ($this->CheckDom()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo needs the PHP DOM core functionality enabled.') . '</p></div>';
		}
		
		// Check for DOM XML
		$message = __('DOM XML Extension');

		if ($this->CheckDomXml()) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('Xibo needs the PHP DOM XML extension to function.') . '</p></div>';
		}
		
		// Check to see if we are allowed to open remote URLs (homecall will not work otherwise)
		$message = __("Allow PHP to open external URLs");

		if (ini_get('allow_url_fopen')) 
		{
			$output .= $imgGood.' '.$message.'<br />';
		}
		else
		{
			// Not a fault as this will not block installation/upgrade. Informational.
			$this->envFault = false;
			
			$output .= $imgBad.' '.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('You must have allow_url_fopen = On in your PHP.ini file for statistics gathering to function.') . '<br />';
			$output .= __('If you do not intend to enable homecall you need not worry about this problem.') . '</p></div>';
		}
				
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Is there an environment fault
	 * @return 
	 */
	public function EnvironmentFault()
	{
		return $this->envFault;
	}
	
	/**
	 * Check FileSystem Permissions
	 * @return 
	 */
	function CheckFsPermissions() 
	{
	  return ((is_writable("install.php") && (is_writable("settings.php")) && (is_writable("upgrade.php")) || is_writable(".")));
	}
	
	/**
	 * Check PHP version > 5
	 * @return 
	 */
	function CheckPHP() 
	{
		return (version_compare("5",phpversion(), "<="));
	}
	
	/**
	 * Check PHP has MySQL module installed
	 * @return 
	 */
	function CheckMySQL() 
	{
		return extension_loaded("mysql");
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
	 * Check PHP has the DOM functionality installed
	 * @return 
	 */
	function CheckDom()
	{
		return class_exists("DOMDocument");
	}
}

?>
