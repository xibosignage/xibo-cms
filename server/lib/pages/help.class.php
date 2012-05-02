<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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

class helpDAO
{
    private $db;
    private $user;
    private $helpLink;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        return true;
    }

    /**
     * Displays the particular help subject / page
     * @return
     */
    function Display()
    {
        $db =& $this->db;
        $user =& $this->user;

        $response	= new ResponseManager();
        $width          = 1000;
        $height         = 650;

        $topic	 	= Kit::GetParam('Topic', _REQUEST, _WORD);
        $category 	= Kit::GetParam('Category', _REQUEST, _WORD, 'General');

        if ($topic != '')
        {
            Debug::LogEntry($db, 'audit', 'Help requested for Topic = ' . $topic);

            // Look up this help topic / category in the db
            $SQL = "SELECT Link FROM help WHERE Topic = '%s' and Category = '%s'";
            $SQL = sprintf($SQL, $db->escape_string($topic), $db->escape_string($category));

            Debug::LogEntry($db, 'audit', $SQL);

            if(!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                trigger_error(__('Error getting Help Link'), E_USER_ERROR);
            }

            if ($db->num_rows($results) != 0)
            {
                $row 	= $db->get_row($results);
                $link 	= $row[0];

                // Store the link for the requested help page
                $this->helpLink = $link;
            }
            else
            {
                trigger_error(sprintf(__('No help file found for Topic %s and Category %s.'), $topic, $category), E_USER_ERROR);
            }
        }
        else
        {
            trigger_error(__('You must specify a help page.'), E_USER_ERROR);
        }

        $helpLink 	= $this->helpLink;
        $out 		= '<iframe src="' . $helpLink . '" width="' . ($width - 35) . '" height="' . ($height - 60) . '"></iframe>';

        $response->SetFormRequestResponse($out, __('Help'), $width, $height);
        $response->Respond();

        return true;
    }

    public function Filter()
    {
        $filterForm = <<<END
        <div class="FilterDiv" id="HelpFilter">
                <form onsubmit="return false">
                        <input type="hidden" name="p" value="help">
                        <input type="hidden" name="q" value="Grid">
                </form>
        </div>
END;

        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        $filterForm
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
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

        if(!($results = $db->query($SQL)))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to list Help topics'), E_USER_ERROR);
        }

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgHelpTopic = __('Topic');
        $msgHelpCategory = __('Category');
        $msgHelpLink = __('Link');

        $output = <<<END
        <div class="info_table">
            <table style="width:100%">
            <thead>
                <tr>
                    <th>$msgHelpTopic</th>
                    <th>$msgHelpCategory</th>
                    <th>$msgHelpLink</th>
                    <th>$msgAction</th>
                </tr>
            </thead>
            <tbody>
END;

        while($row = $db->get_assoc_row($results))
        {
            $helpId = Kit::ValidateParam($row['HelpID'], _INT);
            $topic = Kit::ValidateParam($row['Topic'], _STRING);
            $category = Kit::ValidateParam($row['Category'], _STRING);
            $link = Kit::ValidateParam($row['Link'], _STRING);

            // we only want to show certain buttons, depending on the user logged in
            if ($user->GetUserTypeID() != 1)
            {
                //dont any actions
                $buttons = __("No available Actions");
            }
            else
            {
                $buttons = <<<END
                <button class="XiboFormButton" href="index.php?p=help&q=EditForm&HelpID=$helpId"><span>$msgEdit</span></button>
                <button class="XiboFormButton" href="index.php?p=help&q=DeleteForm&HelpID=$helpId"><span>$msgDelete</span></button>
END;
            }

            $output .= <<<END
            <tr>
                <td>$topic</td>
                <td>$category</td>
                <td>$link</td>
                <td>$buttons</td>
            </tr>
END;
        }

        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * No display page functionaility
     * @return
     */
    function displayPage()
    {
        require("template/pages/help_view.php");

            return false;
    }

    /**
     * No onload
     * @return
     */
    function on_page_load()
    {
            return '';
    }

    /**
     * No page heading
     * @return
     */
    function echo_page_heading()
    {
            return true;
    }

    public function AddForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Help UI
        $iconHelpTopic = $helpManager->HelpIcon(__('The Topic for this Help Link'), true);
        $iconHelpCategory = $helpManager->HelpIcon(__('The Category for this Help Link'), true);
        $iconHelpLink = $helpManager->HelpIcon(__('The Link to open for this help topic and category'), true);

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgHelpTopic = __('Topic');
        $msgHelpCategory = __('Category');
        $msgHelpLink = __('Link');

        $form = <<<END
        <form id="HelpAddForm" class="XiboForm" action="index.php?p=help&q=Add" method="post">
            <table>
                <tr>
                    <td>$msgHelpTopic</td>
                    <td>$iconHelpTopic <input class="required" type="text" name="Topic" maxlength="254"></td>
                </tr>
                <tr>
                    <td>$msgHelpCategory</span></td>
                    <td>$iconHelpCategory <input class="required" type="text" name="Category" maxlength="254"></td>
                </tr>
                <tr>
                    <td>$msgHelpLink</span></td>
                    <td>$iconHelpLink <input class="required" type="text" name="Link" maxlength="254"></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Add Help Link'), '350px', '325px');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#HelpAddForm").submit()');
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
        $helpManager = new HelpManager($db, $user);

        $helpId	= Kit::GetParam('HelpID', _REQUEST, _INT);

        // Pull the currently known info from the DB
        $SQL = "SELECT HelpID, Topic, Category, Link FROM `help` WHERE HelpID = %d ";
        $SQL = sprintf($SQL, $helpId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Help Link'));
        }

        $topic = Kit::ValidateParam($row['Topic'], _STRING);
        $category = Kit::ValidateParam($row['Category'], _STRING);
        $link = Kit::ValidateParam($row['Link'], _STRING);

        // Help UI
        $iconHelpTopic = $helpManager->HelpIcon(__('The Topic for this Help Link'), true);
        $iconHelpCategory = $helpManager->HelpIcon(__('The Category for this Help Link'), true);
        $iconHelpLink = $helpManager->HelpIcon(__('The Link to open for this help topic and category'), true);

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgHelpTopic = __('Topic');
        $msgHelpCategory = __('Category');
        $msgHelpLink = __('Link');

        $form = <<<END
        <form id="HelpEditForm" class="XiboForm" action="index.php?p=help&q=Edit" method="post">
            <input type="hidden" name="HelpID" value="$helpId" />
            <table>
                <tr>
                    <td>$msgHelpTopic</td>
                    <td>$iconHelpTopic <input class="required" type="text" name="Topic" value="$topic" maxlength="254"></td>
                </tr>
                <tr>
                    <td>$msgHelpCategory</span></td>
                    <td>$iconHelpCategory <input class="required" type="text" name="Category" value="$category" maxlength="254"></td>
                </tr>
                <tr>
                    <td>$msgHelpLink</span></td>
                    <td>$iconHelpLink <input class="required" type="text" name="Link" value="$link" maxlength="254"></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Edit Help Link'), '350px', '325px');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#HelpEditForm").submit()');
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

        $msgWarn = __('Are you sure you want to delete?');

        //we can delete
        $form = <<<END
        <form id="HelpDeleteForm" class="XiboForm" method="post" action="index.php?p=help&q=Delete">
            <input type="hidden" name="HelpID" value="$helpId" />
            <p>$msgWarn</p>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete Help Link'), '350px', '175px');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#HelpDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Adds a help link
     */
    public function Add()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $topic = Kit::GetParam('Topic', _POST, _STRING);
        $category = Kit::GetParam('Category', _POST, _STRING);
        $link = Kit::GetParam('Link', _POST, _STRING);

        // Validation
        if ($topic == '')
            trigger_error(__('Topic is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

        if ($category == '')
            trigger_error(__('Category is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

        if ($link == '')
            trigger_error(__('Link is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

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
        $db =& $this->db;
        $response = new ResponseManager();

        $helpId	= Kit::GetParam('HelpID', _POST, _INT);
        $topic = Kit::GetParam('Topic', _POST, _STRING);
        $category = Kit::GetParam('Category', _POST, _STRING);
        $link = Kit::GetParam('Link', _POST, _STRING);

        // Validation
        if ($topic == '')
            trigger_error(__('Topic is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

        if ($category == '')
            trigger_error(__('Category is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

        if ($link == '')
            trigger_error(__('Link is a required field. It must be between 1 and 254 characters.'), E_USER_ERROR);

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