<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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

class Stat extends data
{
    public function Add($type, $fromDT, $toDT, $scheduleID, $displayID, $layoutID, $mediaID, $tag)
    {
        try {
            $dbh = PDOConnect::init();

            // Lower case the type for consistency
            $type = strtolower($type);

            // Prepare a statement
            $sth = $dbh->prepare('INSERT INTO stat (Type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag) VALUES (:type, :statdate, :start, :end, :scheduleid, :displayid, :layoutid, :mediaid, :tag)');
        
            // Construct a parameters array to execute
            $params = array();
            $params['statdate'] = date("Y-m-d H:i:s");
            $params['type'] = $type;
            $params['start'] = $fromDT;
            $params['end'] = $toDT;
            $params['scheduleid'] = $scheduleID;
            $params['displayid'] = $displayID;
            $params['layoutid'] = $layoutID;

            // Optional parameters
            $params['mediaid'] = null;
            $params['tag'] = null;
                
            // We should run different SQL depending on what Type we are
            switch ($type)
            {
                case 'media':
                    $params['mediaid'] = $mediaID;
            
                    break;

                case 'layout':
                    // Nothing additional to do
                    break;
                    
                case 'event':
                
                    $params['layoutid'] = 0;
                    $params['tag'] = $tag;
                
                    break;
                    
                default:
                    // Nothing to do, just exit
                    return true;
            }

            $sth->execute($params);
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, 'Stat Insert Failed.');
        
            return false;
        }
    }

    public function displayDown($displayId, $lastAccessed)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Prepare a statement
            $sth = $dbh->prepare('
                INSERT INTO stat (Type, statDate, start, scheduleID, displayID) 
                    VALUES (:type, :statdate, :start, :scheduleid, :displayid)');
        
            // Construct a parameters array to execute
            $params = array();
            $params['type'] = 'displaydown';
            $params['displayid'] = $displayId;
            $params['statdate'] = date('Y-m-d H:i:s');
            $params['start'] = date('Y-m-d H:i:s', $lastAccessed);
            $params['scheduleid'] = 0;

            $sth->execute($params);

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    public function displayUp($displayId) {
        try {
            $dbh = PDOConnect::init();

            Debug::Audit('Display Up: ' . $displayId);
        
            $sth = $dbh->prepare('UPDATE `stat` SET end = :toDt WHERE displayId = :displayId AND end IS NULL AND type = :type');
            $sth->execute(array(
                    'toDt' => date('Y-m-d H:i:s'),
                    'type' => 'displaydown',
                    'displayId' => $displayId
                ));
          
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }
}
?>