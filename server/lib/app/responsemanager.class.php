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
 
class ResponseManager 
{
	/**
	 * 
	 * 0 = Success
	 * 1 = Failure
	 * 2 = Log In
	 * 3 = Redirect
	 * 4 = Refresh
	 */
	function __construct() 
	{
		
	}
	
	function login() 
	{
		//prints out the login box
		$login_form = file_get_contents("template/pages/login_box_ajax.php");
		
		$this->response("2", $login_form);
	}
	
	function decode_response($success, $message) 
	{
		//return code to all AJAX forms
		if ($success) 
		{
			$code = 0;
		}
		else 
		{
			$code = 1;
		}
		$this->response($code, $message);
	}
	
	function response($code, $html = "") 
	{
		//output the code and exit
		echo "$code|$html";
		exit;
	}
}

?>