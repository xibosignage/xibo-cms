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
 
class Config {

	static function Load() 
	{
			
		include("settings.php");
		
	}
	
	//Gets the required setting from the DB
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
	
		define('VERSION', $appVer);
		
		if ($object != '')
		{
			return Kit::GetParam($object, $row, _STRING, false);
		}
		
		return $row;
	}
}

?>