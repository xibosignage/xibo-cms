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
 
class logDAO 
{
	private $db;
	private $user;

	function __construct(database $db, user $user) 
	{
		$this->db 	=& $db;
		$this->user =& $user;
	}

	public function displayPage() 
	{
		$db =& $this->db;

		// Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="log"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));
        Theme::Set('truncate_url', 'index.php?p=log&q=TruncateForm');

        // Construct Filter Form
        if (Kit::IsFilterPinned('log', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_type', Session::Get('user', 'filter_type'));
            Theme::Set('filter_page', Session::Get('user', 'filter_page'));
            Theme::Set('filter_function', Session::Get('user', 'filter_function'));
            Theme::Set('filter_display', Session::Get('user', 'filter_display'));
            Theme::Set('filter_fromdt', Session::Get('user', 'filter_fromdt'));
            Theme::Set('filter_seconds', Session::Get('user', 'filter_seconds'));
        }
        else {
            Theme::Set('filter_type', '0');
            Theme::Set('filter_seconds', 120);
            Theme::Set('filter_page', '0');
            Theme::Set('filter_function', '0');
			Theme::Set('filter_display', 0);
        }

        // Lists
        $types = array(array('typeid' => '0', 'type' => 'All'), array('typeid' => 'audit', 'type' => 'Audit'), array('typeid' => 'error', 'type' => 'Error'));
        Theme::Set('type_field_list', $types);

		$pages = $db->GetArray("SELECT DISTINCT IFNULL(page, '-1') AS pageid, page FROM log ORDER BY 2");
        array_unshift($pages, array('pageid' => '0', 'page' => 'All'));
        Theme::Set('page_field_list', $pages);

        $functions = $db->GetArray("SELECT DISTINCT IFNULL(function, '-1') AS functionid, function FROM log ORDER BY 2");
        array_unshift($functions, array('functionid' => '0', 'function' => 'All'));
        Theme::Set('function_field_list', $functions);

        $displays = $db->GetArray('SELECT displayid, display FROM display WHERE licensed = 1 ORDER BY 2');
        array_unshift($displays, array('displayid' => 0, 'display' => 'All'));
        Theme::Set('display_field_list', $displays);

        // Render the Theme and output
        Theme::Render('log_page');
	}
	
	function Grid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$type 		= Kit::GetParam('filter_type', _REQUEST, _STRING, '0');
		$function 	= Kit::GetParam('filter_function', _REQUEST, _STRING, '0');
		$page 		= Kit::GetParam('filter_page', _REQUEST, _STRING, '0');
		$fromdt 	= Kit::GetParam('filter_fromdt', _REQUEST, _STRING);
		$displayid	= Kit::GetParam('filter_display', _REQUEST, _INT);
		$seconds 	= Kit::GetParam('filter_seconds', _POST, _INT, 120);
                
        setSession('log', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('log', 'filter_type', $type);
        setSession('log', 'filter_function', $function);
        setSession('log', 'filter_page', $page);
        setSession('log', 'filter_fromdt', $fromdt);
        setSession('log', 'filter_display', $displayid);
        setSession('log', 'filter_seconds', $seconds);
		
		//get the dates and times
		if ($fromdt == '') {
			$starttime_timestamp = time();
		}
		else {
			$start_date = explode("/",$fromdt); //		dd/mm/yyyy
			$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . ' ' . date("H", time()) . ":" . date("i", time()) . ':59');
		}

		$todt = date("Y-m-d H:i:s", $starttime_timestamp);
		$fromdt = date("Y-m-d H:i:s", $starttime_timestamp - $seconds);
		
		$SQL  = "";
		$SQL .= "SELECT logid, logdate, page, function, message FROM log ";
		$SQL .= sprintf(" WHERE  logdate > '%s' AND logdate <= '%s' ", $fromdt, $todt);

		if ($type != "0") 
			$SQL .= sprintf("AND type = '%s' ", $db->escape_string($type));
		
		if($page != "0") 
			$SQL .= sprintf("AND page = '%s' ", $db->escape_string($page));
		
		if($function != "0") 
			$SQL .= sprintf("AND function = '%s' ", $db->escape_string($function));
		
		if($displayid != 0) 
			$SQL .= sprintf("AND displayID = %d ", $displayid);

		$SQL .= " ORDER BY logid ";

		// Load results into an array
        $log = $db->GetArray($SQL);

        if (!is_array($log)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting the log'), E_USER_ERROR);
        }

        $rows = array();
		
		foreach ($log as $row) { 

            $row['logid'] = Kit::ValidateParam($row['logid'], _INT);
			$row['logdate'] = Kit::ValidateParam($row['logdate'], _STRING);
			$row['page'] = Kit::ValidateParam($row['page'], _STRING);
			$row['function'] = Kit::ValidateParam($row['function'], _STRING);
			$row['message'] = nl2br(htmlspecialchars($row['message']));
			
			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('log_page_grid');
		
		$response->initialSortOrder = 2;
		$response->pageSize = 20;
		$response->SetGridResponse($output);
		$response->Respond();
	}

	function LastHundredForDisplay() {
        $response = new ResponseManager();
        $displayId = Kit::GetParam('displayid', _GET, _INT);

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT logid, logdate, page, function, message FROM log WHERE displayid = :displayid ORDER BY logid DESC LIMIT 100');
            $sth->execute(array(
                    'displayid' => $displayId
                ));
                
            $log = $sth->fetchAll();

            if (count($log) <= 0)
                throw new Exception(__('No log messages for this display'));

            $rows = array();
   
            foreach ($log as $row) { 
    
                $row['logid'] = Kit::ValidateParam($row['logid'], _INT);
                $row['logdate'] = Kit::ValidateParam($row['logdate'], _STRING);
                $row['page'] = Kit::ValidateParam($row['page'], _STRING);
                $row['function'] = Kit::ValidateParam($row['function'], _STRING);
                $row['message'] = nl2br(htmlspecialchars($row['message']));
                
                $rows[] = $row;
            }
        
            Theme::Set('table_rows', $rows);
                
            $output = Theme::RenderReturn('log_form_display_last100');
                
            $response->initialSortOrder = 2;
            $response->pageSize = 10;
            $response->SetGridResponse($output);
            $response->Respond();  
        }
        catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

	public function TruncateForm() {
		$db =& $this->db;
        $user =& $this->user;
		$response = new ResponseManager();

		if ($this->user->usertypeid != 1)
			trigger_error(__('Only Administrator Users can truncate the log'), E_USER_ERROR);
		
        // Set some information about the form
        Theme::Set('form_id', 'TruncateForm');
        Theme::Set('form_action', 'index.php?p=log&q=Truncate');

        $form = Theme::RenderReturn('log_form_truncate');

		$response->SetFormRequestResponse($form, __('Truncate Log'), '430px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Log', 'Truncate') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#TruncateForm").submit()');
		$response->Respond();
	}

	/**
	 * Truncate the Log
	 */
	public function Truncate() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;

		if ($this->user->usertypeid != 1)
			trigger_error(__('Only Administrator Users can truncate the log'), E_USER_ERROR);
		
		$db->query("TRUNCATE TABLE log");
		
		$response = new ResponseManager();
		$response->SetFormSubmitResponse('Log Truncated');
        $response->Respond();
	}
}
?>
