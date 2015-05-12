<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008,2009 Daniel Garner and James Packer
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
 
class groupDAO extends baseDAO {	
	//general fields
	private $groupid;
	private $group = "";
	
	
	//init
	function __construct(database $db, user $user) 
	{
		$this->db 		=& $db;
		$this->user 	=& $user;
		
		$usertype 		= Kit::GetParam('usertype', _SESSION, _INT, 0);
		$this->groupid	= Kit::GetParam('groupid', _REQUEST, _INT, 0);
		
		// Do we have a user group selected?
		if ($this->groupid != 0) 
		{						
			// If so then we will need to get some information about it
			$SQL = <<<END
			SELECT 	group.GroupID,
					group.Group
			FROM `group`
			WHERE groupID = %d
END;
			
			$SQL = sprintf($SQL, $this->groupid);
			
			if (!$results = $db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error(__("Can not get Group information."), E_USER_ERROR);
			}
			
			$aRow = $db->get_assoc_row($results);
			
			$this->group = $aRow['Group'];
		}
                
            // Include the group data classes
            include_once('lib/data/usergroup.data.class.php');
	}

	/**
	 * Display page logic
	 */
	function displayPage() 
	{
		// Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Default options
        if (Kit::IsFilterPinned('usergroup', 'Filter')) {
            $filter_pinned = 1;
            $filter_name = Session::Get('usergroup', 'filter_name');
        }
        else {
            $filter_pinned = 0;
            $filter_name = NULL;
        }

        $formFields = array();
        $formFields[] = FormManager::AddText('filter_name', __('Name'), $filter_name, NULL, 'n');

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $filter_pinned, NULL, 
            'k');

        // Call to render the template
        Theme::Set('header_text', __('User Groups'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
	}

    function actionMenu() {

        return array(
                array('title' => __('Add User Group'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=group&q=GroupForm',
                    'help' => __('Add a new User Group'),
                    'onclick' => ''
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

	/**
	 * Group Grid
	 * Called by AJAX
	 * @return 
	 */
	function Grid() 
	{
		$db =& $this->db;
		$user =& $this->user;
		
		$filter_name = Kit::GetParam('filter_name', _POST, _STRING);
                
		setSession('usergroup', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
		setSession('usergroup', 'filter_name', $filter_name);
	
		$SQL = <<<END
		SELECT 	group.group,
				group.groupID,
				group.libraryQuota
		FROM `group`
		WHERE IsUserSpecific = 0 AND IsEveryone = 0
END;

		if ($filter_name != '') 
			$SQL .= sprintf(" AND group.group LIKE '%%%s%%' ", $db->escape_string($filter_name));
		
		$SQL .= " ORDER BY group.group ";
		
		//Debug::LogEntry('audit', $SQL);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not get group information."), E_USER_ERROR);
		}

		$cols = array(
                array('name' => 'usergroup', 'title' => __('User Group')),
                array('name' => 'libraryQuotaText', 'title' => __('Library Quota'))
            );
        Theme::Set('table_cols', $cols);

		$rows = array();

		while ($row = $db->get_assoc_row($results)) 
		{
			$groupid = Kit::ValidateParam($row['groupID'], _INT);
			$group = Kit::ValidateParam($row['group'], _STRING);

            $row['usergroup'] = $group;
            $row['libraryQuota'] = Kit::ValidateParam($row['libraryQuota'], _INT);
            $row['libraryQuotaText'] = ($row['libraryQuota'] == 0) ? '' : Kit::formatBytes($row['libraryQuota'] * 1024);

            // we only want to show certain buttons, depending on the user logged in
			if ($user->GetUserTypeID() == 1) 
			{
				// Edit
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_edit',
	                    'url' => 'index.php?p=group&q=GroupForm&groupid=' . $groupid,
	                    'text' => __('Edit')
	                );

				// Delete
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_delete',
	                    'url' => 'index.php?p=group&q=DeleteForm&groupid=' . $groupid,
	                    'text' => __('Delete')
	                );

				// Members
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_members',
	                    'url' => 'index.php?p=group&q=MembersForm&groupid=' . $groupid,
	                    'text' => __('Members')
	                );

				// Page Security
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_page_security',
	                    'url' => 'index.php?p=group&q=PageSecurityForm&groupid=' . $groupid,
	                    'text' => __('Page Security')
	                );

				// Menu Security
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_menu_security',
	                    'url' => 'index.php?p=group&q=MenuItemSecurityForm&groupid=' . $groupid,
	                    'text' => __('Menu Security')
	                );

				// User Quota
	            $row['buttons'][] = array(
	                    'id' => 'usergroup_button_quota',
	                    'url' => 'index.php?p=group&q=quotaForm&groupid=' . $groupid,
	                    'text' => __('Set User Quota')
	                );
			}

			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

		$response = new ResponseManager();
        $response->SetGridResponse($output);
        $response->Respond();
	}
	
	/**
	 * Add / Edit Group Form
	 * @return 
	 */
	function GroupForm() 
	{
		$db =& $this->db;
		$user =& $this->user;
		$response = new ResponseManager();
				
		Theme::Set('form_id', 'UserGroupForm');

		// alter the action variable depending on which form we are after
		if ($this->groupid == "") 
		{
        	Theme::Set('form_action', 'index.php?p=group&q=Add');

        	$form_name = 'Add User Group';
        	$form_help_link = HelpManager::Link('UserGroup', 'Add');
		}
		else 
		{
        	Theme::Set('form_action', 'index.php?p=group&q=Edit');
        	Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $this->groupid . '">');
        	Theme::Set('group', $this->group);

        	$form_name = 'Edit User Group';
        	$form_help_link = HelpManager::Link('UserGroup', 'Edit');
		}

		$formFields = array();
        $formFields[] = FormManager::AddText('group', __('Name'), $this->group, 
            __('The Name for this User Group'), 'n', 'maxlength="50" required');

        Theme::Set('form_fields', $formFields);

		// Construct the Response		
		$response->SetFormRequestResponse(NULL, $form_name, '400', '180');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . $form_help_link . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#UserGroupForm").submit()');
		$response->Respond();

		return true;
	}
	
	/**
	 * Assign Page Security Filter form (will trigger grid)
	 * @return boolean
	 */
	function PageSecurityForm()
	{
		$response	= new ResponseManager();
		
		$id = uniqid();
        Theme::Set('id', $id);
		Theme::Set('header_text', __('Please select your Page Security Assignments'));
		Theme::Set('pager', ResponseManager::Pager($id));
		Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="PageSecurityFormGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');

		$formFields = array();
		$formFields[] = FormManager::AddText('filter_name', __('Name'), NULL, NULL, 'n');
		Theme::Set('form_fields', $formFields);

		// Call to render the template
		$xiboGrid = Theme::RenderReturn('grid_render');
			
		// Construct the Response		
		$response->SetFormRequestResponse($xiboGrid, __('Page Security'), '500', '380');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'PageSecurity') . '")');
		$response->AddButton(__('Close'), 'XiboDialogClose()');
		$response->AddButton(__('Assign / Unassign'), '$("#UserGroupForm").submit()');
		$response->Respond();

		return true;
	}
	
	/**
	 * Assign Page Security Grid
	 */
	function PageSecurityFormGrid() 
	{
		$db	=& $this->db;
		$groupId = Kit::GetParam('groupid', _POST, _INT);

		Theme::Set('form_id', 'UserGroupForm');
		Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupId . '">');
		Theme::Set('form_action', 'index.php?p=group&q=assign');

		$params = array();

		$SQL = <<<END
		SELECT 	pagegroup.pagegroup,
				pagegroup.pagegroupID,
				CASE WHEN pages_assigned.pagegroupID IS NULL 
					THEN 0
		        	ELSE 1
		        END AS AssignedID
		FROM	pagegroup
		LEFT OUTER JOIN 
				(SELECT DISTINCT pages.pagegroupID
				 FROM	lkpagegroup
				 INNER JOIN pages ON lkpagegroup.pageID = pages.pageID
				 WHERE  groupID = :groupId
				) pages_assigned
		ON pagegroup.pagegroupID = pages_assigned.pagegroupID
END;
		$params['groupId'] = $groupId;

		// Filter by Name?
		if (Kit::GetParam('filter_name', _POST, _STRING) != '') {
			$SQL .= ' WHERE pagegroup.pagegroup LIKE :name ';
			$params['name'] = '%' . Kit::GetParam('filter_name', _POST, _STRING) . '%';
		}

		try {
			$dbh = PDOConnect::init();

			$sth = $dbh->prepare($SQL);
			$sth->execute($params);

			$results = $sth->fetchAll();

			// while loop
			$rows = array();

			foreach ($results as $row) {
				$row['name'] = $row['pagegroup'];
				$row['pageid'] = $row['pagegroupID'];
				$row['assigned'] = (($row['AssignedID'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove');
				$row['assignedid'] = $row['AssignedID'];
				$row['checkbox_value'] = $row['AssignedID'] . ',' . $row['pagegroupID'];
				$row['checkbox_ticked'] = '';

				$rows[] = $row;
			}

			Theme::Set('table_rows', $rows);

			$output = Theme::RenderReturn('usergroup_form_pagesecurity_grid');

			$response = new ResponseManager();
			$response->SetGridResponse($output);
			$response->initialSortColumn = 2;
			$response->Respond();
		}
		catch (Exception $e) {
			Debug::Error($e);
			trigger_error(__('Unable to process request'), E_USER_ERROR);
		}
	}
	
	/**
	 * Shows the Delete Group Form
	 */
	function DeleteForm() 
	{
		$groupId = $this->groupid;
		$response = new ResponseManager();

        // Get the group name
        $group = __('Unknown');
        try {
            $dbh = PDOConnect::init();
            $sth = $dbh->prepare('SELECT `group` FROM `group` WHERE groupId = :groupId');
            $sth->execute(array('groupId' => $groupId));

            if ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $group = Kit::ValidateParam($row['group'], _STRING);
            }
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
        }
		
		// Set some information about the form
        Theme::Set('form_id', 'UserGroupDeleteForm');
        Theme::Set('form_action', 'index.php?p=group&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupId . '">');

        Theme::Set('form_fields', array(FormManager::AddMessage(sprintf(__('Are you sure you want to delete %s?'), $group))));

		// Construct the Response		
		$response->SetFormRequestResponse(NULL, sprintf(__('Delete %s'), $group), '400', '180');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('UserGroup', 'Delete') . '")');
		$response->AddButton(__('No'), 'XiboDialogClose()');
		$response->AddButton(__('Yes'), '$("#UserGroupDeleteForm").submit()');
		$response->Respond();

		return true;
	}
	
	/**
	 * Adds a group
	 * @return 
	 */
	function Add() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $group 	= Kit::GetParam('group', _POST, _STRING);

        $userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Add($group, 0))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('User Group Added'), false);
        $response->Respond();
	}
	
	/**
	 * Edits the Group Information
	 * @return 
	 */
	function Edit() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;
		
		$groupid = Kit::GetParam('groupid', _POST, _INT);
		$group = Kit::GetParam('group', _POST, _STRING);

		$userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Edit($groupid, $group))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);
		
		$response = new ResponseManager();
		$response->SetFormSubmitResponse(__('User Group Edited'), false);
        $response->Respond();
	}
	
	/**
	 * Deletes a Group
	 * @return 
	 */
	function Delete() 
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;		
		$groupid = Kit::GetParam('groupid', _POST, _INT);
		
		$userGroupObject = new UserGroup($db);

        if (!$userGroupObject->Delete($groupid))
            trigger_error($userGroupObject->GetErrorMessage(), E_USER_ERROR);
		
		$response = new ResponseManager();
		$response->SetFormSubmitResponse(__('User Group Deleted'), false);
        $response->Respond();
	}
	
	/**
	 * Assigns and unassigns pages from groups
	 * @return JSON object
	 */
	function assign() 
	{
		$db 		=& $this->db;
		$groupid 	= Kit::GetParam('groupid', _POST, _INT);

		$pageids 	= $_POST['pageids'];
		
		foreach ($pageids as $pagegroupid) 
		{
			$row = explode(",",$pagegroupid);
			
			// The page ID actually refers to the pagegroup ID - we have to look up all the page ID's for this
			// PageGroupID
			$SQL = "SELECT pageID FROM pages WHERE pagegroupID = " . Kit::ValidateParam($row[1], _INT);
			
			if (!$results = $db->query($SQL))
			{
				trigger_error($db->error());
				Kit::Redirect(array('success'=>false, 'message' => __('Can\'t assign this page to this group') . ' [error getting pages]'));
			}
			
			while ($page_row = $db->get_row($results)) 
			{
				$pageid = $page_row[0];
			
				if ($row[0]=="0") 
				{
					//it isnt assigned and we should assign it
					$SQL = "INSERT INTO lkpagegroup (groupID, pageID) VALUES ($groupid, $pageid)";
					
					if(!$db->query($SQL)) 
					{
						trigger_error($db->error());
						Kit::Redirect(array('success'=>false, 'message' => __('Can\'t assign this page to this group')));
					}
				}
				else 
				{ 
					//it is already assigned and we should remove it
					$SQL = "DELETE FROM lkpagegroup WHERE groupid = $groupid AND pageID = $pageid";
					
					if(!$db->query($SQL)) 
					{
						trigger_error($db->error());
						Kit::Redirect(array('success'=>false, 'message' => __('Can\'t remove this page from this group')));
					}
				}	
			}
		}

		$response = new ResponseManager();
		$response->SetFormSubmitResponse(__('User Group Page Security Edited'));
		$response->keepOpen = true;
        $response->Respond();
	}
	
	/**
	 * Security for Menu Items
	 * @return 
	 */
	function MenuItemSecurityForm()
	{
		$db =& $this->db;
		$user =& $this->user;
		
		$id = uniqid();
        Theme::Set('id', $id);
		Theme::Set('header_text', __('Select your Menu Assignments'));
		Theme::Set('pager', ResponseManager::Pager($id));
		Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="MenuItemSecurityGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');

		$formFields = array();
		$formFields[] = FormManager::AddCombo(
			'filter_menu',
			__('Menu'),
			null,
			$db->GetArray("SELECT MenuID, Menu FROM menu"),
			'MenuID',
			'Menu',
			NULL,
			'r');

		Theme::Set('form_fields', $formFields);

		// Call to render the template
		$xiboGrid = Theme::RenderReturn('grid_render');

		// Construct the Response
		$response = new ResponseManager();		
		$response->SetFormRequestResponse($xiboGrid, __('Menu Item Security'), '500', '380');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('User', 'MenuSecurity') . '")');
		$response->AddButton(__('Close'), 'XiboDialogClose()');
		$response->AddButton(__('Assign / Unassign'), '$("#UserGroupMenuForm").submit()');
		$response->Respond();

		return true;
	}
	
	/**
	 * Assign Menu Item Security Grid
	 * @return 
	 */
	function MenuItemSecurityGrid() 
	{
		$db =& $this->db;
		$groupid = Kit::GetParam('groupid', _POST, _INT);
		
		$filter_menu = Kit::GetParam('filter_menu', _POST, _STRING);
		
		Theme::Set('form_id', 'UserGroupMenuForm');
		Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupid . '">');
		Theme::Set('form_action', 'index.php?p=group&q=MenuItemSecurityAssign');
		
		$SQL = <<<END
		SELECT 	menu.Menu,
				menuitem.Text,
				menuitem.MenuItemID,
				CASE WHEN menuitems_assigned.MenuItemID IS NULL 
					THEN 0
		        	ELSE 1
		        END AS AssignedID
		FROM	menuitem
		INNER JOIN menu
		ON		menu.MenuID = menuitem.MenuID
		LEFT OUTER JOIN 
				(SELECT DISTINCT lkmenuitemgroup.MenuItemID
				 FROM	lkmenuitemgroup
				 WHERE  GroupID = $groupid
				) menuitems_assigned
		ON menuitem.MenuItemID = menuitems_assigned.MenuItemID
		WHERE menuitem.MenuID = %d
END;
		
		$SQL = sprintf($SQL, $filter_menu);

		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__('Cannot get the menu items for this Group.'), E_USER_ERROR);
		}
		
		if ($db->num_rows($results) == 0) 
		{
			trigger_error(__('Cannot get the menu items for this Group.'), E_USER_ERROR);
		}
		
		// while loop
		$rows = array(); 
		
		while ($row = $db->get_assoc_row($results)) 
		{
			$row['name'] = $row['Text'];
			$row['pageid'] = $row['MenuItemID'];
			$row['assigned'] = (($row['AssignedID'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove');
			$row['assignedid'] = $row['AssignedID'];
			$row['checkbox_value'] = $row['AssignedID'] . ',' . $row['MenuItemID'];
			$row['checkbox_ticked'] = '';
			
			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);

		$output = Theme::RenderReturn('usergroup_form_menusecurity_grid');
		
		$response = new ResponseManager();
        $response->SetGridResponse($output);
        $response->initialSortColumn = 2;
        $response->Respond();
	}
	
	/**
	 * Menu Item Security Assignment to Groups
	 * @return 
	 */
	function MenuItemSecurityAssign()
	{
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
		$db =& $this->db;
		$groupid = Kit::GetParam('groupid', _POST, _INT);

		$pageids = $_POST['pageids'];
		
		foreach ($pageids as $menuItemId) 
		{
			$row = explode(",", $menuItemId);
			
			$menuItemId = $row[1];
			
			// If the ID is 0 then this menu item is not currently assigned
			if ($row[0]=="0") 
			{
				//it isnt assigned and we should assign it
				$SQL = sprintf("INSERT INTO lkmenuitemgroup (GroupID, MenuItemID) VALUES (%d, %d)", $groupid, $menuItemId);
				
				if(!$db->query($SQL)) 
				{
					trigger_error($db->error());
					Kit::Redirect(array('success'=>false, 'message' => __('Can\'t assign this menu item to this group')));
				}
			}
			else 
			{ 
				//it is already assigned and we should remove it
				$SQL = sprintf("DELETE FROM lkmenuitemgroup WHERE groupid = %d AND MenuItemID = %d", $groupid, $menuItemId);
				
				if(!$db->query($SQL)) 
				{
					trigger_error($db->error());
					Kit::Redirect(array('success'=>false, 'message' => __('Can\'t remove this menu item from this group')));
				}
			}
		}
		
		// Response
		$response = new ResponseManager();
		$response->SetFormSubmitResponse(__('User Group Menu Security Edited'));
		$response->keepOpen = true;
        $response->Respond();
	}

    /**
     * Shows the Members of a Group
     */
    public function MembersForm()
	{
        $db =& $this->db;
        $response = new ResponseManager();
        $groupID = Kit::GetParam('groupid', _REQUEST, _INT);

        // There needs to be two lists here.
        
        // Set some information about the form
        Theme::Set('users_assigned_id', 'usersIn');
        Theme::Set('users_available_id', 'usersOut');
        Theme::Set('users_assigned_url', 'index.php?p=group&q=SetMembers&GroupID=' . $groupID);

        // Users in group
        $usersAssigned = $this->user->userList(null, array('groupIds' => array($groupID)));

		Theme::Set('users_assigned', $usersAssigned);

        // Users not in group
        if (!$allUsers = $this->user->userList())
			trigger_error(__('Error getting all users'), E_USER_ERROR);

        // The available users are all users except users already in assigned users
		$usersAvailable = array();

		foreach ($allUsers as $user) {
			// Check to see if it exists in $usersAssigned
			$exists = false;
			foreach ($usersAssigned as $userAssigned) {
				if ($userAssigned['userid'] == $user['userid']) {
					$exists = true;
					break;
				}
			}

			if (!$exists)
				$usersAvailable[] = $user;
		}

        Theme::Set('users_available', $usersAvailable);
		
        $form = Theme::RenderReturn('usergroup_form_user_assign');

        $response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'ManageMembersCallBack');
        $response->AddButton(__('Help'), "XiboHelpRender('" . HelpManager::Link('UserGroup', 'Members') . "')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'MembersSubmit()');
        $response->Respond();
	}

	/**
	 * Sets the Members of a group
	 */
	public function SetMembers()
	{
        $db             =& $this->db;
        $response       = new ResponseManager();
        $groupObject    = new UserGroup($db);

        $groupId = Kit::GetParam('GroupID', _REQUEST, _INT);
        $users = Kit::GetParam('UserID', _POST, _ARRAY, array());

		// We will receive a list of users from the UI which are in the "assign column" at the time the form is
		// submitted.
		// We want to go through and unlink any users that are NOT in that list, but that the current user has access
		// to edit.
		// We want to add any users that are in that list (but aren't already assigned)

		// All users that this session has access to
		if (!$allUsers = $this->user->userList())
			trigger_error(__('Error getting all users'), E_USER_ERROR);

		// Convert to an array of ID's for convenience
		$allUserIds = array_map(function ($array) { return $array['userid']; }, $allUsers);

        // Users in group
		$usersAssigned = UserData::entries(null, array('groupIds' => array($groupId)));

        Debug::Audit('All userIds we want to assign: ' . var_export($users, true));
        Debug::Audit('All userIds we have access to: ' . var_export($allUserIds, true));

        foreach ($usersAssigned as $user) {
            /* @var Userdata $user */
			// Did this session have permission to do anything to this user?
			// If not, move on
			if (!in_array($user->userId, $allUserIds))
				continue;

            Debug::Audit('Logged in user has permission to make changes to this assigned user ' . $user->userId);

            // Is this user in the provided list of users?
			if (in_array($user->userId, $users)) {
                // This user is already assigned, so we remove it from the $users array
                Debug::Audit('This user is already assigned ' . $user->userId);

                if (($key = array_search($user->userId, $users)) !== false) {
                    unset($users[$key]);
                }
            }
            else
            {
                Debug::Audit('This user is assigned, but not in the list of assignments ' . $user->userId);

				// It isn't therefore needs to be removed
				if (!$groupObject->Unlink($groupId, $user->userId))
					trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
            }
        }

        Debug::Audit('All userIds we want to assign after sorting: ' . var_export($users, true));

		// Add any users that are still missing after tha assignment process
        foreach ($users as $userId) {
            Debug::Audit('User was missing, linking them: ' . $userId);
            // Add any that are missing
			if (!$groupObject->Link($groupId, $userId))
			{
				trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
			}
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);
        $response->Respond();
	}

    public function quotaForm()
    {
        $response = new ResponseManager();
        $groupId = Kit::GetParam('groupId', _GET, _INT);

        // Look up the existing quota
        $libraryQuota = UserGroup::getLibraryQuota($groupId);

        $formFields = array();
        $formFields[] = FormManager::AddNumber('libraryQuota', __('Library Quota'), $libraryQuota, __('The quota in Kb that should be applied. Enter 0 for no quota.'), 'q', 'required');
        Theme::Set('form_fields', $formFields);

        // Set some information about the form
        Theme::Set('form_id', 'GroupQuotaForm');
        Theme::Set('form_action', 'index.php?p=group&q=quota');
        Theme::Set('form_meta', '<input type="hidden" name="groupId" value="' . $groupId . '" />');

        $response->SetFormRequestResponse(Theme::RenderReturn('form_render'), __('Edit Library Quota'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Group', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#GroupQuotaForm").submit()');
        $response->Respond();
    }

    public function quota()
    {
        $response = new ResponseManager();

        $groupId = Kit::GetParam('groupId', _POST, _INT);
        $libraryQuota = Kit::GetParam('libraryQuota', _POST, _INT);

        try {
            UserGroup::updateLibraryQuota($groupId, $libraryQuota);
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
            trigger_error(__('Problem setting quota'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Quota has been updated'), false);
        $response->Respond();
    }
}
