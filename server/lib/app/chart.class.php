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
class chartDAO {
	private $db;
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";

	private $chartid;
	private $datasetid = "";
	
	//vars for forms
	private $medianame;
	private $length = 200;
	private $description;
	private $xaxis = 'x';
	private $yaxis = 'y';
	private $canvas_color = "bad0f5";
	private $title_color = "525964";
	private $series_colors = "3c5888,6699cc";
	private $charttypeid = "";
	private $isprivate;
	
	private $chart_type = "";

	function chartDAO(database $db) {
		$this->db =& $db;
		
		//if ($_SESSION['usertype']==1) $this->isadmin = true;
		
		if (isset($_REQUEST['sp'])) {
			$this->sub_page = $_REQUEST['sp'];
		}
		else {
			$this->sub_page = "view";
		}
		
		switch ($this->sub_page) {
		
			case 'edit':
			
				if (!isset($_REQUEST['chartid'])) {
					displayMessage(MSG_MODE_MANUAL, "This page requires a chart id");
					exit;
				}
				$this->chartid = $_REQUEST['chartid'];
				
				$SQL = <<<END
				SELECT
					media.medianame,
					media.length,
					media.text,
					chart.x_axis,
					chart.y_axis,
					chart.charttypeid,
					chart.datasetID,
					chart.canvas_color,
					chart.title_color,
					chart.series_colors,
					media.permissionID
				FROM chart
				INNER JOIN media
				ON media.mediaid = chart.mediaid
				WHERE chart.chartid = $this->chartid
END;
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the chart information", E_USER_ERROR);
				}
				
				if ($db->num_rows($results) == "") {
					trigger_error($SQL . " returned 0 rows... 1 expected.");
					displayMessage(MSG_MODE_MANUAL, "No information returned for that chart id");
				}
				
				$row = $db->get_row($results);
				
				$this->medianame 	= $row[0];
				$this->length 		= $row[1];
				$this->description 	= $row[2];
				$this->xaxis 		= $row[3];
				$this->yaxis 		= $row[4];
				$this->charttypeid 	= $row[5];
				$this->datasetid 	= $row[6];
				$this->canvas_color = $row[7];
				$this->title_color 	= $row[8];
				$this->series_colors = $row[9];
				$this->isprivate	= $row[10];
				
				break;
				
		}
		
		if (isset($_REQUEST['chartid'])) {
			$this->chartid = $_REQUEST['chartid'];
		}
		
	}
	
	function on_page_load() {
		$onload = "onload=\"1";
		
		switch ($this->sub_page) {
			
			case 'view':
				$onload .= ",exec_filter('filter_form','data_table')\"";
				break;
				
			case 'edit':
				
				$onload .= ",refresh_list($('#datacolumnid'),'chartfieldtypeid','$this->chartid')";
			
				break;
		}
    	return $onload .= "\"";
	}
	
	function echo_page_heading() {
		echo "Chart View";
		return true;
	}
	
	function filter() {
		$db =& $this->db;
		
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['chart']['name'])) $filter_name = $_SESSION['chart']['name'];
		
		$filter_userid = "";
		if (isset($_SESSION['content']['filter_userid'])) $filter_userid = $_SESSION['content']['filter_userid'];
		$user_list = listcontent("all|All,".userlist("SELECT DISTINCT userid FROM playlist"),"filter_userid", $filter_userid);
		
		$datasetid = $_SESSION['chart']['datasetid'];
		$userid = $_SESSION['userid'];
		$datasetlist = dropdownlist("SELECT 'all', 'All' UNION SELECT datasetID, dataset FROM dataset WHERE userid = $userid ORDER BY 2", 'datasetid', $datasetid);
		
		//shared list
		$private = $_SESSION['chart']['private'];
		$shared_list = dropdownlist("SELECT 'all','All' UNION SELECT permissionID, permission FROM permission", "private", $private);
		
		$output = <<<END
		<form id="filter_form" onsubmit="return false">
			<input type="hidden" name="p" value="chart">
			<input type="hidden" name="q" value="data_table">
			<input type="hidden" name="pages" id="pages">
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" value="$filter_name"></td>
					<td>Shared</td>
					<td>$shared_list</td>
				</tr>
				<tr>
					<td>Dataset</td>
					<td>$datasetlist</td>
					<td>Owner</td>
					<td>$user_list</td>
				</tr>
			</table>
		</form>
END;
		echo $output;
	}
	
	function data_table() {
		$db =& $this->db;
		global $user;
		
		$filter_name = clean_input($_REQUEST['name'], VAR_FOR_SQL, $db);
		setSession('chart', 'name', $filter_name);
		
		$datasetid = clean_input($_REQUEST['datasetid'], VAR_FOR_SQL, $db);
		setSession('chart', 'datasetid', $datasetid);
		
		$filter_userid = $_REQUEST['filter_userid'];
		setSession('chart', 'filter_userid', $filter_userid);
		
		$private = clean_input($_REQUEST['private'], VAR_FOR_SQL, $db);
		setSession('chart', 'private', $private);
		
		$page_number = clean_input($_REQUEST['pages'], VAR_FOR_SQL, $db);
		if ($page_number == "") $page_number = 1;
	
		$SQL  = "";
		$SQL .= "SELECT  media.MediaName AS Name, ";
		$SQL .= "        chart.x_axis, ";
		$SQL .= "        chart.y_axis, ";
		$SQL .= "        media.mediaid, ";
		$SQL .= "        media.mediatype, ";
		$SQL .= "        media.class, ";
		$SQL .= "        chart.chartid, ";
		$SQL .= "        dataset.dataset, ";
		$SQL .= "		 permission AS Shared, ";
		$SQL .= "		 media.userid, ";
		$SQL .= "		 media.permissionid ";
		$SQL .= "FROM    chart ";
		$SQL .= "INNER JOIN media ";
		$SQL .= "ON      media.mediaid = chart.mediaid ";
		$SQL .= "INNER JOIN dataset ";
		$SQL .= "ON dataset.datasetid = chart.datasetid ";
		$SQL .= "INNER JOIN permission ";
		$SQL .= "ON permission.permissionID = media.permissionID ";
		$SQL .= "WHERE   1=1 ";
		if ($filter_name != "") {
			$SQL .= " AND media.medianame LIKE '%$filter_name%' ";
		}
		if ($datasetid != "all") {
			$SQL .= " AND dataset.datasetID = $datasetid ";
		}
		if ($private!="all") {
			$SQL .= " AND (media.permissionID = $private) ";
		}
		if ($filter_userid != "all") {
			$SQL .= " AND media.userid = $filter_userid ";
		}
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the chart information", E_USER_ERROR);
		}
			
		$table = <<<END
		<div class="info_table">
		<table style="width: 100%">
			<thead><tr>
			<th>Name</th>
			<th>Data Set</th>
			<th>X_axis label</th>
			<th>Y_axis label</th>
			<th>Shared</th>
			<th>Owner</th>
			<th>Actions</th>
			</tr></thead>
			<tbody>
END;
		echo $table;
		
		while ($row = $db->get_row($results)) {
		
			$name   		= $row[0];
			$x_axis 		= $row[1];
			$y_axis 		= $row[2];
			$mediaid 		= $row[3];
			$type 			= $row[4];
			$class 			= $row[5];
			$chartid 		= $row[6];
			$dataset 		= $row[7];
			$shared 		= $row[8];
			$userid 		= $row[9];
			$permissionid 	= $row[10];
			$preview_class 	= $class."_".$type;
			
			$username = $user->getNameFromID($userid);
			
			list($see_permissions , $edit_permissions) = $user->eval_permission($userid, $permissionid);
			
			if ($see_permissions) {
				$table = <<<END
				<tr>
					<td>$name</td>
					<td>$dataset</td>
					<td>$x_axis</td>
					<td>$y_axis</td>
					<td>$shared</td>
					<td>$username</td>
					<td>
						<div class="buttons">
							<a class="positive" href="index.php?p=chart&sp=edit&chartid=$chartid">Edit</a>
						</div>
					</td>
				</tr>
END;
				echo $table;
			}
		}
		echo "</tbody></table></div>";
		
	}
	
	function chart_fields() {
		$db =& $this->db;
		
		$SQL = <<<END
		SELECT
			CASE WHEN chartfield.datacolumnID = 0 THEN
				'Row Number'
			ELSE
				datacolumn.heading
			END AS heading,
			chartfield.chartfieldid,
			chartfieldtype.chartfieldtype
		FROM chart
		INNER JOIN chartfield
		ON chart.chartid = chartfield.chartid
		INNER JOIN chartfieldtype
		ON chartfield.chartfieldtypeID = chartfieldtype.chartfieldtypeid
		LEFT OUTER JOIN datacolumn
		ON datacolumn.datacolumnID = chartfield.datacolumnID
		WHERE chart.chartid = $this->chartid
END;
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the chart information", E_USER_ERROR);
		}
		
		$table = <<<END
		<table>
			<tr>
				<th>Heading</th>
				<th>Field Type</th>
				<th>Action</th>
			</tr>
END;
		echo $table;
		
		while($row = $db->get_row($results)) {
		
			$heading = $row[0];
			$chartfieldid = $row[1];
			$chartfieldtype = $row[2];
			
			$table = <<<END
				<tr>
					<td>$heading</td>
					<td>$chartfieldtype</td>
					<td>
						<div class="buttons">
							<a class="negative" href="index.php?p=chart&q=delete_field&chartfieldid=$chartfieldid&chartid=$this->chartid" alt="Edit Chart Field">Delete</a>
						</div>
					</td>
				</tr>
END;
			echo $table;
		}
		
		//an add row at the end (makes it easier to add fields)
		$SQL = <<<END
		SELECT datacolumnID, heading
		FROM
			(SELECT  0 AS datacolumnID, 'Rownumber' AS heading
			UNION
			SELECT  datacolumnID,
			        heading
			FROM    datacolumn
			WHERE   datasetID       = $this->datasetid
			) datacols
		WHERE 1=1
		    AND datacolumnID NOT IN
		        (SELECT datacolumn.datacolumnID
		        FROM    chartfield
		        INNER JOIN datacolumn
		        ON      datacolumn.datacolumnID = chartfield.datacolumnID
		        WHERE   chartfield.chartid      = $this->chartid
		            AND datacolumn.datatypeID   IN (1,3)
		        )
		    AND datacolumnID NOT IN
		        (SELECT datacolumnID
		        FROM    chartfield
		        WHERE   chartfield.chartid = $this->chartid
		        )
		ORDER BY 2
END;
		$datacolumnlist = dropdownlist($SQL, "datacolumnid", "", "onchange=\"refresh_list(this,'chartfieldtypeid','$this->chartid')\"");
		
		$SQL = "SELECT chartfieldtypeid, chartfieldtype FROM chartfieldtype ORDER BY 2";
		$fieldtypelist = dropdownlist($SQL, "chartfieldtypeid");

		$table = <<<END
			<form action="index.php?p=chart&q=add_field" method="post">
			<tr>
				<td>$datacolumnlist</td>
				<td>$fieldtypelist</td>
				<td>
					<input type="hidden" name="chartid" value="$this->chartid">
					<input type="hidden" name="datasetid" value="$this->datasetid">
					<button id="chartfield_button" class="positive" type="submit">Add</button>
				</td>
			</tr>
			</form>
		</table>
END;
		echo $table;
	
		return true;
	}
	
	function live_preview() {
		$db =& $this->db;
		
		$chartid = $this->chartid;
		
		if ($chartid == "") {
			displayMessage(MSG_MODE_MANUAL, "No chart to preview");
			return true;
		}
		
		//need id & class name
		$SQL = <<<END
		SELECT media.mediaid,
			CONCAT_WS('_',media.class, media.MediaType) AS class_name
		FROM chart
		INNER JOIN media ON chart.mediaid = media.mediaid
		WHERE chart.chartid = $chartid
END;
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get this charts Media ID", E_USER_ERROR);
		}
		$row = $db->get_row($results);
		
		$mediaid = $row[0];
		$class_name = $row[1];
		
		echo "<iframe scrolling='no' frameborder=0 src='index.php?p=client&q=wrap_media&mediaid=$mediaid&class_name=$class_name&width=800&height=600' width='800' height='600'></iframe>";
		
		
		return true;
	}
	
	function add_chart() {
		$db =& $this->db;
		
		//we have some post vars
		$refer = $_REQUEST['refer'];
		$failrefer = $_REQUEST['failrefer'];
		
		$medianame 		= clean_input($_REQUEST['medianame'], VAR_FOR_SQL, $db);
		$length 		= clean_input($_REQUEST['length'], VAR_FOR_SQL, $db);
		$description 	= clean_input($_REQUEST['description'], VAR_FOR_SQL, $db);
		$xaxis 			= clean_input($_REQUEST['xaxis'], VAR_FOR_SQL, $db);
		$yaxis 			= clean_input($_REQUEST['yaxis'], VAR_FOR_SQL, $db);
		$charttypeid 	= clean_input($_REQUEST['charttypeid'], VAR_FOR_SQL, $db);
		$datasetid 		= clean_input($_REQUEST['datasetid'], VAR_FOR_SQL, $db);
		$canvas_color	= clean_input($_REQUEST['canvas_color'], VAR_FOR_SQL, $db);
		$title_color	= clean_input($_REQUEST['title_color'], VAR_FOR_SQL, $db);
		$series_colors	= clean_input($_REQUEST['series_colors'], VAR_FOR_SQL, $db);
		$private		= clean_input($_REQUEST['private'], VAR_FOR_SQL, $db);
		
		$userid = $_SESSION['userid'];
		
		if ($medianame == "") {
			setMessage("Can not have a blank media name");
			return $failrefer;
		}
		
		//add a media item
		include("lib/app/item.class.php");
		$item = new itemDAO($db);
		
		$mediaid = $item->db_add($medianame,NULL,'chart',$length,'0','0',$private,$userid,$description);
		
		$chartid = $this->db_add_chart($mediaid, $charttypeid, $datasetid, $xaxis, $yaxis, $canvas_color, $title_color, $series_colors);
		
		setMessage('Chart Added');
		
		return "$refer&chartid=$chartid";
	
	}
	
	function add_field() {
		$db =& $this->db;
		$refer = "index.php?p=chart&sp=edit";
	
		$chartid 			= clean_input($_REQUEST['chartid'], VAR_FOR_SQL, $db);
		$datacolumnid 		= clean_input($_REQUEST['datacolumnid'], VAR_FOR_SQL,$db);
		$datasetid 			= clean_input($_REQUEST['datasetid'], VAR_FOR_SQL,$db);
		$chartfieldtypeid 	= clean_input($_REQUEST['chartfieldtypeid'], VAR_FOR_SQL,$db);
		
		if ($chartid == "" || $datacolumnid == "" || $datasetid == "" || $chartfieldtypeid == "") {
			displayMessage(MSG_MODE_MANUAL, "Everything needs to be filled in");
			exit;
		}
		
		$SQL = "INSERT INTO chartfield (chartID, datacolumnID, datasetID, chartfieldtypeID) ";
		$SQL.= " VALUES ($chartid, $datacolumnid, $datasetid, $chartfieldtypeid)";

		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not insert this chart field", E_USER_ERROR);
		}
		
		
		return "$refer&chartid=$chartid";
	}
	
	function edit_chart() {
		$db =& $this->db;
		
		//we have some post vars
		$refer			= $_REQUEST['refer'];
		$failrefer 		= $_REQUEST['failrefer'];
		
		$chartid		= clean_input($_REQUEST['chartid'], VAR_FOR_SQL, $db);
		$medianame 		= clean_input($_REQUEST['medianame'], VAR_FOR_SQL, $db);
		$length 		= clean_input($_REQUEST['length'], VAR_FOR_SQL, $db);
		$description 	= clean_input($_REQUEST['description'], VAR_FOR_SQL, $db);
		$xaxis 			= clean_input($_REQUEST['xaxis'], VAR_FOR_SQL, $db);
		$yaxis 			= clean_input($_REQUEST['yaxis'], VAR_FOR_SQL, $db);
		$charttypeid 	= clean_input($_REQUEST['charttypeid'], VAR_FOR_SQL, $db);
		$canvas_color 	= clean_input($_REQUEST['canvas_color'], VAR_FOR_SQL, $db);
		$title_color	= clean_input($_REQUEST['title_color'], VAR_FOR_SQL, $db);
		$series_colors	= clean_input($_REQUEST['series_colors'], VAR_FOR_SQL, $db);		
		$private		= clean_input($_REQUEST['private'], VAR_FOR_SQL, $db);
		
		$userid 		= $_SESSION['userid'];
		
		if ($medianame == "") {
			setMessage("Can not have a blank media name");
			return $failrefer;
		}
		
		//get media id
		$SQL = "SELECT mediaid FROM chart WHERE chartid = $chartid";
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get this charts Media ID", E_USER_ERROR);
		}
		$row = $db->get_row($results);
		
		$mediaid = $row[0];
		
		//edit the media record (only a few fields)
		$SQL = <<<END
		UPDATE media SET
			medianame = '$medianame', 
			text = '$description',
			length = '$length',
			permissionID = '$private'
		WHERE mediaid = $mediaid;
END;
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not edit this chart", E_USER_ERROR);
		}		
		
		//edit the chart record
		$SQL = <<<END
		UPDATE chart SET
			charttypeid = $charttypeid, 
			x_axis = '$xaxis',
			y_axis = '$yaxis',
			canvas_color = '$canvas_color',
			title_color = '$title_color',
			series_colors = '$series_colors' 
		WHERE chartid = $chartid;
END;
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not edit this chart", E_USER_ERROR);
		}
		
		setMessage('Chart Edited');
		
		return "$refer&chartid=$chartid";
	
	}
	
	function delete_field() {
		$db =& $this->db;
		
		$chartfieldid = clean_input($_REQUEST['chartfieldid'], VAR_FOR_SQL, $db);
		$chartid	  = clean_input($_REQUEST['chartid'], VAR_FOR_SQL, $db);
	
		$SQL = "DELETE FROM chartfield WHERE chartfieldid = $chartfieldid ";
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not delete this chart", E_USER_ERROR);
		}
		
		return "index.php?p=chart&sp=edit&chartid=$chartid";
	}
	
	function db_add_chart($mediaid, $charttypeid, $datasetid, $xaxis, $yaxis, $canvas_color, $title_color, $series_colors) {
		$db =& $this->db;
		
		$SQL = <<<END
		INSERT INTO chart (mediaid, charttypeid, datasetID, x_axis, y_axis, canvas_color, title_color, series_colors)
			VALUES ($mediaid, $charttypeid, $datasetid, '$xaxis', '$yaxis', '$canvas_color', '$title_color', '$series_colors')
END;
	
		//add the chart item
		if (!$id = $db->insert_query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add a new chart", E_USER_ERROR);
		}
		return $id;
	}

	/**
	 * Displays the page logic
	 *
	 * @return unknown
	 */
	function displayPage() {
		$db =& $this->db;
		
		if (!$this->has_permissions) {
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) {
				
			case 'view':
				require("template/pages/chart_view.php");
				break;
			
			case 'add':
				require("template/pages/chart_add.php");
				break;
				
			case 'edit':
				require("template/pages/chart_edit.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	//displays the form used to add/edit a chart
	function chart_form($action="index.php?p=chart&q=add_chart", $refer="index.php?p=chart&sp=edit", $failrefer="index.php?p=chart&sp=add", $onsubmit="", $button_text = "Cancel") {
		$db =& $this->db;
		
		$SQL = "SELECT charttypeID, type FROM charttype WHERE enabled = 1 ORDER BY 2";
		$charttypelist = dropdownlist($SQL, 'charttypeid', $this->charttypeid);
		
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission ", "private", $this->isprivate);

		
		if ($this->chartid == "") {
			//get some dropdowns to use later on
			$SQL = "SELECT datasetID, dataset FROM dataset WHERE userID = ".$_SESSION['userid']." ORDER BY 2";
			$datasetlist = dropdownlist($SQL, 'datasetid', $this->datasetid);
		}
		else {
			$SQL = <<<END
			SELECT dataset
			FROM chart
			INNER JOIN dataset
			ON dataset.datasetID = chart.datasetID 
			WHERE chart.chartid = $this->chartid
END;
			if (!$results = $db->query($SQL)) {
				trigger_error($db->error());
				trigger_error("Can not get chart info", E_USER_ERROR);
			}
			$row = $db->get_row($results);
			
			$dataset = $row[0];

			$datasetlist = "<input type='text' value='$dataset' disabled>";
		}
	
		$form = <<<END
		<form method="post" action="$action">
			<input type="hidden" name="refer" value="$refer">
			<input type="hidden" name="failrefer" value="$failrefer">
			<input type="hidden" name="chartid" value="$this->chartid">
			<table>
				<tr>
					<td>Dataset</td>
					<td>$datasetlist</td>
					<td>Chart Type</td>
					<td>$charttypelist</td>
				</tr>
				<tr>
					<td>Name</td>
					<td><input type="text" name="medianame" value="$this->medianame"></td>
					<td>Description</td>
					<td><input type="text" name="description" value="$this->description"></td>
				</tr>
				<tr>
					<td>Title Color</td>
					<td><input type="text" name="title_color" value="$this->title_color"></td>

					<td>Series Colors</td>
					<td><input type="text" name="series_colors" value="$this->series_colors"></td>
				</tr>
				<tr>
					<td>Canvas Color</td>
					<td><input type="text" name="canvas_color" value="$this->canvas_color"></td>

					<td>Length</td>
					<td><input type="text" name="length" value="$this->length"></td>
				</tr>
				<tr>
					<td>X-axis Label</td>
					<td><input type="text" name="xaxis" value="$this->xaxis"></td>
					<td>Y-axis Label</td>
					<td><input type="text" name="yaxis" value="$this->yaxis"></td>
				</tr>
				<tr>
					<td>Shared</td>
					<td>$shared_list</td>					
				</tr>
				<tr>
					<td colspan="4">
						<div class="buttons">
							<button type="submit">Save</button>
							<a href="index.php?p=chart" alt="Cancel" class="negative">$button_text</a>
						</div>
					</td>
				</tr>
			</table>
		</form>
END;
		echo $form;
	}
	
	function field_list() {
		//generates the chart field dropdown, based on what has been selected previously
		$db =& $this->db;
	
		$chartid = $_REQUEST['chartid'];
		$datacolumnid = $_REQUEST['datacolumnid'];
		
		if ($datacolumnid == "") {
			exit;
		}
		
		//does the chart already have a x-axis column used as values assigned
		$SQL = "SELECT chartfieldtypeID FROM chartfield WHERE chartid = $chartid ";
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the datatype for this datacolumn", E_USER_ERROR);
		}
		$hasfields = true;
		if ($db->num_rows($results) == 0) {
			$hasfields = false;
		}
		
		$xaxis = false;
		while ($row = $db->get_row($results)) {
			$chartfieldtypeid = $row[0];

			if ($chartfieldtypeid == 1) {
				//there is an X-axis col as VALUE defined
				$xaxis = true;
			}
		}
		
		if ($datacolumnid == "0") {
			//we have the psudo column name row
			if ($xaxis) {
				echo "";
				return false;
			}
			else {
				$SQL = "SELECT chartfieldtypeid, chartfieldtype ";
				$SQL.= "FROM chartfieldtype ";
				$SQL.= "WHERE chartfieldtypeid = 1 ";
				
				$fieldtypelist = dropdownlist($SQL, "chartfieldtypeid", "", "", true);
				echo $fieldtypelist;
				
				return false;
			}
			
		}
		
		//get the datatype for this datacolumnid
		$SQL = "SELECT datatypeID FROM datacolumn WHERE datacolumnID = $datacolumnid ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the datatype for this datacolumn", E_USER_ERROR);
		}
		$row = $db->get_row($results);
		$datatypeid = $row[0]; //the datatypeid for the data column that has been selected
		
		//construct the dropdown query
		$SQL = "SELECT chartfieldtypeid, chartfieldtype ";
		$SQL.= "FROM chartfieldtype ";
		$SQL.= "WHERE 1=1 ";
		if ($xaxis) {
			$SQL .= " AND chartfieldtypeid <> 1 "; //there can only be one X-axis
		}
		if ($datatypeid != 2) { //number
			$SQL .= " AND chartfieldtypeid <> 3 "; //cant have strings on the X
		}
		$SQL.= "ORDER BY 2 ";
		
		$fieldtypelist = dropdownlist($SQL, "chartfieldtypeid", "", "", true);
	
		echo $fieldtypelist;
	
		return false;
	}

	function chart_xml($chartid = "") {
		$db =& $this->db;
		//outputs the XML for a chart
		//get the information for this chart
		if($chartid == "") {
			$chartid = $this->chartid;
		}
		
		$SQL = <<<END
		SELECT datasetID, type, media.medianame, canvas_color, chart.title_color, chart.series_colors, chart.x_axis, chart.y_axis
		FROM chart
		INNER JOIN charttype ON chart.charttypeID = charttype.charttypeID
		INNER JOIN media ON chart.mediaid = media.mediaid
		WHERE chartid = $chartid
END;
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the dataset for this chart", E_USER_ERROR);
		}
		$row = $db->get_row($results);
		$this->datasetid 	= $row[0]; //the datasetid
		$this->chart_type 	= $row[1];
		$name 				= $row[2];
		$canvas_color	 	= $row[3];
		$title_color	 	= $row[4];
		$this->series_colors = explode(",",$row[5]);
		$x_axis 			= $row[6];
		$y_axis 			= $row[7];
		
		$chart_data = $this->chart_data_xml($chartid);
		$axis_3d = "&x_axis_3d=12&";
		if (stripos($this->chart_type, "3d") === false) {
			$axis_3d = "";
		}
		
		$xml = <<<END
&title=$name,{font-size:20px; color: #$title_color; margin: 5px; background-color: #$canvas_color; padding:5px; padding-left: 20px; padding-right: 20px;}&
&x_axis_steps=1&
$axis_3d
&bg_colour=#$canvas_color&
$chart_data

&y_min=0&
&y_ticks=5,10,5&
&x_axis_colour=#909090&
&x_grid_colour=#ADB5C7&
&y_axis_colour=#909090&
&y_grid_colour=#ADB5C7&
&x_label_style=20,#$title_color,0&
&y_label_style=20,#$title_color,0&
&x_legend=$x_axis,15,#$title_color&
&y_legend=$y_axis,15,#$title_color&
END;
		return $xml;
	
	}
	
	function default_xml() {
			
			$xml = <<<END
	<chart>
		<draw>
			<text	transition='slide_left'
					delay='0'
					duration='1'
					width='320' 
					x='0' 
					y='0' 
					height='240' 
					alpha='100' 
					size='40' 
					color='FF0000' 
					h_align='center'
					v_align='middle'
					bold='true'
					>Incomplete\rChart\rDefinition</text>
		</draw>
	</chart>
END;
		echo $xml;
	
	}
	
	function chart_data_xml($chartid) {
		$db =& $this->db;
		//$chart_data = "<chart_data>\n";
		$chart_data = "";
		
		//put the data for this dataset into a temporary table for us to use
		$table_name = "TempTable";
		$this->chart_data_temptable($table_name,$this->datasetid);
		
		//set both to false
		$columns_as_x = false;
		$value_as_x = false;
		$x_axis_datacolumnid = "";
		$x_axis_datacolumn = "";
		$series_datacolumnid = "";
		$series_datacolumn = "";
		$y_axis_datacolumn = "";		//not used	
		
		
		//get the field information for this chart
		$SQL = <<<END
		SELECT chartfield.datacolumnID, chartfieldtypeID,
			CASE WHEN chartfield.datacolumnID = 0 THEN
				'rownumber'
			ELSE
				datacolumn.heading
			END AS heading
		FROM chartfield 
		LEFT OUTER JOIN datacolumn 
		ON datacolumn.datacolumnid = chartfield.datacolumnid
		WHERE chartid = $chartid
END;
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the dataset for this chart", E_USER_ERROR);
		}
		while ($row = $db->get_row($results)) {
			//we are looking for the field types
			$row_datacolumnid = $row[0];
			$row_chartfieldtypeID = $row[1];
			$row_heading = $row[2];
			
			switch ($row_chartfieldtypeID) {
			
				case 1:
					//there is a column to be used as a value on the X-AXIS
					$value_as_x = true;
					$x_axis_datacolumn = $row_heading;
					break;
					
				case 2:
					$series_datacolumn .= ",$row_heading";
					break;
					
				case 3:
					//there is a column to be used as a series identifier
					$y_axis_datacolumn .= ",$row_heading";
					break;
			}
		}
		
		//if we dont have certain things then we know getting the chart data is going to fail, so we should stop it here!
		
		//Particularly we know: If there is no X-axis data column OR no series then stop
		if ($x_axis_datacolumn == "" || $y_axis_datacolumn == "") {
			//OUT some default chart XML and die right here
			$this->default_xml();
			exit;
		}

		$y_axis_datacolumn = ltrim($y_axis_datacolumn,',');
		$series_datacolumn = ltrim($series_datacolumn,',');
		
		$chart_data = $this->chart_data_x_as_value($chart_data, $table_name, $x_axis_datacolumn, $series_datacolumn, $y_axis_datacolumn);
		
		$this->chart_destroy_temp($table_name);
		
		return $chart_data;
	}
	
	function chart_data_temptable($table_name, $datasetid) {
		$db =& $this->db;
		//puts the data set into a temporary table we can use to more effectively query the data

		//otherwise we have created it and now want to select into it		
		$SQL = <<<END
		SELECT 	heading, datacolumnid
		FROM    datacolumn
		WHERE   datasetID = $datasetid
		ORDER BY columnorder
END;
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the chart cross tab", E_USER_ERROR);
		}
		
		$select = "";
		$num_cols = 1; //there is always 1
		
		while ($row = $db->get_row($results)) {
			$heading = $row[0];
			$datacolumnid = $row[1];
			
			$num_cols++;
			$select .= <<<END
			MAX(
			CASE
					WHEN    datacolumnid = $datacolumnid
					THEN    value
					ELSE    null
			END) AS '$heading',			
END;
		}
		
		$SQL = <<<END
		CREATE TEMPORARY TABLE IF NOT EXISTS $table_name TYPE=HEAP 
		SELECT
			$select
			rownumber
		FROM
			(SELECT value      ,
					rownumber  ,
					data.datacolumnid
			FROM    `data`
			INNER JOIN datacolumn
			ON      data.datacolumnID  = datacolumn.datacolumnID
			WHERE   data.datasetID     = $datasetid
			)
			chart_data
		GROUP BY rownumber
		ORDER BY rownumber;
END;

		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the chart information", E_USER_ERROR);
		}

		return true;
	}
	
	function chart_destroy_temp($table_name) {
		$db =& $this->db;
		//drops the temporary table
		
		$SQL = "DROP TABLE $table_name";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the chart information", E_USER_ERROR);
		}
		return true;
	}
	
	function chart_data_x_as_value($chart_data, $table_name, $x_axis_datacolumn, $series_datacolumn, $y_axis_datacolumn) {
		$db =& $this->db;
		
		$max_value = 0; //keeps a MAX of the values
		
		$chart_series_width = 0; //set the width of the line (each series)
		if ($this->chart_type == "line") {
			$chart_series_width = 3;
		}
		else {
			$chart_series_width = 75;
		}

		//for each series_datacolumn we need a seperate UNION therefore seperate out the $series_datacolumns
		$y_axis_datacolumns = split(",",$y_axis_datacolumn);
		
		//make up some SUM columns, so that we can sub query correctly
		$summed_series = "";
		for ($i=0; $i<count($y_axis_datacolumns); $i++) {
			$heading = $y_axis_datacolumns[$i];
			$summed_series.=" SUM($heading) AS $heading,";
		}
		$summed_series = rtrim($summed_series, ",");

		//for each one	
		$ident_number = 0; //the number of the series identifier we are on at the momnet
		$series_number = 0;
		
		for ($i=0; $i<count($y_axis_datacolumns); $i++) { //each loop generates a ROW of XML, the first one will be generated from the X-Axis Datacolumn

			//if ($i == 0) $chart_data .= "<row>\n<null/>\n"; //our first cell will be blank
			if ($i == 0 && $this->chart_type == "pie") $chart_data .= "&pie_labels="; //our first cell will be blank
			if ($i == 0 && $this->chart_type != "pie") $chart_data .= "&x_labels="; //our first cell will be blank
			
			//get the current heading
			$heading = $y_axis_datacolumns[$i];
		
			//get each value in the $x_axis_datacolumn
			$SQL_headings = <<<END
			SELECT DISTINCT $x_axis_datacolumn FROM $table_name ORDER BY 1
END;
			if (!$results = $db->query($SQL_headings)) {
				trigger_error($db->error());
				trigger_error("Can not get the chart information", E_USER_ERROR);
			}

			$select = ""; //start out with a blank select
			$values = ""; //start out with a blank select
			$num_xaxis_values = 0;
			//for each one,
			while ($row = $db->get_row($results)) {
				//get the value of the datacolumn
				$axis_datacolumn_value = $row[0];
				$num_xaxis_values++;
				
				// and generate a cross tab for $heading (which is the current UNION - series identifier)
				$select.= <<<END
				, MAX(CASE WHEN $x_axis_datacolumn = '$axis_datacolumn_value' THEN $heading ELSE NULL END) AS '$axis_datacolumn_value'
END;

				//we also want to make our heading XML as we go... makes sense as it will be in the same order as we Xtab
				//if ($i == 0) $chart_data.="<string>$axis_datacolumn_value</string>\n";	
				if ($i == 0) $values.="$axis_datacolumn_value,";	
			}
			//if ($i == 0) $chart_data .= "</row>\n"; //end our heading row
			if ($i == 0) $chart_data .= rtrim($values,",")."&\n"; //end our heading row
			
			//we need to see if there are any series identifiers (because we will need to generate a ROW for each series identifier (and do that for each Y)
			if ($series_datacolumn != "" && $this->chart_type != "pie") {
				
				//get the values for this datacolumn
				$series_SQL = "SELECT DISTINCT $series_datacolumn FROM $table_name ORDER BY 1";
				if (!$series_results = $db->query($series_SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the chart series information", E_USER_ERROR);
				}

				while ($row = $db->get_row($series_results)) {
				
					$ident_number++;
					
					//get the value of the datacolumn
					$series_value = $row[0];
					
					$series_where = " WHERE $series_datacolumn = '$series_value' ";
					
					//then put this onto the standard $table_name query
					$SQL = ""; //start out with blank SQL
					$SQL.= <<<END
					SELECT
						'$heading' AS row_data
						$select
					FROM
						(
						SELECT
							$x_axis_datacolumn,
							$summed_series
						FROM
							$table_name
							
						$series_where
						
						GROUP BY 
							$x_axis_datacolumn
						) chart_data
					GROUP BY row_data
END;
					//now we can query and generate the XML
					if (!$results = $db->query($SQL)) {
						trigger_error($db->error());
						trigger_error("Can not get the chart information", E_USER_ERROR);
					}
					
					while ($row = $db->get_row($results)) {
					
						//$chart_data .= "<row>\n";
						$series_heading = $row[0]; //x_axis_datacolumn value
						//$chart_data .= "<string>$series_value</string>\n";
						
						if ($series_number >= count($this->series_colors)) {
							$series_number = 0;
						}
						
						$color = $this->series_colors[$series_number];
						if ($ident_number > 1) {
							$chart_data .= "&".$this->chart_type."_$ident_number=$chart_series_width,#$color,$series_heading-$series_value,15&\n";
							$chart_data .= "&values_$ident_number=";
						}
						else {
							$chart_data .= "&".$this->chart_type."=$chart_series_width,#$color,$series_heading-$series_value,15&\n";
							$chart_data .= "&values=";
						}
						$values = "";

						//how many columns have we got... we can determine this from the number of values returned by the first query
						for ($p = 1; $p <= $num_xaxis_values; $p++) {
							$value = $row[$p];
							
							if ($value > $max_value) $max_value = $value;
							
							//$chart_data .= "<number>$value</number>\n";
							$values .= "$value,";
						}
						
						//$chart_data .= "</row>\n";
						$chart_data .= rtrim($values,",")."&\n";
						
						$series_number++;
					}
				}
			}
			else { //query without series identifiers
				$ident_number++;
			
				$SQL = ""; //start out with blank SQL
				$SQL.= <<<END
				SELECT
					'$heading' AS row_data
					$select
				FROM
					(
					SELECT
						$x_axis_datacolumn,
						$summed_series
					FROM
						$table_name
				
					GROUP BY 
						$x_axis_datacolumn
					) chart_data
				GROUP BY row_data
END;
				//now we can query and generate the XML
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the chart information", E_USER_ERROR);
				}
				while ($row = $db->get_row($results)) {
					
					//$chart_data .= "<row>\n";
					
					$series_heading = $row[0]; //x_axis_datacolumn value
					//$chart_data .= "<string>$series_heading</string>\n";
					
					
					
					if ($this->chart_type == "pie") {
						$colors = implode(",#",$this->series_colors);
						$chart_data .= "&".$this->chart_type."=$chart_series_width,#000000,#000000,15&\n";
						$chart_data .= "&colours=#$colors&\n";
						$chart_data .= "&values=";
					}
					else {					
						$color = $this->series_colors[$series_number];
						if ($ident_number > 1) {
							$chart_data .= "&".$this->chart_type."_$ident_number=$chart_series_width,#$color,$series_heading,15&\n";
							$chart_data .= "&values_$ident_number=";
						}
						else {
							$chart_data .= "&".$this->chart_type."=$chart_series_width,#$color,$series_heading,15&\n";
							$chart_data .= "&values=";
						}
					}
					$values = "";

					//how many columns have we got... we can determine this from the number of values returned by the first query
					for ($p = 1; $p <= $num_xaxis_values; $p++) {
						$value = $row[$p];
						
						if ($value > $max_value) $max_value = $value;
						
						//$chart_data .= "<number>$value</number>\n";
						$values .= "$value,";
					}
					
					//$chart_data .= "</row>\n";
					$chart_data .= rtrim ($values, ",")."&\n";
					
					$series_number++;
				}
			}
		}
		
		//$chart_data .= "</chart_data>\n";
		$powerOf10 = floor(log10(abs($max_value))); //get the smallest power of 10 for our max value
		
		$yInterval = floor(pow(10,$powerOf10)); //raise to the power of 10 (we have floored powerOf10)
		
		$max_value = floor(((abs($max_value) / $yInterval)+1)) * $yInterval;
		
		$chart_data .= "&y_max=$max_value&";
		
		return $chart_data;
	}
}
?>