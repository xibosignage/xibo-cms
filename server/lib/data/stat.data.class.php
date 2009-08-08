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

class Stat extends data
{
	public function Add($type, $fromDT, $toDT, $scheduleID, $displayID, $layoutID, $mediaID, $tag)
	{
		$db 		=& $this->db;
		$statDate 	= date("Y-m-d H:i:s");
		$SQL		= '';
		
		$type		= $db->escape_string($type);
		
		// We should run different SQL depending on what Type we are
		switch ($type)
		{
			case 'Media':
			case 'media':
				$SQL .= " INSERT INTO stat (Type, statDate, scheduleID, displayID, layoutID, mediaID, start, end)";
				$SQL .= sprintf("  VALUES ('%s', '%s', %d, %d, %d, '%s', '%s', '%s')", $type, $statDate, $scheduleID, $displayID, $layoutID, $db->escape_string($mediaID), $fromDT, $toDT);
		
				break;
				
			case 'Layout':
			case 'layout':
				$SQL .= " INSERT INTO stat (Type, statDate, scheduleID, displayID, layoutID, start, end)";
				$SQL .= sprintf("  VALUES ('%s', '%s', %d, %d, %d, '%s', '%s')", $type, $statDate, $scheduleID, $displayID, $layoutID, $fromDT, $toDT);

				break;
				
			case 'Event':
			case 'event':
			
				$SQL .= " INSERT INTO stat (Type, statDate, scheduleID, displayID, layoutID, start, end, Tag)";
				$SQL .= sprintf("  VALUES ('%s', '%s', %d, %d, %d, '%s', '%s', '%s')", $type, $statDate, $scheduleID, $displayID, 0, $fromDT, $toDT, $db->escape_string($tag));
			
				break;
				
			default:
				// Nothing to do, just exit
				return true;
		}
		
		// We only get here if we have some SQL to run
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25000, 'Stat Insert Failed.');
			return false;
		}
		
		return true;
	}
}
?>