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

class templateDAO extends baseDAO {
    /**
     * Display page logic
     */
    function displayPage() {

        $db =& $this->db;

        // Default options
        if (Kit::IsFilterPinned('template', 'Filter')) {
            $pinned = 1;
            $name = Session::Get('template', 'filter_name');
            $tags = Session::Get('template', 'filter_tags');
            $showThumbnail = Session::Get('template', 'showThumbnail');
        }
        else {
            $pinned = 0;
            $name = '';
            $tags = '';
            $showThumbnail = 1;
        }
        
        $id = uniqid();
        Theme::Set('header_text', __('Templates'));
        Theme::Set('id', $id);
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="template"><input type="hidden" name="q" value="TemplateView">');
        
        $formFields = array();
        $formFields[] = FormManager::AddText('filter_name', __('Name'), $name, NULL, 'n');
        $formFields[] = FormManager::AddText('filter_tags', __('Tags'), $tags, NULL, 't');
        $formFields[] = FormManager::AddCheckbox('showThumbnail', __('Show Thumbnails'), 
            $showThumbnail, NULL, 
            't');
        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
            $pinned, NULL, 
            'k');

        Theme::Set('form_fields', $formFields);

        // Call to render the template
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
                    ),
                array('title' => __('Import'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=layout&q=ImportForm&template=true',
                    'help' => __('Import a Layout from a ZIP file.'),
                    'onclick' => ''
                    )
            );                   
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
        
        setSession('template', 'filter_name', $filter_name);
        setSession('template', 'filter_tags', $filter_tags);
        setSession('template', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        
        // Show filter_showThumbnail
        $showThumbnail = Kit::GetParam('showThumbnail', _POST, _CHECKBOX);
        setSession('layout', 'showThumbnail', $showThumbnail);
    
        $templates = $user->TemplateList(null, array('layout' => $filter_name, 'tags' => $filter_tags));

        if (!is_array($templates)) {
            trigger_error(__('Unable to get list of templates for this user'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'layout', 'title' => __('Name')),
                array('name' => 'owner', 'title' => __('Owner')),
                array('name' => 'descriptionWithMarkup', 'title' => __('Description')),
                array('name' => 'thumbnail', 'title' => __('Thumbnail'), 'hidden' => ($showThumbnail == 0)),
                array('name' => 'permissions', 'title' => __('Permissions'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($templates as $template) {
            
            $template['permissions'] = $this->GroupsForTemplate($template['campaignid']);
            $template['owner'] = $user->getNameFromID($template['ownerid']);

            $template['thumbnail'] = '';

            if ($showThumbnail == 1 && $template['backgroundImageId'] != 0)
                $template['thumbnail'] = '<a class="img-replace" data-toggle="lightbox" data-type="image" data-img-src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $template['backgroundImageId'] . '&width=100&height=100&dynamic=true&thumb=true" href="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $template['backgroundImageId'] . '"><i class="fa fa-file-image-o"></i></a>';


            $template['buttons'] = array();

            // Parse down for description
            $template['descriptionWithMarkup'] = Parsedown::instance()->text($template['description']);

            if ($template['edit']) {
                // Edit Button
                $template['buttons'][] = array(
                        'id' => 'template_button_edit',
                        'url' => 'index.php?p=template&q=EditForm&modify=true&layoutid=' . $template['layoutid'],
                        'text' => __('Edit')
                    );
            }

            if ($template['del']) {

                // Delete Button
                $template['buttons'][] = array(
                        'id' => 'layout_button_delete',
                        'url' => 'index.php?p=template&q=DeleteTemplateForm&layoutid=' . $template['layoutid'],
                        'text' => __('Delete')
                    );
            }

            if ($template['modifyPermissions']) {

                // Permissions Button
                $template['buttons'][] = array(
                        'id' => 'layout_button_delete',
                        'url' => 'index.php?p=campaign&q=PermissionsForm&CampaignID=' . $template['campaignid'],
                        'text' => __('Permissions')
                    );
            }

            $template['buttons'][] = array('linkType' => 'divider');

            // Export Button
            $template['buttons'][] = array(
                    'id' => 'layout_button_export',
                    'linkType' => '_self',
                    'url' => 'index.php?p=layout&q=Export&layoutid=' . $template['layoutid'],
                    'text' => __('Export')
                );

            // Add this row to the array
            $rows[] = $template;    
        }

        Theme::Set('table_rows', $rows);
        
        $response->SetGridResponse(Theme::RenderReturn('table_render'));
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

        $formFields = array();
        $formFields[] = FormManager::AddText('template', __('Name'), NULL, 
            __('The Name of the Template - (1 - 50 characters)'), 'n', 'maxlength="50" required');

        $formFields[] = FormManager::AddText('tags', __('Tags'), NULL, 
            __('Tags for this Template - used when searching for it. Space delimited. (1 - 250 characters)'), 't', 'maxlength="250"');

        $formFields[] = FormManager::AddMultiText('description', __('Description'), NULL, 
            __('An optional description of the Template. (1 - 250 characters)'), 'd', 5, 'maxlength="250"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Save this Layout as a Template?'), '550px', '230px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplateAddForm").submit()');
        $response->Respond();
    }

    function EditForm() {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _GET, _INT);

        // Get the layout    
        $layout = $user->LayoutList(NULL, array('layoutId' => Kit::GetParam('layoutid', _GET, _INT), 'excludeTemplates' => 0));

        if (count($layout) <= 0)
            trigger_error(__('Unable to find Template'), E_USER_ERROR);
        
        $layout = $layout[0];
    
        Theme::Set('form_id', 'TemplateEditForm');

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('description', __('Description'));
        
        Theme::Set('form_tabs', $tabs);
        
        $formFields = array();
        $formFields['general'][] = FormManager::AddText('layout', __('Name'), $layout['layout'], __('The Name of the Layout - (1 - 50 characters)'), 'n', 'required');
        
        $formFields['description'][] = FormManager::AddMultiText('description', __('Description'), $layout['description'], 
            __('An optional description of the Layout. (1 - 250 characters)'), 'd', 5, 'maxlength="250"');

        // We are editing
        Theme::Set('form_action', 'index.php?p=template&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutId . '">');

        $formFields['general'][] = FormManager::AddCombo(
                'retired', 
                __('Retired'), 
                $layout['retired'],
                array(array('retiredid' => '1', 'retired' => 'Yes'), array('retiredid' => '0', 'retired' => 'No')),
                'retiredid',
                'retired',
                __('Retire this template or not? It will no longer be visible in lists'), 
                'r');

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_description', $formFields['description']);

        // Initialise the template and capture the output
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Edit Template'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplateEditForm").submit()');
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
        $templateLayoutId = Kit::GetParam('layoutid', _POST, _INT);
        
        // Copy the layout and adjust the values of the new layout
        $layoutObject = new Layout();

        if (!$layoutId = $layoutObject->Copy($templateLayoutId, $template, $description, $user->userid, true))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        // Tag it
        if ($tags != '') {
            // Create an array out of the tags
            $tagsArray = explode(',', $tags);
            $tagsArray[] = 'template';
        }
        else {
            $tagsArray = array('template');
        }

        // Add the tags XML to the layout
        $layoutObject->EditTags($layoutId, $tagsArray);
        
        $response->SetFormSubmitResponse('Template Added.');
        $response->Respond();
    }

    function Edit() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response       = new ResponseManager();

        $layoutid       = Kit::GetParam('layoutid', _POST, _INT);
        $layout         = Kit::GetParam('layout', _POST, _STRING);
        $description    = Kit::GetParam('description', _POST, _STRING);
        $retired        = Kit::GetParam('retired', _POST, _INT, 0);
        $userid         = Kit::GetParam('userid', _SESSION, _INT);
        $tags = 'template';
        
        // Add this layout
        $layoutObject = new Layout();

        if (!$layoutObject->Edit($layoutid, $layout, $description, $tags, $userid, $retired))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Template Details Changed.'));
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

        $templateId = Kit::GetParam('templateId', _POST, _INT);

        if ($templateId == 0)
            trigger_error(__('No template selected'), E_USER_ERROR);

        // Is this user allowed to delete this template?
        $auth = $this->user->TemplateAuth($templateId, true);
        
        if (!$auth->del)
            trigger_error(__('Access denied'), E_USER_ERROR);

        // Use the data class
        $template = new Layout();

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
        $response = new ResponseManager();
        
        $templateId = Kit::GetParam('layoutid', _GET, _INT);

        $auth = $this->user->TemplateAuth($templateId, true);
        if (!$auth->del)
            trigger_error(__('You do not have permissions to delete this template'), E_USER_ERROR);
        
        Theme::Set('form_id', 'DeleteForm');
        Theme::Set('form_action', 'index.php?p=template&q=DeleteTemplate');
        Theme::Set('form_meta', '<input type="hidden" name="templateId" value="' . $templateId . '">');
        Theme::Set('form_fields', array(
            FormManager::AddMessage(__('Are you sure you want to delete this template?'))
            ));

        $form = Theme::RenderReturn('form_render');
        
        $response->SetFormRequestResponse($form, __('Delete Template'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteForm").submit()');
        $response->Respond();
    }
     
     /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForTemplate($campaignId)
    {
        $db =& $this->db;

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkcampaigngroup ';
        $SQL .= '   ON `group`.GroupID = lkcampaigngroup.GroupID ';
        $SQL .= ' WHERE lkcampaigngroup.CampaignID = %d ';

        $SQL = sprintf($SQL, $campaignId);

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
}
?>
