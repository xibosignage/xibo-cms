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

class moduleDAO 
{
	private $db;
	private $user;
	private $module;

	/**
	 * Module constructor.
	 * @return 
	 * @param $db Object
	 */
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
		
		$mod = Kit::GetParam('mod', _REQUEST, _WORD);
		
		// If we have the module - create an instance of the module class
		// This will only be true when we are displaying the Forms
		if ($mod != '') 
		{			
			require_once("modules/$mod.module.php");
			
			// Try to get the layout, region and media id's
			$layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);
			$regionid = Kit::GetParam('regionid', _REQUEST, _STRING);
			$mediaid  = Kit::GetParam('mediaid', _REQUEST, _STRING);
			
			Debug::LogEntry($db, 'audit', 'Creating new module with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);
			
			$this->module = new $mod($db, $user, $mediaid, $layoutid, $regionid);
		}
		
		return true;
	}
	
	/**
	 * No display page functionaility
	 * @return 
	 */
	function displayPage() 
	{
		return false;
	}
	
	/**
	 * No onload
	 * @return 
	 */
	function on_page_load() 
	{
		return '';
	}
	
	/**
	 * No page heading
	 * @return 
	 */
	function echo_page_heading() 
	{
		return true;
	}
	
	/**
	 * What action to perform?
	 * @return 
	 */
	public function Exec()
	{
		// What module has been requested?
		$method	= Kit::GetParam('method', _REQUEST, _WORD);
		
		if (method_exists($this->module,$method)) 
		{
			$response = $this->module->$method();
		}
		else
		{
			// Set the error to display
			$response = new ResponseManager();
			$response->SetError(__('This Module does not exist'));
		}
		
		$response->Respond();
	}

	/**
	 * Returns an image stream to the browser - for the mediafile specified.
	 * @return 
	 */
	function GetImage()
	{
		$db 		=& $this->db;
		
		$file 		= Kit::GetParam('file', _REQUEST, _STRING);
		$dynamic	= isset($_REQUEST['dynamic']);
		
		//File upload directory.. get this from the settings object
		$library 	= Config::GetSetting($db, "LIBRARY_LOCATION");
		
		$fileName 	= $library . $file;
		$uid 		= $fileName;
		
		// Get the info for this new temporary file
		$info 		= getimagesize($uid);
		
		if ($dynamic && $info[2] != IMAGETYPE_GIF)
		{
			$width 	= Kit::GetParam('width', _GET, _INT);
			$height = Kit::GetParam('height', _GET, _INT);
			
			// dynamically create an image of the correct size - used for previews
			ResizeImage($uid, "", $width, $height, true, 'browser');
			
			exit;
		}

		if (!$image = file_get_contents($uid))
		{
			//not sure
			Debug::LogEntry($db, "audit", "Cant find: $uid", "module", "GetImage");
			
			$uid 	= "img/forms/filenotfound.png";
			$image 	= file_get_contents($uid);
		}
		
		$size = getimagesize($uid);
		
		//Output the image header
		header("Content-type: {$size['mime']}");
		
		echo $image;
		
		exit;
	}
}
?>