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

class statsDAO
{
	private $db;
	private $user;
	
	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}
	
	function displayPage() 
	{
	
		include("template/pages/stats_view.php");
		
		return false;
	}
	
	function on_page_load() 
	{
		return '';
	}
	
	function echo_page_heading() 
	{
		echo 'Display Statistics';
		return true;
	}
	
	public function StatsForm()
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		$output = '';
		
		$fromdt			= date("Y-m-d H:i:s", time() - 86400);
		$todt			= date("Y-m-d H:i:s");
		$display_list 	= dropdownlist("SELECT 'All', 'All' UNION SELECT displayID, display FROM display WHERE licensed = 1 ORDER BY 2", "displayid");
		
		// We want to build a form which will sit on the page and allow a button press to generate a CSV file.
		$output	.= '<script type="text/javascript">$(document).onload(function(){$(".date-pick").datepicker({dateFormat: "dd/mm/yy"})});</script>';
		$output	.= '<form action="index.php?p=stats&q=OutputCSV" method="post">';
		$output	.= ' <table>';
		$output	.= '  <tr>';
		$output	.= '   <td>From Date</td>';
		$output	.= '   <td><input type="text" class="date-pick" name="fromdt" value="' . $fromdt . '"/></td>';
		$output	.= '   <td>To Date</td>';
		$output	.= '   <td><input type="text" class="date-pick" name="todt" value="' . $todt . '" /></td>';
		$output	.= '  </tr>';
		$output	.= '  <tr>';
		$output	.= '   <td>Display</td>';
		$output	.= '   <td>' . $display_list . '</td>';
		$output	.= '  </tr>';
		
		$output	.= '  <tr>';
		$output	.= '   <td><input type="submit" value="Export" /></td>';
		$output	.= '  </tr>';
		$output	.= ' </table>';
		$output	.= '</form>';
		
		echo $output;
	}
	
	/**
	 * Outputs a CSV of stats
	 * @return 
	 */
	public function OutputCSV()
	{
		$db 		=& $this->db;
		$output		= '';
		
		// We are expecting some parameters
		$fromdt		= Kit::GetParam('fromdt', _POST, _STRING);
		$todt		= Kit::GetParam('todt', _POST, _STRING);
		$displayID	= Kit::GetParam('displayid', _POST, _INT);
		
		// We want to output a load of stuff to the browser as a text file.
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="stats.csv"');
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		
		$SQL =  'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName ';
		$SQL .= '  FROM stat ';
		$SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
		$SQL .= '  INNER JOIN layout ON layout.LayoutID = stat.LayoutID ';
		$SQL .= '  LEFT OUTER JOIN media ON media.mediaID = stat.mediaID ';
		$SQL .= ' WHERE 1=1 ';
		$SQL .= sprintf("  AND stat.end > '%s' ", $fromdt);
		$SQL .= sprintf("  AND stat.start <= '%s' ", $todt);

		if ($displayID != 0)
		{
			$SQL .= sprintf("  AND stat.displayID = %d ", $displayID);
		}
		
		Debug::LogEntry($db, 'audit', $SQL, 'Stats', 'OutputCSV');
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error('Failed to query for Stats.', E_USER_ERROR);
		}
		
		// Header row
		$output		.= "Type, FromDT, ToDT, Layout, Display, Media, Tag\n";
		
		while($row = $db->get_assoc_row($result))
		{
			// Read the columns
			$type		= Kit::ValidateParam($row['Type'], _STRING);
			$fromdt		= Kit::ValidateParam($row['start'], _STRING);
			$todt		= Kit::ValidateParam($row['end'], _STRING);
			$layout		= Kit::ValidateParam($row['Layout'], _STRING);
			$display	= Kit::ValidateParam($row['Display'], _STRING);
			$media		= Kit::ValidateParam($row['MediaName'], _STRING);
			$tag		= Kit::ValidateParam($row['Tag'], _STRING);
			
			$output		.= "$type, $fromdt, $todt, $layout, $display, $media, $tag\n";
		}
		
		//Debug::LogEntry($db, 'audit', 'Output: ' . $output, 'Stats', 'OutputCSV');
		
		echo $output;
		exit;
	}
}
?>