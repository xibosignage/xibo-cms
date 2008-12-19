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
	private $module;

	/**
	 * Module constructor.
	 * @return 
	 * @param $db Object
	 */
	function __construct(database $db) 
	{
		$this->db =& $db;
		
		$mod = Kit::GetParam('mod', _REQUEST, _WORD);
		
		// If we have the module - create an instance of the module class
		// This will only be true when we are displaying the Forms
		if ($mod != '') 
		{			
			$moduleName = 'media_'.$mod;
			
			require_once("modules/module_$moduleName.php");
			
			$this->module = new $moduleName();
			
			$this->module->SetDb($db);
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
		return "";
	}
	
	/**
	 * Echo's the page heading
	 * @return 
	 */
	function echo_page_heading() 
	{
		echo "Module Admin";
		return true;
	}	
	
	/**
	 * Sets the media id for this module class
	 * @return 
	 * @param $id Object
	 */
	function SetMediaId($id)
	{
		$db =& $this->db;
		
		$SQL = sprintf("SELECT type FROM media WHERE mediaID = %d ", $id);
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Cant get this medias type");
			return false;
		}
		
		$row = $db->get_row($results);
		
		//we have the type
		$type = Kit::ValidateParam($row[0], _STRING);
		
		//we require the standard class
		$className = "media_".$type;
		
		require_once("modules/module_$className.php");
		
		//Create the class object
		$this->module = new $className();
		
		//Give it the Db Object
		$this->module->SetDb($db);
		
		//Set the media ID of this module object
		if (!$this->module->SetMediaId($id)) return false;
		
		return true;
	}

	/**
	 * A wrapper for the modules own method
	 * @return 
	 */
	public function AddForm()
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		$form = $this->module->AddForm();
		
		if (!$form) 
		{
			$arh->decode_response(false,$this->module->message);
		}
		$arh->decode_response(true, $form);
		
		return false;
	}
	
	/**
	 * A wrapper for the modules own method
	 * @return 
	 */
	public function EditForm()
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		$form = $this->module->EditForm();
		
		if (!$form) 
		{
			$arh->decode_response(false,$this->module->message);
		}
		$arh->decode_response(true, $form);
		
		return false;
	}
	
	/**
	 * A wrapper for the modules own method
	 * @return 
	 */
	public function DeleteForm()
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		$form = $this->module->DeleteForm();
		
		if (!$form) 
		{
			$arh->decode_response(false,$this->module->message);
		}
		$arh->decode_response(true, $form);
		
		return false;
	}

	/**
	 * Calls the relevant add procedure
	 * @return 
	 */
	function AddMedia() 
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		if (!isset($_REQUEST['termsOfService']))
		{
			$arh->decode_response(false, "Media cannot be added without agreeing to the terms of service.");
		}
		
		//Optional parameters
		$layoutid = Kit::GetParam('layoutid', _POST, _INT);
		$regionid = Kit::GetParam('regionid', _POST, _STRING);
		
		if (!$this->module->AddMedia())
		{
			$arh->decode_response(false, $this->module->message);
		}
		
		if ($regionid != '') //layout page
		{
			$arh->response(AJAX_LOAD_FORM, urlencode("index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions")."||region_options_callback");
		}
		else //Context page
		{
			$arh->decode_response(true, "Media Added");
		}
		
		return;
	}

	/**
	 * Calls the relevant modify procedure
	 * @return 
	 */
	function EditMedia() 
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		if (!isset($_REQUEST['termsOfService']))
		{
			$arh->decode_response(false, "Media cannot be edited without agreeing to the terms of service.");
		}
		
		//Optional parameters
		if (isset($_POST['layoutid'])) $layoutid = Kit::GetParam('layoutid', _POST, _INT);
		if (isset($_POST['regionid'])) $regionid = Kit::GetParam('regionid', _POST, _STRING);
		
		if (!$this->module->EditMedia())
		{
			$arh->decode_response(false, $this->module->message);
		}
		
		if ($regionid != "") //layout page
		{
			$arh->response(AJAX_LOAD_FORM, urlencode("index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions")."||region_options_callback");
		}
		else //Context page
		{
			$arh->decode_response(true, "Media Edited");
		}
		
		return;
	}
	
	/**
	 * Calls the relevant delete procedure
	 * @return 
	 */
	function DeleteMedia() 
	{
		//ajax request handler
		$arh = new AjaxRequest();
		
		//Optional parameters
		if (isset($_POST['layoutid'])) $layoutid = Kit::GetParam('layoutid', _POST, _INT);
		if (isset($_POST['regionid'])) $regionid = Kit::GetParam('regionid', _POST, _STRING);
		
		if (!$this->module->DeleteMedia())
		{
			$arh->decode_response(false, $this->module->message);
		}
		
		if ($regionid != "") //layout page
		{
			$arh->response(AJAX_LOAD_FORM, "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions||region_options_callback");
		}
		else //Context page
		{
			$arh->decode_response(true, "Media Deleted");
		}
		
		return;
	}
	
	/**
	 * Calls the relevant AsXml procedure
	 * @return 
	 */
	function AsXml() 
	{
		return $this->module->AsXml();
	}
	
	/**
	 * Returns an image stream to the browser - for the mediafile specified.
	 * @return 
	 */
	function GetImage()
	{
		$db =& $this->db;
		
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