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

class DateManager
{
	private $db;
	
	public function __construct(database $db)
	{
		$this->db 	=& $db;
	}
	
	public function GetClock()
	{
		return date("H:i T");
	}
	
	public function GetSystemClock()
	{
		return gmdate("H:i T");
	}
	
	public function GetLocalDate($format = 'Y-m-d H:i:s', $timestamp = '')
	{
		$db	=& $this->db;
		
		if ($timestamp == '')
		{
			$timestamp = time();
		}
		
		Debug::LogEntry($db, 'audit', 'Converting ' . $timestamp . ' to a Local Date');
		
		return date($format, $timestamp);
	}
	
	public function GetSystemDate($format = 'Y-m-d H:i:s', $timestamp = '')
	{
		$db	=& $this->db;
				
		if ($timestamp == '')
		{
			$timestamp = time();
		}
		
		Debug::LogEntry($db, 'audit', 'Converting ' . $timestamp . ' to a System Date');
		
		return gmdate($format, $timestamp);
	}

        /**
         * Gets an ISO date from a US formatted date string
         * @param <string> $usDate
         * @param <string> $time
         */
        public function GetDateFromUS($usDate, $time)
        {
            // Split the US date by /
            $datePart = explode('/', $usDate);

            return (int) strtotime($datePart[2] . '-' . $datePart[1] . '-' . $datePart[0] . ' ' . $time);
        }
} 
?>