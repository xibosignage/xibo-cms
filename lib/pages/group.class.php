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
 
class groupDAO 
{
	private $db;
	private $user;
	
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
        Theme::Set('usergroup_form_add_url', 'index.php?p=group&q=GroupForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Default options
        if (Kit::IsFilterPinned('usergroup', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_name', Session::Get('usergroup', 'filter_name'));
        }

        // Render the Theme and output
        Theme::Render('usergroup_page');
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
				group.groupID
		FROM `group`
		WHERE IsUserSpecific = 0 AND IsEveryone = 0
END;

		if ($filter_name != '') 
			$SQL .= sprintf(" AND group.group LIKE '%%%s%%' ", $db->escape_string($filter_name));
		
		$SQL .= " ORDER BY group.group ";
		
		Debug::LogEntry('audit', $SQL);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not get group information."), E_USER_ERROR);
		}

		$rows = array();

		while ($row = $db->get_assoc_row($results)) 
		{
			$groupid	= Kit::ValidateParam($row['groupID'], _INT);
			$group 		= Kit::ValidateParam($row['group'], _STRING);

			$row['usergroup'] = $group;

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
			}

			$rows[] = $row;
		}

		Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('usergroup_page_grid');

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

        	$theme_file = 'usergroup_form_add';
        	$form_name = 'Add User Group';
        	$form_help_link = HelpManager::Link('UserGroup', 'Add');
		}
		else 
		{
        	Theme::Set('form_action', 'index.php?p=group&q=Edit');
        	Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $this->groupid . '">');
        	Theme::Set('group', $this->group);

        	$theme_file = 'usergroup_form_edit';
        	$form_name = 'Edit User Group';
        	$form_help_link = HelpManager::Link('UserGroup', 'Edit');
		}
		
		$form = Theme::RenderReturn($theme_file);

		// Construct the Response		
		$response->SetFormRequestResponse($form, $form_name, '400', '180');
		$response->AddButton(__('Help'), 'XiboHelpRender("' . $form_help_link . '")');
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#UserGroupForm").submit()');
		$response->Respond();

		return true;
	}
	
	/**
	 * Assign Page Security Filter form (will trigger grid)
	 * @return 
	 */
	function PageSecurityForm()
	{
		$response	= new ResponseManager();
		
		$id = uniqid();
        Theme::Set('id', $id);
		Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="PageSecurityFormGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');

		$xiboGrid = Theme::RenderReturn('usergroup_form_pagesecurity');
			
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
	 * @return 
	 */
	function PageSecurityFormGrid() 
	{
		$db 		=& $this->db;
		$groupid 	= Kit::GetParam('groupid', _POST, _INT);

		Theme::Set('form_id', 'UserGroupForm');
		Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupid . '">');
		Theme::Set('form_action', 'index.php?p=group&q=assign');
		
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
				 WHERE  groupID = $groupid
				) pages_assigned
		ON pagegroup.pagegroupID = pages_assigned.pagegroupID
END;
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error(__("Can't get this groups information"), E_USER_ERROR);
		}
		
		if ($db->num_rows($results) == 0) 
		{
			echo '';
			exit;
		}
		
		// while loop
		$rows = array(); 
		
		while ($row = $db->get_assoc_row($results)) 
		{
			$row['name'] = $row['pagegroup'];
			$row['pageid'] = $row['pagegroupID'];
			$row['assigned'] = (($row['AssignedID'] == 1) ? 'icon-ok' : 'icon-remove');
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
	
	/**
	 * Shows the Delete Group Form
	 * @return 
	 */
	function DeleteForm() 
	{
		$db =& $this->db;
		$groupid = $this->groupid;
		$response = new ResponseManager();
		
		// Set some information about the form
        Theme::Set('form_id', 'UserGroupDeleteForm');
        Theme::Set('form_action', 'index.php?p=group&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="groupid" value="' . $groupid . '">');

        $form = Theme::RenderReturn('usergroup_form_delete');
				
		// Construct the Response		
		$response->SetFormRequestResponse($form, __('Delete Group'), '400', '180');
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
		Theme::Set('form_meta', '<input type="hidden" name="p" value="group"><input type="hidden" name="q" value="MenuItemSecurityGrid"><input type="hidden" name="groupid" value="' . $this->groupid . '">');
		Theme::Set('menu_field_list', $db->GetArray("SELECT MenuID, Menu FROM menu"));

		$xiboGrid = Theme::RenderReturn('usergroup_form_menusecurity');
		
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
			$row['assigned'] = (($row['AssignedID'] == 1) ? 'icon-ok' : 'icon-remove');
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
        $SQL  = "";
        $SQL .= "SELECT user.UserID, ";
        $SQL .= "       user.UserName, ";
        $SQL .= "       CONCAT('UserID_', user.userID) AS list_id ";
        $SQL .= "FROM   `user` ";
        $SQL .= "       INNER JOIN lkusergroup ";
        $SQL .= "       ON     lkusergroup.UserID = user.UserID ";
        $SQL .= sprintf("WHERE  lkusergroup.GroupID   = %d", $groupID);

        $usersAssigned = $db->GetArray($SQL);

        if (!is_array($usersAssigned))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting users'), E_USER_ERROR);
        }

        Theme::Set('users_assigned', $usersAssigned);

        // Users not in group
        $SQL  = "";
        $SQL .= "SELECT user.UserID, ";
        $SQL .= "       user.UserName, ";
        $SQL .= "       CONCAT('UserID_', user.userID) AS list_id ";
        $SQL .= "FROM   `user` ";
        $SQL .= " WHERE user.UserID NOT       IN ( ";
        $SQL .= "   SELECT user.UserID ";
        $SQL .= "  FROM   `user` ";
        $SQL .= "  INNER JOIN lkusergroup ";
        $SQL .= "  ON     lkusergroup.UserID = user.UserID ";
        $SQL .= sprintf("WHERE  lkusergroup.GroupID   = %d", $groupID);
        $SQL .= "       )";

        $usersAvailable = $db->GetArray($SQL);
		
		if (!is_array($usersAvailable))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting users'), E_USER_ERROR);
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
	 * @return
	 */
	public function SetMembers()
	{
        $db             =& $this->db;
        $response       = new ResponseManager();
        $groupObject    = new UserGroup($db);

        $groupID	= Kit::GetParam('GroupID', _REQUEST, _INT);
        $users	= Kit::GetParam('UserID', _POST, _ARRAY, array());
        $members	= array();

        // Users in group
        $SQL  = "";
        $SQL .= "SELECT user.UserID, ";
        $SQL .= "       user.UserName ";
        $SQL .= "FROM   `user` ";
        $SQL .= "       INNER JOIN lkusergroup ";
        $SQL .= "       ON     lkusergroup.UserID = user.UserID ";
        $SQL .= sprintf("WHERE  lkusergroup.GroupID   = %d", $groupID);

        if(!$resultIn = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Users'));
        }

        while($row = $db->get_assoc_row($resultIn))
        {
            // Test whether this ID is in the array or not
            $userID	= Kit::ValidateParam($row['UserID'], _INT);

            if (!in_array($userID, $users))
            {
                // Its currently assigned but not in the $displays array
                //  so we unassign
                if (!$groupObject->Unlink($groupID, $userID))
                {
                    trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
            else
            {
                $members[] = $userID;
            }
        }

        foreach($users as $userID)
        {
            // Add any that are missing
            if(!in_array($userID, $members))
            {
                if (!$groupObject->Link($groupID, $userID))
                {
                    trigger_error($groupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);
        $response->Respond();
	}
}
?>
