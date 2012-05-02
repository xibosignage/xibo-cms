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
                <button class="XiboFormButton" href="index.php?p=displaygroup&q=EditForm&HelpID=$helpId"><span>$msgEdit</span></button>
                <button class="XiboFormButton" href="index.php?p=displaygroup&q=DeleteForm&HelpID=$helpId"><span>$msgDelete</span></button>
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
}
?>