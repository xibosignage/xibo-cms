<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
    private $user;
    
    public $ownerId;

    public $view;
    public $edit;
    public $del;
    public $modifyPermissions;

    /**
     * Constructs the Module Manager.
     * @return
     * @param $db Object
     * @param $user Object
     */
    public function __construct(User $user)
    {
        $this->user 	=& $user;

        $this->view = false;
        $this->edit = false;
        $this->del = false;
        $this->modifyPermissions = false;
    }

    public function Evaluate($ownerId, $view, $edit, $del)
    {
        $user =& $this->user;

        $this->ownerId = $ownerId;
        $this->view = $view;
        $this->edit = $edit;
        $this->del = $del;

        // Basic checks first
        if ($this->user->usertypeid == 1 || $ownerId == $user->userid)
        {
            // Super admin or owner, therefore permission granted to everything
            $this->FullAccess();
        }
        else if ($this->user->usertypeid == 2 && $this->view == 1)
        {
            // Group Admin and we have view permissions (i.e. this group is assigned to this item)
            $this->view = true;
            $this->edit = true;
            $this->del = true;
        }
    }

    public function FullAccess()
    {
        $this->view = true;
        $this->edit = true;
        $this->del = true;
        $this->modifyPermissions = true;
    }
}