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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

class datasetDAO
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
            echo __("Layouts");
            return true;
    }

    function displayPage()
    {
        require('template/pages/dataset_view.php');
    }

    public function DataSetFilter()
    {
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        <form onsubmit="return false">
				<input type="hidden" name="p" value="dataset">
				<input type="hidden" name="q" value="DataSetGrid">
                        </form>
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function DataSetGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $msgPermissions = __('Permissions');

        $output = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Owner</th>
                <th>$msgPermissions</th>
                <th>Action</th>
                </tr>
            </thead>
            <tbody>
END;

        foreach($this->user->DataSetList() as $dataSet)
        {
            $output .= '<tr>';
            $output .= '    <td>' . $dataSet['dataset'] . '</td>';
            $output .= '    <td>' . $dataSet['ownerid'] . '</td>';
            $output .= '    <td></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        $response->SetGridResponse($output);
        $response->Respond();
    }
}
?>
