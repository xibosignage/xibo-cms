<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Helper\Date;
use Xibo\Helper\ApplicationState;

defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class clockDAO extends baseDAO {
    
    /**
     * Shows the Time Information
     * @return 
     */
    function ShowTimeInfo()
    {
        $response       = new ApplicationState();
                
        $output  = '<ul>';
        $output .= '<li>' . __('Local Time') . ': ' . Date::getClock() . '</li>';
        $output .= '<li>' . __('System Time') . ': ' . Date::getSystemClock() . '</li>';
        $output .= '<li>' . __('Local Date') . ': ' . Date::getLocalDate() . '</li>';
        $output .= '<li>' . __('System Date') . ': ' . Date::getSystemDate() . '</li>';
        $output .= '</ul>';
        
        $response->SetFormRequestResponse($output, __('Date / Time Information'), '480px', '240px');
        $response->Respond();
    }
    
    /**
     * Gets the Time
     * @return 
     */
    function GetClock()
    {
        $db             =& $this->db;
        $response       = new ApplicationState();
        
        $output = Date::GetClock();
        
        $response->SetFormRequestResponse($output, __('Date / Time Information'), '480px', '240px');
        $response->clockUpdate  = true;
        $response->success      = false;
        $response->Respond();
    }
}
?>