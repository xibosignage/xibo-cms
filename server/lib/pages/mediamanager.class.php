<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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

class mediamanagerDAO 
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;
    }

    function on_page_load()
    {
        return "";
    }

    function echo_page_heading()
    {
        global $user;

        $userid = Kit::GetParam('userid', _SESSION, _INT);
        $uid 	= $user->getNameFromID($userid);

        echo "$uid's " . __('Dashboard');
        return true;
    }

    function displayPage()
    {
        $db =& $this->db;
        $user =& $this->user;

        include_once("template/pages/mediamanager.php");
    }

    public function MediaManagerFilter()
    {
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        <form onsubmit="return false">
				<input type="hidden" name="p" value="mediamanager">
				<input type="hidden" name="q" value="MediaManagerGrid">
                        </form>
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function MediaManagerGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        
        // We would like a list of all layouts, media and media assignments that this user
        // has access to.
        $mediaItems = $user->MediaAssignmentList();

        $msgLayout = __('Layout');
        $msgRegion = __('Region');
        $msgMedia = __('Media');

        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $output = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                    <th>$msgLayout</th>
                    <th>$msgRegion</th>
                    <th>$msgMedia</th>
                    <th>$msgAction</th>
                </tr>
            </thead>
            <tbody>
END;

        foreach ($mediaItems as $media)
        {
            // Every layout this user has access to.. get the region and media link
            $output .= '<tr>';
            $output .= '    <td>' . $media['layout'] . '</td>';
            $output .= '    <td></td>';
            $output .= '    <td></td>';
            $output .= '    <td></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';

        $response->SetGridResponse($output);
        $response->Respond();
    }
}
?>