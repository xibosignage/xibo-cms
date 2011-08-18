<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner
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
	
	public $message;
	public $success;
	public $html;
	public $callBack;
	public $buttons;
	
	public $sortable;
	public $sortingDiv;
	
	public $dialogSize;
	public $dialogWidth;
	public $dialogHeight;
	public $dialogTitle;
	
	public $keepOpen;
	public $hideMessage;
	public $loadForm;
	public $loadFormUri;
	public $refresh;
	public $refreshLocation;
	public $focusInFirstInput;
        public $appendHiddenSubmit;
	
	public $login;
	public $clockUpdate;

        public $uniqueReference;
	
	public function __construct()
	{		
		// Determine if this is an AJAX call or not
		$this->ajax = Kit::GetParam('ajax', _REQUEST, _BOOL, false);
		
		// Assume success
		$this->success = true;
		$this->clockUpdate = false;
		$this->focusInFirstInput = true;
                $this->appendHiddenSubmit = true;
                $this->uniqueReference = '';
		$this->buttons = '';
		
		return true;
	}
	
	/**
	 * Sets the Default response if for a login box
	 * @return 
	 */	
	function Login() 
	{
		//prints out the login box
		$login_form 	= file_get_contents("template/pages/login_box_ajax.php");
		$this->message	= $login_form;
		$this->login	= true;
		$this->success	= false;
		
		return;
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
	 * @param $message String
	 */
	public function SetError($message)
	{
		$this->success = false;
		$this->message = $message;
		
		return;
	}
	
	/**
	 * Sets the Default response options for a form request
	 * @return 
	 * @param $form Object
	 * @param $title Object
	 * @param $width Object[optional]
	 * @param $height Object[optional]
	 * @param $callBack Object[optional]
	 */
	public function SetFormRequestResponse($form, $title, $width = '', $height = '', $callBack = '')
	{
		$this->html 					= $form;
		$this->dialogTitle 				= $title;
		$this->callBack 				= $callBack;
		
		if ($width != '' && $height != '')
		{
			$this->dialogSize 	= true;
			$this->dialogWidth 	= $width;
			$this->dialogHeight	= $height;
		}
		
		return;
	}
	
	/**
	 * Sets the Defaul response for a grid
	 * @return 
	 * @param $table Object
	 * @param $sortingDiv Object[optional]
	 */
	public function SetGridResponse($table, $sortingDiv = '.info_table table')
	{		
		$this->html 		= $table;
		$this->success		= true;
		$this->sortable		= true;
		$this->sortingDiv	= $sortingDiv;
		
		return;
	}
	
	/**
	 * Sets the Default response options for a form submit
	 * @return 
	 * @param $message String
	 * @param $refresh Boolean[optional]
	 * @param $refreshLocation String[optional]
	 */
	public function SetFormSubmitResponse($message, $refresh = false, $refreshLocation = '')
	{
		$this->success			= true;
		$this->message			= $message;
		$this->refresh			= $refresh;
		$this->refreshLocation 	= $refreshLocation;
		
		return;
	}
	
	/**
	 * Adds a button to the form
	 * @return 
	 * @param $name Object
	 * @param $function Object
	 */
	public function AddButton($name, $function)
	{
		$this->buttons[$name] = $function;
		
		return true;
	}

        /**
         * Responds with an Error
         * @param <string> $message
         * @param <bool> $keepOpen
         * @return <type>
         */
        public function Error($message, $keepOpen = false)
        {
            $this->SetError($message);
            $this->keepOpen = $keepOpen;
            $this->Respond();

            return false;
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
			$response['html'] 			= $this->html;
			$response['buttons']		= $this->buttons;
                        $response['uniqueReference'] = $this->uniqueReference;
			
			$response['success']		= $this->success;
			$response['callBack']		= $this->callBack;
			$response['message']		= $this->message;
			$response['clockUpdate']	= $this->clockUpdate;
			
			// Grids
			$response['sortable']		= $this->sortable;
			$response['sortingDiv']		= $this->sortingDiv;
			
			// Dialogs
			$response['dialogSize']		= $this->dialogSize;
			$response['dialogWidth']	= $this->dialogWidth;
			$response['dialogHeight'] 	= $this->dialogHeight;
			$response['dialogTitle']	= $this->dialogTitle;
			
			// Tweak the width and height
			$response['dialogWidth'] 	= (int) str_replace('px', '', $response['dialogWidth']);
			$response['dialogHeight'] 	= (int) str_replace('px', '', $response['dialogHeight']);
			
			// Form Submits
			$response['keepOpen']		= $this->keepOpen;
			$response['hideMessage']	= $this->hideMessage;
			$response['loadForm']		= $this->loadForm;
			$response['loadFormUri']	= $this->loadFormUri;
			$response['refresh']		= $this->refresh;
			$response['refreshLocation']= $this->refreshLocation;
			$response['focusInFirstInput']= $this->focusInFirstInput;
			
			// Login
			$response['login']			= $this->login;

                        // Log the response if we are auditing
                        //global $db;
                        //Debug::LogEntry($db, 'audit', json_encode($response), 'Response Manager', 'Respond');
			
			echo json_encode($response);
			
			// End the execution
			die();
		}
		else
		{			
			// If the response does not equal success then output an error
			if (!$this->success)
			{
				// Store the message
				$_SESSION['ErrorMessage'] 	= $this->message;
				
				// Redirect to the following
				$url						= 'index.php?p=error';
				
				// Header or JS redirect
				if (headers_sent()) 
				{
					echo "<script>document.location.href='$url';</script>\n";
				} 
				else 
				{
					header( 'HTTP/1.1 301 Moved Permanently' );
					header( 'Location: ' . $url );
				}
				
				// End the execution
				die();
			}
		}
		
		return;
	}
}

?>