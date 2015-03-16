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
 
class sessionsDAO extends baseDAO {
	
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
        	$filter_pinned = 1;
            $filter_type = Session::Get('sessions', 'filter_type');
            $filter_fromdt = Session::Get('sessions', 'filter_fromdt');
        }
        else {
        	$filter_pinned = 0;
            $filter_type = '0';
            $filter_fromdt = NULL;
        }

		$formFields = array();
        $formFields[] = FormManager::AddDatePicker('filter_fromdt', __('From Date'), $filter_fromdt, NULL, 't');

        $formFields[] = FormManager::AddCombo(
            'filter_type', 
            __('Type'), 
            $filter_type,
            array(array('typeid' => '0', 'type' => 'All'), array('typeid' => 'active', 'type' => 'Active'), array('typeid' => 'guest', 'type' => 'Guest'), array('typeid' => 'expired', 'type' => 'Expired')),
            'typeid',
            'type',
            NULL, 
            'd');

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $filter_pinned, NULL, 
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Sessions'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
	}

    function actionMenu() {

        return array(
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
		$db =& $this->db;
		$response = new ResponseManager();
		
		$type = Kit::GetParam('filter_type', _POST, _WORD);
		$fromDt = Kit::GetParam('filter_fromdt', _POST, _STRING);

        setSession('sessions', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('sessions', 'filter_type', $type);
        setSession('sessions', 'filter_fromdt', $fromDt);

        $SQL  = "SELECT session.userID, user.UserName,  IsExpired, LastPage,  session.LastAccessed,  RemoteAddr,  UserAgent ";
        $SQL .= "FROM `session` LEFT OUTER JOIN user ON user.userID = session.userID ";
        $SQL .= "WHERE 1 = 1 ";

        if ($fromDt != '')
            // From Date is the Calendar Formatted DateTime in ISO format
            $SQL .= sprintf(" AND session.LastAccessed < '%s' ", DateManager::getMidnightSystemDate(DateManager::getTimestampFromString($fromDt)));
		
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

        $cols = array(
                array('name' => 'lastaccessed', 'title' => __('Last Accessed')),
                array('name' => 'isexpired', 'title' => __('Active'), 'icons' => true),
                array('name' => 'username', 'title' => __('User Name')),
                array('name' => 'lastpage', 'title' => __('Last Page')),
                array('name' => 'ip', 'title' => __('IP Address')),
                array('name' => 'browser', 'title' => __('Browser'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();
		
		foreach ($log as $row) { 

            $row['userid'] = Kit::ValidateParam($row['userID'], _INT);
			$row['username'] = Kit::ValidateParam($row['UserName'], _STRING);
			$row['isexpired'] = (Kit::ValidateParam($row['IsExpired'], _INT) == 1) ? 0 : 1;
			$row['lastpage'] = Kit::ValidateParam($row['LastPage'], _STRING);
			$row['lastaccessed'] = DateManager::getLocalDate(strtotime(Kit::ValidateParam($row['LastAccessed'], _STRING)));
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

		$response->SetGridResponse(Theme::RenderReturn('table_render'));
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

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to logout this user?'))));

		$response->SetFormRequestResponse(NULL, __('Logout User'), '430px', '200px');
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