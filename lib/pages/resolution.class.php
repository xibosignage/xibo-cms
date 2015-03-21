<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
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
use Xibo\Helper\Help;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Theme;

defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

include_once('lib/data/resolution.data.class.php');

class resolutionDAO extends baseDAO
{
    /**
     * Display the Resolution Page
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="resolution"><input type="hidden" name="q" value="ResolutionGrid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));

        if (\Kit::IsFilterPinned('resolution', 'ResolutionFilter')) {
            $pinned = 1;
            $enabled = Session::Get('resolution', 'filterEnabled');
        }
        else {
            $enabled = 1;
            $pinned = 0;
        }

        $formFields = array();
        $formFields[] = FormManager::AddCombo(
            'filterEnabled',
            __('Enabled'),
            $enabled,
            array(array('enabledid' => 1, 'enabled' => 'Yes'), array('enabledid' => 0, 'enabled' => 'No')),
            'enabledid',
            'enabled',
            NULL,
            'e');

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Resolutions'));
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
            ),
            array('title' => __('Add Resolution'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=resolution&q=AddForm',
                    'help' => __('Add a new resolution for use on layouts'),
                    'onclick' => ''
                    )
            );                   
    }

    /**
     * Resolution Grid
     */
    function ResolutionGrid()
    {
        $user =& $this->user;
        $response = new ApplicationState();

        \Session::Set('resolution', 'ResolutionFilter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        // Show enabled
        $filterEnabled = \Kit::GetParam('filterEnabled', _POST, _INT);
        \Session::Set('resolution', 'filterEnabled', $filterEnabled);

        $resolutions = $user->ResolutionList(array('resolution'), array('enabled' => $filterEnabled));
        $rows = array();

        $cols = array(
                array('name' => 'resolutionid', 'title' => __('ID')),
                array('name' => 'resolution', 'title' => __('Resolution')),
                array('name' => 'intended_width', 'title' => __('Width')),
                array('name' => 'intended_height', 'title' => __('Height')),
                array('name' => 'enabled', 'title' => __('Enabled?'), 'icons' => true)
            );
        Theme::Set('table_cols', $cols);

        foreach ($resolutions as $resolution) {
            /* @var \Xibo\Entity\Resolution $resolution */
            $row = array();
            $row['resolutionid'] = $resolution->resolutionId;
            $row['resolution'] = $resolution->resolution;
            $row['intended_width'] = $resolution->width;
            $row['intended_height'] = $resolution->height;
            $row['enabled'] = ($resolution->enabled) ? 1 : 0;

            // Edit Button
            $row['buttons'][] = array(
                    'id' => 'resolution_button_edit',
                    'url' => 'index.php?p=resolution&q=EditForm&resolutionid=' . $row['resolutionid'],
                    'text' => __('Edit')
                );

            // Delete Button
            $row['buttons'][] = array(
                    'id' => 'resolution_button_delete',
                    'url' => 'index.php?p=resolution&q=DeleteForm&resolutionid=' . $row['resolutionid'],
                    'text' => __('Delete')
                );

            // Add to the rows objects
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $response->SetGridResponse(Theme::RenderReturn('table_render'));
        $response->Respond();
    }

    /**
     * Resolution Add
     */
    function AddForm()
    {
        $response = new ApplicationState();

        Theme::Set('form_id', 'AddForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Add');

        $formFields = array();
        $formFields[] = FormManager::AddText('resolution', __('Resolution'), NULL, 
            __('A name for this Resolution'), 'r', 'required');

        $formFields[] = FormManager::AddNumber('width', __('Width'), NULL, 
            __('The Width for this Resolution'), 'w', 'required');

        $formFields[] = FormManager::AddNumber('height', __('Height'), NULL, 
            __('The Height for this Resolution'), 'h', 'required');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Resolution'), '350px', '250px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Resolution', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#AddForm").submit()');
        $response->Respond();
    }

    /**
     * Resolution Edit Form
     */
    function EditForm()
    {
        $response = new ApplicationState();

        $resolution = \Xibo\Factory\ResolutionFactory::getById(Kit::GetParam('resolutionid', _GET, _INT));

        if (!$this->user->checkEditable($resolution))
            trigger_error(__('Access Denied'));

        $formFields = array();
        $formFields[] = FormManager::AddText('resolution', __('Resolution'), $resolution->resolution,
            __('A name for this Resolution'), 'r', 'required');

        $formFields[] = FormManager::AddNumber('width', __('Width'),$resolution->width,
            __('The Width for this Resolution'), 'w', 'required');

        $formFields[] = FormManager::AddNumber('height', __('Height'), $resolution->height,
            __('The Height for this Resolution'), 'h', 'required');

        $formFields[] = FormManager::AddCheckbox('enabled', __('Enable?'), $resolution->enabled,
            __('Is the Resolution enabled for use?'), 'e');
        
        Theme::Set('form_fields', $formFields);

        Theme::Set('form_id', 'ResolutionForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="resolutionid" value="' . $resolution->resolutionId . '" >');

        $response->SetFormRequestResponse(NULL, __('Edit Resolution'), '350px', '250px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Resolution', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ResolutionForm").submit()');
        $response->Respond();
    }

    /**
     * Resolution Delete Form
     */
    function DeleteForm()
    {
        $response = new ApplicationState();

        $resolution = \Xibo\Factory\ResolutionFactory::getById(Kit::GetParam('resolutionid', _GET, _INT));

        if (!$this->user->checkDeleteable($resolution))
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DeleteForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="resolutionid" value="' . $resolution->resolutionId . '" />');
        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete?'))));
        
        $response->SetFormRequestResponse(Theme::RenderReturn('form_render'), __('Delete Resolution'), '250px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Resolution', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteForm").submit()');
        $response->Respond();
    }

    function Add()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ApplicationState();

        $resolution = \Kit::GetParam('resolution', _POST, _STRING);
        $width = \Kit::GetParam('width', _POST, _INT);
        $height = \Kit::GetParam('height', _POST, _INT);

        // Add the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Add($resolution, $width, $height))
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('New resolution added');
        $response->Respond();
    }

    function Edit()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ApplicationState();

        $resolutionID = \Kit::GetParam('resolutionid', _POST, _INT);
        $resolution = \Kit::GetParam('resolution', _POST, _STRING);
        $width = \Kit::GetParam('width', _POST, _INT);
        $height = \Kit::GetParam('height', _POST, _INT);
        $enabled = \Kit::GetParam('enabled', _POST, _CHECKBOX);

        // Edit the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Edit($resolutionID, $resolution, $width, $height, $enabled))
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('Resolution edited');
        $response->Respond();
    }

    function Delete()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ApplicationState();

        $resolutionID = \Kit::GetParam('resolutionid', _POST, _INT);

        // Remove the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Delete($resolutionID))
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('Resolution deleted');
        $response->Respond();
    }
}
?>