<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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

// Companion classes
//Kit::ClassLoader('');

class DisplayProfile extends Data {

    public $displayProfileId;
    public $name;
    public $type;
    public $config;
    public $isDefault;
    public $userId;
    public $isNew;

    public function __construct() {
        $this->isNew = true;
    }

    public function Load() {

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT * FROM `displayprofile` WHERE displayprofileid = :displayprofileid');
            $sth->execute(array(
                    'displayprofileid' => $this->displayProfileId
                ));
          
            if (!$row = $sth->fetch())
                $this->ThrowError(25004, __('Cannot find display profile'));

            $this->name = Kit::ValidateParam($row['name'], _STRING);
            $this->type = Kit::ValidateParam($row['type'], _STRING);
            $this->config = Kit::ValidateParam($row['config'], _HTMLSTRING);
            $this->isDefault = Kit::ValidateParam($row['isdefault'], _INT);
            $this->userId = Kit::ValidateParam($row['userid'], _INT);

            // Load the client settings into an array
            $this->config = ($this->config == '') ? array() : json_decode($this->config, true);

            $this->isNew = false;

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

    }

    public function Save() {

        // Validation.
        if (empty($this->name))
            return $this->SetError(__('Missing name'));

        if (empty($this->type))
            return $this->SetError(__('Missing type'));

        // Display profile should be null if 0
        if ($this->displayProfileId == 0)
            $this->displayProfileId = NULL;

        // Check that there aren't other defaults for this type.
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT COUNT(*) FROM `displayprofile` WHERE type = :type AND isdefault = :isdefault AND displayprofileid <> :displayprofileid');
            $sth->execute(array('type' => $this->type, 'isdefault' => 1, 'displayprofileid' => ((empty($this->displayProfileId)) ? 0 : $this->displayProfileId)));

            $count = $sth->fetchColumn(0) + (int)$this->isDefault;
            
            if ($count != 1)
                $this->ThrowError(__('Must have 1 default per display type.'));
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
        

        if ($this->isNew) {
            return $this->Add();
        }
        else {
            if (empty($this->displayProfileId) || $this->displayProfileId == 0)
                return $this->SetError(__('Missing displayProfileId'));

            return $this->Update();
        }
    }

    private function Add() {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('INSERT INTO `displayprofile` (name, type, config, isdefault, userid) VALUES (:name, :type, :config, :isdefault, :userid)');
            $sth->execute(array(
                    'name' => $this->name,
                    'type' => $this->type,
                    'config' => ($this->config == '') ? '[]' : json_encode($this->config),
                    'isdefault' => $this->isDefault,
                    'userid' => $this->userId
                ));
          
            $this->displayProfileId = $dbh->lastInsertId();
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    private function Update() {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('UPDATE `displayprofile` SET name = :name, type = :type, config = :config, isdefault = :isdefault WHERE displayprofileid = :displayprofileid');
            $sth->execute(array(
                    'name' => $this->name,
                    'type' => $this->type,
                    'config' => ($this->config == '') ? '[]' : json_encode($this->config),
                    'isdefault' => $this->isDefault,
                    'displayprofileid' => $this->displayProfileId
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
