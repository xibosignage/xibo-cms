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

class LayoutRegionGroupSecurity extends Data
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
    public function Link($layoutId, $regionId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutRegionGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutregiongroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, '%s', '%s', %d, %d, %d ", $layoutId, $regionId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25026, __('Could not Link Layout Region to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutRegionGroupSecurity', 'Link');

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
    public function LinkEveryone($layoutId, $regionId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutGroupSecurity', 'LinkEveryone');

        $groupId = $db->GetSingleValue("SELECT GroupID FROM `group` WHERE IsEveryone = 1", 'GroupID', _INT);

        return $this->Link($layoutId, $regionId, $groupId, $view, $edit, $del);
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($layoutId, $regionId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutRegionGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutregiongroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' AND GroupID = %d ", $layoutId, $regionId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25027, __('Could not Unlink Layout Region from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutRegionGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId, $regionId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutRegionGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutregiongroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' ", $layoutId, $regionId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Unlink Layout Region from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutRegionGroupSecurity', 'Unlink');

        return true;
    }

    /**
     * Copys all region security for a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @return <type>
     */
    public function CopyAll($layoutId, $newLayoutId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutRegionGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutregiongroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', RegionID, GroupID, View, Edit, Del ";
        $SQL .= "   FROM lklayoutregiongroup ";
        $SQL .= "  WHERE LayoutID = %d ";

        $SQL = sprintf($SQL, $newLayoutId, $layoutId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy All Layout Region Security'));

            return false;
        }

        return true;
    }
}
?>