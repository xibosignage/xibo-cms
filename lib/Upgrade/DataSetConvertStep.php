<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetConvertStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Storage\PDOConnect;

class DataSetConvertStep implements Step
{
    public static function doStep()
    {
        // TODO: Implement doStep() method


        PDOConnect::update('DROP TABLE `datasetdata`;', []);
    }
}