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
		echo __("License Management");
		return true;
	}
	
	function displayPage() 
	{
		$db =& $this->db;
		
		include("template/pages/license_view.php");
	}
	
	function license_info() 
	{
		$output = <<<END
		<h1>License Information</h1>
		Xibo - Digitial Signage - http://www.xibo.org.uk
		Copyright (C) 2006,2007,2008,2009 Daniel Garner, James Packer and Alex Harrington
		 
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