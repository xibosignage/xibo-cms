<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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

class resolutionDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        include_once('lib/data/resolution.data.class.php');
    }

    /**
     * Display the Resolution Page
     */
    function displayPage()
    {
        $db =& $this->db;

        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('resolution_form_add_url', 'index.php?p=resolution&q=AddForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="resolution"><input type="hidden" name="q" value="ResolutionGrid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('resolution_page');
    }

    /**
     * Resolution Grid
     */
    function ResolutionGrid()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $SQL = "SELECT * FROM resolution ORDER BY resolution";

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error('Unable to Query for resolutions.');
        }

        $rows = array();

        while($row = $db->get_assoc_row($results))
        {
            $resolutionID = Kit::ValidateParam($row['resolutionID'], _INT);
            $row['resolution'] = Kit::ValidateParam($row['resolution'], _STRING);
            $row['width'] = Kit::ValidateParam($row['width'], _INT);
            $row['height'] = Kit::ValidateParam($row['height'], _INT);
            $row['intended_width'] = Kit::ValidateParam($row['intended_width'], _INT);
            $row['intended_height'] = Kit::ValidateParam($row['intended_height'], _INT);

            // Edit Button
            $row['buttons'][] = array(
                    'id' => 'resolution_button_edit',
                    'url' => 'index.php?p=resolution&q=EditForm&resolutionid=' . $resolutionID,
                    'text' => __('Edit')
                );

            // Delete Button
            $row['buttons'][] = array(
                    'id' => 'resolution_button_delete',
                    'url' => 'index.php?p=resolution&q=DeleteForm&resolutionid=' . $resolutionID,
                    'text' => __('Delete')
                );

            // Add to the rows objects
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('resolution_page_grid');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Resolution Add
     */
    function AddForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        Theme::Set('form_id', 'AddForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Add');

        $form = Theme::RenderReturn('resolution_form_add');

        $response->SetFormRequestResponse($form, __('Add Resolution'), '350px', '250px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Resolution', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#AddForm").submit()');
        $response->Respond();
    }

    /**
     * Resolution Edit Form
     */
    function EditForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionID   = Kit::GetParam('resolutionid', _GET, _INT);

        $SQL = sprintf("SELECT resolution, width, height, intended_width, intended_height FROM resolution WHERE resolutionID = %d", $resolutionID);

        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to edit this resolution'), E_USER_ERROR);
        }

        if ($db->num_rows($result) == 0)
            trigger_error(__('Incorrect resolution id'), E_USER_ERROR);

        $row = $db->get_assoc_row($result);

        Theme::Set('resolution', Kit::ValidateParam($row['resolution'], _STRING));
        Theme::Set('width', Kit::ValidateParam($row['intended_width'], _INT));
        Theme::Set('height', Kit::ValidateParam($row['intended_height'], _INT));

        Theme::Set('form_id', 'ResolutionForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="resolutionid" value="' . $resolutionID . '" >');

        $form = Theme::RenderReturn('resolution_form_edit');

        $response->SetFormRequestResponse($form, __('Edit Resolution'), '350px', '250px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Template', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ResolutionForm").submit()');
        $response->Respond();
    }

    /**
     * Resolution Delete Form
     */
    function DeleteForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionid   = Kit::GetParam('resolutionid', _GET, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'DeleteForm');
        Theme::Set('form_action', 'index.php?p=resolution&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="resolutionid" value="' . $resolutionid . '" />');

        $form = Theme::RenderReturn('resolution_form_delete');

        $response->SetFormRequestResponse($form, __('Delete Resolution'), '250px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Campaign', 'Delete') . '")');
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
        $response = new ResponseManager();

        $resolution = Kit::GetParam('resolution', _POST, _STRING);
        $width = Kit::GetParam('width', _POST, _INT);
        $height = Kit::GetParam('height', _POST, _INT);

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
        $response = new ResponseManager();

        $resolutionID = Kit::GetParam('resolutionid', _POST, _INT);
        $resolution = Kit::GetParam('resolution', _POST, _STRING);
        $width = Kit::GetParam('width', _POST, _INT);
        $height = Kit::GetParam('height', _POST, _INT);

        // Edit the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Edit($resolutionID, $resolution, $width, $height))
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
        $response = new ResponseManager();

        $resolutionID = Kit::GetParam('resolutionid', _POST, _INT);

        // Remove the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Delete($resolutionID))
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse('Resolution deleted');
        $response->Respond();
    }
}
?>