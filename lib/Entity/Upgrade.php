<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Upgrade.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

class Upgrade implements \JsonSerializable
{
    use EntityTrait;

    public $stepId;
    public $dbVersion;

    public $requestDate;
    public $lastTryDate;
    public $complete;

    public $appVersion;
    public $step;
    public $action;

    public function doStep()
    {

    }

    public function save()
    {
        if ($this->stepId == 0)
            $this->add();
        else
            $this->edit();
    }

    private function add()
    {
        $this->stepId = PDOConnect::insert('
            INSERT INTO `upgrade` (appVersion, dbVersion, step, `action`, `requestDate`)
            VALUES (:appVersion, :dbVersion, :step, :action, :requestDate)
        ', [
            'appVersion' => $this->appVersion,
            'dbVersion' => $this->dbVersion,
            'step' => $this->step,
            'action' => $this->action,
            'requestDate' => $this->requestDate
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE `upgrade` SET
              `complete` = :complete,
              `lastTryDate` = :lastTryDate
             WHERE stepId = :stepId
        ', [
            'stepId' => $this->stepId,
            'complete' => $this->complete,
            'lastTryDate' => $this->lastTryDate
        ]);
    }

    public static function createTable()
    {
        // Insert the table.
        PDOConnect::update('
                CREATE TABLE IF NOT EXISTS `upgrade` (
                  `stepId` int(11) NOT NULL AUTO_INCREMENT,
                  `appVersion` varchar(20) NOT NULL,
                  `dbVersion` int(11) NOT NULL,
                  `step` varchar(254) NOT NULL,
                  `action` text NOT NULL,
                  `complete` tinyint(4) NOT NULL DEFAULT \'0\',
                  `requestDate` int(11) NULL,
                  `lastTryDate` int(11) NULL,
                  PRIMARY KEY (`stepId`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
            ', []);
    }
}