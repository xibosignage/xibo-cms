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

class faultDAO
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
	
		include("template/pages/fault_view.php");
		
		return false;
	}
	
	function on_page_load() 
	{
		return '';
	}
	
	function echo_page_heading() 
	{
		echo 'Report a Fault';
		return true;
	}
	
	function ReportForm()
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		$output = '';
		
		$config = new Config($db);

		$output .= '<div class="ReportFault">';		
		$output .= '<ol>';		
		$output .= '<li><p>Check that the Environment passes all the Xibo Environment checks.</p>';		
		$output .= $config->CheckEnvironment();
		$output .= '</li>';

		$output .= '<li><p>Turn ON full auditing and debugging.</p>';
		$output .= '	<form class="XiboForm" action="index.php?p=admin" method="post">';
		$output .= '		<input type="hidden" name="q" value="SetMaxDebug" />';
		$output .= '		<input type="submit" value="Turn ON Debugging" />';
		$output .= '	</form>';
		$output .= '</li>';

		$output .= '<li><p>Recreate the Problem in a new window.</p>';		
		$output .= '</li>';
		
		$output .= '<li><p>Automatically collect and export relevant information into a text file. Please save this file to your PC.</p>';		
		$output .= '</li>';

		$output .= '<li><p>Turn full auditing and debugging OFF.</p>';	
		$output .= '	<form class="XiboForm" action="index.php?p=admin" method="post">';
		$output .= '		<input type="hidden" name="q" value="SetMinDebug" />';
		$output .= '		<input type="submit" value="Turn OFF Debugging" />';
		$output .= '	</form>';	
		$output .= '</li>';
		
		$output .= '<li><p>Click on the below link to open the bug report page for this Xibo release. Describe the problem and upload the file you obtained earlier.</p>';		
		$output .= '</li>';
		
		$output .= '</ol>';
		$output .= '</div>';
		
		echo $output;	
		return;
	}
}
?>