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
	private $envTested;
	private $envFault;
	private $envWarning;
	
	public function __construct(database $db)
	{
		$this->db			=& $db;
		
		// Populate an array of loaded extensions just in case we need it for something.
		$this->extensions 	= get_loaded_extensions();
		
		// Assume the environment is OK
		$this->envFault		= false;
		$this->envWarning	= false;
		$this->envTested	= false;
		
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
		$imgWarn = '<img src="install/dot_amber.gif"> ';
		
		$output .= '<div class="checks">';
		
		// Check for PHP version
		$message = 'PHP Version 5.2.0 or later';

		if ($this->CheckPHP() == 1) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else if ($this->CheckPHP() == 2)
		{
			$output .= $imgWarn.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
			<p>Xibo requires PHP version 5.2.0 or later. It may run on PHP 5.1.0 and we have provided compatibility functions to enable that.</p>
			<p>However, we recommend upgrading your version of PHP to 5.2.0 or later.</p>
			</div>
END;
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo requires PHP version 5.2.0 or later.</p>
      		</div>
END;
		}
		
		// Check for file system permissions
		$message = 'Filesystem Permissions';

		if ($this->CheckFsPermissions()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs to be able to write to the following
      			<ul>
        			<li> settings.php
        			<li> install.php
					<li> upgrade.php
      			</ul>
      			Please fix this, and retest.</p>
      		</div>
END;
		}
		
		// Check for MySQL
		$message = 'MySQL';

		if ($this->CheckMySQL()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo requires a MySQL database.</p>
      		</div>
END;
		}
		
		// Check for JSON
		$message = 'JSON Extension';

		if ($this->CheckJson()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs the PHP JSON extension to function.</p>
      		</div>
END;
		}
		
		// Check for GD (graphics)
		$message = 'GD Extension';

		if ($this->CheckGd()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs the PHP GD extension to function.</p>
      		</div>
END;
		}
		
		
		// Check for Calendar
		$message = 'Calendar Extension';

		if ($this->CheckCal()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs the PHP Calendar extension to function.</p>
      		</div>
END;
		}
		
		// Check for DOM
		$message = 'DOM Extension';

		if ($this->CheckDom()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs the PHP DOM core functionality enabled.</p>
      		</div>
END;
		}
		
		// Check for DOM XML
		$message = 'DOM XML Extension';

		if ($this->CheckDomXml()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs the PHP DOM XML extension to function.</p>
      		</div>
END;
		}
		
		// Check to see if we are allowed to open remote URL's (homecall will not work otherwise)
		$message = 'Allow PHP to open external URL\'s';

		if (ini_get('allow_url_fopen')) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			// Not a fault as this will not block installation/upgrade. Informational.
			$this->envWarning = true;
			$output .= $imgWarn.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>You must have allow_url_fopen = On in your php.ini file for anonymous statistics gathering to function.<br />
				If you do not intend to enable anonymous statistics gathering you need not worry about this problem.</p>
	      		</div>
END;
		}

		// Check to see if timezone_identifiers_list exists
		$message = 'DateTimeZone';

		if (function_exists('timezone_idenitfiers_list')) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envWarning = true;
			
			$output .= $imgWarn.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo needs to be able to get a list of timezones. Your version of PHP does not support this<br />
			We have provided a compatibility function so that Xibo still works, but you are advised to upgrade to PHP > 5.2.0</p>
      		</div>
END;
		}
				
		$output .= '</div>';
		
		$this->envTested = true;
		return $output;
	}
	
	/**
	 * Is there an environment fault
	 * @return 
	 */
	public function EnvironmentFault()
	{
		if (! $this->envTested) {
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
		if (! $this->envTested) {
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
	  return ((is_writable("install.php") && (is_writable("settings.php")) && (is_writable("upgrade.php")) || is_writable(".")));
	}
	
	/**
	 * Check PHP version > 5
	 * @return 
	 */
	function CheckPHP() 
	{
		if (phpversion() >= '5.2.0') {
			return 1;
		}
	
		if (phpversion() >= '5.1.0') {
			return 2;
		}

		return 0;
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
