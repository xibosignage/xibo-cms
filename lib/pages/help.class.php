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

class helpDAO extends baseDAO {
    private $helpLink;

    /**
     * No display page functionaility
     * @return
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="help"><input type="hidden" name="q" value="Grid">');
        Theme::Set('pager', ResponseManager::Pager($id));

        // Call to render the template
        Theme::Set('header_text', __('Help Links'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    function actionMenu() {

        return array(
                array('title' => __('Add Help Link'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=help&q=AddForm',
                    'help' => __('Add a new Help page'),
                    'onclick' => ''
                    )
            );
    }

    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        //display the display table
        $SQL = <<<SQL
        SELECT HelpID, Topic, Category, Link
          FROM `help`
        ORDER BY Topic, Category
SQL;

        // Load results into an array
        $helplinks = $db->GetArray($SQL);

        if (!is_array($helplinks)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting list of helplinks'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'topic', 'title' => __('Topic')),
                array('name' => 'category', 'title' => __('Category')),
                array('name' => 'link', 'title' => __('Link'))
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach ($helplinks as $row) {

            $row['helpid'] = Kit::ValidateParam($row['HelpID'], _INT);
            $row['topic'] = Kit::ValidateParam($row['Topic'], _STRING);
            $row['category'] = Kit::ValidateParam($row['Category'], _STRING);
            $row['link'] = Kit::ValidateParam($row['Link'], _STRING);

            $row['buttons'] = array();

            // we only want to show certain buttons, depending on the user logged in
            if ($user->usertypeid == 1) {
                
                // Edit        
                $row['buttons'][] = array(
                        'id' => 'help_button_edit',
                        'url' => 'index.php?p=help&q=EditForm&HelpID=' . $row['helpid'],
                        'text' => __('Edit')
                    );

                // Delete        
                $row['buttons'][] = array(
                        'id' => 'help_button_delete',
                        'url' => 'index.php?p=help&q=DeleteForm&HelpID=' . $row['helpid'],
                        'text' => __('Delete')
                    );

                // Test
                $row['buttons'][] = array(
                        'id' => 'help_button_test',
                        'url' => HelpManager::Link($row['topic'], $row['category']),
                        'text' => __('Test')
                    );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function AddForm()
    {
        $response = new ResponseManager();
        
        // Set some information about the form
        Theme::Set('form_id', 'HelpAddForm');
        Theme::Set('form_action', 'index.php?p=help&q=Add');

        $formFields = array();
        $formFields[] = FormManager::AddText('Topic', __('Topic'), NULL, 
            __('The Topic for this Help Link'), 't', 'maxlength="254" required');

        $formFields[] = FormManager::AddText('Category', __('Category'), NULL, 
            __('The Category for this Help Link'), 'c', 'maxlength="254" required');

        $formFields[] = FormManager::AddText('Link', __('Link'), NULL, 
            __('The Link to open for this help topic and category'), 'c', 'maxlength="254" required');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Help Link'), '350px', '325px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#HelpAddForm").submit()');
        $response->Respond();
    }

    /**
     * Help Edit form
     */
    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $helpId	= Kit::GetParam('HelpID', _REQUEST, _INT);

        // Pull the currently known info from the DB
        $SQL = "SELECT HelpID, Topic, Category, Link FROM `help` WHERE HelpID = %d ";
        $SQL = sprintf($SQL, $helpId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Help Link'));
        }

        // Set some information about the form
        Theme::Set('form_id', 'HelpEditForm');
        Theme::Set('form_action', 'index.php?p=help&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="HelpID" value="' . $helpId . '" />');

        $formFields = array();
        $formFields[] = FormManager::AddText('Topic', __('Topic'), Kit::ValidateParam($row['Topic'], _STRING), 
            __('The Topic for this Help Link'), 't', 'maxlength="254" required');

        $formFields[] = FormManager::AddText('Category', __('Category'), Kit::ValidateParam($row['Category'], _STRING), 
            __('The Category for this Help Link'), 'c', 'maxlength="254" required');

        $formFields[] = FormManager::AddText('Link', __('Link'), Kit::ValidateParam($row['Link'], _STRING), 
            __('The Link to open for this help topic and category'), 'c', 'maxlength="254" required');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Help Link'), '350px', '325px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#HelpEditForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Help Link Form
     */
    public function DeleteForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpId	= Kit::GetParam('HelpID', _REQUEST, _INT);

        // Set some information about the form
        Theme::Set('form_id', 'HelpDeleteForm');
        Theme::Set('form_action', 'index.php?p=help&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="HelpID" value="' . $helpId . '" />');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(NULL, __('Delete Help Link'), '350px', '175px');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#HelpDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Adds a help link
     */
    public function Add()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $topic = Kit::GetParam('Topic', _POST, _STRING);
        $category = Kit::GetParam('Category', _POST, _STRING);
        $link = Kit::GetParam('Link', _POST, _STRING);

        // Deal with the Edit
        Kit::ClassLoader('help');
        $helpObject = new Help($db);

        if (!$helpObject->Add($topic, $category, $link))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Added'), false);
        $response->Respond();
    }

    /**
     * Edits a help link
     */
    public function Edit()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $helpId	= Kit::GetParam('HelpID', _POST, _INT);
        $topic = Kit::GetParam('Topic', _POST, _STRING);
        $category = Kit::GetParam('Category', _POST, _STRING);
        $link = Kit::GetParam('Link', _POST, _STRING);

        // Deal with the Edit
        Kit::ClassLoader('help');
        $helpObject = new Help($db);

        if (!$helpObject->Edit($helpId, $topic, $category, $link))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Edited'), false);
        $response->Respond();
    }

    public function Delete()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        $helpId	= Kit::GetParam('HelpID', _POST, _INT);

        // Deal with the Edit
        Kit::ClassLoader('help');
        $helpObject = new Help($db);

        if (!$helpObject->Delete($helpId))
            trigger_error($helpObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Help Link Deleted'), false);
        $response->Respond();
    }
}
?>
