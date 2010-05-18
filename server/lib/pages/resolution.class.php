<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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

    function displayPage()
    {
        $db =& $this->db;

        require("template/pages/resolution_view.php");
    }

    function ResolutionFilter()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $filterForm = <<<END
            <div class="FilterDiv" id="ResolutionFilter">
                <form onsubmit="return false">
                    <input type="hidden" name="p" value="resolution">
                    <input type="hidden" name="q" value="ResolutionGrid">
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

        $output = <<<END
            <div class="info_table">
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th>Resolution</th>
                            <th>Designer Width</th>
                            <th>Designer Height</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
END;

        while($row = $db->get_assoc_row($results))
        {
            $resolutionID = Kit::ValidateParam($row['resolutionID'], _INT);
            $resolution = Kit::ValidateParam($row['resolution'], _STRING);
            $width      = Kit::ValidateParam($row['width'], _INT);
            $height     = Kit::ValidateParam($row['height'], _INT);

            $output .= '<tr>';
            $output .= '<td>' . $resolution . '</td>';
            $output .= '<td>' . $width . '</td>';
            $output .= '<td>' . $height . '</td>';
            $output .= '<td>';
            $output .= '  <button class="XiboFormButton" href="index.php?p=resolution&q=EditForm&resolutionid=' . $resolutionID . '"><span>Edit</span></button>';
            $output .= '  <button class="XiboFormButton" href="index.php?p=resolution&q=DeleteForm&resolutionid=' . $resolutionID . '"><span>Delete</span></button>';
            $output .= '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';

        $response->SetGridResponse($output);
        $response->Respond();
    }

    function AddForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $form = <<<END
            <form class="XiboForm" method="post" action="index.php?p=resolution&q=Add">
                <table>
                    <tr>
                        <td><label for="resolution" title="A name for this resolution">Resolution<span class="required">*</span></label></td>
                        <td><input name="resolution" type="text" id="resolution" tabindex="1" /></td>
                    </tr>
                    <tr>
                        <td><label for="width" title="Width">Width<span class="required">*</span></label></td>
                        <td><input name="width" type="text" id="width" tabindex="2" /></td>
                    </tr>
                    <tr>
                        <td><label for="height" title="Height">Height<span class="required">*</span></label></td>
                        <td><input name="height" type="text" id="height" tabindex="3" /></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <input type="submit" value="Save" tabindex="4" />
                            <input id="btnCancel" type="button" title="No / Cancel" onclick="$('#div_dialog').dialog('close');return false; " value="Cancel" />
                        </td>
                    </tr>
                </table>
            </form>
END;

        $response->SetFormRequestResponse($form, 'Add new resolution', '350px', '250px');
        $response->Respond();
    }

    function EditForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionID   = Kit::GetParam('resolutionid', _GET, _INT);

        $SQL = sprintf("SELECT resolution, width, height FROM resolution WHERE resolutionID = %d", $resolutionID);

        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error('Unable to edit this resolution', E_USER_ERROR);
        }

        if ($db->num_rows($result) == 0)
            trigger_error('Incorrect resolution id', E_USER_ERROR);

        $row        = $db->get_assoc_row($result);

        $resolution = Kit::ValidateParam($row['resolution'], _STRING);
        $width      = Kit::ValidateParam($row['width'], _INT);
        $height     = Kit::ValidateParam($row['height'], _INT);

        $form = <<<END
            <form class="XiboForm" method="post" action="index.php?p=resolution&q=Edit">
                <input type="hidden" name="resolutionid" value="$resolutionID" />
                <table>
                    <tr>
                        <td><label for="resolution" title="A name for this resolution">Resolution<span class="required">*</span></label></td>
                        <td><input name="resolution" type="text" id="resolution" value="$resolution" tabindex="1" /></td>
                    </tr>
                    <tr>
                        <td><label for="width" title="Width">Width<span class="required">*</span></label></td>
                        <td><input name="width" type="text" id="width" tabindex="2" value="$width" /></td>
                    </tr>
                    <tr>
                        <td><label for="height" title="Height">Height<span class="required">*</span></label></td>
                        <td><input name="height" type="text" id="height" tabindex="3" value="$height" /></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <input type="submit" value="Save" tabindex="4" />
                            <input id="btnCancel" type="button" title="No / Cancel" onclick="$('#div_dialog').dialog('close');return false; " value="Cancel" />
                        </td>
                    </tr>
                </table>
            </form>
END;

        $response->SetFormRequestResponse($form, 'Edit Resolution', '350px', '250px');
        $response->Respond();
    }

    function DeleteForm()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionid   = Kit::GetParam('resolutionid', _GET, _INT);

        // Output the delete form
        $form = <<<END
        <form class="XiboForm" method="post" action="index.php?p=resolution&q=Delete">
            <input type="hidden" name="resolutionid" value="$resolutionid">
            <p>Are you sure you want to delete this resolution?</p>
            <input type="submit" value="Yes" tabindex="1">
            <input type="submit" value="No" onclick="$('#div_dialog').dialog('close');return false; ">
        </form>
END;

        $response->SetFormRequestResponse($form, 'Confirm Delete', '250px', '150px');
        $response->Respond();
    }

    function Add()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolution = Kit::GetParam('resolution', _POST, _STRING);
        $width_old  = Kit::GetParam('width', _POST, _INT);
        $height_old = Kit::GetParam('height', _POST, _INT);

        if ($resolution == '' || $width_old == '' || $height_old == '')
        {
            trigger_error('All fields must be filled in', E_USER_ERROR);
        }

        // Alter the width / height to fit with 800 px
        $width          = 800;
        $height         = 800;
        $factor         = min ( $width / $width_old, $height / $height_old);

        $final_width    = round ($width_old * $factor);
        $final_height   = round ($height_old * $factor);

        // Add the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Add($resolution, $final_width, $final_height))
        {
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse('New resolution added');
        $response->Respond();
    }

    function Edit()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionID = Kit::GetParam('resolutionid', _POST, _INT);
        $resolution = Kit::GetParam('resolution', _POST, _STRING);
        $width_old  = Kit::GetParam('width', _POST, _INT);
        $height_old = Kit::GetParam('height', _POST, _INT);

        if ($resolutionID == '' || $resolution == '' || $height_old == '' || $height_old == '')
        {
            trigger_error('All fields must be filled in', E_USER_ERROR);
        }

        // Alter the width / height to fit with 800 px
        $width          = 800;
        $height         = 800;
        $factor         = min ( $width / $width_old, $height / $height_old);

        $final_width    = round ($width_old * $factor);
        $final_height   = round ($height_old * $factor);

        // Edit the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Edit($resolutionID, $resolution, $final_width, $final_height))
        {
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse('Resolution edited');
        $response->Respond();
    }

    function Delete()
    {
        $db 	=& $this->db;
        $user 	=& $this->user;
        $response = new ResponseManager();

        $resolutionID = Kit::GetParam('resolutionid', _POST, _INT);

        // Remove the resolution
        $resObject = new Resolution($db);

        if (!$resObject->Delete($resolutionID))
        {
            trigger_error($resObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse('Resolution deleted');
        $response->Respond();
    }
}