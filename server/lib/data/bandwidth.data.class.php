<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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

class Bandwidth extends Data {

    public static $REGISTER = 1;
    public static $RF = 2;
    public static $SCHEDULE = 3;
    public static $GETFILE = 4;
    public static $GETRESOURCE = 5;
    public static $MEDIAINVENTORY = 6;
    public static $NOTIFYSTATUS = 7;
    public static $SUBMITSTATS = 8;
    public static $SUBMITLOG = 9;
    public static $BLACKLIST = 10;
    public static $SCREENSHOT = 11;

	public function Log($displayId, $type, $sizeInBytes) {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                INSERT INTO `bandwidth` (Month, Type, DisplayID, Size) VALUES (:month, :type, :displayid, :size)
                ON DUPLICATE KEY UPDATE Size = Size + :size2
                ');

            $sth->execute(array(
                    'month' => strtotime(date('m').'/02/'.date('Y').' 00:00:00'), 
                    'type' => $type, 
                    'displayid' => $displayId, 
                    'size' => $sizeInBytes,
                    'size2' => $sizeInBytes
                ));
            
            return true;  
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return false;
        }
    }
} 
?>
