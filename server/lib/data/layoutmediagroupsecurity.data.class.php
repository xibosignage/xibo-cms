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

class LayoutMediaGroupSecurity extends Data
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
    public function Link($layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, '%s', '%s', %d, %d, %d, %d ", $layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25026, __('Could not Link Layout Media to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Link');

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
    public function LinkEveryone($layoutId, $regionId, $mediaId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'LinkEveryone');

        $groupId = $db->GetSingleValue("SELECT GroupID FROM `group` WHERE IsEveryone = 1", 'GroupID', _INT);

        return $this->Link($layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del);
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($layoutId, $regionId, $mediaId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutmediagroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' AND MediaID = '%s' AND GroupID = %d ", $layoutId, $regionId, $mediaId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25027, __('Could not Unlink Layout Media from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId, $regionId, $mediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutmediagroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' AND MediaID = '%s' ", $layoutId, $regionId, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Unlink Layout Media from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');

        return true;
    }

    /**
     * Copies a media items permissions
     * @param <type> $layoutId
     * @param <type> $regionId
     * @param <type> $mediaId
     * @param <type> $newMediaId
     * @return <type>
     */
    public function Copy($layoutId, $regionId, $mediaId, $newMediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT LayoutID, RegionID, '%s', GroupID, View, Edit, Del ";
        $SQL .= "   FROM lklayoutmediagroup ";
        $SQL .= "  WHERE LayoutID = %d AND RegionID = '%s' AND MediaID = '%s' ";

        $SQL = sprintf($SQL, $newMediaId, $layoutId, $regionId, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy Layout Media Security'));

            return false;
        }

        return true;
    }

    /**
     * Copys all media security for a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @return <type>
     */
    public function CopyAll($layoutId, $newLayoutId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', RegionID, MediaID, GroupID, View, Edit, Del ";
        $SQL .= "   FROM lklayoutmediagroup ";
        $SQL .= "  WHERE LayoutID = %d ";

        $SQL = sprintf($SQL, $newLayoutId, $layoutId);

        Debug::LogEntry($db, 'audit', $SQL);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy All Layout Media Security'));

            return false;
        }

        return true;
    }

    /**
     * Copys all security for specific media on a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     * @return <type>
     */
    public function CopyAllForMedia($layoutId, $newLayoutId, $oldMediaId, $newMediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', RegionID, '%s', GroupID, View, Edit, Del ";
        $SQL .= "   FROM lklayoutmediagroup ";
        $SQL .= "  WHERE LayoutID = %d AND MediaID = '%s' ";

        $SQL = sprintf($SQL, $newLayoutId, $newMediaId, $layoutId, $oldMediaId);

        Debug::LogEntry($db, 'audit', $SQL);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy All Layout Media Security'));

            return false;
        }

        return true;
    }
}
?>