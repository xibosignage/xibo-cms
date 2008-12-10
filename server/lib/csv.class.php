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
 
class csv {

	private $db;
	private $current_stage;
	
	private $table;

	function __construct() 
	{
		
	}
	
	function set_db(database $db) 
	{
		$this->db =& $db;
		
		return true;
	}
	
	function determine_current_stage() 
	{
		//looks at the POST vars to determine the current stage
		if (!isset($_POST['csv_stage'])) 
		{
			$this->current_stage = "";
		}
		else 
		{
			$this->current_stage = $_POST['csv_stage'];
		}
	}

	function current_stage() 
	{
		//determine which stage we are on from the current_stage var
		$current_stage = $this->current_stage;
		
		switch ($current_stage) {
			case '':
				//we are on the first stage
				$this->upload_form();
				break;
				
			case 'review':
				$this->review();
				break;
		
			case 'import':
				//we are on the second stage
				$this->import();
				break;
				
			case 'exec_import':
				$this->exec_import();
				break;
		}
	}
	
	function upload_form() 
	{
		$form = <<<END
<form id="upload_form" action="index.php?p=dataset&sp=csv" method="post" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="1048576000">
	<input type="hidden" name="csv_stage" value="review" />
	<table>
		<tr> 
			<td>Find your CSV File</td>
			<td>
				<input name="csv_file" type="file">
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<div class="buttons">
					<button class="positive" type='submit'>Import</button>
					<a class="negative" href="index.php?p=dataset&sp=view" alt="Cancel">Cancel</a>
				</div>
			</td>
		</tr>
	</table>
</form>
END;
		echo $form;
	}
	
	function review() 
	{
		//reviews the data in the file.. involves presenting it in a list such that the user can select their desired columns
		$table;
		
		//echo "<pre>"; print_r($_FILES); echo "</pre>";
		
		if(!$file = fopen($_FILES['csv_file']['tmp_name'], "r")) 
		{
			displayMessage(MSG_MODE_MANUAL, "Can not open CSV File");
			exit;
		}
		
		$dataset = $_FILES['csv_file']['name'];
		$ext = strtolower(substr(strrchr($dataset, "."), 1));
		
		if ($ext != "csv") 
		{
			displayMessage(MSG_MODE_MANUAL, "Can only upload CSV files");
			exit;
		}
		
		$row = 0;
		$max_cols = 0;
		while($data = fgetcsv($file)) 
		{
		
			$table[$row] = $data;
			
			if (count($data) > $max_cols) 
			{
				$max_cols = count($data);
			}
			
			$row++;
		}
		$row = count($table);
		
		$table['num_rows'] = count($table);
		$table['max_cols'] = $max_cols;
		$this->table = $table; //preserve this data
		
		//output this data back into a ROW format
		$review = <<<END
		<div class="info_table">
			<form class="csv_nav" method='post' onsubmit="return false">
			<input type="hidden" name="csv_stage" value="import" />
			<table>
				<tr>
					<th>Dataset Name</th>
					<th><input type="text" name="dataset" value="$dataset"></th>
				</tr>
				<tr>
					<th>Row #</th>
END;
		$cols = $table[0]; //grab the first row in the CSV, there are normally headings here

		$checkbox = "<tr><td>Add Cols</td>";
		
		for ($i=0; $i < $max_cols; $i++) 
		{ //FIRST ROW + CHECKBOX ROW
			$value = "";
			$value = $cols[$i]; //use the first row to default the input boxes
			$review .= <<<END
			<th><input type="text" name="col$i" value="$value"></th>
END;
			$checkbox .= <<<END
			<td><input type="checkbox" name="chk_col$i"></td>
END;
		}
		$review .= "</tr>";
		$checkbox .= "</tr>";
		
		for ($i=0; $i < count($table)-2; $i++) 
		{ //each ROW
			
			$cols = $table[$i];
			
			$review.= "<tr><td>$i</td>";
			
			for ($c = 0; $c < count($cols); $c++) 
			{ //each COL
				$value = "";
				$value = $cols[$c];
			
				$review.= "<td>$value</td>";
			
			}
			$review.= "</tr>";
		}
		
		$review .= $checkbox;
		
		//we also need some fields to determine which rows are added
		$row--;
		$review .= <<<END
		<tr>
			<td>Row Range</td>
			<td><input type="text" name="rowsfrom" value="1"></td>
			<td><input type="text" name="rowsto" value="$row"></td>
		</tr>
		<tr>
			<td><button type="submit">Import</button></td>
		</tr>
END;
		
		//close table and form
		$review .= "</table>";
		$review .= "</form></div>";
		
		echo $review;
		
		/*echo "<pre>";
		print_r($table);
		echo "</pre>";*/
		
		return true;
	}
	
	//checks the selected data, and imports
	function import() 
	{
		$db =& $this->db;
		//get some data
		
		$table = $this->table;
		
		$max_cols = $table['max_cols'];
		$num_rows = $table['num_rows'];
		
		$rows_from 	= $_REQUEST['rowsfrom'];
		$rows_to 	= $_REQUEST['rowsto'];
		
		$dataset	= $_REQUEST['dataset'];
		$userid 	= $_SESSION['userid'];
		
		if ($dataset == "") 
		{
			$form = <<<FORM
			No dataset name
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="" />
				<input type="submit" value="Back" />
			</form>
FORM;
			displayMessage(MSG_MODE_MANUAL, $form, false);
			exit;
		}
		
		//check for duplication
		$SQL = "SELECT datasetID FROM dataset WHERE dataset = '$dataset' AND userid = $userid";
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can not query for duplicate datasets", E_USER_ERROR);
		}
		
		if ($db->num_rows($results)>0) 
		{
			$form = <<<FORM
			You already have a dataset with this name [$dataset]
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="" />
				<input type="submit" value="Back" />
			</form>
FORM;
			displayMessage(MSG_MODE_MANUAL, $form, false);
			exit;
		}
		
		$this->table['dataset'] = $dataset;
		
		//check that we have some rows selected AND that they exist
		$num_rows_to_add = $rows_to - $rows_from;
		
		if (($rows_to - $rows_from) < 0 || ($rows_to - $rows_from) > $num_rows) 
		{
			//error - for errors we set the previous state to this, and halt
			$form = <<<FORM
			No rows selected
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="" />
				<input type="submit" value="Back" />
			</form>
FORM;
			displayMessage(MSG_MODE_MANUAL, $form, false);
			exit;
		}
		
		//loop around the possible cols (max_cols) and for each one check to see if the check box was checked or not
		//this will determine which rows the user wants added.
		$cols_to_add;
		
		for ($c = 0; $c < $max_cols; $c++) 
		{ //for each possible COL
			
			//check to see if the check box is checked
			if (isset($_REQUEST["chk_col$c"]) == "on") 
			{
				$cols_to_add[count($cols_to_add)+1] = array($_REQUEST["col$c"], $c);
			}
		
		}
		//if we get to the end, and there is nothing in the $cols_to_add, then error
		if (count($cols_to_add) < 1) 
		{
			//error - for errors we set the previous state to this, and halt
			$form = <<<FORM
			No columns selected.
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="" />
				<input type="submit" value="Back" />
			</form>
FORM;
			displayMessage(MSG_MODE_MANUAL, $form, false);
			exit;
		}
		$num_cols_to_add = count($cols_to_add);
		
		$this->table['cols_to_add'] = $cols_to_add;
		$this->table['rows_from'] 	= $rows_from;
		$this->table['rows_to'] 	= $rows_to;
		
		$output = <<<END
		<div class="info_table">
			<p>You have chosed to add:
				<ul>
					<li>Columns: $num_cols_to_add</li>
					<li>Rows: $num_rows_to_add</li>
				</ul>
			</p>
			<p>Press ok to continue</p>
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="exec_import" />
				<input type="submit" value="Import" />
			</form>
			<form class="csv_nav" method='post' onsubmit="return false">
				<input type="hidden" name="csv_stage" value="review" />
				<input type="submit" value="Back" />
			</form>
		</div>
END;
		echo $output;	
	}
	
	//execs the import
	function exec_import() 
	{
		$db =& $this->db;
		
		$date 		= date('Y-m-d H:i:s');
		$userid 	= $_SESSION['userid'];
		
		$table 		= $this->table; //contains the CSV file
		$dataset	= $table['dataset'];
		$rows_from 	= $table['rows_from'];
		$rows_to 	= $table['rows_to'];
		
		//we need to add the dataset
		$SQL = "INSERT INTO dataset (dataset, description, createdDT, userid) ";
		$SQL.= "	   VALUES ('$dataset', 'Created by the CSV Importer', '$date', $userid) ";
		
		if (!$datasetid = $db->insert_query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can not add the dataset", E_USER_ERROR);
		}
		
		//echo "<pre>"; print_r($table); echo "</pre>";
		
		//add the columns
		$cols_to_add = $table['cols_to_add'];
		
		for ($c = 1; $c <= count($cols_to_add); $c++) {
			//for each column, get the name
			$heading = $cols_to_add[$c][0];
			
			//the heading must be without spaces or special characters
			$heading = preg_replace('/\s+/','',$heading);
			$heading = ereg_replace("[^[:alnum:] ]","",$heading);
			
			$col_in_table = $cols_to_add[$c][1];
			
			$SQL = "INSERT INTO datacolumn (datasetID, heading, columnorder, datatypeID, listcontent) ";
			$SQL.= " VALUES ($datasetid, '$heading', $c, 1, '')";
			
			if (!$datacolumnid = $db->insert_query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error("Can not add a new col", E_USER_ERROR);
			}
		
			//add the data (rows) for this column
			$count = 0;
			for ($r = $rows_from; $r <= $rows_to; $r++) 
			{
				//for each row
				$value = $table[$r][$col_in_table];
				$count++;
			
				$SQL = " INSERT INTO data (datasetID, datacolumnID, rownumber, createdDT, modifiedDT, userID, value) ";
				$SQL.= " VALUES ($datasetid, $datacolumnid, $count, '$date', '$date', $userid, '$value') ";
				
				if (!$db->query($SQL)) 
				{
					trigger_error($db->error());
					trigger_error("Can not add data", E_USER_ERROR);
				}
			}
		}
		
		$form = <<<FORM
		<form class="csv_nav" method='post' onsubmit="return false">
			<input type="hidden" name="csv_stage" value="" />
			<input type="submit" value="Done" />
		</form>
		<a href="index.php?p=dataset&sp=view" alt="Datasets">View Datasets</a>
FORM;
		echo $form;
	
	}
}
?>