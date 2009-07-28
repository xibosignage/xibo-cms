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
class Stat extends data
{
	public function Add($type, $fromDT, $toDT, $scheduleID, $displayID, $layoutID, $mediaID)
	{
		$db 		=& $this->db;
		$statDate 	= date("Y-m-d H:i:s");
		
		$SQL  = "";
		$SQL .= " INSERT INTO stat (statDate, scheduleID, displayID, layoutID, mediaID, start, end)";
		$SQL .= sprintf("  VALUES ('%s', %d, %d, %d, '%s', '%s', '%s')", $statDate, $scheduleID, $displayID, $layoutID, $db->escape_string($mediaID), $fromDT, $toDT);
		
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