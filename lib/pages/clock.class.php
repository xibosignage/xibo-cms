<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

class clockDAO
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
		return false;
	}
	
	/**
	 * Shows the Time Information
	 * @return 
	 */
	function ShowTimeInfo()
	{
		$db				=& $this->db;
		$response		= new ResponseManager();
		$datemanager 	= new DateManager($db);
				
		$output  = '<h3>' . __('System Information') . '</h3>';
		$output .= '<ul>';
		$output .= '<li>' . __('Local Time') . ': ' . $datemanager->GetClock() . '</li>';
		$output .= '<li>' . __('System Time') . ': ' . $datemanager->GetSystemClock() . '</li>';
		$output .= '<li>' . __('Local Date') . ': ' . $datemanager->GetLocalDate('Y-m-d H:i:s') . '</li>';
		$output .= '<li>' . __('System Date') . ': ' . $datemanager->GetSystemDate('Y-m-d H:i:s') . '</li>';
		$output .= '</ul>';
		
		$response->SetFormRequestResponse($output, __('Date / Time Information'), '480px', '240px');
		$response->Respond();
	}
	
	/**
	 * Gets the Time
	 * @return 
	 */
	function GetClock()
	{
		$db				=& $this->db;
		$response		= new ResponseManager();
		$datemanager 	= new DateManager($db);
		
		$output = $datemanager->GetClock();
		
		$response->SetFormRequestResponse($output, __('Date / Time Information'), '480px', '240px');
		$response->clockUpdate 	= true;
		$response->success		= false;
		$response->Respond();
	}
}
?>