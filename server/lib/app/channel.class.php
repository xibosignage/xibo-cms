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
class channelDAO 
{
	private $db;
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";

	//
	
	//general fields
	private $channelid;
	private $channel = "";
	private $desc = "";
	private $mediaid;
	private $medianame = "";
	
	//channel code
	private $caching 		= 200;
	private $chroma 		= "";
	private $fps 			= 0;
	private $tuner_channel 	= "";
	private $tuner_country 	= 0;
	private $tuner_input 	= 2;
	private $video_input 	= -1;
	private $audio_input 	= -1;
	private $video_output 	= -1;
	private $audio_output 	= -1;
	
	//init
	function __construct(database $db) 
	{
		$this->db =& $db;
		
		if ($_SESSION['usertype']==1) $this->isadmin = true;
		
		if (isset($_REQUEST['sp'])) 
		{
			$this->sub_page = $_REQUEST['sp'];
		}
		else 
		{
			$this->sub_page = "view";
		}
		
		//if available get the layoutid
		if (isset($_REQUEST['channelid'])) 
		{
			$this->channelid = clean_input($_REQUEST['channelid'], VAR_FOR_SQL, $db);
		}
		
		switch ($this->sub_page) 
		{
		
			case 'edit':
								
				if ($this->channelid == "") 
				{
					displayMessage(MSG_MODE_MANUAL, "No channel ID present on edit page.");
					exit;
				}
				
				$SQL = <<<END
				SELECT 	channel.caching, 
						channel.chroma, 
						channel.fps, 
						channel.tuner_channel, 
						channel.tuner_country, 
						channel.tuner_input, 
						channel.video_input, 
						channel.audio_input, 
						channel.video_output, 
						channel.audio_output,
						channel.channel,
						channel.description,
						channel.mediaid,
						media.medianame
				FROM channel
				INNER JOIN media
				ON channel.mediaID = media.mediaid
				WHERE channelID = $this->channelid
END;
				
				if (!$results = $db->query($SQL)) 
				{
					trigger_error($db->error());
					trigger_error("Can not get Channel information.", E_USER_ERROR);
				}
				
				$aRow = $db->get_row($results);
				
				$this->caching 			= $aRow[0];
		        $this->chroma 			= $aRow[1];
		        $this->fps 				= $aRow[2];
		        $this->tuner_channel 	= $aRow[3];
		        $this->tuner_country 	= $aRow[4];
		        $this->tuner_input 		= $aRow[5];
		        $this->video_input 		= $aRow[6];
		        $this->audio_input 		= $aRow[7];
		        $this->video_output		= $aRow[8];
		        $this->audio_output 	= $aRow[9];
				
		        $this->channel 			= $aRow[10];
		        $this->desc 			= $aRow[11];
		        $this->mediaid 			= $aRow[12];
		        $this->medianame 		= $aRow[13];

				break;
		}
	}
	
	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		echo "Channels";
		return true;
	}
	
	function channel_filter() 
	{
		$db =& $this->db;
		
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['channel']['name'])) $filter_name = $_SESSION['channel']['name'];
		
		$output = <<<END
		<form id="filter_form">
			<input type="hidden" name="p" value="channel">
			<input type="hidden" name="q" value="channel_view">
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" value="$filter_name"></td>
				</tr>
			</table>
		</form>
END;
		echo $output;
	}
	
	function channel_view() 
	{
		$db =& $this->db;
		
		$filter_name = clean_input($_REQUEST['name'], VAR_FOR_SQL, $db);
		setSession('channel', 'name', $filter_name);
	
		$SQL = <<<END
		SELECT 	channel.caching, 
				channel.chroma, 
				channel.fps, 
				channel.tuner_channel, 
				channel.tuner_country, 
				CASE WHEN channel.tuner_input = 0 THEN
				'Default'
				WHEN channel.tuner_input = 1 THEN
				'Cable'
				ELSE
				'Antenna'
				END AS tuner_input, 
				channel.video_input, 
				channel.audio_input, 
				channel.video_output, 
				channel.audio_output,
				channel.channel,
				channel.description,
				channel.mediaid,
				media.medianame,
				channel.channelID
		FROM channel
		INNER JOIN media
		ON channel.mediaID = media.mediaid
		WHERE 1=1
END;
		if ($filter_name != "") 
		{
			$SQL .= " AND channel.name LIKE '%$filter_name%' ";
		}
		
		$SQL .= " ORDER BY channel.channel ";
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can not get Channel information.", E_USER_ERROR);
		}
		
		$table = <<<END
		<div class="info_table">
		<table style="width:100%;">
			<thead>
				<tr>
					<th>Name</th>
					<th>Tuner Channel</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
END;
		echo $table;
		
		while ($row = $db->get_row($results)) 
		{
		
			$caching 		= $row[0];
			$chroma 		= $row[1];
			$fps 			= $row[2];
			$tuner_channel 	= $row[3];
			$tuner_country 	= $row[4];
			$tuner_input 	= $row[5];
			$video_input 	= $row[6];
			$audio_input 	= $row[7];
			$video_output	= $row[8];
			$audio_output 	= $row[9];
			
			$channel 		= $row[10];
			$desc 			= $row[11];
			$mediaid 		= $row[12];
			$medianame 		= $row[13];
			$channelid		= $row[14];
			
			//we only want to show certain buttons, depending on the user logged in
			if ($_SESSION['usertype']!="1") 
			{
				//dont any actions
				$buttons = "No available Actions";
			}
			else 
			{
				$buttons = <<<END
				<a class="positive" href="index.php?p=channel&sp=edit&channelid=$channelid">Edit</a>
				<a class="negative" href="index.php?p=channel&q=delete&channelid=$channelid">Delete</a>
END;
			}
			
			$table = <<<END
			<tr>
				<td>$channel</td>
				<td>$tuner_channel</td>
				<td>
					<div class="buttons">
						$buttons
					</div>
				</td>
			</tr>
END;
			echo $table;
		}
		echo "</tbody></table></div>";
		
	}
	
	function displayPage() 
	{
		$db =& $this->db;
		
		if (!$this->has_permissions) 
		{
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) 
		{
				
			case 'view':
				require("template/pages/channel_view.php");
				break;
			
			case 'add':
				require("template/pages/channel_add.php");
				break;
				
			case 'edit':
				require("template/pages/channel_edit.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	function channel_form ($action, $refer, $failrefer, $onsubmit) 
	{
		//make up the tuner input list
		$tuner_input = listcontent("0|Default,1|Cable,2|Antenna","tuner_input", $this->tuner_input);

		$form = <<<END
		
		<form id="channel_form" action="$action" method="post" $onsubmit>
			<input type="hidden" name="refer" value="$refer">
			<input type="hidden" name="failrefer" value="$failrefer">
			<input type="hidden" name="channelid" value="$this->channelid">
			<input type="hidden" name="mediaid" value="$this->mediaid">
			
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="channel" value="$this->channel"></td>
					<td>Description</td>
					<td><input type="text" name="desc" value="$this->desc"></td>
				</tr>
				<tr>
					<td>Channel</td>
					<td><input type="text" name="tuner_channel" value="$this->tuner_channel"></td>
					<td>Input Type</td>
					<td>$tuner_input</td>
				</tr>
				<tr>
					<td>Caching</td>
					<td><input type="text" name="caching" value="$this->caching"></td>
					<td>FPS</td>
					<td><input type="text" name="fps" value="$this->fps"></td>
				</tr>
				<tr>
					<td>Chroma</td>
					<td><input type="text" name="chroma" value="$this->chroma"></td>
					<td>Country</td>
					<td><input type="text" name="tuner_country" value="$this->tuner_country"></td>
				</tr>
				<tr>
					<td>Video Input</td>
					<td><input type="text" name="video_input" value="$this->video_input"></td>
					<td>Audio Input</td>
					<td><input type="text" name="audio_input" value="$this->audio_input"></td>
				</tr>
				<tr>
					<td>Video Output</td>
					<td><input type="text" name="video_output" value="$this->video_output"></td>
					<td>Audio Output</td>
					<td><input type="text" name="audio_output" value="$this->audio_output"></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<div class="buttons">
							<button type="submit">Save</button>
							<a class="negative" href="$refer" alt="Cancel">Cancel</a>
						</div>
					</td>
				</tr>
			</table>
		</form>
END;
		echo $form;
	
		return true;
	}
	
	function add() 
	{
		$db =& $this->db;
		
		$refer 			= $_POST['refer'];
		$failrefer 		= $_POST['failrefer'];
		
		$channel 		= $_POST['channel'];
		$desc 			= $_POST['desc'];
		
		//channel code
		$caching 		= $_POST['caching'];
		$chroma 		= $_POST['chroma'];
		$fps 			= $_POST['fps'];
		$tuner_channel 	= $_POST['tuner_channel'];
		$tuner_country 	= $_POST['tuner_country'];
		$tuner_input 	= $_POST['tuner_input'];
		$video_input 	= $_POST['video_input'];
		$audio_input 	= $_POST['audio_input'];
		$video_output 	= $_POST['video_output'];
		$audio_output 	= $_POST['audio_output'];
		
		$duration		= 300; //this will need to be sorted before we can roll this out - duration has to be a property of the slide in this case.
		$userid 		= $_SESSION['userid'];
		
		//check on required fields
		if ($channel == "" || $tuner_channel == "") {
			displayMessage(MSG_MODE_MANUAL,'Channel must have a value');
			return "$failrefer";
		}
		
		if (!is_numeric($tuner_channel)) {
			displayMessage(MSG_MODE_MANUAL,'The Tuner channel must be a number');
			return "$failrefer";
		}
		
		//add a media item
		include("lib/app/item.class.php");
		$item = new itemDAO($db);
		
		$mediaid = $item->db_add($channel,NULL,'channel',$duration,'0','0',0,$userid,$desc);
		
		//add the channel record
		$SQL = "INSERT INTO channel (channel, description, caching, chroma, fps, tuner_channel, tuner_country, tuner_input, video_input, audio_input, video_output, audio_output, mediaID) ";
		$SQL.= " VALUES ('$channel', '$description', $caching, '$chroma', $fps, $tuner_channel, $tuner_country, $tuner_input, $video_input, $audio_input, $video_output, $audio_output, $mediaid) ";
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add the channel", E_USER_ERROR);
		}
		
		setMessage('Channel Added');
		
		return $refer;	
	}
	
	function edit() 
	{
		$db =& $this->db;
		
		$refer 			= $_POST['refer'];
		$failrefer 		= $_POST['failrefer'];
		
		$channelid 		= $_POST['channelid'];
		$mediaid 		= $_POST['mediaid'];
		$channel 		= $_POST['channel'];
		$desc 			= $_POST['desc'];
		
		//channel code
		$caching 		= $_POST['caching'];
		$chroma 		= $_POST['chroma'];
		$fps 			= $_POST['fps'];
		$tuner_channel 	= $_POST['tuner_channel'];
		$tuner_country 	= $_POST['tuner_country'];
		$tuner_input 	= $_POST['tuner_input'];
		$video_input 	= $_POST['video_input'];
		$audio_input 	= $_POST['audio_input'];
		$video_output 	= $_POST['video_output'];
		$audio_output 	= $_POST['audio_output'];
		
		$duration		= 300; //this will need to be sorted before we can roll this out - duration has to be a property of the slide in this case.
		$userid 		= $_SESSION['userid'];
		
		//check on required fields
		if ($channel == "" || $tuner_channel == "") {
			displayMessage(MSG_MODE_MANUAL,'Channel must have a value');
			return "$failrefer";
		}
		
		if (!is_numeric($tuner_channel)) {
			displayMessage(MSG_MODE_MANUAL,'The Tuner channel must be a number');
			return "$failrefer";
		}
		
		$SQL = "UPDATE media SET medianame = '$channel', text = '$desc' WHERE mediaid = $mediaid";
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not edit the channels media record", E_USER_ERROR);
		}
		
		//add the channel record
		$SQL = <<<END
		UPDATE channel SET 
			channel = '$channel', 
			description = '$description', 
			caching = $caching, 
			chroma = '$chroma', 
			fps = $fps, 
			tuner_channel = $tuner_channel, 
			tuner_country = $tuner_country, 
			tuner_input = $tuner_input, 
			video_input = $video_input, 
			audio_input = $audio_input, 
			video_output = $video_output,
			audio_output = $audio_output
		WHERE channelID = $channelid
END;
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not the channel", E_USER_ERROR);
		}
		
		setMessage('Channel Edited');
		
		return $refer;	
	}
	
	function delete() 
	{
		$db =& $this->db;
		
		$channelid 	= $_REQUEST['channelid'];
		$mediaid 	= $_REQUEST['mediaid'];
		$refer 		= "index.php?p=channel&sp=view";
		
		$SQL = "DELETE FROM media WHERE mediaid = $mediaid";
	
		if (!$db->query($SQL)) {
			displayMessage(MSG_MODE_MANUAL,'You can not delete this channel, it has been used on a slide.');
			exit;
		}
		
		$SQL = "DELETE FROM channel WHERE channelid = $channelid";
	
		if (!$db->query($SQL)) {
			displayMessage(MSG_MODE_MANUAL,'You can not delete this channel.');
			exit;
		}
		
		setMessage('Channel Deleted');
	
		return $refer;
	}
}
?>