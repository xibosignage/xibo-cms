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
	private $ajax;
	private $success;
	private $message;
	
	public function __construct()
	{
		// Determine if this is an AJAX call or not
		$this->ajax	= Kit::GetParam('ajax', _REQUEST, _BOOL, false);
		
		return true;
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
	
	/**
	 * Sets the error message for the response
	 * @return 
	 * @param $message Object
	 */
	public function SetError($message)
	{
		$this->success = false;
		$this->message = $message;
		
		return true;
	}
	
	/**
	 * Outputs the Response to the browser
	 * @return 
	 */
	public function Respond()
	{
		if ($this->ajax)
		{
			// Construct the Response
			$response 					= array();
			
			// General
			$response['html'] 			= $table;
			$response['success']		= $this->success;
			$response['callBack']		= '';
			$response['message']		= $this->message;
			
			// Grids
			$response['sortable']		= true;
			$response['sortingDiv']		= '.info_table table';
			
			// Dialogs
			$response['dialogSize']		= true;
			$response['dialogWidth']	= '400px';
			$response['dialogHeight'] 	= '180px';
			$response['dialogTitle']	= 'Add/Edit Group';
			
			// Form Submits
			$response['keepOpen']		= true;
			$response['hideMessage']	= false;
			$response['loadForm']		= false;
			$response['loadFormUri']	= '';
			$response['refresh']		= false;
			$response['refreshLocation']= '';
			
			// Login
			$response['login']			= false;
			
			echo json_encode($response);
			
			// End the execution
			die();
		}
		else
		{
			// If the response does not equal success then output an error
			if (!$this->success)
			{
				setMessage($this->message);
				trigger_error($this->message, E_USER_ERROR);
				
				// End the execution
				die();
			}
		}
		
		return;
	}
}

?>