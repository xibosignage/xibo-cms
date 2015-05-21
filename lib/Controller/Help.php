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
namespace Xibo\Controller;
use baseDAO;
use Xibo\Factory\HelpFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Form;
use Xibo\Helper\Theme;


class Help extends Base
{
    /**
     * Help Page
     */
    function displayPage()
    {
        $this->getState()->template = 'help-page';
    }

    public function grid()
    {
        $helpLinks = HelpFactory::query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($helpLinks as $row) {
            /* @var \Xibo\Entity\Help $row */

            // we only want to show certain buttons, depending on the user logged in
            if ($this->getUser()->userTypeId == 1) {

                // Edit
                $row->buttons[] = array(
                    'id' => 'help_button_edit',
                    'url' => 'index.php?p=help&q=EditForm&HelpID=' . $row->helpId,
                    'text' => __('Edit')
                );

                // Delete
                $row->buttons[] = array(
                    'id' => 'help_button_delete',
                    'url' => 'index.php?p=help&q=DeleteForm&HelpID=' . $row->helpId,
                    'text' => __('Delete')
                );

                // Test
                $row->buttons[] = array(
                    'id' => 'help_button_test',
                    'url' => \Xibo\Helper\Help::Link($row->topic, $row->category),
                    'text' => __('Test')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($helpLinks);
    }

    public function AddForm()
    {
        $response = $this->getState();

        // Set some information about the form
        Theme::Set('form_id', 'HelpAddForm');
        Theme::Set('form_action', 'index.php?p=help&q=Add');

        $formFields = array();
        $formFields[] = Form::AddText('Topic', __('Topic'), NULL,
            __('The Topic for this Help Link'), 't', 'maxlength="254" required');

        $formFields[] = Form::AddText('Category', __('Category'), NULL,
            __('The Category for this Help Link'), 'c', 'maxlength="254" required');

        $formFields[] = Form::AddText('Link', __('Link'), NULL,
            __('The Link to open for this help topic and category'), 'c', 'maxlength="254" required');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Help Link'), '350px', '325px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#HelpAddForm").submit()');

    }

    /**
     * Help Edit form
     */
    public function EditForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $helpId = \Xibo\Helper\Sanitize::getInt('HelpID');

        // Pull the currently known info from the DB
        $SQL = "SELECT HelpID, Topic, Category, Link FROM `help` WHERE HelpID = %d ";
        $SQL = sprintf($SQL, $helpId);

        if (!$row = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting Help Link'));
        }

        // Set some information about the form
        Theme::Set('form_id', 'HelpEditForm');
        Theme::Set('form_action', 'index.php?p=help&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="HelpID" value="' . $helpId . '" />');

        $formFields = array();
        $formFields[] = Form::AddText('Topic', __('Topic'), \Xibo\Helper\Sanitize::string($row['Topic']),
            __('The Topic for this Help Link'), 't', 'maxlength="254" required');

        $formFields[] = Form::AddText('Category', __('Category'), \Xibo\Helper\Sanitize::string($row['Category']),
            __('The Category for this Help Link'), 'c', 'maxlength="254" required');

        $formFields[] = Form::AddText('Link', __('Link'), \Xibo\Helper\Sanitize::string($row['Link']),
            __('The Link to open for this help topic and category'), 'c', 'maxlength="254" required');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Help Link'), '350px', '325px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#HelpEditForm").submit()');

    }

    /**
     * Delete Help Link Form
     */
    public function DeleteForm()
    {

        $response = $this->getState();
        $helpId = \Xibo\Helper\Sanitize::getInt('HelpID');

        // Set some information about the form
        Theme::Set('form_id', 'HelpDeleteForm');
        Theme::Set('form_action', 'index.php?p=help&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="HelpID" value="' . $helpId . '" />');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(NULL, __('Delete Help Link'), '350px', '175px');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#HelpDeleteForm").submit()');

    }

    /**
     * Adds a help link
     */
    public function Add()
    {


        $response = $this->getState();

        $topic = \Xibo\Helper\Sanitize::getString('Topic');
        $category = \Xibo\Helper\Sanitize::getString('Category');
        $link = \Xibo\Helper\Sanitize::getString('Link');

        // Deal with the Edit

        $helpObject = new Help($db);

        if (!$helpObject->Add($topic, $category, $link))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Added'), false);

    }

    /**
     * Edits a help link
     */
    public function Edit()
    {


        $response = $this->getState();

        $helpId = \Xibo\Helper\Sanitize::getInt('HelpID');
        $topic = \Xibo\Helper\Sanitize::getString('Topic');
        $category = \Xibo\Helper\Sanitize::getString('Category');
        $link = \Xibo\Helper\Sanitize::getString('Link');

        // Deal with the Edit

        $helpObject = new Help($db);

        if (!$helpObject->Edit($helpId, $topic, $category, $link))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Edited'), false);

    }

    public function Delete()
    {


        $response = $this->getState();

        $helpId = \Xibo\Helper\Sanitize::getInt('HelpID');

        // Deal with the Edit

        $helpObject = new Help($db);

        if (!$helpObject->Delete($helpId))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Deleted'), false);

    }
}

?>
