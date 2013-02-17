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

class Resolution extends Data
{
    /**
     * Adds a resolution
     * @param string $resolution
     * @param int $width
     * @param int $height
     * @return <type>
     */
    public function Add($resolution, $width, $height)
    {
        $db =& $this->db;

        if ($resolution == '' || $width == '' || $height == '')
            return $this->SetError(__('All fields must be filled in'), E_USER_ERROR);

        // Alter the width / height to fit with 800 px
        $factor = min (800 / $width, 800 / $height);

        $final_width    = round ($width * $factor);
        $final_height   = round ($height * $factor);
        
        $SQL = "INSERT INTO resolution (resolution, width, height, intended_width, intended_height) VALUES ('%s', %d, %d, %d, %d)";
        $SQL = sprintf($SQL, $db->escape_string($resolution), $final_width, $final_height, $width, $height);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25000, __('Cannot add this resolution.'));
        }

        return true;
    }

    /**
     * Edits a resolution
     * @param <type> $resolutionID
     * @param <type> $resolution
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Edit($resolutionID, $resolution, $width, $height)
    {
        $db =& $this->db;

        if ($resolution == '' || $width == '' || $height == '')
            return $this->SetError(__('All fields must be filled in'), E_USER_ERROR);

        // Alter the width / height to fit with 800 px
        $factor = min (800 / $width, 800 / $height);

        $final_width    = round ($width * $factor);
        $final_height   = round ($height * $factor);

        $SQL = "UPDATE resolution SET resolution = '%s', width = %d, height = %d, intended_width = %d, intended_height = %d WHERE resolutionID = %d ";
        $SQL = sprintf($SQL, $db->escape_string($resolution), $final_width, $final_height, $width, $height, $resolutionID);

        if(!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25000, __('Cannot edit this resolution.'));
        }

        return true;
    }

    /**
     * Deletes a Resolution
     * @param <type> $resolutionID
     * @return <type>
     */
    public function Delete($resolutionID)
    {
        $db =& $this->db;

        $SQL = "DELETE FROM resolution WHERE resolutionID = %d";
        $SQL = sprintf($SQL, $resolutionID);

        if(!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, __('Cannot delete this resolution.'));

            return false;
        }

        return true;
    }
}
?>