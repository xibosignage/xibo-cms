<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

class ModuleManager
{
    private $user;
    
    public $message;
    
    private $theMenu;
    private $current;
    private $numberItems;
    
    /**
     * Constructs the Module Manager.
     * @return 
     * @param $user Object
     */
    public function __construct(User $user, $regionSpecific = -1, $module = '', $assignable = 1)
    {
        $this->user =& $user;
            
        if (!$this->theMenu = $user->ModuleAuth($regionSpecific, $module, $assignable))
        {
            $this->message = 'No enabled modules.';
            return false;
        }

        // Set some information about this menu     
        $this->current = 0;
        $this->numberItems = count($this->theMenu);
        
        // We dont want to do 0 items
        if ($this->numberItems == 0) $this->numberItems = -1;
        
        $this->message = $this->numberItems . ' modules loaded';

        return true;
    }
    
    /**
     * Returns the internal message
     * @return 
     */
    public function GetMessage()
    {
        return $this->message;
    }
    
    /**
     * Gets the next menu item in the queue
     * @return 
     */
    public function GetNextModule()
    {
        if (!$item = $this->GetModule($this->current))
        {
            $message = 'No more modules.';
            return false;
        }
        
        $this->current++;
        
        return $item;
    }
    
    /**
     * Gets the menu item at position i
     * @return 
     * @param $i Object
     */
    public function GetModule($i)
    {
        if ($i >= $this->numberItems)
        {
            $this->message = 'There are only ' . $this->numberItems . ' modules.';
            return false;
        }
        
        return $this->theMenu[$i];
    }
}
?>
