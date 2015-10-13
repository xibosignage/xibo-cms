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
 
class layoutDAO extends baseDAO 
{
    private $auth;
    private $has_permissions = true;
    
    private $sub_page = "";
    
    private $layoutid;
    private $layout;
    private $retired;
    private $description;
    private $tags;
    
    private $xml;
    
    /**
     * Layout Page Logic
     * @return 
     * @param $db Object
     */
    function __construct(database $db, user $user)
    {
            $this->db   =& $db;
            $this->user =& $user;
        
            $this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
            $this->layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);

            // If we have modify selected then we need to get some info
            if ($this->layoutid != '') {
                // get the permissions
                Debug::LogEntry('audit', 'Loading permissions for layoutid ' . $this->layoutid);

                $this->auth = $user->LayoutAuth($this->layoutid, true);

                if (!$this->auth->edit)
                    trigger_error(__("You do not have permissions to edit this layout"), E_USER_ERROR);

                $this->sub_page = "edit";

                $sql  = " SELECT layout, description, userid, retired, xml FROM layout ";
                $sql .= sprintf(" WHERE layoutID = %d ", $this->layoutid);

                if(!$results = $db->query($sql))
                {
                        trigger_error($db->error());
                        trigger_error(__("Cannot retrieve the Information relating to this layout. The layout may be corrupt."), E_USER_ERROR);
                }

                if ($db->num_rows($results) == 0)
                    $this->has_permissions = false;

                while($aRow = $db->get_row($results))
                {
                    $this->layout = Kit::ValidateParam($aRow[0], _STRING);
                    $this->description  = Kit::ValidateParam($aRow[1], _STRING);
                    $this->retired = Kit::ValidateParam($aRow[3], _INT);
                    $this->xml = $aRow[4];
                }
            }
    }

    /**
     * Displays the Layout Page
     */
    function displayPage() 
    {
        $db =& $this->db;
        
        switch ($this->sub_page) 
        {   
            case 'view':

                // Default options
                if (Kit::IsFilterPinned('layout', 'LayoutFilter')) {
                    $layout = Session::Get('layout', 'filter_layout');
                    $tags = Session::Get('layout', 'filter_tags');
                    $retired = Session::Get('layout', 'filter_retired');
                    $owner = Session::Get('layout', 'filter_userid');
                    $filterLayoutStatusId = Session::Get('layout', 'filterLayoutStatusId');
                    $showDescriptionId = Session::Get('layout', 'showDescriptionId');
                    $showThumbnail = Session::Get('layout', 'showThumbnail');
                    $showTags = Session::Get('content', 'showTags');
                    $pinned = 1;
                }
                else {
                    $layout = NULL;
                    $tags = NULL;
                    $retired = 0;
                    $owner = NULL;
                    $filterLayoutStatusId = 1;
                    $showDescriptionId = 2;
                    $pinned = 0;
                    $showThumbnail = 1;
                    $showTags = 0;
                }
                
                $id = uniqid();
                Theme::Set('header_text', __('Layouts'));
                Theme::Set('id', $id);
                Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
                Theme::Set('pager', ResponseManager::Pager($id));
                Theme::Set('form_meta', '<input type="hidden" name="p" value="layout"><input type="hidden" name="q" value="LayoutGrid">');
                
                $formFields = array();
                $formFields[] = FormManager::AddText('filter_layout', __('Name'), $layout, NULL, 'l');
                $formFields[] = FormManager::AddText('filter_tags', __('Tags'), $tags, NULL, 't');

                // Users we have permission to see
                $users = $this->user->userList();
                array_unshift($users, array('userid' => '', 'username' => 'All'));

                $formFields[] = FormManager::AddCombo(
                    'filter_userid', 
                    __('Owner'), 
                    $owner,
                    $users,
                    'userid',
                    'username',
                    NULL, 
                    'r');
                $formFields[] = FormManager::AddCombo(
                    'filter_retired', 
                    __('Retired'), 
                    $retired,
                    array(array('retiredid' => 1, 'retired' => 'Yes'), array('retiredid' => 0, 'retired' => 'No')),
                    'retiredid',
                    'retired',
                    NULL, 
                    'r');
                $formFields[] = FormManager::AddCombo(
                    'filterLayoutStatusId', 
                    __('Show'), 
                    $filterLayoutStatusId,
                    array(
                        array('filterLayoutStatusId' => 1, 'filterLayoutStatus' => __('All')),
                        array('filterLayoutStatusId' => 2, 'filterLayoutStatus' => __('Only Used')),
                        array('filterLayoutStatusId' => 3, 'filterLayoutStatus' => __('Only Unused'))
                        ),
                    'filterLayoutStatusId',
                    'filterLayoutStatus',
                    NULL, 
                    's');
                $formFields[] = FormManager::AddCombo(
                    'showDescriptionId', 
                    __('Description'), 
                    $showDescriptionId,
                    array(
                        array('showDescriptionId' => 1, 'showDescription' => __('All')),
                        array('showDescriptionId' => 2, 'showDescription' => __('1st line')),
                        array('showDescriptionId' => 3, 'showDescription' => __('None'))
                        ),
                    'showDescriptionId',
                    'showDescription',
                    NULL, 
                    'd');

                $formFields[] = FormManager::AddCheckbox('showTags', __('Show Tags'), 
                    $showTags, NULL, 
                    't');

                $formFields[] = FormManager::AddCheckbox('showThumbnail', __('Show Thumbnails'), 
                    $showThumbnail, NULL, 
                    'i');

                $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'), 
                    $pinned, NULL, 
                    'k');

                Theme::Set('form_fields', $formFields);

                // Call to render the template
                Theme::Render('grid_render');
                break;
                
            case 'edit':
                
                Theme::Set('layout_form_edit_url', 'index.php?p=layout&q=displayForm&modify=true&layoutid=' . $this->layoutid);
                Theme::Set('layout_form_edit_background_url', 'index.php?p=layout&q=BackgroundForm&modify=true&layoutid=' . $this->layoutid);
                Theme::Set('layout_form_savetemplate_url', 'index.php?p=template&q=TemplateForm&layoutid=' . $this->layoutid);
                Theme::Set('layout_form_addregion_url', 'index.php?p=timeline&q=AddRegion&layoutid=' . $this->layoutid);
                Theme::Set('layout_form_preview_url', 'index.php?p=preview&q=render&ajax=true&layoutid=' . $this->layoutid);
                Theme::Set('layout', $this->layout);

                Kit::ClassLoader('campaign');
                $campaign = new Campaign($db);
                $campaignId = $campaign->GetCampaignId($this->layoutid);
                Theme::Set('layout_form_schedulenow_url', 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $campaignId);
                Theme::Set('layout_designer_editor', $this->RenderDesigner());

                // Set up the theme variables for the Layout Jump List
                Theme::Set('layoutId', $this->layoutid);
                Theme::Set('layouts', $this->user->LayoutList());

				// Set up any JavaScript translations
                Theme::SetTranslation('save_position_button', __('Save Position'));
   				Theme::SetTranslation('revert_position_button', __('Undo'));
                Theme::SetTranslation('savePositionsFirst', Theme::Translate('Please save the pending position changes first by clicking "Save Positions" or cancel by clicking "Undo".'));

                // Call the render the template
                Theme::Render('layout_designer');

                break;
                
            default:
                break;
        }
        
        return false;
    }

    function actionMenu() {

        if ($this->sub_page != 'view')
            return NULL;

        return array(
                array('title' => __('Filter'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Open the filter form'),
                    'onclick' => 'ToggleFilterView(\'Filter\')'
                    ),
                array('title' => __('Add Layout'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=layout&q=displayForm',
                    'help' => __('Add a new Layout and jump to the layout designer.'),
                    'onclick' => ''
                    ),
                array('title' => __('Import'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=layout&q=ImportForm',
                    'help' => __('Import a Layout from a ZIP file.'),
                    'onclick' => ''
                    )
            );                   
    }

    /**
     * Adds a layout record to the db
     * @return 
     */
    function add() 
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db             =& $this->db;
        $response       = new ResponseManager();

        $layout         = Kit::GetParam('layout', _POST, _STRING);
        $description    = Kit::GetParam('description', _POST, _STRING);
        $tags           = Kit::GetParam('tags', _POST, _STRING);
        $templateId     = Kit::GetParam('templateid', _POST, _INT, 0);
        $resolutionId = Kit::GetParam('resolutionid', _POST, _INT, 0);
        $userid         = Kit::GetParam('userid', _SESSION, _INT);

        // Add this layout
        $layoutObject = new Layout($db);

        if (!$id = $layoutObject->Add($layout, $description, $tags, $userid, $templateId, $resolutionId))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        // Successful layout creation
        $response->SetFormSubmitResponse(__('Layout Details Changed.'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $id));
        $response->Respond();
    }

    /**
     * Modifies a layout record
     *
     * @param int $id
     */
    function modify ()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response       = new ResponseManager();

        $layoutid       = Kit::GetParam('layoutid', _POST, _INT);
        $layout         = Kit::GetParam('layout', _POST, _STRING);
        $description    = Kit::GetParam('description', _POST, _STRING);
        $tags           = Kit::GetParam('tags', _POST, _STRING);
        $retired        = Kit::GetParam('retired', _POST, _INT, 0);
        $userid         = Kit::GetParam('userid', _SESSION, _INT);
        
        // Add this layout
        $layoutObject = new Layout();

        if (!$layoutObject->Edit($layoutid, $layout, $description, $tags, $userid, $retired))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Layout Details Changed.'));
        $response->Respond();
    }
    
    /**
     * Upgrade Layout Form
     */
    public function upgradeForm() 
    {
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutId', _GET, _INT);

        if ($layoutId == 0)
            trigger_error(__('layoutId missing'), E_USER_ERROR);

        // Do we have permission to touch this layout?
        $auth = $this->user->LayoutAuth($layoutId, true);

        if (!$auth->edit)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);
        
        Theme::Set('form_id', 'LayoutUpgradeForm');
        Theme::Set('form_action', 'index.php?p=layout&q=upgrade');
        Theme::Set('form_meta', '<input type="hidden" name="layoutId" value="' . $layoutId . '">');

        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Are you sure you want to upgrade this layout?'));
        $formFields[] = FormManager::AddMessage(__('Layouts are now designed at the display resolution allowing better positioning, smoother scrolling and much more. To upgrade this layout you need to select the intended resolution.'));
        $formFields[] = FormManager::AddCombo(
                    'resolutionId', 
                    __('Resolution'), 
                    NULL,
                    $this->user->ResolutionList(),
                    'resolutionid',
                    'resolution',
                    __('The regions will be resized to fit with the new resolution, but you may need to adjust the content manually.'), 
                    'r', 'required');

        // Provide a check box which will attempt to scale up the contents of all media items
        $formFields[] = FormManager::AddCheckbox('scaleContent', __('Upscale?'), 1,
            __('Automatically upscale all media content on this Layout to fit with the new resolution selected. Manual adjustment may still be required.'),
            's');

        Theme::Set('form_fields', $formFields);

        $form = Theme::RenderReturn('form_render');
        
        $response->SetFormRequestResponse($form, __('Upgrade Layout'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Upgrade') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutUpgradeForm").submit()');
        $response->Respond();
    }

    public function upgrade()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutId', _POST, _INT);
        $resolutionId = Kit::GetParam('resolutionId', _POST, _INT);
        $scaleContent = Kit::GetParam('scaleContent', _POST, _CHECKBOX);

        if ($layoutId == 0)
            trigger_error(__('layoutId missing'), E_USER_ERROR);

        // Do we have permission to touch this layout?
        $auth = $this->user->LayoutAuth($layoutId, true);

        if (!$auth->edit)
            trigger_error(__('You do not have permissions to upgrade this layout'), E_USER_ERROR);

        $layoutObject = new Layout();

        if (!$layoutObject->upgrade($layoutId, $resolutionId, $scaleContent))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Layout has been Upgraded'), true, 'index.php?p=layout&modify=true&layoutid=' . $layoutId);
        $response->Respond();
    }
    
    function DeleteLayoutForm() 
    {
        $db =& $this->db;
        $response = new ResponseManager();
        
        $layoutid = $this->layoutid;

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);
        
        Theme::Set('form_id', 'LayoutDeleteForm');
        Theme::Set('form_action', 'index.php?p=layout&q=delete');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');
        Theme::Set('form_fields', array(
            FormManager::AddMessage(__('Are you sure you want to delete this layout?')),
            FormManager::AddMessage(__('All media will be unassigned and any layout specific media such as text/rss will be lost. The layout will be removed from all Schedules.')),
            ));

        $form = Theme::RenderReturn('form_render');
        
        $response->SetFormRequestResponse($form, __('Delete Layout'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Delete') . '")');
        $response->AddButton(__('Retire'), 'XiboSwapDialog("index.php?p=layout&q=RetireForm&layoutid=' . $layoutid . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutDeleteForm").submit()');
        $response->Respond();
    }

    public function RetireForm() {
        $response = new ResponseManager();
        
        $layoutid = $this->layoutid;

        if (!$this->auth->edit)
            trigger_error(__('You do not have permissions to retire this layout'), E_USER_ERROR);
        
        Theme::Set('form_id', 'RetireForm');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');
        
        // Retire the layout
        Theme::Set('form_action', 'index.php?p=layout&q=Retire');
        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to retire this layout ?'))));

        $form = Theme::RenderReturn('form_render');
        
        $response->SetFormRequestResponse($form, __('Retire Layout'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Retire') . '")');
        $response->AddButton(__('Delete'), 'XiboSwapDialog("index.php?p=layout&q=DeleteLayoutForm&layoutid=' . $layoutid . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#RetireForm").submit()');
        $response->Respond();
    }

    /**
     * Deletes a layout record from the DB
     */
    function delete() 
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _POST, _INT, 0);

        if (!$this->auth->del)
            trigger_error(__('You do not have permissions to delete this layout'), E_USER_ERROR);

        $layoutObject = new Layout();

        if (!$layoutObject->Delete($layoutId))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Layout has been Deleted'));
        $response->Respond();
    }

    /**
     * Retires a layout 
     */
    function Retire() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _POST, _INT, 0);

        if (!$this->auth->edit)
            trigger_error(__('You do not have permission to retire this layout'), E_USER_ERROR);

        $layoutObject = new Layout();

        if (!$layoutObject->Retire($layoutId))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Layout has been Retired'));
        $response->Respond();
    }
    
    /**
     * Shows the Layout Grid
     * @return 
     */
    function LayoutGrid() 
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        
        // Filter by Name
        $name = Kit::GetParam('filter_layout', _POST, _STRING);
        setSession('layout', 'filter_layout', $name);
        
        // User ID
        $filter_userid = Kit::GetParam('filter_userid', _POST, _INT);
        setSession('layout', 'filter_userid', $filter_userid);
        
        // Show retired
        $filter_retired = Kit::GetParam('filter_retired', _POST, _INT);
        setSession('layout', 'filter_retired', $filter_retired);

        // Show filterLayoutStatusId
        $filterLayoutStatusId = Kit::GetParam('filterLayoutStatusId', _POST, _INT);
        setSession('layout', 'filterLayoutStatusId', $filterLayoutStatusId);

        // Show showDescriptionId
        $showDescriptionId = Kit::GetParam('showDescriptionId', _POST, _INT);
        setSession('layout', 'showDescriptionId', $showDescriptionId);
        
        // Show filter_showThumbnail
        $showTags = Kit::GetParam('showTags', _POST, _CHECKBOX);
        setSession('layout', 'showTags', $showTags);

        // Show filter_showThumbnail
        $showThumbnail = Kit::GetParam('showThumbnail', _POST, _CHECKBOX);
        setSession('layout', 'showThumbnail', $showThumbnail);

        // Tags list
        $filter_tags = Kit::GetParam("filter_tags", _POST, _STRING);
        setSession('layout', 'filter_tags', $filter_tags);
        
        // Pinned option?        
        setSession('layout', 'LayoutFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        
        // Get all layouts
        $layouts = $user->LayoutList(NULL, array('layout' => $name, 'userId' => $filter_userid, 'retired' => $filter_retired, 'tags' => $filter_tags, 'filterLayoutStatusId' => $filterLayoutStatusId, 'showTags' => $showTags));

        if (!is_array($layouts))
            trigger_error(__('Unable to get layouts for user'), E_USER_ERROR);

        $cols = array(
                array('name' => 'layoutid', 'title' => __('ID')),
                array('name' => 'tags', 'title' => __('Tag'), 'hidden' => ($showTags == 0), 'colClass' => 'group-word'),
                array('name' => 'layout', 'title' => __('Name')),
                array('name' => 'description', 'title' => __('Description'), 'hidden' => ($showDescriptionId == 1 || $showDescriptionId == 3)),
                array('name' => 'descriptionWithMarkdown', 'title' => __('Description'), 'hidden' => ($showDescriptionId == 2 || $showDescriptionId == 3)),
                array('name' => 'thumbnail', 'title' => __('Thumbnail'), 'hidden' => ($showThumbnail == 0)),
                array('name' => 'owner', 'title' => __('Owner')),
                array('name' => 'permissions', 'title' => __('Permissions')),
                array('name' => 'status', 'title' => __('Status'), 'icons' => true, 'iconDescription' => 'statusDescription')
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($layouts as $layout) {
            // Construct an object containing all the layouts, and pass to the theme
            $row = array();

            $row['layoutid'] = $layout['layoutid'];
            $row['layout'] = $layout['layout'];
            $row['description'] = $layout['description'];
            $row['tags'] = $layout['tags'];
            $row['owner'] = $user->getNameFromID($layout['ownerid']);
            $row['permissions'] = $this->GroupsForLayout($layout['layoutid']);

            $row['thumbnail'] = '';

            if ($showThumbnail == 1 && $layout['backgroundImageId'] != 0)
                $row['thumbnail'] = '<a class="img-replace" data-toggle="lightbox" data-type="image" data-img-src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $layout['backgroundImageId'] . '&width=100&height=100&dynamic=true&thumb=true" href="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $layout['backgroundImageId'] . '"><i class="fa fa-file-image-o"></i></a>';

            // Fix up the description
            if ($showDescriptionId == 1) {
                // Parse down for description
                $row['descriptionWithMarkdown'] = Parsedown::instance()->text($row['description']);
            }
            else if ($showDescriptionId == 2) {
                $row['description'] = strtok($row['description'], "\n");
            }

            switch ($layout['status']) {

                case 1:
                    $row['status'] = 1;
                    $row['statusDescription'] = __('This Layout is ready to play');
                    break;

                case 2:
                    $row['status'] = 2;
                    $row['statusDescription'] = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $row['status'] = 0;
                    $row['statusDescription'] = __('This Layout is invalid and should not be scheduled');
                    break;

                default:
                    $row['status'] = 0;
                    $row['statusDescription'] = __('The Status of this Layout is not known');
            }

            
            $row['layout_form_edit_url'] = 'index.php?p=layout&q=displayForm&layoutid=' . $layout['layoutid'];

            // Add some buttons for this row
            if ($layout['edit']) {
                // Design Button
                $row['buttons'][] = array(
                        'id' => 'layout_button_design',
                        'linkType' => '_self',
                        'url' => 'index.php?p=layout&modify=true&layoutid=' . $layout['layoutid'],
                        'text' => __('Design')
                    );
            }

            // Preview
            $row['buttons'][] = array(
                    'id' => 'layout_button_preview',
                    'linkType' => '_blank',
                    'url' => 'index.php?p=preview&q=render&ajax=true&layoutid=' . $layout['layoutid'],
                    'text' => __('Preview Layout')
                );

            // Schedule Now
            $row['buttons'][] = array(
                    'id' => 'layout_button_schedulenow',
                    'url' => 'index.php?p=schedule&q=ScheduleNowForm&CampaignID=' . $layout['campaignid'],
                    'text' => __('Schedule Now')
                );

            $row['buttons'][] = array('linkType' => 'divider');

            // Only proceed if we have edit permissions
            if ($layout['edit']) {

                // Edit Button
                $row['buttons'][] = array(
                        'id' => 'layout_button_edit',
                        'url' => 'index.php?p=layout&q=displayForm&modify=true&layoutid=' . $layout['layoutid'],
                        'text' => __('Edit')
                    );

                // Copy Button
                $row['buttons'][] = array(
                        'id' => 'layout_button_copy',
                        'url' => 'index.php?p=layout&q=CopyForm&layoutid=' . $layout['layoutid'] . '&oldlayout=' . urlencode($layout['layout']),
                        'text' => __('Copy')
                    );

                // Retire Button
                $row['buttons'][] = array(
                        'id' => 'layout_button_retire',
                        'url' => 'index.php?p=layout&q=RetireForm&layoutid=' . $layout['layoutid'],
                        'text' => __('Retire'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'multiselectlink', 'value' => 'index.php?p=layout&q=Retire'),
                            array('name' => 'rowtitle', 'value' => $row['layout']),
                            array('name' => 'layoutid', 'value' => $layout['layoutid'])
                        )
                    );

                // Extra buttons if have delete permissions
                if ($layout['del']) {
                    // Copy Button
                    $row['buttons'][] = array(
                            'id' => 'layout_button_delete',
                            'url' => 'index.php?p=layout&q=DeleteLayoutForm&layoutid=' . $layout['layoutid'],
                            'text' => __('Delete'),
                            'multi-select' => true,
                            'dataAttributes' => array(
                                array('name' => 'multiselectlink', 'value' => 'index.php?p=layout&q=delete'),
                                array('name' => 'rowtitle', 'value' => $row['layout']),
                                array('name' => 'layoutid', 'value' => $layout['layoutid'])
                            )
                        );
                }

                $row['buttons'][] = array('linkType' => 'divider');

                // Export Button
                $row['buttons'][] = array(
                        'id' => 'layout_button_export',
                        'linkType' => '_self',
                        'url' => 'index.php?p=layout&q=Export&layoutid=' . $layout['layoutid'],
                        'text' => __('Export')
                    );

                // Extra buttons if we have modify permissions
                if ($layout['modifyPermissions']) {
                    // Permissions button
                    $row['buttons'][] = array(
                            'id' => 'layout_button_permissions',
                            'url' => 'index.php?p=campaign&q=PermissionsForm&CampaignID=' . $layout['campaignid'],
                            'text' => __('Permissions')
                        );  
                }
            }

            // Add the row
            $rows[] = $row;
        }

        // Store the table rows
        Theme::Set('table_rows', $rows);
        Theme::Set('gridId', Kit::GetParam('gridId', _REQUEST, _STRING));

        // Initialise the theme and capture the output
        $output = Theme::RenderReturn('table_render');
        
        $response->SetGridResponse($output);
        $response->initialSortColumn = 3;
        $response->Respond();
    }

    /**
     * Displays an Add/Edit form
     */
    function displayForm () 
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutid', _GET, _INT);

        // Get the layout
        if ($layoutId != 0) {
            $layout = $user->LayoutList(NULL, array('layoutId' => Kit::GetParam('layoutid', _GET, _INT), 'retired' => -1));

            if (count($layout) <= 0)
                trigger_error(__('Unable to find Layout'), E_USER_ERROR);

            $layout = $layout[0];
        }
        
        Theme::Set('form_id', 'LayoutForm');

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('description', __('Description'));
        
        Theme::Set('form_tabs', $tabs);
        
        $formFields = array();
        $formFields['general'][] = FormManager::AddText('layout', __('Name'), (isset($layout['layout']) ? $layout['layout'] : NULL), __('The Name of the Layout - (1 - 50 characters)'), 'n', 'required');
        $formFields['general'][] = FormManager::AddText('tags', __('Tags'), (isset($layout['tags']) ? $layout['tags'] : NULL), __('Tags for this layout - used when searching for it. Comma delimited. (1 - 250 characters)'), 't', 'maxlength="250"');
        
        $formFields['description'][] = FormManager::AddMultiText('description', __('Description'), (isset($layout['description']) ? $layout['description'] : NULL), 
            __('An optional description of the Layout. (1 - 250 characters)'), 'd', 5, 'maxlength="250"');

        if ($layoutId != 0) {
            // We are editing
            Theme::Set('form_action', 'index.php?p=layout&q=modify');
            Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutId . '">');

            $formFields['general'][] = FormManager::AddCombo(
                    'retired', 
                    __('Retired'), 
                    $layout['retired'],
                    array(array('retiredid' => '1', 'retired' => 'Yes'), array('retiredid' => '0', 'retired' => 'No')),
                    'retiredid',
                    'retired',
                    __('Retire this layout or not? It will no longer be visible in lists'), 
                    'r');
        }
        else
        {
            // We are adding
            Theme::Set('form_action', 'index.php?p=layout&q=add');

            $templates = $user->TemplateList();
            array_unshift($templates, array('layoutid' => '0', 'layout' => 'None'));

            $formFields['general'][] = FormManager::AddCombo(
                    'templateid', 
                    __('Template'), 
                    NULL,
                    $templates,
                    'layoutid',
                    'layout',
                    __('Optionally choose a template you have saved before.'), 
                    't');

            $formFields['general'][] = FormManager::AddCombo(
                    'resolutionid', 
                    __('Resolution'), 
                    NULL,
                    $user->ResolutionList(),
                    'resolutionid',
                    'resolution',
                    __('Choose the resolution this Layout should be designed for.'), 
                    'r',
                    'resolution-group');

            $response->AddFieldAction('templateid', 'change', 0, array('.resolution-group' => array('display' => 'block')));
            $response->AddFieldAction('templateid', 'change', 0, array('.resolution-group' => array('display' => 'none')), "not");
        }

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_description', $formFields['description']);

        // Initialise the template and capture the output
        $form = Theme::RenderReturn('form_render');

        $dialogTitle = ($this->layoutid == 0) ? __('Add Layout') : __('Edit Layout');

        $response->SetFormRequestResponse($form, $dialogTitle, '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . (($this->layoutid != '') ? HelpManager::Link('Layout', 'Edit') : HelpManager::Link('Layout', 'Add')) . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#LayoutForm").submit()');
        $response->Respond();
    }
    
    /**
     * Generates a form for the background edit
     */
    function BackgroundForm() 
    {
        $db         =& $this->db;
        $user       =& $this->user;

        $response   = new ResponseManager();

        // Permission to retire?
        if (!$this->auth->edit)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);

        // Load the XML into a SimpleXML OBJECT
        $xml                = simplexml_load_string($this->xml);

        $backgroundImage    = (string) $xml['background'];
        $backgroundColor    = (string) $xml['bgcolor'];
        $width              = (string) $xml['width'];
        $height             = (string) $xml['height'];
        $resolutionid = (int)$xml['resolutionid'];
        $zindex = (int)$xml['zindex'];
        $bgImageId          = 0;

        // Do we need to override the background with one passed in?
        $bgOveride          = Kit::GetParam('backgroundOveride', _GET, _STRING);

        if ($bgOveride != '')
            $backgroundImage = $bgOveride;
        
        // Manipulate the images slightly
        if ($backgroundImage != '')
        {
            // Get the ID for the background image
            $bgImageInfo = explode('.', $backgroundImage);
            $bgImageId = $bgImageInfo[0];

            $thumbBgImage = "index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=$bgImageId&width=200&height=200&dynamic";
        }
        else
        {
            $thumbBgImage = "theme/default/img/forms/filenotfound.gif";
        }

        // Configure some template variables.
        Theme::Set('form_id', 'LayoutBackgroundForm');
        Theme::Set('form_action', 'index.php?p=layout&q=EditBackground');
        Theme::Set('form_meta', '<input type="hidden" id="layoutid" name="layoutid" value="' . $this->layoutid . '">');
        
        // Get the ID of the current resolution
        if ($resolutionid == 0) {
            $SQL = sprintf("SELECT resolutionID FROM resolution WHERE width = %d AND height = %d", $width, $height);
            
            if (!$resolutionid = $db->GetSingleValue($SQL, 'resolutionID', _INT)) 
            {
                trigger_error($db->error());
                trigger_error(__("Unable to get the Resolution information"), E_USER_ERROR);
            }
        }
        
        // Begin the form output
        $formFields = array();

        // A list of web safe colours
        $formFields[] = FormManager::AddText('bg_color', __('Background Colour'), $backgroundColor, 
            __('Use the colour picker to select the background colour'), 'c', 'required');

        // A list of available backgrounds
        $backgrounds = $user->MediaList(NULL, array('type' => 'image'));
        array_unshift($backgrounds, array('mediaid' => '0', 'media' => 'None'));

        $formFields[] = FormManager::AddCombo(
                    'bg_image', 
                    __('Background Image'), 
                    $bgImageId,
                    $backgrounds,
                    'mediaid',
                    'media',
                    __('Select the background image from the library'), 
                    'b', '', true, 'onchange="background_button_callback()"');

        $formFields[] = FormManager::AddCombo(
                    'resolutionid', 
                    __('Resolution'), 
                    $resolutionid,
                    $user->ResolutionList(NULL, array('withCurrent' => $resolutionid)),
                    'resolutionid',
                    'resolution',
                    __('Change the resolution'), 
                    'r');

        $formFields[] = FormManager::AddNumber('zindex', __('Layer'), ($zindex == 0) ? '' : $zindex, 
            __('The layering order of this region (z-index). Advanced use only. '), 'z');

        Theme::Set('append', '<img id="bg_image_image" src="' . $thumbBgImage . '" alt="' . __('Background thumbnail') . '" />');
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Change the Background Properties'), '550px', '240px');
        $response->callBack = 'backGroundFormSetup';
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Background') . '")');
        $response->AddButton(__('Add Image'), 'XiboFormRender("index.php?p=module&q=Exec&mod=image&method=AddForm&backgroundImage=true&layoutid=' . $this->layoutid . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#LayoutBackgroundForm").submit()');
        $response->Respond();
    }
    
    /**
     * Edits the background of the layout
     */
    function EditBackground()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db             =& $this->db;
        $user           =& $this->user;
        $response       = new ResponseManager();

        $layoutid       = Kit::GetParam('layoutid', _POST, _INT);
        $bg_color       = Kit::GetParam('bg_color', _POST, _STRING);
        $mediaID        = Kit::GetParam('bg_image', _POST, _INT);
        $resolutionid   = Kit::GetParam('resolutionid', _POST, _INT);
        $zindex   = Kit::GetParam('zindex', _POST, _INT);

        // Permission to retire?
        if (!$this->auth->edit)
            trigger_error(__('You do not have permissions to edit this layout'), E_USER_ERROR);

        $layoutObject = new Layout($db);

        if (!$layoutObject->SetBackground($layoutid, $resolutionid, $bg_color, $mediaID, $zindex))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);
        
        $response->SetFormSubmitResponse(__('Layout Background Changed'), true, sprintf("index.php?p=layout&layoutid=%d&modify=true", $layoutid));
        $response->Respond();
    }
    
    /**
     * Render the designer
     */
    function RenderDesigner() 
    {
        $db =& $this->db;

        // What zoom level are we at?
        $zoom = Kit::GetParam('zoom', _GET, _DOUBLE, 1);
        
        // Assume we have the xml in memory already
        // Make a DOM from the XML
        $xml = new DOMDocument();
        $xml->loadXML($this->xml);
        
        // get the width and the height
        $resolutionid = (int)$xml->documentElement->getAttribute('resolutionid');
        $width  = $xml->documentElement->getAttribute('width');
        $height = $xml->documentElement->getAttribute('height');
        $version = (int)$xml->documentElement->getAttribute('schemaVersion');
        Theme::Set('layoutVersion', $version);

        // Get the display width / height
        if ($resolutionid != 0) {
            $SQL = sprintf("SELECT intended_width, intended_height, width, height, version FROM `resolution` WHERE resolutionid = %d", $resolutionid);
        }
        else {
            $SQL = sprintf("SELECT intended_width, intended_height, width, height, version FROM `resolution` WHERE width = %d AND height = %d", $width, $height);
        }

        if (!$resolution = $db->GetSingleRow($SQL)) {
            trigger_error(__('Unable to determine display resolution'));

            $designerScale = 1;
            $tipScale = 1;
            Theme::Set('layout_upgrade_url', 'index.php?p=layout&q=upgradeForm&layoutId=' . $this->layoutid);
        }
        else {
            // Version 1 layouts had the designer resolution in the XLF and therefore did not need anything scaling in the designer.
            // Version 2 layouts have the layout resolution in the XLF and therefore need to be scaled back by the designer.

            $tipScale = ($version == 1) ? min($resolution['intended_width'] / $resolution['width'], $resolution['intended_height'] / $resolution['height']) : 1;
            $designerScale = ($version == 1) ? 1 : min($resolution['width'] / $resolution['intended_width'], $resolution['height'] / $resolution['intended_height']);

            // To do - version 2 layout can support zooming?
            if ($version > 1) {
                $designerScale = $designerScale * $zoom;

                Theme::Set('layout_zoom_in_url', 'index.php?p=layout&modify=true&layoutid=' . $this->layoutid . '&zoom=' . ($zoom - 0.3));
                Theme::Set('layout_zoom_out_url', 'index.php?p=layout&modify=true&layoutid=' . $this->layoutid . '&zoom=' . ($zoom + 0.3));
            }
            else {
                Theme::Set('layout_upgrade_url', 'index.php?p=layout&q=upgradeForm&layoutId=' . $this->layoutid);
            }
        }

        // Pass the designer scale to the theme (we use this to present an error message in the default theme, if the scale drops below 0.41)
        Theme::Set('designerScale', $designerScale);
        
        // do we have a background? Or a background color (or both)
        $bgImage = $xml->documentElement->getAttribute('background');
        $bgColor = $xml->documentElement->getAttribute('bgcolor');

        // Library location
        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");
        
        $width  = ($width * $designerScale) . "px";
        $height = ($height * $designerScale) . "px";
        
        // Fix up the background css
        if ($bgImage == '')
        {
            $background_css = $bgColor;
        }
        else
        {
            // Get the ID for the background image
            $bgImageInfo = explode('.', $bgImage);
            $bgImageId = $bgImageInfo[0];

            $background_css = "url('index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=$bgImageId&width=$width&height=$height&dynamic&proportional=0') top center no-repeat; background-color:$bgColor";
        }
        
        // Get all the regions and draw them on
        $regionHtml     = "";
        $regionNodeList = $xml->getElementsByTagName('region');

        //get the regions
        foreach ($regionNodeList as $region)
        {
            // get dimensions
            $tipWidth       = round($region->getAttribute('width') * $tipScale, 0);
            $tipHeight      = round($region->getAttribute('height') * $tipScale, 0);
            $tipLeft        = round($region->getAttribute('left') * $tipScale, 0);
            $tipTop         = round($region->getAttribute('top') * $tipScale, 0);

            $regionWidth    = ($region->getAttribute('width') * $designerScale) . "px";
            $regionHeight   = ($region->getAttribute('height') * $designerScale) . "px";
            $regionLeft = ($region->getAttribute('left') * $designerScale) . "px";
            $regionTop  = ($region->getAttribute('top') * $designerScale) . "px";
            $regionid   = $region->getAttribute('id');
            $regionZindex = ($region->getAttribute('zindex') == '') ? '' : 'zindex="' . $region->getAttribute('zindex') . '"';
            $styleZindex = ($region->getAttribute('zindex') == '') ? '' : 'z-index: ' . $region->getAttribute('zindex') . ';';
            $ownerId = $region->getAttribute('userId');

            $regionAuth = $this->user->RegionAssignmentAuth($ownerId, $this->layoutid, $regionid, true);

            $paddingTop = $regionHeight / 2 - 16;
            $paddingTop = $paddingTop . "px";

            $regionAuthTransparency = ($regionAuth->edit) ? '' : ' regionDisabled';
            $regionDisabledClass = ($regionAuth->edit) ? 'region' : 'regionDis';
            $regionPreviewClass = ($regionAuth->view) ? 'regionPreview' : '';

            $regionTransparency  = '<div class="regionTransparency ' . $regionAuthTransparency . '" style="width:100%; height:100%;"></div>';
            $doubleClickLink = ($regionAuth->edit) ? "XiboFormRender($(this).attr('href'))" : '';

            $regionHtml .= "<div id='region_$regionid' regionEnabled='$regionAuth->edit' regionid='$regionid' layoutid='$this->layoutid' $regionZindex tip_scale='$tipScale' designer_scale='$designerScale' width='$regionWidth' height='$regionHeight' href='index.php?p=timeline&layoutid=$this->layoutid&regionid=$regionid&q=Timeline' ondblclick=\"$doubleClickLink\"' class='$regionDisabledClass $regionPreviewClass' style=\"position:absolute; width:$regionWidth; height:$regionHeight; top: $regionTop; left: $regionLeft; $styleZindex\">
                      $regionTransparency";

            if ($regionAuth->edit) {

                $regionHtml .= '<div class="btn-group regionInfo pull-right">';
                $regionHtml .= '    <button class="btn dropdown-toggle" data-toggle="dropdown">';
                $regionHtml .= '<span class="region-tip">' . $tipWidth . ' x ' . $tipHeight . ' (' . $tipLeft . ',' . $tipTop . ')' . '</span>';
                $regionHtml .= '        <span class="caret"></span>';
                $regionHtml .= '    </button>';
                $regionHtml .= '    <ul class="dropdown-menu">';
                $regionHtml .= '        <li><a class="XiboFormButton" href="index.php?p=timeline&q=Timeline&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Timeline') . '">' . __('Edit Timeline') . '</a></li>';
                $regionHtml .= '        <li><a class="RegionOptionsMenuItem" href="#" title="' . __('Options') . '">' . __('Options') . '</a></li>';
                $regionHtml .= '        <li><a class="XiboFormButton" href="index.php?p=timeline&q=DeleteRegionForm&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Delete') . '">' . __('Delete') . '</a></li>';
                $regionHtml .= '        <li><a class="XiboFormButton" href="index.php?p=timeline&q=RegionPermissionsForm&layoutid=' . $this->layoutid . '&regionid=' . $regionid . '" title="' . __('Permissions') . '">' . __('Permissions') . '</a></li>';
                $regionHtml .= '    </ul>';
                $regionHtml .= '</div>';
                
            }
            else if ($regionAuth->view)
            {
                $regionHtml .= '<div class="regionInfo">';
                $regionHtml .= '<span class="region-tip">' . $tipWidth . ' x ' . $tipHeight . ' (' . $tipLeft . ',' . $tipTop . ')' . '</span>';
                $regionHtml .= '</div>';
            }

            $regionHtml .= '    <div class="preview">';
            $regionHtml .= '        <div class="previewContent"></div>';
            $regionHtml .= '        <div class="previewNav label label-info"></div>';
            $regionHtml .= '    </div>';
            $regionHtml .= '</div>';
        }
        
        //render the view pane
        $surface = <<<HTML

        <div id="layout" zoom="$zoom" tip_scale="$tipScale" designer_scale="$designerScale" version="$version" class="layout" layoutid="$this->layoutid" data-background-color="$bgColor" style="position:relative; width:$width; height:$height; background:$background_css;">
        $regionHtml
        </div>
HTML;
        
        return $surface;
    }

    /**
     * Copy layout form
     */
    public function CopyForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $layoutid = Kit::GetParam('layoutid', _REQUEST, _INT);

        $layout = $user->LayoutList(NULL, array('layoutId' => $layoutid));

        $copyMediaChecked = (Config::GetSetting('LAYOUT_COPY_MEDIA_CHECKB') == 'Checked') ? 1 : 0;

        Theme::Set('form_id', 'LayoutCopyForm');
        Theme::Set('form_action', 'index.php?p=layout&q=Copy');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '">');

        $formFields = array();
        $formFields[] = FormManager::AddText('layout', __('Name'), $layout[0]['layout'] . ' 2', __('The Name of the Layout - (1 - 50 characters)'), 'n', 'required');
        $formFields[] = FormManager::AddText('description', __('Description'), $layout[0]['description'], __('An optional description of the Layout. (1 - 250 characters)'), 'd', 'maxlength="250"');
        $formFields[] = FormManager::AddCheckbox('copyMediaFiles', __('Make new copies of all media on this layout?'), $copyMediaChecked, 
            __('This will duplicate all media that is currently assigned to the Layout being copied.'), 'c');

        Theme::Set('form_fields', $formFields);

        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Copy a Layout.'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Layout', 'Copy') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Copy'), '$("#LayoutCopyForm").submit()');
        $response->Respond();
    }

    /**
     * Copys a layout
     */
    public function Copy()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $layoutid = Kit::GetParam('layoutid', _POST, _INT);
        $layout = Kit::GetParam('layout', _POST, _STRING);
        $description = Kit::GetParam('description', _POST, _STRING);
        $copyMedia = Kit::GetParam('copyMediaFiles', _POST, _CHECKBOX);

        Kit::ClassLoader('Layout');

        $layoutObject = new Layout($db);

        if (!$layoutObject->Copy($layoutid, $layout, $description, $user->userid, (bool)$copyMedia))
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Layout Copied'));
        $response->Respond();
    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForLayout($layoutId)
    {
        $db =& $this->db;

        Kit::ClassLoader('campaign');
        $campaign = new Campaign($db);
        $campaignId = $campaign->GetCampaignId($layoutId);

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
            trigger_error(__('Unable to get group information for layout'), E_USER_ERROR);
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

    public function LayoutStatus() {

        $db =& $this->db;
        $response = new ResponseManager();
        $layoutId = Kit::GetParam('layoutId', _GET, _INT);

        Kit::ClassLoader('Layout');
        $layout = new Layout($db);

        $status = "";

        switch ($layout->IsValid($layoutId)) {

            case 1:
                $status = '<span title="' . __('This Layout is ready to play') . '" class="glyphicon glyphicon-ok-circle"></span>';
                break;

            case 2:
                $status = '<span title="' . __('There are items on this Layout that can only be assessed by the client') . '" class="glyphicon glyphicon-question-sign"></span>';
                break;

            case 3:
                $status = '<span title="' . __('This Layout is invalid and should not be scheduled') . '" class="glyphicon glyphicon-remove-sign"></span>';
                break;

            default:
                $status = '<span title="' . __('The Status of this Layout is not known') . '" class="glyphicon glyphicon-warning-sign"></span>';
        }

        // Keep things tidy
        // Maintenance should also do this.
        Media::removeExpiredFiles();

        $response->html = $status;
        $response->success = true;
        $response->Respond();
    }

    public function Export() {
        
        $layoutId = Kit::GetParam('layoutid', _REQUEST, _INT);

        Kit::ClassLoader('layout');
        $layout = new Layout($this->db);

        if (!$layout->Export($layoutId)) {
            trigger_error($layout->GetErrorMessage(), E_USER_ERROR);
        }

        exit;
    }

    public function ImportForm() {
        global $session;
        $db =& $this->db;
        $response = new ResponseManager();
        
        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();

        $session->setSecurityToken($securityToken);

        // Find the max file size
        $maxFileSizeBytes = convertBytes(ini_get('upload_max_filesize'));

         // Set some information about the form
        Theme::Set('form_id', 'LayoutImportForm');
        Theme::Set('form_action', 'index.php?p=layout&q=Import');
        Theme::Set('form_meta', '<input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" /><input type="hidden" name="template" value="' . Kit::GetParam('template', _GET, _STRING, 'false') . '" />');

        Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
        Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $maxFileSizeBytes . '" />');

        Theme::Set('prepend', Theme::RenderReturn('form_file_upload_single'));

        $formFields = array();
        $formFields[] = FormManager::AddText('layout', __('Name'), NULL, __('The Name of the Layout - (1 - 50 characters). Leave blank to use the name from the import.'), 'n');
        $formFields[] = FormManager::AddCheckbox('replaceExisting', __('Replace Existing Media?'), 
            NULL, 
            __('If the import finds existing media with the same name, should it be replaced in the Layout or should the Layout use that media.'), 
            'r');

        if (Kit::GetParam('template', _GET, _STRING, 'false') != 'true')
            $formFields[] = FormManager::AddCheckbox('importTags', __('Import Tags?'), 
                NULL, 
                __('Would you like to import any tags contained on the layout.'), 
                't');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Import Layout'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'ImportCsv') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#LayoutImportForm").submit()');
        $response->Respond();
    }

    public function Import() {

        $db =& $this->db;
        $response = new ResponseManager();

        // What are we importing?
        $template = Kit::GetParam('template', _POST, _STRING, 'false');
        $template = ($template == 'true');
        
        $layout = Kit::GetParam('layout', _POST, _STRING);
        $replaceExisting = Kit::GetParam('replaceExisting', _POST, _CHECKBOX);
        $importTags = Kit::GetParam('importTags', _POST, _CHECKBOX, (!$template));
        
        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);

        if ($tmpName == '')
            trigger_error(__('Please ensure you have picked a file and it has finished uploading'), E_USER_ERROR);

        // File name and extension (orignial name)
        $fileName = Kit::GetParam('txtFileName', _POST, _STRING);
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // File upload directory.. get this from the settings object
        $fileLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $tmpName;

        Kit::ClassLoader('layout');
        $layoutObject = new Layout($this->db);

        if (!$layoutObject->Import($fileLocation, $layout, $this->user->userid, $template, $replaceExisting, $importTags)) {
            trigger_error($layoutObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Layout Imported'));
        $response->Respond();
    }
}
?>
