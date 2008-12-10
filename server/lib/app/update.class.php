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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */ 
class updateDAO {
	private $db;
	private $update;
	
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";

	//init
	function updateDAO(database $db) {
		$this->db =& $db;
		
		require("lib/update.class.php");
		$update = new update($db);
		
		if ($_SESSION['usertype']==1) $this->isadmin = true;
		
		if (isset($_REQUEST['sp'])) {
			$this->sub_page = $_REQUEST['sp'];
		}
		else {
			$this->sub_page = "view";
		}
		
		$this->update = $update;
	}
	
	function on_page_load() {
    	return;
	}
	
	function echo_page_heading() {
		echo "Update Manager";
		return true;
	}
	
	function upgrade_slide_wrap() {
		$db =& $this->db;
		
		include("update/slide_update_script.php");
		
		upgrade_slides($db);
		
		exit;
	}
	
	function upgrade_event_wrap() {
		$db =& $this->db;
		
		include("update/event_update_script.php");
		
		upgrade_events($db);
		
		exit;
	}
	
	function upgrade_media_wrap() {
		$db =& $this->db;
		
		include("update/media_update_script.php");
		
		upgrade_slides($db);
		
		exit;
	}
		
	function displayPage() {
		$db =& $this->db;
				
		if (!$this->has_permissions) {
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) {
				
			case 'view':
				include("template/pages/update_view.php");
				break;
				
			case 'add':
				include("template/pages/update_add.php");
				break;
					
			default:
				break;
		}
		return false;
	}
	
	function init_update() {
		echo $this->update->init_update();
		return false; //dont proceed further
	}
	
	function available_updates() {
		$db =& $this->db;
	
		//Check that PHP modules are enabled... what do we need? 
		$extensions = get_loaded_extensions();

		if (!array_search('xmlrpc', $extensions)) {
			echo "XML RPC not available, please check your settings.";
		}
		else {
			$this->get_update(); //will check and download all available updates
		}
		
		$this->update->list_available_updates();
		
		return false; //dont redirect or anything
	}
	
	function get_update() {
		$db =& $this->db;
		//we need to consume a webservice here - which may be an issue for the automatic updator...
		$request = xmlrpc_encode_request("xibo_update_request", array($_SERVER['SERVER_NAME']));
		
		$context = stream_context_create(array('http' => array(
		    'method' => "POST",
		    'header' => "Content-Type: text/xml",
		    'content' => $request
		)));
		
		$xws_uri = config::getSetting($db,"update_location");
		
		$file = file_get_contents("http://xmdev01.local/xws/update.php", false, $context);
		$response = xmlrpc_decode_request($file, $method);
		$response = xmlrpc_decode($response);
		
		if ($response == "" || $response == "None") { //either no updates OR and error
			return;
		}
		
		//check we were authed
		if (substr($response,0,1) == "0") {
			displayMessage(MSG_MODE_MANUAL, $response, false);
			return;
		}
		
		//use SimpleXML to work through the response
		if (!$xml = simplexml_load_string($response)) {
			trigger_error("Malformed XML returned from the Xibo server [$response]");
			return;
		}
		
		foreach ($xml->file as $file) {
			$source = (string) $file->source;
			$dest_dir = "update";
			
			//get the file using the webservice
			$request = xmlrpc_encode_request("xibo_get_file", array($_SERVER['SERVER_NAME'], $source));
			
			$context = stream_context_create(array('http' => array(
			    'method' => "POST",
			    'header' => "Content-Type: text/xml",
			    'content' => $request
			)));
			
			$file = file_get_contents("http://xmdev01.local/xws/update.php", false, $context);
			$response = xmlrpc_decode_request($file, $method);
			$response = xmlrpc_decode($response);
			
			$response = $response->scalar;
			
			if ($response == "") {
				displayMessage(MSG_MODE_MANUAL, "Empty file returned when requested: [$source] ");
				exit;
			}
			
			if (!file_put_contents($dest_dir.'/tmp',$response)) {
				displayMessage(MSG_MODE_MANUAL, "Unable to copy the update file <br /> [$dest_dir/tmp] ");
				exit;
			}
			
			$zip = new ZipArchive();
			$zip->open($dest_dir.'/tmp');
			
			$zip->extractTo($dest_dir);
			
			$zip->close();
			
			//need a call back to say that its been recieved
			$request = xmlrpc_encode_request("xibo_mark_recieved", array($_SERVER['SERVER_NAME'], $source));
		
			$context = stream_context_create(array('http' => array(
			    'method' => "POST",
			    'header' => "Content-Type: text/xml",
			    'content' => $request
			)));
			
			$file = file_get_contents("http://xmdev01.local/xws/update.php", false, $context);
			$response = xmlrpc_decode_request($file, $method);
			$response = xmlrpc_decode($response);
			
			if ($response != "1") {
				trigger_error("Failed when marking as recieved, we might get this update again");
			}
			
			return false;
		}
	}

	function display_upload_form() {
		$form = <<<END
<form action="index.php?p=update&q=upload_zip" method="post" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="1048576000">
	<table>
		<tr> 
			<td>Find your Update Package</td>
			<td>
				<input name="package" type="file">
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<div class="buttons">
					<button class="positive" type='submit'>Update</button>
					<a class="negative" href="index.php?p=update" alt="Cancel">Cancel</a>
				</div>
			</td>
		</tr>
	</table>
</form>
END;
		echo $form;
	}
	
	function upload_zip() {
		//uploads a user submitted Zip file. and decompresses it
		if(!$temp_file = $_FILES['package']) {
			displayMessage(MSG_MODE_MANUAL, "No file selected");
		}
		
		$extensions = get_loaded_extensions();
		if (!array_search('zip', $extensions)) {
			echo "<p>Zip must be enabled to use Updates</p>";
			exit;
		}
		
		$zip = new ZipArchive();
		
		if ($zip->open($temp_file['tmp_name']) === false) {
			displayMessage(MSG_MODE_MANUAL, "Unable to open the file, check your upload settings");
			exit;
		}
		$zip->extractTo('update');
		
		$zip->close();
		
		return "index.php?p=update";
	}
}
?>