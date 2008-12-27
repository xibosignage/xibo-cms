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
class licenseDAO 
{
	private $db;
	private $user;
	private $has_permissions = true;
	
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
		echo "License Management";
		return true;
	}
	
	function displayPage() 
	{
		$db =& $this->db;
		
		if (!$this->has_permissions) 
		{
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		include("template/pages/license_view.php");
	}
	
	function license_info() 
	{
		/**
		 * If we are on this page we must assume that the user already has a license
		 * as getting here is protected by a license.
		 * 
		 * Inputting a license from first principles will have to be done somewhere else
		 */
		$output = <<<END
		<h1>License Information</h1>
		Xibo - Digitial Signage - http://www.xibo.org.uk
		Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
		 
		Xibo is free software: you can redistribute it and/or modify
		it under the terms of the GNU Affero General Public License as published by
		the Free Software Foundation, either version 3 of the License, or
		any later version. 
		 
		Xibo is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU Affero General Public License for more details.
		 
		You should have received a copy of the GNU Affero General Public License
		along with Xibo.  If not, see <a href="http://www.gnu.org/licenses/">http://www.gnu.org/licenses/</a>. 
END;
		echo nl2br($output);
	}
}
?>