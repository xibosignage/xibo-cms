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

class PermissionManager
{
    private $db;
    private $user;
    
    private $ownerId;
    private $permissionId;

    public $view;
    public $edit;
    public $modifyPermissions;

    /**
     * Constructs the Module Manager.
     * @return
     * @param $db Object
     * @param $user Object
     */
    public function __construct(database $db, User $user)
    {
        $this->db       =& $db;
        $this->user 	=& $user;

        $this->view = false;
        $this->edit = false;
        $this->modifyPermissions = false;
    }

    public function Evaluate($ownerId, $permissionId)
    {
        $user =& $this->user;

        // Basic checks first
        if ($this->user->usertypeid == 1 || $ownerId == $user->userid)
        {
            // Super admin or owner, therefore permission granted to everything
            $this->view = true;
            $this->edit = true;
            $this->modifyPermissions = true;
            return;
        }

        // Get the permissions for this permissionId
        if (!$permissions = $this->db->GetSingleRow(sprintf('SELECT PublicView, PublicEdit, GroupView, GroupEdit FROM permission WHERE PermissionID = %d', $permissionId)))
        {
            trigger_error($this->db->error());
            return;
        }
        
        // Not a super admin, get groups
        $groupIds = $user->GetUserGroups($user->userid, true);
        $ownerGroupIds = $user->GetUserGroups($ownerId, true);

        if (count(array_intersect($ownerGroupIds, $groupIds)) > 0)
        {
            // User is in the group

            if ($this->user->usertypeid == 2)
            {
                // User is in the group AND a group admin
                $this->view = true;
                $this->edit = true;
            }
            else
            {
                // User is in the group AND NOT a group admin
                $this->view = $permissions['GroupView'];
                $this->edit = $permissions['GroupEdit'];
            }
        }
        else
        {
            // User is not in the group
            $this->view = $permissions['PublicView'];
            $this->edit = $permissions['PublicEdit'];
        }
    }
}