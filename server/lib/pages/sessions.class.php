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
 
class sessionsDAO 
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
		$db =& $this->db;

		// Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="sessions"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));
		
		// Construct Filter Form
        if (Kit::IsFilterPinned('sessions', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_type', Session::Get('sessions', 'filter_type'));
            Theme::Set('filter_fromdt', Session::Get('sessions', 'filter_fromdt'));
        }
        else {
            Theme::Set('filter_type', 0);
        }

        // Lists
        $types = array(array('typeid' => 0, 'type' => 'All'), array('typeid' => 'active', 'type' => 'Active'), array('typeid' => 'guest', 'type' => 'Guest'), array('typeid' => 'expired', 'type' => 'Expired'));
        Theme::Set('type_field_list', $types);

        // Render the Theme and output
        Theme::Render('sessions_page');
	}
	
	function Grid() 
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();
		
		$type = Kit::GetParam('filter_type', _POST, _WORD);
		$fromdt = Kit::GetParam('filter_fromdt', _POST, _STRING);
		
		///get the dates and times
		if ($fromdt != '') {
			$start_date = explode("/",$fromdt); //		dd/mm/yyyy
			$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . ' ' . date("H", time()) . ":" . date("i", time()) . ':59');
		}

		setSession('sessions', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		setSession('sessions', 'filter_type', $type);
		setSession('sessions', 'filter_fromdt', $fromdt);
		
		$SQL  = "SELECT session.userID, user.UserName,  IsExpired, LastPage,  session.LastAccessed,  RemoteAddr,  UserAgent ";
		$SQL .= "FROM `session` LEFT OUTER JOIN user ON user.userID = session.userID ";
		$SQL .= "WHERE 1 = 1 ";

		if ($fromdt != '')
			$SQL .= sprintf(" AND session.LastAccessed < '%s' ", date("Y-m-d h:i:s", $starttime_timestamp));
		
		if ($type == "active")
			$SQL .= " AND IsExpired = 0 ";

		if ($type == "expired")
			$SQL .= " AND IsExpired = 1 ";
		
		if ($type == "guest")
			$SQL .= " AND session.userID IS NULL ";
		
		// Load results into an array
        $log = $db->GetArray($SQL);

        Debug::LogEntry('audit', $SQL);

        if (!is_array($log)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting the log'), E_USER_ERROR);
        }

        $rows = array();
		
		foreach ($log as $row) { 

            $row['userid'] = Kit::ValidateParam($row['userID'], _INT);
			$row['username'] = Kit::ValidateParam($row['UserName'], _STRING);
			$row['isexpired'] = (Kit::ValidateParam($row['IsExpired'], _INT) == 0) ? 'icon-ok' : 'icon-remove';
			$row['lastpage'] = Kit::ValidateParam($row['LastPage'], _STRING);
			$row['lastaccessed'] = Kit::ValidateParam($row['LastAccessed'], _STRING);
			$row['ip'] = Kit::ValidateParam($row['RemoteAddr'], _STRING);
			$row['browser'] = Kit::ValidateParam($row['UserAgent'], _STRING);

			// Edit        
            $row['buttons'][] = array(
                    'id' => 'sessions_button_logout',
                    'url' => 'index.php?p=sessions&q=ConfirmLogout&userid=' . $row['userid'],
                    'text' => __('Logout')
                );
			
			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('sessions_page_grid');
		
		$response->SetGridResponse($output);
		$response->Respond();
	}
	
	function ConfirmLogout()
	{
		$db =& $this->db;
		$response = new ResponseManager();
		
		$userid = Kit::GetParam('userid', _GET, _INT);
		
		// Set some information about the form
        Theme::Set('form_id', 'SessionsLogoutForm');
        Theme::Set('form_action', 'index.php?p=sessions&q=LogoutUser');
        Theme::Set('form_meta', '<input type="hidden" name="userid" value="' . $userid . '" />');

        $form = Theme::RenderReturn('sessions_form_logout');

		$response->SetFormRequestResponse($form, __('Logout User'), '430px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Sessions', 'Logout') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#SessionsLogoutForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Logs out a user
	 * @return 
	 */
	function LogoutUser()
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;
		
		//ajax request handler
		$response = new ResponseManager();
		$userID = Kit::GetParam('userid', _POST, _INT);
		
		$SQL = sprintf("UPDATE session SET IsExpired = 1 WHERE userID = %d", $userID);
		
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__("Unable to log out this user"), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__('User Logged Out.'));
		$response->Respond();
	}
}
?>