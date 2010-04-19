<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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

class Media extends Data
{
    public function Add($type, $name, $duration, $fileName, $permissionId, $userId)
    {
        $db =& $this->db;

        // TODO: Validation

        // All OK to insert this record
        $SQL  = "INSERT INTO media (name, type, duration, originalFilename, permissionID, userID, retired ) ";
        $SQL .= "VALUES ('%s', '%s', '%s', '%s', %d, %d, 0) ";

        $SQL = sprintf($SQL, $db->escape_string($name), $db->
            $db->escape_string($duration), $db->escape_string($fileName), $permissionid, $userid);

        if (!$mediaId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25001, __('Could not add a display group for the new display.'));
            return false;
        }

        return $mediaId;
    }

    public function Edit()
    {
       $db =& $this->db;
    }

    public function Retire()
    {
        $db =& $this->db;
    }

    public function Delete()
    {
        $db =& $this->db;
    }
}
?>
