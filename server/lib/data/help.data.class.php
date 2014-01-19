<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-13 Daniel Garner
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

class Help extends Data {
    /**
     * Add a new Help Link
     * @param <string> $topic
     * @param <string> $category
     * @param <string> $link
     */
    public function Add($topic, $category, $link)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Validation
            if ($topic == '')
                $this->ThrowError(__('Topic is a required field. It must be between 1 and 254 characters.'));
    
            if ($category == '')
                $this->ThrowError(__('Category is a required field. It must be between 1 and 254 characters.'));
    
            if ($link == '')
                $this->ThrowError(__('Link is a required field. It must be between 1 and 254 characters.'));

            $sth = $dbh->prepare('INSERT INTO `help` (Topic, Category, Link) VALUES (:topic, :category, :link)');
            $sth->execute(array(
                    'topic' => $topic,
                    'category' => $category,
                    'link' => $link
                ));
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25000, __('Unable to Add Help record'));
        
            return false;
        }
    }

    /**
     * Edit an existing Help Link
     * @param <int> $helpId
     * @param <string> $topic
     * @param <string> $category
     * @param <string> $link
     */
    public function Edit($helpId, $topic, $category, $link)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Validation
            if ($helpId == 0)
                $this->ThrowError(__('Help Link not selected'));

            if ($topic == '')
                $this->ThrowError(__('Topic is a required field. It must be between 1 and 254 characters.'));

            if ($category == '')
                $this->ThrowError(__('Category is a required field. It must be between 1 and 254 characters.'));

            if ($link == '')
                $this->ThrowError(__('Link is a required field. It must be between 1 and 254 characters.'));

            // Update the Help Record
            $sth = $dbh->prepare('UPDATE `help` SET Topic = :topic, Category = :category, Link = :link WHERE HelpID = :helpid');
            $sth->execute(array(
                    'topic' => $topic,
                    'category' => $category,
                    'link' => $link,
                    'helpid' => $helpId
                ));

            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                return $this->SetError(25000, __('Unable to Edit Help record'));
        
            return false;
        }
    }

    /**
     * Delete a Help Link
     * @param <int> $helpId
     */
    public function Delete($helpId)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Validation
            if ($helpId == 0)
                $this->ThrowError(__('Help Link not selected'));

            $sth = $dbh->prepare('DELETE FROM `help` WHERE HelpID = :helpid');
            $sth->execute(array(
                    'helpid' => $helpId
                ));
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, __('Unable to Delete Help record'));
        
            return false;
        }
    }
}
?>