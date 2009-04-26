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

class mediamanagerDAO 
{
	private $db;
	private $user;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}
	
	function on_page_load() 
	{
		return "";
	}
	
	function echo_page_heading() 
	{
		global $user;
		
		$userid = Kit::GetParam('userid', _SESSION, _INT);
		$uid 	= $user->getNameFromID($userid);
		
		echo "$uid's " . __('Dashboard');
		return true;
	}

	function displayPage() 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		$layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);
		$regionid = Kit::GetParam('regionid', _REQUEST, _INT);
		
		$SQL = sprintf("SELECT layout FROM layout WHERE layoutID = %d ", $layoutid);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error(__("Incorrect home page setting, please contact your system admin."), E_USER_ERROR);
		}
		
		$row = $db->get_row($result);
		
		$layout = Kit::ValidateParam($row[0], _STRING);
		
		/**
		 * Include the design layer for this page
		 */
		include_once("template/pages/mediamanager.php");
	}
}
?>