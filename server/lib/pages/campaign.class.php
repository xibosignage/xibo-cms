<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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

class campaignDAO
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
        echo __("Campaign Administration");
        return true;
    }

    public function displayPage()
    {
        require("template/pages/campaign_view.php");

        return false;
    }

    /**
     * Shows the Filter form for display groups
     * @return
     */
    public function Filter()
    {
        $filterForm = <<<END
        <div class="FilterDiv" id="DisplayGroupFilter">
            <form onsubmit="return false">
                    <input type="hidden" name="p" value="campaign">
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

        $output  = '<div class="info_table"><table style="width:100%">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') .'</th>';
        $output .= '    <th>' . __('# Layouts') .'</th>';
        $output .= '    <th>' . __('Actions') .'</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';


        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }
}
?>
