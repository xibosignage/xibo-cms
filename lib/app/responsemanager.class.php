<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
	public $fieldActions;
	
	public $sortable;
	public $sortingDiv;
    public $paging;
    public $pageSize;
    public $pageNumber;
    public $initialSortColumn;
    public $initialSortOrder;
	
	public $dialogSize;
	public $dialogWidth;
	public $dialogHeight;
	public $dialogTitle;
	public $dialogClass;
	
	public $keepOpen;
	public $hideMessage;
	public $loadForm;
	public $loadFormUri;
	public $refresh;
	public $refreshLocation;
	public $focusInFirstInput;
    public $appendHiddenSubmit;
    public $modal;
    public $nextToken;
	
	public $login;
	public $clockUpdate;

    public $uniqueReference;

    public $extra;
	
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
		$this->fieldActions = '';
        $this->pageSize = 10;
        $this->pageNumber = 0;
        $this->initialSortColumn = 1;
        $this->initialSortOrder = 1;
        $this->modal = false;
        $this->extra = array();
        $this->dialogClass = '';

        // Start a DB transaction for all returns from the Web Portal
        try {
            $dbh = PDOConnect::init();

            if (!$dbh->inTransaction())
            	$dbh->beginTransaction();
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            trigger_error(__('Unable to open connection and start transaction'), E_USER_ERROR);
        }
		
		return true;
	}
	
	/**
	 * Sets the Default response if for a login box
	 */	
	function Login() 
	{
		$this->login	= true;
		$this->success	= false;
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
		if ($form == NULL)
			$form = Theme::RenderReturn('form_render');

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
	public function SetGridResponse($table, $sortingDiv = 'table')
	{		
		$this->html 		= $table;
		$this->success		= true;
		$this->sortable		= true;
		$this->sortingDiv	= $sortingDiv;
        $this->paging = true;
		
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
		$this->nextToken = Kit::Token();	
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
	 * Add a Field Action to a Field
	 * @param string $field   The field name
	 * @param string $action  The action name
	 * @param string $value  The value to trigger on
	 * @param string $actions The actions (field => action)
	 * @param string $operation The Operation (optional)
	 */
	public function AddFieldAction($field, $action, $value, $actions, $operation = "equals") {
		$this->fieldActions[] = array(
			'field' => $field,
			'trigger' => $action, 
			'value' => $value,
			'operation' => $operation,
			'actions' => $actions
			);

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
		// Roll back any open transactions if we are in an error state
		try {
			$dbh = PDOConnect::init();
			
			if (!$this->success) {
				$dbh->rollBack();
			}
			else {
				$dbh->commit();
			}
		}
		catch (Exception $e) {
			Debug::LogEntry('audit', 'Unable to commit/rollBack');
        }

		if ($this->ajax)
		{
			// Construct the Response
			$response 					= array();
			
			// General
			$response['html'] 			= $this->html;
			$response['buttons']		= $this->buttons;
			$response['fieldActions'] = $this->fieldActions;
            $response['uniqueReference'] = $this->uniqueReference;
			
			$response['success']		= $this->success;
			$response['callBack']		= $this->callBack;
			$response['message']		= $this->message;
			$response['clockUpdate']	= $this->clockUpdate;
			
			// Grids
			$response['sortable']		= $this->sortable;
			$response['sortingDiv']		= $this->sortingDiv;
            $response['paging'] = $this->paging;
            $response['pageSize'] = $this->pageSize;
            $response['pageNumber'] = $this->pageNumber;
            $response['initialSortColumn'] = $this->initialSortColumn - 1;
            $response['initialSortOrder'] = $this->initialSortOrder - 1;
			
			// Dialogs
			$response['dialogSize']		= $this->dialogSize;
			$response['dialogWidth']	= $this->dialogWidth;
			$response['dialogHeight'] 	= $this->dialogHeight;
			$response['dialogTitle']	= $this->dialogTitle;
			$response['dialogClass'] = $this->dialogClass;
			
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
			$response['modal'] = $this->modal;
			$response['nextToken'] = $this->nextToken;
			
			// Login
			$response['login']			= $this->login;

			// Extra
			$response['extra'] = $this->extra;

            // Clear the object buffer, and if it isn't empty error with the contents.
            $buffer = ob_get_clean();

            if ($buffer != '')
                trigger_error($buffer, E_USER_ERROR);
			
			echo json_encode($response);
			
			// End the execution
			die();
		}
		else {			
			// If the response does not equal success then output an error
			if (!$this->success) {
				// Store the message
				$_SESSION['ErrorMessage'] 	= $this->message;
				
				// Redirect to the following
				$url = 'index.php?p=error';
			}
			else {
				// Have we been asked to refresh?
				$url = ($this->refresh) ? $this->refreshLocation : 'index.php?p=' . Kit::GetParam('p', _GET, _WORD, 'index');
			}

			// Redirect and end execution
			Kit::Redirect($url);
		}
		
		return;
	}
        
    public static function Pager($id, $type = 'grid_pager') {
        Theme::Set('pager_id', 'XiboPager_' . $id);
        
        return Theme::RenderReturn($type);
    }
}
?>
