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

class LayoutGroupSecurity extends Data
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
    public function Link($layoutId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutgroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, %d, %d, %d, %d ", $layoutId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25024, __('Could not Link Layout to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutGroupSecurity', 'Link');

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
    public function LinkEveryone($layoutId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'LinkEveryone');

        $groupId = $db->GetSingleValue("SELECT GroupID FROM `group` WHERE IsEveryone = 1", 'GroupID', _INT);

        return $this->Link($layoutId, $groupId, $view, $edit, $del);
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($layoutId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutgroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND GroupID = %d ", $layoutId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Layout from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutgroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d ", $layoutId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Layout from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutGroupSecurity', 'Unlink');

        return true;
    }

    /**
     * Copys all security for a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @return <type>
     */
    public function CopyAll($layoutId, $newLayoutId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutgroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', GroupID, View, Edit, Del ";
        $SQL .= "   FROM lklayoutgroup ";
        $SQL .= "  WHERE LayoutID = %d ";

        $SQL = sprintf($SQL, $newLayoutId, $layoutId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy All Layout Security'));

            return false;
        }

        return true;
    }
}
?>