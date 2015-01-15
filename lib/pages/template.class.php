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
        $this->db           =& $db;
        $this->user         =& $user;
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
                    array('is_systemid' => -1, 'is_system' => 'All'), 
                    array('is_systemid' => 1, 'is_system' => 'Yes'),
                    array('is_systemid' => 0, 'is_system' => 'No')
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
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();
        
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
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();
        $layoutid   = Kit::GetParam('layoutid', _REQUEST, _INT);
        
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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $template = Kit::GetParam('template', _POST, _STRING);
        $tags = Kit::GetParam('tags', _POST, _STRING);
        $description = Kit::GetParam('description', _POST, _STRING);
        $layoutid = Kit::GetParam('layoutid', _POST, _INT);
        
        // Use the data class
        Kit::ClassLoader('template');
        $templateObject = new Template($db);

        // Delete the template
        if (!$templateObject->Add($template, $description, $tags, $layoutid, $user->userid))
            trigger_error($templateObject->GetErrorMessage(), E_USER_ERROR);
        
        $response->SetFormSubmitResponse('Template Added.');
        $response->Respond();
    }
    
    /**
     * Deletes a template
     * @return
     */
    function DeleteTemplate()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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
            trigger_error($template->GetErrorMessage(), E_USER_ERROR);

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

        $templateId = Kit::GetParam('templateid', _GET, _INT);

        if ($templateId == 0)
            trigger_error(__('No template selected'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        $auth = $this->user->TemplateAuth($templateId, true);
        
        if (!$auth->del)
            trigger_error(__('Access denied'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DeleteTemplateForm');
        Theme::Set('form_action', 'index.php?p=template&q=DeleteTemplate');
        Theme::Set('form_meta', '<input type="hidden" name="templateid" value="' . $templateId . '" />');

        $form = Theme::RenderReturn('campaign_form_delete');

        $response->SetFormRequestResponse($form, __('Delete a Template'), '350px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteTemplateForm").submit()');
        $response->Respond();
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

    /**
     * Permissions form
     */
    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $templateId = Kit::GetParam('templateid', _GET, _INT);

        if ($templateId == 0)
            trigger_error(__('No template selected'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        $auth = $this->user->TemplateAuth($templateId, true);

        // Set some information about the form
        Theme::Set('form_id', 'TemplatePermissionsForm');
        Theme::Set('form_action', 'index.php?p=template&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="templateid" value="' . $templateId . '" />');

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

        $checkboxes = array();

        while ($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $rowClass = ($row['IsUserSpecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                    'id' => $groupId,
                    'name' => Kit::ValidateParam($row['Group'], _STRING),
                    'class' => $rowClass,
                    'value_view' => $groupId . '_view',
                    'value_view_checked' => (($row['View'] == 1) ? 'checked' : ''),
                    'value_edit' => $groupId . '_edit',
                    'value_edit_checked' => (($row['Edit'] == 1) ? 'checked' : ''),
                    'value_del' => $groupId . '_del',
                    'value_del_checked' => (($row['Del'] == 1) ? 'checked' : ''),
                );

            $checkboxes[] = $checkbox;
        }

        Theme::Set('form_rows', $checkboxes);

        $form = Theme::RenderReturn('campaign_form_permissions');

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Template', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplatePermissionsForm").submit()');
        $response->Respond();
    }

    /**
     * Set this templates permissions
     */
    public function Permissions()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        
        $templateId = Kit::GetParam('templateid', _POST, _INT);

        if ($templateId == 0)
            trigger_error(__('No template selected'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        $auth = $this->user->TemplateAuth($templateId, true);

        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        // Unlink all
        Kit::ClassLoader('templategroupsecurity');
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
