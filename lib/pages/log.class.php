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
 
class logDAO extends baseDAO {

	public function displayPage() 
	{
		$db =& $this->db;

		// Configure the theme
        $id = uniqid();
        Theme::Set('id', 'LogGridForRefresh');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="log"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager('LogGridForRefresh'));
        
        // Construct Filter Form
        if (Kit::IsFilterPinned('log', 'Filter')) {
            $filter_pinned = 1;
            $filter_type = Session::Get('log', 'filter_type');
            $filter_page = Session::Get('log', 'filter_page');
            $filter_function = Session::Get('log', 'filter_function');
            $filter_display = Session::Get('log', 'filter_display');
            $filter_fromdt = Session::Get('log', 'filter_fromdt');
            $filter_seconds = Session::Get('log', 'filter_seconds');
            $filter_intervalTypeId = Session::Get('log', 'filter_intervalTypeId');
        }
        else {
            $filter_pinned = 0;
            $filter_type = 0;
            $filter_page = NULL;
            $filter_function = NULL;
            $filter_display = 0;
            $filter_fromdt = NULL;
            $filter_seconds = 120;
            $filter_intervalTypeId = 1;
        }

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));

        $formFields = array();
        $formFields['general'][] = FormManager::AddCombo(
            'filter_type', 
            __('Type'), 
            $filter_type,
            array(array('typeid' => 0, 'type' => 'All'), array('typeid' => 2, 'type' => 'Audit'), array('typeid' => 1, 'type' => 'Error')),
            'typeid',
            'type',
            NULL, 
            't');

        $formFields['general'][] = FormManager::AddCombo(
            'filter_intervalTypeId', 
            __('Interval'), 
            $filter_intervalTypeId,
            array(array('intervalTypeid' => 1, 'intervalType' => __('Seconds')), 
                array('intervalTypeid' => 60, 'intervalType' => __('Minutes')), 
                array('intervalTypeid' => 3600, 'intervalType' => __('Hours'))),
            'intervalTypeid',
            'intervalType',
            NULL, 
            'i');

        $formFields['general'][] = FormManager::AddText('filter_seconds', __('Duration back'), $filter_seconds, NULL, 's');

        $formFields['general'][] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $filter_pinned, NULL, 
            'k');

        // Advanced Tab
        $formFields['advanced'][] = FormManager::AddDatePicker('filter_fromdt', __('From Date'), $filter_fromdt, NULL, 't');
        $formFields['advanced'][] = FormManager::AddText('filter_page', __('Page'), $filter_page, NULL, 'p');
        $formFields['advanced'][] = FormManager::AddText('filter_function', __('Function'), $filter_function, NULL, 'f');
        
        // Display
        $displays = $this->user->DisplayList();
        array_unshift($displays, array('displayid' => 0, 'display' => 'All'));

        $formFields['advanced'][] = FormManager::AddCombo(
            'filter_display', 
            __('Display'), 
            $filter_display,
            $displays,
            'displayid',
            'display',
            NULL, 
            't');

        // Call to render the template
        Theme::Set('header_text', __('Logs'));
        Theme::Set('form_tabs', $tabs);
        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_advanced', $formFields['advanced']);
        Theme::Render('grid_render');
	}

    function actionMenu() {

        return array(
                array('title' => __('Truncate'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=log&q=TruncateForm',
                    'help' => __('Truncate the Log'),
                    'onclick' => ''
                    ),
                array('title' => __('Refresh'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Truncate the Log'),
                    'onclick' => 'XiboGridRender(\'LogGridForRefresh\')'
                    ),
                array('title' => __('Filter'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Open the filter form'),
                    'onclick' => 'ToggleFilterView(\'Filter\')'
                    )
            );
    }
	
	function Grid() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$type 		= Kit::GetParam('filter_type', _REQUEST, _INT, 0);
		$function 	= Kit::GetParam('filter_function', _REQUEST, _STRING);
		$page 		= Kit::GetParam('filter_page', _REQUEST, _STRING);
		$fromdt 	= Kit::GetParam('filter_fromdt', _REQUEST, _STRING);
		$displayid	= Kit::GetParam('filter_display', _REQUEST, _INT);
        $seconds    = Kit::GetParam('filter_seconds', _POST, _INT, 120);
		$filter_intervalTypeId = Kit::GetParam('filter_intervalTypeId', _POST, _INT, 1);
                
        setSession('log', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('log', 'filter_type', $type);
        setSession('log', 'filter_function', $function);
        setSession('log', 'filter_page', $page);
        setSession('log', 'filter_fromdt', $fromdt);
        setSession('log', 'filter_display', $displayid);
        setSession('log', 'filter_seconds', $seconds);
        setSession('log', 'filter_intervalTypeId', $filter_intervalTypeId);
		
		//get the dates and times
		if ($fromdt == '') {
			$starttime_timestamp = time();
		}
		else {
			$start_date = DateManager::getTimestampFromString($fromdt);
			$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . ' ' . date("H", time()) . ":" . date("i", time()) . ':59');
		}

		$todt = date("Y-m-d H:i:s", $starttime_timestamp);
		$fromdt = date("Y-m-d H:i:s", $starttime_timestamp - ($seconds * $filter_intervalTypeId));
		
		$SQL  = "";
		$SQL .= "SELECT logid, logdate, page, function, message, display.display FROM log LEFT OUTER JOIN display ON display.displayid = log.displayid ";
		$SQL .= sprintf(" WHERE  logdate > '%s' AND logdate <= '%s' ", $fromdt, $todt);

		if ($type != 0) 
			$SQL .= sprintf("AND type = '%s' ", ($type == 1) ? 'error' : 'audit');
		
		if($page != "") 
			$SQL .= sprintf("AND page = '%s' ", $db->escape_string($page));
		
		if($function != "") 
			$SQL .= sprintf("AND function = '%s' ", $db->escape_string($function));
		
		if($displayid != 0) 
			$SQL .= sprintf("AND display.displayID = %d ", $displayid);

		$SQL .= " ORDER BY logid ";

		// Load results into an array
        $log = $db->GetArray($SQL);

        if (!is_array($log)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting the log'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'logid', 'title' => __('ID')),
                array('name' => 'logdate', 'title' => __('Date')),
                array('name' => 'display', 'title' => __('Display')),
                array('name' => 'page', 'title' => __('Page')),
                array('name' => 'function', 'title' => __('Function')),
                array('name' => 'message', 'title' => __('Message'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();
		
		foreach ($log as $row) { 

            $row['logid'] = Kit::ValidateParam($row['logid'], _INT);
			$row['logdate'] = DateManager::getLocalDate(strtotime(Kit::ValidateParam($row['logdate'], _STRING)), 'y-m-d h:i:s');
            $row['display'] = (Kit::ValidateParam($row['display'], _STRING) == '') ? __('CMS') : Kit::ValidateParam($row['display'], _STRING);
			$row['page'] = Kit::ValidateParam($row['page'], _STRING);
			$row['function'] = Kit::ValidateParam($row['function'], _STRING);
			$row['message'] = nl2br(htmlspecialchars($row['message']));
			
			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('table_render');
		
        $response->initialSortOrder = 2;
		$response->initialSortColumn = 1;
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

            $cols = array(
                    array('name' => 'logid', 'title' => __('ID')),
                    array('name' => 'logdate', 'title' => __('Date')),
                    array('name' => 'page', 'title' => __('Page')),
                    array('name' => 'function', 'title' => __('Function')),
                    array('name' => 'message', 'title' => __('Message'))
                );
            Theme::Set('table_cols', $cols);

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
                
            $output = Theme::RenderReturn('table_render');

            $response->initialSortOrder = 2;
            $response->dialogClass = 'modal-big';
            $response->dialogTitle = __('Recent Log Messages');
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

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to truncate?'))));

		$response->SetFormRequestResponse(NULL, __('Truncate Log'), '430px', '200px');
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
