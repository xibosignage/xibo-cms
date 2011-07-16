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
class templateDAO 
{
	private $db;
	private $user;
	private $isadmin = false;
	private $has_permissions = true;
	
	private $sub_page = "";

	//template table fields
	private $templateid;
	private $template;
	private $permissionid;
	private $retired;
	private $description;
	private $tags;
	private $thumbnail;
	private $isSystem;
	
	private $xml;
	
	/**
	 * Constructor
	 * @return 
	 * @param $db Object
	 */
	function __construct(database $db, user $user) 
	{
		$this->db 			=& $db;
		$this->user 		=& $user;
		$this->templateid 	= Kit::GetParam('templateid', _REQUEST, _INT);
		$this->sub_page 	= Kit::GetParam('sp', _REQUEST, _WORD, 'view');
		
		if ($_SESSION['usertype'] ==1 ) $this->isadmin = true;
		
		
		//If we have the template ID get the templates information
		if ($this->templateid != "")
		{	
			$SQL = "SELECT template, description, permissionID, xml, tags, retired, isSystem, thumbnail FROM template WHERE templateID = $this->templateid ";
			
			if (!$results = $db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error("Can not get template information.", E_USER_ERROR);
			}
			
			$row = $db->get_row($results);
			
			$this->template		= $row[0];
			$this->description	= $row[1];
			$this->permissionid = $row[2];
			$this->xml			= $row[3];
			$this->tags 		= $row[4];
			$this->retired 		= $row[5];
			$this->isSystem		= $row[6];
			$this->thumbnail	= $row[7];
			
			// get the permissions
			list($see_permission , $this->has_permissions) = $user->eval_permission($ownerid, $this->permissionid);
			
			//check on permissions
			if (isset($_REQUEST['ajax']) && (!$this->has_permissions || !$see_permission)) {
				//ajax request handler
				trigger_error("You do not have permissions to edit this layout", E_USER_ERROR);
			}
		}
	}
	
	function on_page_load() 
	{
    	return '';
	}
	
	function echo_page_heading() 
	{
		echo 'Templates';
		return true;
	}
	
	/**
	 * Template filter
	 * @return 
	 */
	function TemplateFilter() 
	{
		$db =& $this->db;
		
		//filter form defaults
		$filter_name = "";
		if (isset($_SESSION['template']['name'])) $filter_name = $_SESSION['template']['name'];
		
		$tags = "";
		if (isset($_SESSION['template']['tags'])) $tags = $_SESSION['template']['tags'];

                $is_system = 'all';
		if (isset($_SESSION['template']['is_system'])) $is_system = $_SESSION['template']['is_system'];
		
		$system_list = dropdownlist("SELECT 'all','All' UNION SELECT '1','Yes' UNION SELECT '0','No'","is_system",$is_system);
		
		//Output the filter form
		$output = <<<END
		<div class="FilterDiv" id="TemplateFilter">
			<form>
				<input type="hidden" name="p" value="template">
				<input type="hidden" name="q" value="TemplateView">
				<table>
					<tr>
						<td>Name</td>
						<td><input type="text" name="name" value="$filter_name"></td>
						<td>System</td>
						<td>$system_list</td>
					</tr>
					<tr>
						<td>Tags</td>
						<td><input type="text" name="tags" value="$tags"></td>
					</tr>
				</table>
			</form>
		</div>
END;
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$output
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		echo $xiboGrid;
	}
	
	/**
	 * Data grid
	 * @return 
	 */
	function TemplateView() 
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$filter_name = Kit::GetParam('name', _POST, _STRING);
		$tags		 = Kit::GetParam('tags', _POST, _STRING);
		$is_system	 = Kit::GetParam('is_system', _POST, _INT);
		
		setSession('template', 'name', $filter_name);
		setSession('template', 'tags', $tags);
		setSession('template', 'is_system', $is_system);
	
		$SQL  = "";
		$SQL .= "SELECT  template.templateID, ";
		$SQL .= "        template.template, ";
		$SQL .= "        CASE WHEN template.issystem = 1 THEN 'Yes' ELSE 'No' END AS issystem, ";
		$SQL .= "        template.tags, ";
		$SQL .= "        permission.permission, ";
		$SQL .= "        permission.permissionID, ";
		$SQL .= "        template.userID ";
		$SQL .= "FROM    template ";
		$SQL .= "INNER JOIN permission ON template.permissionID = permission.permissionID ";
		$SQL .= "WHERE 1=1 ";
		if ($filter_name != "") 
		{
			$SQL .= " AND template.template LIKE '%" . $db->escape_string($filter_name) . "%' ";
		}
		if ($tags != "") 
		{
			$SQL .= " AND template.tags LIKE '%" . $db->escape_string($tags) . "%' ";
		}
		if ($is_system != "all") 
		{
			$SQL .= sprintf(" AND template.issystem = %d ", $is_system);
		}
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Can not get the templates - 1st query", E_USER_ERROR);
		}
		
		$table = <<<END
		<div class="info_table">
		<table style="width:100%;">
			<thead>
				<tr>
					<th>Name</th>
					<th>Is System</th>
					<th>Tags</th>
					<th>Permissions</th>
					<th>Owner</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
END;
		
		while ($row = $db->get_row($results)) 
		{
			$templateId = Kit::ValidateParam($row[0], _INT);
			$template 	= $row[1];
			$issystem 	= $row[2];
			$tags		= $row[3];
			$permission	= $row[4];
			$permissionid = $row[5];
			$userid		 = $row[6];
			
			//get the username from the userID using the user module
			$username 		= $user->getNameFromID($userid);
			$group			= $user->getGroupFromID($userid);
			
			//get the permissions
			list($see_permissions, $edit_permissions) = $user->eval_permission($userid, $permissionid);
			
			$buttons = "No available Actions";

                        if ($edit_permissions && $issystem == 'No')
                        {
                            $buttons = '<button class="XiboFormButton" href="index.php?p=template&q=DeleteTemplateForm&templateId=' . $templateId . '"><span>' . __('Delete') . '</span></button>';
                        }
			
			if ($see_permissions)
			{
				$table .= <<<END
				<tr>
					<td>$template</td>
					<td>$issystem</td>
					<td>$tags</td>
					<td>$permission</td>
					<td>$username</td>
					<td>$buttons</td>
				</tr>
END;
			}
		}
		$table .= "</tbody></table></div>";
		
		$response->SetGridResponse($table);
		$response->Respond();
	}
	
	/**
	 * Display page logic
	 * @return 
	 */
	function displayPage() {
		$db =& $this->db;
		
		if (!$this->has_permissions) {
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}
		
		switch ($this->sub_page) {
				
			case 'view':
				require("template/pages/template_view.php");
				break;
					
			default:
				break;
		}
		
		return false;
	}
	
	/**
	 * Displays the TemplateForm (for adding and editing)
	 * @return 
	 */
	function TemplateForm() 
	{
		$db 		=& $this->db;
		$user 		=& $this->user;
		$response 	= new ResponseManager();
		$layoutid 	= Kit::GetParam('layoutid', _REQUEST, _INT, 0);
			
		//database fields
		$templateid 		= $this->templateid;
		$template 			= $this->template;
		$description		= $this->description;
		$tags		 		= $this->tags;
		$permissionid		= $this->permissionid;
		$retired			= $this->retired;
		
		//init the retired option
		$retired_option = "";
		
		$action = "index.php?p=template&q=AddTemplate";
		
		if ($templateid != "") 
		{ 
			//assume an edit
			$action = "index.php?p=template&q=EditTemplate";
			
			//build the retired option
			$retired_list = listcontent("1|Yes,0|No","retired",$retired);
			$retired_option = <<<END
			<tr>
				<td><label for='retired'>Retired<span class="required">*</span></label></td>
				<td>$retired_list</td>
			</tr>
END;
		}
		
		$shared_list = dropdownlist("SELECT permissionID, permission FROM permission", "permissionid", $permissionid);
	
		$form = <<<END
		
			<form class="XiboForm" action="$action" method="post">

				<input type="hidden" name="templateid" value="$templateid">
				<input type="hidden" name="layoutid" value="$layoutid">
				
				<table>
					<tr>
						<td><label for="name" title="This templates name">Name <span class="required">*</span></label></td>
						<td><input type="text" id="template" name="template" value="$template"></td>
						<td><label for="tags" title="Tags can be used to search for this template.">Tags</label></td>
						<td><input type="text" id="tags" name="tags" value="$tags"></td>
					</tr>
					<tr>
						<td><label for="description"  title="An optional description of this template.">Description</label></td>
						<td><input type="text" id="description" name="description" value="$description"></td>
						<td><label for="permissionid" title="What permissions to give this template.">Sharing <span class="required">*</span></label></td>
						<td>$shared_list</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<input type="submit" value="Save" />
						</td>
					</tr>
				</table>
			</form>
END;
		
		$response->SetFormRequestResponse($form, 'Save this layout as a Template?', '550px', '200px');
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
		$permissionid 	= Kit::GetParam('permissionid', _POST, _INT);
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
		$SQL = "INSERT INTO template (template, tags, issystem, retired, description, createdDT, modifiedDT, userID, xml, permissionID) ";
		$SQL.= "	   VALUES ('$template', '$tags', 0, 0, '$description', '$currentdate', '$currentdate', $userid, '$xml', $permissionid) ";
		
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
	 * Edits a template
	 * @return 
	 */
	function EditTemplate() 
	{
		$db =& $this->db;
		
		//ajax request handler
		trigger_error('Editing of templates currently unavailable.', E_USER_ERROR);
		return false;	
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
        $helpManager = new HelpManager($db, $user);

        $templateId = Kit::GetParam('templateId', _POST, _INT);

        if ($templateId == 0)
            trigger_error(__('No template found'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        if (!$this->user->TemplateAuth($templateId))
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
        
        $templateId = Kit::GetParam('templateId', _GET, _INT);

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
}
?>