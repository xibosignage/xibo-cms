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
     * @param <type> $resolution
     * @param <type> $width
     * @param <type> $height
     * @return <type>
     */
    public function Add($resolution, $width, $height)
    {
        $db =& $this->db;
        
        $SQL = "INSERT INTO resolution (resolution, width, height) VALUES ('%s', %d, %d)";
        $SQL = sprintf($SQL, $db->escape_string($resolution), $width, $height);

        if(!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, 'Cannot add this resolution.');

            return false;
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

        $SQL = "UPDATE resolution SET resolution = '%s', width = %d, height = %d WHERE resolutionID = %d ";
        $SQL = sprintf($SQL, $db->escape_string($resolution), $width, $height, $resolutionID);

        if(!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25000, 'Cannot edit this resolution.');

            return false;
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
            $this->SetError(25000, 'Cannot delete this resolution.');

            return false;
        }

        return true;
    }
}
?>