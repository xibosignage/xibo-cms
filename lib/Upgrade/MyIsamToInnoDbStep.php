<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MyIsamToInnoDbStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Helper\Config;

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

        foreach ($this->getStore()->select($sql, []) as $table) {
            $this->getStore()->update('ALTER TABLE `' . $table['TABLE_NAME'] . '` ENGINE=INNODB', []);
        }
    }
}