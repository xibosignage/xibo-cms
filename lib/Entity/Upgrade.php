<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Upgrade.php)
 */


namespace Xibo\Entity;


use Xibo\Helper\Install;
use Xibo\Storage\PDOConnect;
use Xibo\Upgrade\Step;

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
    public $type;

    public function doStep()
    {
        // SQL or not?
        switch ($this->type) {

            case 'sql':

                // Split the statement and run
                // DDL doesn't rollback, so ideally we'd only have 1 statement
                $dbh = PDOConnect::init();

                // Run the SQL to create the necessary tables
                $statements = Install::remove_remarks($this->action);
                $statements = Install::split_sql_file($statements, ';');

                foreach ($statements as $sql) {
                    $dbh->exec($sql);
                }

                break;

            case 'php':

                // Instantiate the class provided in Action.
                $class = $this->action;

                if (!class_exists($class))
                    throw new \InvalidArgumentException(__('PHP step class does not exist'));

                $object = new $class();
                /* @var Step $object */
                $object->doStep();

                break;

            default:
                throw new \InvalidArgumentException(__('Unknown Request Type'));
        }
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
            INSERT INTO `upgrade` (appVersion, dbVersion, step, `action`, `type`, `requestDate`)
            VALUES (:appVersion, :dbVersion, :step, :action, :type, :requestDate)
        ', [
            'appVersion' => $this->appVersion,
            'dbVersion' => $this->dbVersion,
            'step' => $this->step,
            'action' => $this->action,
            'type' => $this->type,
            'requestDate' => $this->requestDate
        ]);
    }

    private function edit()
    {
        // We use a new connection so that if/when the upgrade steps do not run successfully we can still update
        // the state and rollback the executed steps
        $dbh = PDOConnect::newConnection();
        $sth = $dbh->prepare('
            UPDATE `upgrade` SET
              `complete` = :complete,
              `lastTryDate` = :lastTryDate
             WHERE stepId = :stepId
        ');

        $sth->execute([
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
                  `type` varchar(50) NOT NULL,
                  `action` text NOT NULL,
                  `complete` tinyint(4) NOT NULL DEFAULT \'0\',
                  `requestDate` int(11) NULL,
                  `lastTryDate` int(11) NULL,
                  PRIMARY KEY (`stepId`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
            ', []);
    }
}