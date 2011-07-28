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

class TemplateGroupSecurity extends Data
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
    public function Link($templateId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'TemplateGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lktemplategroup ";
        $SQL .= "       ( ";
        $SQL .= "              TemplateID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, %d, %d, %d, %d ", $templateId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25024, __('Could not Link Template to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'TemplateGroupSecurity', 'Link');

        return true;
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($templateId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'TemplateGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lktemplategroup ";
        $SQL .= sprintf("  WHERE TemplateID = %d AND GroupID = %d ", $templateId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Template from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'TemplateGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($templateId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'TemplateGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lktemplategroup ";
        $SQL .= sprintf("  WHERE TemplateID = %d ", $templateId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Template from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'TemplateGroupSecurity', 'Unlink');

        return true;
    }
}
?>