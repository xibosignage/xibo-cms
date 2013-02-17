<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner and James Packer
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
class templateDAO 
{
	private $db;
	private $user;
	private $auth;
	
	/**
	 * Constructor
	 * @return 
	 * @param $db Object
	 */
	function __construct(database $db, user $user) 
	{
		$this->db 			=& $db;
		$this->user 		=& $user;
	}
	
	/**
	 * Display page logic
	 */
	function displayPage() {

		$db =& $this->db;

		// Default options
        if (Kit::IsFilterPinned('template', 'Filter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_name', Session::Get('template', 'filter_name'));
            Theme::Set('filter_tags', Session::Get('template', 'filter_tags'));
            Theme::Set('filter_is_system', Session::Get('template', 'filter_is_system'));
        }
        else {
			Theme::Set('filter_is_system', -1);
        }
		
		$id = uniqid();
		Theme::Set('id', $id);
		Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
		Theme::Set('pager', ResponseManager::Pager($id));
		Theme::Set('form_meta', '<input type="hidden" name="p" value="template"><input type="hidden" name="q" value="TemplateView">');
		
		// Field list for a "retired" dropdown list
        Theme::Set('is_system_field_list', array(
	        		array('is_systemid' => '-1', 'is_system' => 'All'), 
	        		array('is_systemid' => '1', 'is_system' => 'Yes'),
	        		array('is_systemid' => '0', 'is_system' => 'No')
        		)
        	);

		// Call to render the template
		Theme::Render('template_page');
	}
	
	/**
	 * Data grid
	 */
	function TemplateView() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$filter_name = Kit::GetParam('filter_name', _POST, _STRING);
		$filter_tags = Kit::GetParam('filter_tags', _POST, _STRING);
		$filter_is_system = Kit::GetParam('filter_is_system', _POST, _INT);
		
		setSession('template', 'filter_name', $filter_name);
		setSession('template', 'filter_tags', $filter_tags);
		setSession('template', 'filter_is_system', $filter_is_system);
        setSession('template', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
	
		$templates = $user->TemplateList($filter_name, $filter_tags, $filter_is_system);

		if (!is_array($templates)) {
			trigger_error(__('Unable to get list of templates for this user'), E_USER_ERROR);
		}

		$rows = array();

		foreach ($templates as $template) {
			
			$template['permissions'] = $this->GroupsForTemplate($template['templateid']);
			$template['owner'] = $user->getNameFromID($template['ownerid']);
			$template['buttons'] = array();

			if ($template['del'] && $template['issystem'] == 'No') {

				// Delete Button
	    		$template['buttons'][] = array(
	    				'id' => 'layout_button_delete',
	    				'url' => 'index.php?p=template&q=DeleteTemplateForm&templateid=' . $template['templateid'],
	    				'text' => __('Delete')
	    			);
			}

			if ($template['modifyPermissions'] && $template['issystem'] == 'No') {

				// Permissions Button
	    		$template['buttons'][] = array(
	    				'id' => 'layout_button_delete',
	    				'url' => 'index.php?p=template&q=PermissionsForm&templateid=' . $template['templateid'],
	    				'text' => __('Permissions')
	    			);
			}

			// Add this row to the array
			$rows[] = $template;	
		}

		Theme::Set('table_rows', $rows);
		
		$response->SetGridResponse(Theme::RenderReturn('template_page_grid'));
		$response->Respond();
	}
	
	/**
	 * Displays the TemplateForm (for adding)
	 * @return 
	 */
	function TemplateForm() 
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		$response 	= new ResponseManager();
		$layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT);
		
		Theme::Set('form_id', 'TemplateAddForm');
        Theme::Set('form_action', 'index.php?p=template&q=AddTemplate');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');

		$form = Theme::RenderReturn('template_form_add');

		$response->SetFormRequestResponse($form, __('Save this Layout as a Template?'), '550px', '230px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplateAddForm").submit()');
		$response->Respond();
	}
	
	/**
	 * Adds a template
	 * @return 
	 */
	function AddTemplate() 
	{
		$db 			=& $this->db;
		$response		= new ResponseManager();

		$template 		= $_POST['template'];
		$tags		 	= $_POST['tags'];
		$description	= $_POST['description'];
		
		$layoutid		= $_POST['layoutid'];
		
		$userid 		= $_SESSION['userid'];
		$currentdate 	= date("Y-m-d H:i:s");
		
		//validation
		if (strlen($template) > 50 || strlen($template) < 1) 
		{
			$response->SetError("Template Name must be between 1 and 50 characters");
			$response->Respond();
		}
		
		if (strlen($description) > 254) 
		{
			$response->SetError("Description can not be longer than 254 characters");
			$response->Respond();
		}
		
		if (strlen($tags) > 254) 
		{
			$response->SetError("Tags can not be longer than 254 characters");
			$response->Respond();
		}
		
		//Check on the name the user has selected
		$check = "SELECT template FROM template WHERE template = '$template' AND userID = $userid ";
		
		$result = $db->query($check) or trigger_error($db->error());
		
		//Template with the same name?
		if($db->num_rows($result) != 0) 
		{
			$response->SetError("You already own a template called '$template'. Please choose another name.");
			$response->Respond();
		}
		//end validation
		
		//Get the Layout XML (but reconstruct so that there are no media nodes in it)
		if (!$xml = $this->GetLayoutXmlNoMedia($layoutid))
		{
			$response->SetError("Cannot get the Layout Structure.");
			$response->Respond();
		}
		
		//Insert the template
		$SQL = "INSERT INTO template (template, tags, issystem, retired, description, createdDT, modifiedDT, userID, xml) ";
		$SQL.= "	   VALUES ('$template', '$tags', 0, 0, '$description', '$currentdate', '$currentdate', $userid, '$xml') ";
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$response->SetError("Unexpected error adding Template.");
			$response->Respond();
		}
		
		$response->SetFormSubmitResponse('Template Added.');
		$response->Respond();
	}
	
    /**
     * Deletes a template
     * @return
     */
    function DeleteTemplate()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $templateId = Kit::GetParam('templateid', _POST, _INT);

        if ($templateId == 0)
            trigger_error(__('No template selected'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        $auth = $this->user->TemplateAuth($templateId, true);
        
        if (!$auth->del)
            trigger_error(__('Access denied'), E_USER_ERROR);

        // Use the data class
        Kit::ClassLoader('template');
        $template = new Template($db);

        // Delete the template
        if (!$template->Delete($templateId))
            trigger_error($layout->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Template has been Deleted'));
        $response->Respond();
    }

    /**
     * Shows the form to delete a template
     */
    public function DeleteTemplateForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this template'), E_USER_ERROR);
        
        $templateId = Kit::GetParam('templateid', _GET, _INT);

        if ($templateId == 0)
            trigger_error(__('No template found'), E_USER_ERROR);
        
        // Construct some messages to display
        $msgWarn = __('Are you sure you want to delete this template?');

        $form = <<<END
        <form id="DeleteTemplateForm" class="XiboForm" method="post" action="index.php?p=template&q=DeleteTemplate">
            <input type="hidden" name="templateId" value="$templateId">
            <p>$msgWarn</p>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete a Template'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Template', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteTemplateForm").submit()');
        $response->Respond();
    }
	
	/**
	 * Gets the Xml for the specified layout
	 * @return 
	 * @param $layoutid Object
	 */
	private function GetLayoutXmlNoMedia($layoutid)
	{
		$db =& $this->db;
		
		//Get the Xml for this Layout from the DB
		$SQL = "SELECT xml FROM layout WHERE layoutID = $layoutid ";
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$errMsg = "Unable to Query for that layout, there is a database error.";
			return false;
		}
		$row = $db->get_row($results) ;
		
		$xml = new DOMDocument("1.0");
		$xml->loadXML($row[0]);
		
		$xpath = new DOMXPath($xml);
		
		//We want to get all the media nodes
		$mediaNodes = $xpath->query('//media');
		
		foreach ($mediaNodes as $node) 
		{
			$node->parentNode->removeChild($node);
		}
		
		return $xml->saveXML();
	}
     
     /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForTemplate($templateId)
    {
        $db =& $this->db;

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lktemplategroup ';
        $SQL .= '   ON `group`.GroupID = lktemplategroup.GroupID ';
        $SQL .= ' WHERE lktemplategroup.TemplateID = %d ';

        $SQL = sprintf($SQL, $templateId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for template'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results))
        {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }

    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $templateId = Kit::GetParam('templateid', _GET, _INT);

        if (!$this->auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this template'), E_USER_ERROR);

        // Form content
        $form = '<form id="TemplatePermissionsForm" class="XiboForm" method="post" action="index.php?p=template&q=Permissions">';
	$form .= '<input type="hidden" name="templateid" value="' . $templateId . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lktemplategroup ';
        $SQL .= '   ON lktemplategroup.GroupID = group.GroupID ';
        $SQL .= '       AND lktemplategroup.TemplateID = %d ';
        $SQL .= ' WHERE `group`.GroupID <> %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $templateId, $user->getGroupFromId($user->userid, true));

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this template'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Template', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplatePermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        Kit::ClassLoader('templategroupsecurity');

        $templateId = Kit::GetParam('templateid', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        if (!$this->auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this template'), E_USER_ERROR);

        // Unlink all
        $security = new TemplateGroupSecurity($db);
        if (!$security->UnlinkAll($templateId))
            trigger_error(__('Unable to set permissions'), E_USER_ERROR);

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($templateId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'), E_USER_ERROR);

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first)
        {
            if (!$security->Link($templateId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }
}
?>
