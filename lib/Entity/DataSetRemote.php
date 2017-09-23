<?php
/*
 * LukyLuke - http://www.ranta.ch
 * Copyright (C) 2017 LukyLuke - Lukas Zurschmiede - https://github.com/LukyLuke
 * (DataSetRempote.php)
 */

namespace Xibo\Entity;

/**
 * Class DataSetRemote
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataSetRemote extends DataSet
{
    /**
     * @SWG\Property(description="Method to fetch the Data, can be GET or POST")
     * @var string
     */
    public $method;

    /**
     * @SWG\Property(description="URI to call to fetch Data from. Replacements are {{DATE}}, {{TIME}} and, in case this is a sequencial used DataSet, {{COL.NAME}} where NAME is a ColumnName from the underlying DataSet.")
     * @var string
     */
    public $uri;

    /**
     * @SWG\Property(description="Data to send as POST-Data to the remote host with the same Replacements as in the URI.")
     * @var string
     */
    public $postData;

    /**
     * @SWG\Property(description="Authentication method, can be none, plain, digest, basic")
     * @var string
     */
    public $authentication;

    /**
     * @SWG\Property(description="Username to authenticate with")
     * @var string
     */
    public $username;

    /**
     * @SWG\Property(description="Corresponding password")
     * @var string
     */
    public $password;

    /**
     * @SWG\Property(description="Time in seconds this DataSet should fetch new Datas from the remote host")
     * @var int
     */
    public $refreshRate;

    /**
     * @SWG\Property(description="Time in seconds when this Dataset should be cleared. If here is a lower value than in RefreshRate it will be cleared when the data is refreshed")
     * @var int
     */
    public $clearRate;

    /**
     * @SWG\Property(description="DataSetID of the DataSet which should be fetched and present before the Data from this DataSet are fetched")
     * @var int
     */
    public $runsAfter;
    
    
    /**
     * Validate
     */
    public function validate() {
        parent::validate();
    }
    
    /**
     * Load all known information
     */
    public function load() {
        parent::load();
    }
    
    /**
     * Save this DataSet
     * @param array $options
     * @Override
     */
    public function save($options = []) {
        parent::save($options);
        if ($this->exists()) {
            $this->editRemote();
        } else {
            $this->addRemote();
        }
    }
    
    /**
     * Delete DataSet
     */
    public function delete() {
        parent::delete();
        $this->getStore()->update('DELETE FROM `datasetremote` WHERE dataSetId = :dataSetId', ['dataSetId' => $this->dataSetId]);
    }
    
    private function exists() {
        return $this->getStore()->exists('SELECT DataSetID FROM `datasetremote` WHERE DataSetID = :dataSetId;', ['dataSetId' => $this->dataSetId]);
    }
    
    /**
     * Add Remote Settings entry
     */
    private function addRemote() {
        $this->getStore()->insert(
          'INSERT INTO `datasetremote` (`DataSetID`, `method`, `uri`, `postData`, `authentication`, `username`, `password`, `refreshRate`, `clearRate`, `runsAfter`)
            VALUES (:dataSetId, :method, :uri, :postData, :authentication, :username, :password, :refreshRate, :clearRate, :runsAfter)', [
            'dataSetId' => $this->dataSetId,
            'method' => $this->method,
            'uri' => $this->uri,
            'postData' => $this->postData,
            'authentication' => $this->authentication,
            'username' => $this->username,
            'password' => $this->password,
            'refreshRate' => $this->refreshRate,
            'clearRate' => $this->clearRate,
            'runsAfter' => $this->runsAfter
        ]);
    }

    /**
     * Edit Remote Settings Entry
     */
    private function editRemote() {
        $this->getStore()->update(
          'UPDATE datasetremote SET method = :method, uri = :uri, postData = :postData, authentication = :authentication, username = :username, password = :password, refreshRate = :refreshRate, clearRate = :clearRate, runsAfter = :runsAfter
            WHERE DataSetID = :dataSetId', [
            'dataSetId' => $this->dataSetId,
            'method' => $this->method,
            'uri' => $this->uri,
            'postData' => $this->postData,
            'authentication' => $this->authentication,
            'username' => $this->username,
            'password' => $this->password,
            'refreshRate' => $this->refreshRate,
            'clearRate' => $this->clearRate,
            'runsAfter' => $this->runsAfter
        ]);
    }
}
