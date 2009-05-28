<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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

class displaygroupDAO
{
	private $db;
	private $user;
	
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	
		include_once('lib/data/displaygroup.data.class.php');
		
		
	}
	
	function on_page_load() 
	{
		return "";
	}

	function echo_page_heading() 
	{
		echo __("Display Group Administration");
		return true;
	}
	
	public function displayPage()
	{
		require("template/pages/displaygroup_view.php");
		
		return false;
	}
	
	/**
	 * Shows the Filter form for display groups
	 * @return 
	 */
	public function Filter()
	{
		$filterForm = <<<END
		<div class="FilterDiv" id="DisplayGroupFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="displaygroup">
				<input type="hidden" name="q" value="Grid">
			</form>
		</div>
END;
		
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$filterForm
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		echo $xiboGrid;
	}
	
	/**
	 * Shows the Display groups
	 * @return 
	 */
	public function Grid()
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();	
					
		//display the display table
		$SQL = <<<SQL
		SELECT DisplayGroupID, DisplayGroup
		FROM displaygroup
		ORDER BY DisplayGroup
SQL;

		if(!($results = $db->query($SQL))) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not list Display Groups"), E_USER_ERROR);
		}
		
		$msgSave	= __('Save');
		$msgCancel	= __('Cancel');
		$msgAction	= __('Action');
		$msgEdit	= __('Edit');
		$msgDelete	= __('Delete');
		$msgDisplayGroup = __('Display Group');
		
		$output = <<<END
		<div class="info_table">
		<table style="width:100%">
			<thead>
			<tr>
				<th>$msgDisplayGroup</th>
				<th>$msgAction</th>
			</tr>
			</thead>
			<tbody>
END;
		
		while($row = $db->get_assoc_row($results))
		{
			$displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
			$displayGroup	= Kit::ValidateParam($row['DisplayGroup'], _STRING);
		}
		
		$output .= "</tbody></table></div>";
		
		$response->SetGridResponse($output);
		$response->Respond();
	}
}  
?>