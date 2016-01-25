<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MyIsamToInnoDbStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Helper\Config;
use Xibo\Storage\PDOConnect;

class MyIsamToInnoDbStep implements Step
{
    public static function doStep()
    {
        $sql = '
          SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
           WHERE TABLE_SCHEMA = \'' . Config::$dbConfig['name']  . '\'
            AND ENGINE = \'MyISAM\'
        ';

        foreach (PDOConnect::select($sql, []) as $table) {
            PDOConnect::update('ALTER TABLE `' . $table['TABLE_NAME'] . '` ENGINE=INNODB', []);
        }
    }
}