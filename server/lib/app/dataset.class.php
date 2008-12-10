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
class datasetDAO {
	private $db;
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";

	private $datasetid = "";
	private $dataset = "";
	private $description = "";
	
	private $datacolumnid = "";
	private $heading = "";
	private $listcontent = "";
	private $datatypeid = "";
	
	//csv
	private $csv; //csv object

	function datasetDAO(database $db) {
		$this->db =& $db;
		
		if ($_SESSION['usertype']==1) $this->isadmin = true;
		
		if (isset($_REQUEST['sp'])) {
			$this->sub_page = $_REQUEST['sp'];
		}
		else {
			$this->sub_page = "view";
		}
		
		switch ($this->sub_page) {
		
			case 'edit':
				$datasetID = clean_input($_REQUEST['datasetid'], VAR_FOR_SQL, $db);
				$this->datasetid = $datasetID;
				
				if ($datasetID == "") {
					displayMessage(MSG_MODE_MANUAL, "No dataset ID present");
					exit;
				}
				
				$SQL = "SELECT dataset, description, userid FROM dataset WHERE datasetID = $datasetID";
				
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get data set information.", E_USER_ERROR);
				}
				
				$row = $db->get_row($results);
				$this->dataset = $row[0];
				$this->description = $row[1];
				$userid = $row[0];
				
				if ($userid != $_SESSION['userid'] && $_SESSION['usertype']!=1) {
					$this->has_permissions = false;
				}
				
				break;
		
			case 'dataview':
				
				if (!isset($_REQUEST['datasetid'])) $has_permissions = false;
				
				$this->datasetid = clean_input($_REQUEST['datasetid'], VAR_FOR_SQL, $db);
			
				break;
				
			case 'datacolumnadd':
				if (!isset($_REQUEST['datasetid'])) $has_permissions = false;
				
				$this->datasetid = clean_input($_REQUEST['datasetid'], VAR_FOR_SQL, $db);
				
				break;
				
			case 'datacolumnedit':
				if (!isset($_REQUEST['datacolumnid'])) $has_permissions = false;
				$this->datacolumnid = clean_input($_REQUEST['datacolumnid'], VAR_FOR_SQL, $db);
				
				$SQL = "SELECT heading, listcontent, datatypeID, datasetID, columnorder FROM datacolumn WHERE datacolumnID = $this->datacolumnid";
				
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get data column information.", E_USER_ERROR);
				}
				
				$row = $db->get_row($results);
				
				$this->heading 		= $row[0];
				$this->listcontent 	= $row[1];
				$this->datatypeid 	= $row[2];
				$this->datasetid 	= $row[3];
				$this->order 		= $row[4];
			
				break;
		}
	}
	
	function on_page_load() {
		return "";
	}
	
	function echo_page_heading() {
		echo "Data sets";
		return true;
	}
	
	function dataset_filter() {
		$db =& $this->db;
		
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['dataset']['name'])) $filter_name = $_SESSION['dataset']['name'];
		
		$filter_userid = "";
		if (isset($_SESSION['content']['filter_userid'])) $_SESSION['content']['filter_userid'];
		$user_list = listcontent("all|All,".userlist("SELECT DISTINCT userid FROM playlist"),"filter_userid", $filter_userid);

		
		$output = <<<END
		
		<form id="filter_form" onsubmit="return false">
			<input type="hidden" name="p" value="dataset">
			<input type="hidden" name="q" value="dataset_view">
			<input type="hidden" name="pages" id="pages">
			<table>
				<tr>
					<td>Name</td>
					<td><input type="text" name="name" value="$filter_name"></td>
					<td>Owner</td>
					<td>$user_list</td>
				</tr>
			</table>
		</form>
END;
		echo $output;
	}
	
	function dataset_view() {
		$db =& $this->db;
		
		$filter_name = clean_input($_REQUEST['name'], VAR_FOR_SQL, $db);
		setSession('dataset', 'name', $filter_name);
		
		$filter_userid = $_REQUEST['filter_userid'];
		setSession('dataset', 'filter_userid', $filter_userid);
		
		$page_number = clean_input($_REQUEST['pages'], VAR_FOR_SQL, $db);
		if ($page_number == "") $page_number = 1;
	
		$SQL  = "";
		$SQL .= "SELECT  dataset.datasetid, ";
		$SQL .= "        dataset.dataset, ";
		$SQL .= "        dataset.description, ";
		$SQL .= "        dataset.createdDT, ";
		$SQL .= "        dataset.userid ";
		$SQL .= "FROM    dataset ";
		$SQL .= "WHERE 1=1 ";
		if ($filter_name != "") {
			$SQL .= " AND dataset.dataset LIKE '%$filter_name%' ";
		}
		if ($filter_userid != "all") {
			$SQL .= " AND dataset.userid = $filter_userid ";
		}

		/**
		 * Now run the limited query and use these results
		 */
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the datasets", E_USER_ERROR);
		}
		
		$table = <<<END
		<div class="info_table">
		<table style="width:100%;">
			<thead>
			<tr>
			<th>Name</th>
			<th>Description</th>
			<th>User</th>
			<th>Actions</th>
			</tr>
			</thead>
			<tbody>
END;
		echo $table;
		
		while ($row = $db->get_row($results)) {
		
			$datasetID   = $row[0];
			$dataset = $row[1];
			$desc = $row[2];
			$createdDT  = $row[3];
			
			$userid = $row[4];
			
			global $user;
			$username = $user->getNameFromID($userid);
			
			//we only want to show certain buttons, depending on the user logged in
			if ($userid != $_SESSION['userid']) {
				//dont any actions
				$buttons = "No available Actions";
			}
			else {
				$buttons = <<<END
				<a class="positive" href="index.php?p=dataset&sp=dataview&datasetid=$datasetID">View / Edit Data</a>
				<a class="positive" href="index.php?p=dataset&sp=edit&datasetid=$datasetID">Edit Dataset</a>
				<a class="negative" href="index.php?p=dataset&q=delete_dataset&datasetid=$datasetID">Delete</a>
END;
			}
			
			$table = <<<END
			<tr>
				<td>$dataset</td>
				<td>$desc</td>
				<td>$username</td>
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
	
	function dataview() {
		//shows the data for a dataset
		$db =& $this->db;
		
		//get the max number of rows
		$SQL  = "";
		$SQL .= "SELECT  MAX(rownumber) ";
		$SQL .= "FROM    data ";
		$SQL .= "WHERE data.datasetID = $this->datasetid ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the max rows", E_USER_ERROR);
		}
		
		$row = $db->get_row($results);
		$max_rows = $row[0];
		
		//get the max number of rows
		$SQL  = "";
		$SQL .= "SELECT  MAX(columnorder) ";
		$SQL .= "FROM    datacolumn ";
		$SQL .= "WHERE datasetID = $this->datasetid ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the max cols", E_USER_ERROR);
		}
		
		$row = $db->get_row($results);
		$max_cols = $row[0];
		
		//get some headings?
		$SQL = "SELECT heading, datacolumnid FROM datacolumn WHERE datasetID = $this->datasetid ORDER BY columnorder ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the max rows", E_USER_ERROR);
		}
		$headings = "";
		
		while ($row = $db->get_row($results)) {
			$col_heading = $row[0];
			$datacolumnid = $row[1];
			
			$headings .= <<<END
			<th><a href="index.php?p=dataset&sp=datacolumnedit&datacolumnid=$datacolumnid" alt="Edit Column">$col_heading</a></th>
END;
		}
		
		$table = <<<END
			<table>
				<tr>
					<th>Row</th>
					$headings
				</tr>
END;
		echo $table;
		
		//loop through the max rows
		for ($row=1;$row<=$max_rows;$row++) {
		
			$table = <<<END
			<tr>
				<th>$row</th>
END;
			echo $table;
		
			//$row is the current row
			for ($col=1;$col<=$max_cols;$col++) {
			
				//get the datatype for this column
				$SQL = "SELECT datatypeID, listcontent ";
				$SQL .= "FROM datacolumn ";
				$SQL .= "WHERE datasetID = $this->datasetid ";
				$SQL .= "AND columnorder = $col ";
				
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the column datatype ", E_USER_ERROR);
				}
				
				$results_row = $db->get_row($results);
				
				$datatypeid = $results_row[0];
				$listcontent = $results_row[1];
				
				//if the list content is set to something then we need to give a drop down instead of a text box
				$list = false;
				if ($listcontent != "" && $datatypeid != 3) {
					$listcontent = split(",",$listcontent);
					$list = true;
				}

				//get the value
				$value = "";
			
				$SQL  = "";
				$SQL .= "SELECT  value, rownumber, columnorder ";
				$SQL .= "FROM    data ";
				$SQL .= "INNER JOIN datacolumn ";
				$SQL .= "ON      data.datacolumnID = datacolumn.datacolumnID ";
				$SQL .= "WHERE data.datasetID = $this->datasetid ";
				$SQL .= "AND data.rownumber = $row ";
				$SQL .= "AND datacolumn.columnorder = $col ";
				
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the data row/column", E_USER_ERROR);
				}
				
				if ($db->num_rows($results) == 0) {
					//we should make it up when we do the forms, so we can set default values
					//although we can do the add bits here
					if ($list) {
						$select = "<select name='value'>";
						$select.= "<option value='' selected></option>";
						for ($i=0; $i<count($listcontent); $i++) {
							$list_val = $listcontent[$i];
							$select.="<option value='$list_val'>$list_val</option>";
						}
						$select.="</select>";
					}
					else {
						$class = "";
						if ($datatypeid == 3) $class = "class='date-pick'";
						$select = "<input $class type=\"text\" name=\"value\">";
					}
					$form = <<<END
					<td>
						<form class="dataset_data">
							<input type="hidden" name="location" value="index.php?p=dataset&q=add_data">
							<input type="hidden" name="datasetid" value="$this->datasetid">
							<input type="hidden" name="columnorder" value="$col">
							<input type="hidden" name="rownumber" value="$row">
							<input type="hidden" name="datatypeid" value="$datatypeid">
							$select
						</form>
					</td>
END;
					echo $form;
				}
				else {
					//we have a VALUE
					$db_row = $db->get_row($results);
					
					$value = $db_row[0];
					
					if ($list) {
						$select = "<select name='value'>";
						$select.= "<option value=''></option>";
						for ($i=0; $i<count($listcontent); $i++) {
							$list_val = $listcontent[$i];
							
							$selected = "";
							if ($list_val == $value) {
								$selected = " selected";
							}
							
							$select.="<option value='$list_val' $selected>$list_val</option>";
						}
						$select.="</select>";
					}
					else {
						$class = "";
						if ($datatypeid == 3) $class = "class='date-pick'";
						$select = "<input $class type=\"text\" name=\"value\" value=\"$value\">";
					}
					
					$form = <<<END
					<td>
						<form class="dataset_data">
							<input type="hidden" name="location" value="index.php?p=dataset&q=edit_data">
							<input type="hidden" name="datasetid" value="$this->datasetid">
							<input type="hidden" name="columnorder" value="$col">
							<input type="hidden" name="rownumber" value="$row">
							<input type="hidden" name="datatypeid" value="$datatypeid">
							$select
						</form>
					</td>
END;
					echo $form;
				}
			} //cols loop
			echo "</tr>";
		} //rows loop
		
		//add some BLANK ROWS to the bottom
		//4 for each column
		for ($r=1; $r<=4; $r++) {
		
			echo "<tr><th>$row</th>";
			
			for ($col=1; $col<=$max_cols; $col++) {
			
				//get the datatype for this column
				$SQL = "SELECT datatypeID, listcontent ";
				$SQL .= "FROM datacolumn ";
				$SQL .= "WHERE datasetID = $this->datasetid ";
				$SQL .= "AND columnorder = $col ";
				
				if (!$results = $db->query($SQL)) {
					trigger_error($db->error());
					trigger_error("Can not get the column datatype ", E_USER_ERROR);
				}
				
				$results_row = $db->get_row($results);
				
				$datatypeid = $results_row[0];
				$listcontent = $results_row[1];
				
				//if the list content is set to something then we need to give a drop down instead of a text box
				$list = false;
				if ($listcontent != "" && $datatypeid != 3) {
					$listcontent = split(",",$listcontent);
					$list = true;
				}
			
				//we should make it up when we do the forms, so we can set default values
				if ($list) {
					$select = "<select name='value'>";
					$select.= "<option value='' selected></option>";
					for ($i=0; $i<count($listcontent); $i++) {
						$list_val = $listcontent[$i];
						$select.="<option value='$list_val'>$list_val</option>";
					}
					$select.="</select>";
				}
				else {
					$class = "";
					if ($datatypeid == 3) $class = "class='date-pick'";
					$select = "<input $class type=\"text\" name=\"value\">";
				}
				
				$form = <<<END
				<td>
					<form class="dataset_data">
						<input type="hidden" name="location" value="index.php?p=dataset&q=add_data">
						<input type="hidden" name="datasetid" value="$this->datasetid">
						<input type="hidden" name="columnorder" value="$col">
						<input type="hidden" name="rownumber" value="$row">
						<input type="hidden" name="datatypeid" value="$datatypeid">
						$select
					</form>
				</td>
END;
				echo $form;
			}
			echo "</tr>";
			
			$row++;
		} //blank cols loop
		
		echo "</table>";
		
		return false;
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
				require("template/pages/dataset_view.php");
				break;
			
			case 'add':
				require("template/pages/dataset_add.php");
				break;
				
			case 'edit':
				require("template/pages/dataset_edit.php");
				break;
				
			case 'dataview':
				require("template/pages/dataview_view.php");
				break;
				
			case 'datacolumnadd':
				require("template/pages/datacolumn_add.php");
				break;
				
			case 'datacolumnedit':
				require("template/pages/datacolumn_edit.php");
				break;
				
			case 'csv':
				require("template/pages/dataset_csv_import.php");
				break;
				
			default:
				break;
		}
		
		return false;
	}
	
	function dataset_form($action="index.php?p=dataset&q=add_dataset", $refer="index.php?p=dataset&sp=view", $failrefer="index.php?p=dataset&sp=add", $onsubmit="") {
	
		$datasetid = $this->datasetid;
		$dataset = $this->dataset;
		$description = $this->description;
	
		$form = <<<END
		
			<form id="dataset_form" action="$action" method="post" $onsubmit>
				<input type="hidden" name="refer" value="$refer">
				<input type="hidden" name="failrefer" value="$failrefer">
				<input type="hidden" name="datasetid" value="$datasetid">
				
				<table>
					<tr>
						<td>Name</td>
						<td><input type="text" name="dataset" value="$dataset"></td>
					</tr>
					<tr>
						<td>Description</td>
						<td><input type="text" name="description" value="$description"></td>
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
	
	function datacolumn_form($action = "index.php?p=dataset&q=add_datacolumn", $refer = "index.php?p=dataset&sp=dataview", $failrefer = "index.php?p=dataset&sp=datacolumnadd",$onsubmit = "") {
		$db =& $this->db;
	
		$datasetid = $this->datasetid;
		
		$refer .= "&datasetid=$this->datasetid";
		$failrefer .= "&datasetid=$this->datasetid";
		
		if ($this->datacolumnid != "") {
		
			//make up the generated list from this column
			$SQL = "SELECT DISTINCT value FROM data WHERE datacolumnID = $this->datacolumnid ORDER BY 1";
			if (!$results = $db->query($SQL)) {
				trigger_error($db->error());
				trigger_error("Can not get the list content ", E_USER_ERROR);
			}
			$generated_list = "";
			while ($row = $db->get_row($results)) {
				$generated_list .= $row[0].',';
			}
			$generated_list = rtrim($generated_list, ",");
		}
		
		$datatype_list = dropdownlist("SELECT datatypeid, datatype FROM datatype ORDER BY datatype","datatypeid","$this->datatypeid");
		
		$form = <<<END
		
		<form method="post" id="datacolumn_form" action="$action" $onsubmit>
			<input type="hidden" name="refer" value="$refer">
			<input type="hidden" name="failrefer" value="$failrefer">
			<input type="hidden" name="datasetid" value="$datasetid">
			<input type="hidden" name="datacolumnid" value="$this->datacolumnid">
			<input type="hidden" id="generated_list" name="generated_list" value="$generated_list">
			<table>
				<tr>
					<td>Heading</td>
					<td><input type="text" name="heading" value="$this->heading">
				</tr>
				<tr>
					<td>Data Type</td>
					<td>$datatype_list</td>
				</tr>
				<tr>
					<td>List Content</td>
					<td><input type="text" id="listcontent" name="listcontent" value="$this->listcontent">
				</tr>
				<tr>
					<td>Column Order</td>
					<td><input type="text" name="order" value="$this->order">
				</tr>
				
				<tr>
					<td></td>
					<td>
						<div class="buttons">
							<button type="submit">Save</button>
							<a class="negative" href="$refer" alt="Cancel">Cancel</a>
							<a alt="Generate List" onclick="generate_list_content('generated_list','listcontent')">Generate List</a>
						</div>
					</td>
				</tr>
			</table>		
		</form>
END;
		echo $form;
		
		return true;
	}
	
	function add_dataset() {
		$db =& $this->db;
		
		$refer = $_POST['refer'];
		$failrefer = $_POST['failrefer'];
		
		$dataset 		= clean_input($_POST['dataset'], VAR_FOR_SQL, $db);
		$description 	= clean_input($_POST['description'], VAR_FOR_SQL, $db);
		
		$date = date('Y-m-d H:i:s');
		$userid = $_SESSION['userid'];
		
		if ($dataset == "" || $description == "") {
			setMessage('Dataset or description must have a value');
			return $failrefer;
		}
		
		//check for duplication
		$SQL = "SELECT datasetID FROM dataset WHERE dataset = '$dataset' AND userid = $userid";
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not query for duplicate datasets", E_USER_ERROR);
		}
		if ($db->num_rows($results)>0) {
			displayMessage(MSG_MODE_MANUAL, "You already have a dataset with this name [$dataset]");
			return $refer;
		}
		
		$SQL = "INSERT INTO dataset (dataset, description, createdDT, userid) ";
		$SQL.= "	   VALUES ('$dataset', '$description', '$date', $userid) ";
		
		if (!$datasetID = $db->insert_query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add the dataset", E_USER_ERROR);
		}
		
		/* Add default column */		
		$SQL = "INSERT INTO datacolumn (datasetID, heading, columnorder, datatypeID, listcontent) ";
		$SQL.= "       VALUES ($datasetID, 'column1', 1, 1, NULL)  ";
		
		if (!$datasetID = $db->insert_query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add the dataset", E_USER_ERROR);
		}
		setMessage('Dataset Added');
		
		return $refer;	
	}
	
	function edit_dataset() {
		$db =& $this->db;
		
		$refer 			= $_POST['refer'];
		$failrefer 		= $_POST['failrefer'];
		
		$datasetID 		= $_POST['datasetid'];
		$dataset 		= $_POST['dataset'];
		$description 	= $_POST['description'];
		
		$date = date('Y-m-d H:i:s');
		$userid = $_SESSION['userid'];
		
		if ($dataset == "" || $description == "") {
			setMessage('Dataset or description must have a value');
			return $failrefer;
		}
		
		$SQL = "UPDATE dataset SET dataset = '$dataset', description='$description' WHERE datasetid=$datasetID";
	
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not edit the dataset", E_USER_ERROR);
		}
		
		setMessage('Dataset Edited');
		
		return $refer;
	
	}
	
	function delete_dataset() {
		$db =& $this->db;
		
		$datasetID = $_REQUEST['datasetid'];
		$refer = "index.php?p=dataset&sp=view";
		
		$SQL = "DELETE FROM datacolumn WHERE datasetid=$datasetID";
		if (!$db->query($SQL)) {
			setMessage('You can not delete this datasets columns - associated information.');
			return $refer;
		}
		
		$SQL = "DELETE FROM dataset WHERE datasetid=$datasetID";
	
		if (!$db->query($SQL)) {
			setMessage('You can not delete this dataset.');
			return $refer;
		}
		
		setMessage('Dataset Deleted');
	
		return $refer;
	}
	
	function add_data() {
		$db =& $this->db;
		
		//expect some post vars
		$datasetid 		= clean_input($_POST['datasetid'], VAR_FOR_SQL, $db);
		$columnorder 	= clean_input($_POST['columnorder'], VAR_FOR_SQL, $db);
		$rownumber 		= clean_input($_POST['rownumber'], VAR_FOR_SQL, $db);
		$value 			= clean_input($_POST['value'], VAR_FOR_SQL, $db);
		
		$date = date("Y-m-d H:i:s");
		$userid = $_SESSION['userid'];
		
		//get the datacolumn id for this column order and dataset
		$SQL  = "";
		$SQL .= "SELECT  datacolumnID ";
		$SQL .= "FROM    datacolumn ";
		$SQL .= "WHERE datasetID = $datasetid AND columnorder = $columnorder ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the datacolumn id for this column cols", E_USER_ERROR);
		}
		
		$row = $db->get_row($results);
		
		$datacolumnid = $row[0];
		
		$SQL = " INSERT INTO data (datasetID, datacolumnID, rownumber, createdDT, modifiedDT, userID, value) ";
		$SQL.= "        VALUES ($datasetid, $datacolumnid, $rownumber, '$date','$date',$userid, '$value') ";
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			
			echo 0;
			exit;
		}
		echo 1;
		exit;
	}
	

	function edit_data() {
		$db =& $this->db;
		
		//expect some post vars
		$datasetid 		= clean_input($_POST['datasetid'], VAR_FOR_SQL, $db);
		$columnorder 	= clean_input($_POST['columnorder'], VAR_FOR_SQL, $db);
		$rownumber 		= clean_input($_POST['rownumber'], VAR_FOR_SQL, $db);
		$value 			= clean_input($_POST['value'], VAR_FOR_SQL, $db);
		
		$date = date("Y-m-d H:i:s");
		$userid = $_SESSION['userid'];
		
		//get the datacolumn id for this column order and dataset
		$SQL  = "";
		$SQL .= "SELECT  datacolumnID ";
		$SQL .= "FROM    datacolumn ";
		$SQL .= "WHERE datasetID = $datasetid AND columnorder = $columnorder ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			echo 0;
			exit;
		}
		
		$row = $db->get_row($results);
		
		$datacolumnid = $row[0];
		
		//we have a decision to make... if the selection is empty then we should not update, but delete
		if ($value == "") {
			//do the delete
			$SQL = "DELETE FROM data ";
			$SQL.= " WHERE datasetID = $datasetid AND datacolumnID = $datacolumnid AND rownumber = $rownumber ";
			
			$response = 2;
		}
		else {
			//do the update
			$SQL = " UPDATE data SET modifiedDT = '$date', value='$value' ";
			$SQL.= "WHERE datasetID = $datasetid AND datacolumnID = $datacolumnid AND rownumber = $rownumber ";
			
			$response = 1;
		}
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			
			echo 0;
			exit;
		}
		echo $response;
	
		exit;
	}
	
	function delete_data() {

		$db =& $this->db;
		
		//expect some post vars
		$chart_dataid 	= clean_input($_POST['chart_dataid'], VAR_FOR_SQL, $db);
		
		$SQL = " DELETE FROM chart_data WHERE chart_dataid = $chart_dataid ";
		
		if (!$db->query($SQL)) {
			trigger_error($db->error());
			setMessage("Unable to delete data");
		
			return $refer;
		}
		
		
		setMessage("Data Deleted");
		
		return $refer;

	}
	
	function add_datacolumn() {
		$db =& $this->db;
		
		$refer = $_POST['refer'];
		$failrefer = $_POST['failrefer'];
		
		//expect some post vars
		$datasetid 		= clean_input($_POST['datasetid'], VAR_FOR_SQL, $db);
		$heading 		= clean_input($_POST['heading'], VAR_FOR_SQL, $db);
		$datatypeid 	= clean_input($_POST['datatypeid'], VAR_FOR_SQL, $db);
		$listcontent	= clean_input($_POST['listcontent'], VAR_FOR_SQL, $db);
		
		if ($heading == "") {
			displayMessage(MSG_MODE_MANUAL, "You can't have an empty heading.");
			exit;
		}
		
		//the heading must be without spaces or special characters
		$heading = preg_replace('/\s+/','',$heading);
		$heading = ereg_replace("[^[:alnum:] ]","",$heading);
		
		//get the max column order for this data set
		$SQL  = "";
		$SQL .= "SELECT  MAX(columnorder) ";
		$SQL .= "FROM    datacolumn ";
		$SQL .= "WHERE datasetID = $datasetid ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the max cols", E_USER_ERROR);
		}
		
		$row = $db->get_row($results);
		$max_cols = $row[0];
		
		$columnorder = $max_cols + 1;
		
		$SQL = "INSERT INTO datacolumn (datasetID, heading, columnorder, datatypeID, listcontent) ";
		$SQL.= " VALUES ($datasetid, '$heading', $columnorder, $datatypeid, '$listcontent')";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add a new col", E_USER_ERROR);
		}
		
		setMessage('Column Added');
		
		return $refer;
	}
	
	function edit_datacolumn() {
		$db =& $this->db;
		
		$refer = $_POST['refer'];
		$failrefer = $_POST['failrefer'];
		
		//expect some post vars
		$datacolumnid	= clean_input($_POST['datacolumnid'], VAR_FOR_SQL, $db);
		$datasetid		= clean_input($_POST['datasetid'], VAR_FOR_SQL, $db);
		$heading 		= clean_input($_POST['heading'], VAR_FOR_SQL, $db);
		$datatypeid 	= clean_input($_POST['datatypeid'], VAR_FOR_SQL, $db);
		$listcontent	= clean_input($_POST['listcontent'], VAR_FOR_SQL, $db);
		$order			= clean_input($_POST['order'], VAR_FOR_SQL, $db);
		
		if ($heading == "") {
			displayMessage(MSG_MODE_MANUAL, "You can't have an empty heading.");
			exit;
		}
		//the heading must be without spaces or special characters
		$heading = preg_replace('/\s+/','',$heading);
		$heading = ereg_replace("[^[:alnum:] ]","",$heading);
		
		//need to check that the list content is good - i.e. there is no existing data that conflicts with it
		if ($listcontent != "") {
			$list = split(",",$listcontent);
			
			//we can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
			$select = "";
			for ($i=0; $i<count($list); $i++) {
				$list_val = $list[$i];
				$select.="'$list_val',";
			}
			$select = rtrim($select, ',');
			
			$SQL = "SELECT dataid FROM data WHERE datacolumnID = $datacolumnid AND value NOT IN ($select)";
			if (!$results = $db->query($SQL)) {
				trigger_error($db->error());
				trigger_error("Can not check list content is valid", E_USER_ERROR);
			}
			if ($db->num_rows($results) > 0) {
				displayMessage(MSG_MODE_MANUAL, "Incorrect List content - values outside the ones specified already exist.");
				exit;
			}
		}

		$SQL = "UPDATE datacolumn SET heading = '$heading', ";
		$SQL.= " datatypeID = $datatypeid, ";
		$SQL.= " listcontent = '$listcontent', ";
		$SQL.= " columnorder = $order ";
		$SQL.= " WHERE datacolumnID = $datacolumnid ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not add a new col", E_USER_ERROR);
		}
		
		//reorder - run through each column and reorder
		$SQL = "SELECT datacolumnID FROM datacolumn WHERE datasetID = $datasetid ORDER BY columnorder ";
		
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Get cols to reorder", E_USER_ERROR);
		}
		$i = 0;
		
		while ($row = $db->get_row($results)) {
			$i++;
			$upd_datacolumnid = $row[0];
		
			$SQL = "UPDATE datacolumn SET columnorder = $i WHERE datacolumnID = $upd_datacolumnid";
			
			if (!$db->query($SQL)) {
				trigger_error($db->error());
				trigger_error("Get cols to reorder", E_USER_ERROR);
			}
		}
		
		setMessage('Column Edited');
		
		return $refer;
	}

	function csv_import() {
		$db =& $this->db;
	
		//get the CSV from the session vars
		if(!isset($_SESSION['csv'])) {
			$this->csv = new csv();
		}
		else {
			$this->csv = $_SESSION['csv'];
		}
		$this->csv->set_db($db); //cant serialise a DB connection, so set it every time
	
		//works out the current stage of the invoice from the post vars
		$this->csv->determine_current_stage();
		
		$this->csv->current_stage();
	
		$_SESSION['csv'] = $this->csv; //store the object
	
		if ($this->sub_page != "csv") {
			exit;
		}
	}
	
}
?>