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

/*
Updates Xibo by:
	1. searching for packages in the update/ directory
	2. entering sub directories
	3. search for a package.xml file
	4. a) read the XML file - ensuring its valid - and determine the update type
	4. b) if its an "update" check the installed version to make sure they match
	4. c) if its an "addon" proceed
	5. Copy the files, as indicated by the XML file
	6. Run any queries in the XML
	7. Removes the Update directory and the Zip file
	8. Complete
*/
class update {
	private $db;
	
	private $log;
	private $update_dir = "update";

	function update(database $db) {
		$this->db =& $db;
	}
	
	function list_available_updates() {
		//lists all the package.xml files in the update directory		
		$list = <<<LIST
		<div class="info_table">
		<table style="width:100%">
			<thead>
				<tr>
					<th>Name</th>
					<th>Type</th>
					<th>Version</th>
					<th>Description</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
LIST;
	
		if (!$structure = scandir($this->update_dir)) trigger_error("Unable to find update directory", E_USER_ERROR);
		
		$dirs;
		// discounts directories that we arnt interested in (such as filesystem ones, hidden ones and ones without a package.xml file in them
		for ($i = 0; $i < count($structure); $i++) {
			if (substr($structure[$i],0,1) != ".") {
			
				//check for an XML file
				if (file_exists($this->update_dir . "/" . $structure[$i] . "/package.xml")) {
					$dirs[] = $structure[$i];
				}
			}
		}
		
		$dirs = array_map(array($this,'read_dir_xml'), $dirs);
		
		for ($i = 0; $i < count($dirs); $i++) {
		
			$name = $dirs[$i][0];
			$type = $dirs[$i][1];
			$ver = $dirs[$i][2];
			$desc = $dirs[$i][3];
			$filename = $dirs[$i][4];
			
			//check to see if this update has already been run, or not
			if (!$result = $this->db->query("SELECT * FROM `update` WHERE name = '$name' AND type = '$type' AND done = 1 ")) trigger_error($this->db->error());
			
			if ($this->db->num_rows($result)>0) {
				//already updated
				$action = "Update already installed";
			}
			else {
				$action = <<<END
				<form class="update" action="index.php?p=update&q=init_update">
					<input type="hidden" value="$filename" name="filename">
					<button type="submit">Update</button>
				</form>
END;
			}
			
			$list .= <<<LIST
			<tr>
				<td>$name</td>
				<td>$type</td>
				<td>$ver</td>
				<td>$desc</td>
				<td>$action</td>
			</tr>
LIST;
		}
		
		$list .= <<<LIST
			</tbody>
		</table>
		</div>
LIST;
		echo $list;
	}
	
	function read_dir_xml($dir) {
		//reads the directory XML and returns as an array
		$xml = simplexml_load_file($this->update_dir . "/" . $dir . "/package.xml");
		
		$update_package[] = (string) $xml->name[0];
		$update_package[] = (string) $xml['type'];
		$update_package[] = (string) $xml->version[0];
		$update_package[] = (string) $xml->description[0];
		$update_package[] = $this->update_dir . "/" . $dir;
		
		return $update_package;
	}
	
	function init_update() {
		//inits the update - this will be called by some AJAX and should OUTPUT:
			// 0 | $message - when there is a failure
			// 1 | $success_html | delete success / failure - taken from within the package.xml
		$db =& $this->db;

		$filename = $_POST['filename']; //the location of the package XML

		$this->log .= "Starting Update<br />";
		
		if ($filename == "") {
			return "0|No filename provided, invalid update package";
		}
		
		$xml = simplexml_load_file($filename . "/package.xml");
		
		if (!$xml) {
			return "0|There is an error in the update package xml";
		}
		
		$this->log .= "Files found, continuing with Update<br />";
		
		//check what type of update this is (update/addon)
		$type = (string) $xml['type'];
		
		
		//move the files in <files>
		if (!$this->file_block($xml->files, $filename)) {
			return "0|Update failed copying files";
		}

		//run the queries in <queries>
		$this->query_block($xml->queries, $xml['type']);
		
		$this->log .= "Success - update complete.";
		
		//if this is an update package, update the App version information
		if ($type == "update") {
			$db->query("UPDATE version SET app_ver = '".(string)$xml->version."', db_ver='".(string)$xml->dbversion."'");
		}
		
		//leave the update there, but mark it as done (by adding it to the database, or marking it if it exists)
		$this->record((string) $xml->name, $filename, (string) $xml->version, 1, $type, $this->log);
		
		//output the success in <success> (if we get here we have suceeded)
		return "1|". $xml->success->asXML()."<div><a id='details_trigger' href='#'>Details...</a><div id='details'>" . $this->log . "</div></div>";
	}
	
	function file_block($xml, $package_dir) {
		//for each file that is in the package.xml copy it to the relevant Root location
		foreach($xml->file as $file) {
		
			if (is_dir($package_dir.'/'.$file)) {
				@mkdir($file); //try and make the same directory
			}
			else {
				//copys the file, overwriting if its already there
				if (!copy($package_dir.'/'.$file, $file)) {
					//what do we do if it fails? We probabily have a part update or something, not so good
					trigger_error("Update failed copying files");
					echo "0|Update failed copying files";
					exit;
				}
				else {
					$this->log .= "Copied file: $file<br />";
				}
			}
		}
		return true;
	}
	
	function query_block($xml, $type) {
		$db =& $this->db;
		
		//query block of the xml - will give us a list of queries to run, and what version DB they are for
		foreach($xml->query as $SQL) {
			if ($SQL['version'] == "" && $type != "update") {
				
				$query = (string) $SQL;
			
				if ($SQL['useid']=="true") {
					//we need to do some clever replacement with the string
					$num_ids = count($ids);
					
					$this->log .= "$num_ids stored for replacement <br/>";
					
					$count = 0;
					foreach ($ids as $id_replace) {
						$count++;
						
						$this->log .= "Trying to replace [[$count]] with $id_replace <br/>";
						
						$query = str_replace("[[$count]]",$id_replace, $query);
					}
					
					unset($ids); //clear down the array now that we have used the ID's
				}
			
				//version independant
				$id = $db->insert_query($query);
				$this->log .= "Run SQL [$query]<br />";
				
				if ($SQL['getid']=="true") {
					//store the ID for later
					$ids[] = $id;
				}
			}
			else if (XSM_DB_VERSION < $SQL['version']) {
				if (!$id = $db->insert_query((string)$SQL)) {
					$this->log .= "Run SQL Error: ". $db->error() ."<br />";
				}
				else {
					$this->log .= "Run SQL Sucess [$SQL]<br />";
				}
			}
		}
	}
	
	function record($name, $location, $version, $done, $type, $log) {
		$db =& $this->db;
		//record results of this update in the update table
		$current_date = date("Y-m-d H:i:s");
		
		$log = $db->escape_string($this->log);
		
		$SQL = <<<SQL
		INSERT INTO `update` ( name, location, version, done, date_updated, `type`, log )
				VALUES ('$name', '$location', '$version', $done, '$current_date', '$type', '$log') 
SQL;
		if (!$db->query($SQL)) {
			trigger_error($db->error());
		}
	}
}
?>