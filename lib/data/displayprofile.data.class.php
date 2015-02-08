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

        Debug::Audit('Load DisplayProfileId: ' . $this->displayProfileId);

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT * FROM `displayprofile` WHERE displayprofileid = :displayprofileid');
            $sth->execute(array(
                    'displayprofileid' => $this->displayProfileId
                ));
          
            if (!$row = $sth->fetch())
                $this->ThrowError(25004, __('Cannot find display profile'));

            // Get the type so we can load the defaults in from file
            $this->type = Kit::ValidateParam($row['type'], _STRING);

            // Load the config from disk
            $this->loadFromFile();

            // Overwrite the defaults with the values from this specific record
            $this->name = Kit::ValidateParam($row['name'], _STRING);
            $this->isDefault = Kit::ValidateParam($row['isdefault'], _INT);
            $this->userId = Kit::ValidateParam($row['userid'], _INT);

            // Load the client settings into an array
            $config = Kit::ValidateParam($row['config'], _HTMLSTRING);
            $config = ($config == '') ? array() : json_decode($config, true);

            // We have an array of settings that we must use to overwrite the values in our global config
            for ($i = 0; $i < count($this->config); $i++) {
                // Does this setting exist in our store?
                for ($j = 0; $j < count($config); $j++) {
                    if ($config[$j]['name'] == $this->config[$i]['name']) {
                        $this->config[$i]['value'] = $config[$j]['value'];
                        break;
                    }
                }
            }

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

    public function LoadDefault() {

        Debug::Audit('Load Default ' . $this->type);

        try {
            $dbh = PDOConnect::init();

            // Load the config from disk
            $this->loadFromFile();

            // See if we have a default for this player type
            $sth = $dbh->prepare('SELECT * FROM `displayprofile` WHERE `type` = :type AND isdefault = 1');
            $sth->execute(array(
                    'type' => $this->type
                ));
          
            if (!$row = $sth->fetch()) {
                // We don't so we should stick with the global default
                Debug::Audit('Fall back to global default');
            }
            else {
                // We do, so we should overwrite the global default with our stored preferences
                $this->name = Kit::ValidateParam($row['name'], _STRING);
                $this->type = Kit::ValidateParam($row['type'], _STRING);
                $this->isDefault = Kit::ValidateParam($row['isdefault'], _INT);
                $this->userId = Kit::ValidateParam($row['userid'], _INT);

                // Load the client settings into an array
                $config = Kit::ValidateParam($row['config'], _HTMLSTRING);
                $config = ($config == '') ? array() : json_decode($config, true);

                // We have an array of settings that we must use to overwrite the values in our global config
                for ($i = 0; $i < count($this->config); $i++) {
                    // Does this setting exist in our store?
                    for ($j = 0; $j < count($config); $j++) {
                        if ($config[$j]['name'] == $this->config[$i]['name']) {
                            $this->config[$i]['value'] = $config[$j]['value'];
                            break;
                        }
                    }
                }

                $this->isNew = false;
            }

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Load the config from the file
     */
    private function loadFromFile()
    {
        include('config/client.config.php');
        $this->name = $CLIENT_CONFIG[$this->type]['synonym'];
        $this->config = $CLIENT_CONFIG[$this->type]['settings'];
        $this->isDefault = 1;
        $this->userId = 1;

        // Just populate the values with the defaults if the values aren't set already
        for ($i = 0; $i < count($this->config); $i++) {
            $this->config[$i]['value'] = isset($this->config[$i]['value']) ? $this->config[$i]['value'] : $this->config[$i]['default'];
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
        if (!$this->ValidateDefault())
            return false;        

        if ($this->isNew) {
            return $this->Add();
        }
        else {
            if (empty($this->displayProfileId) || $this->displayProfileId == 0)
                return $this->SetError(__('Missing displayProfileId'));

            return $this->Update();
        }
    }

    private function ValidateDefault() {
        // Check that there aren't other defaults for this type.
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT COUNT(*) FROM `displayprofile` WHERE type = :type AND isdefault = :isdefault AND displayprofileid <> :displayprofileid');
            $sth->execute(array('type' => $this->type, 'isdefault' => 1, 'displayprofileid' => ((empty($this->displayProfileId)) ? 0 : $this->displayProfileId)));

            $count = $sth->fetchColumn(0) + (int)$this->isDefault;
            
            if ($count > 1)
                $this->ThrowError(__('Only 1 default per display type is allowed.'));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
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

    public function Delete() {

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `displayprofile` WHERE displayprofileid = :displayprofileid');
            $sth->execute(array('displayprofileid' => $this->displayProfileId));

            // This one is no longer a default
            $this->isDefault = 0;

            // Check that there aren't other defaults for this type.
            if (!$this->ValidateDefault())
                $this->ThrowError(__('Deleting this Profile would result in there not being a default for this type of client'));
          
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
