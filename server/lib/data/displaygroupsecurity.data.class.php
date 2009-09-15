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

class DisplayGroupSecurity extends Data
{
	public function __construct(database $db)
	{
		include_once('lib/data/schedule.data.class.php');
		
		parent::__construct($db);
	}
	
	/**
	 * Links a Display Group to a Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $groupID Object
	 */
	public function Link($displayGroupID, $groupID)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroupSecurity', 'Link');
		
		$SQL  = "";
		$SQL .= "INSERT ";
		$SQL .= "INTO   lkgroupdg ";
		$SQL .= "       ( ";
		$SQL .= "              DisplayGroupID, ";
		$SQL .= "              GroupID ";
		$SQL .= "       ) ";
		$SQL .= "       VALUES ";
		$SQL .= "       ( ";
		$SQL .= sprintf("              %d, %d ", $displayGroupID, $groupID);
		$SQL .= "       )";
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Could not Link Display Group to Group'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroupSecurity', 'Link');
		
		return true;
	}
	
	/**
	 * Unlinks a display group from a group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $groupID Object
	 */
	public function Unlink($displayGroupID, $groupID) 
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroupSecurity', 'Unlink');
		
		$SQL  = "";
		$SQL .= "DELETE FROM ";
		$SQL .= "   lkgroupdg ";
		$SQL .= sprintf("  WHERE DisplayGroupID = %d AND GroupID = %d ", $displayGroupID, $groupID);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25007, __('Could not Unlink Display Group from Group'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroupSecurity', 'Unlink');
		
		return true;
	}
} 
?>