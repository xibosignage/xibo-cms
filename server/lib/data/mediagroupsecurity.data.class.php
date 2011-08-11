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

class MediaGroupSecurity extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($mediaId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'MediaGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lkmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, %d, %d, %d, %d ", $mediaId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25026, __('Could not Link Media to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'MediaGroupSecurity', 'Link');

        return true;
    }

    /**
     * Links everyone to the layout specified
     * @param <type> $layoutId
     * @param <type> $view
     * @param <type> $edit
     * @param <type> $del
     * @return <type>
     */
    public function LinkEveryone($mediaId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'MediaGroupSecurity', 'LinkEveryone');

        $groupId = $db->GetSingleValue("SELECT GroupID FROM `group` WHERE IsEveryone = 1", 'GroupID', _INT);

        return $this->Link($mediaId, $groupId, $view, $edit, $del);
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($mediaId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'MediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkmediagroup ";
        $SQL .= sprintf("  WHERE MediaID = %d AND GroupID = %d ", $mediaId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25027, __('Could not Unlink Layout from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'MediaGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($mediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'MediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkmediagroup ";
        $SQL .= sprintf("  WHERE MediaID = %d ", $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Unlink Media from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'MediaGroupSecurity', 'Unlink');

        return true;
    }

    /**
     * Copies a media items permissions
     * @param <type> $mediaId
     * @param <type> $newMediaId
     * @return <type>
     */
    public function Copy($mediaId, $newMediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'MediaGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lkmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', GroupID, View, Edit, Del ";
        $SQL .= "   FROM lkmediagroup ";
        $SQL .= "  WHERE MediaID = '%s' ";

        $SQL = sprintf($SQL, $newMediaId, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy Layout Media Security'));

            return false;
        }

        return true;
    }
}
?>