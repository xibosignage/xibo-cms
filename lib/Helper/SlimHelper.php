<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (SlimHelper.php)
 */


namespace Xibo\Helper;


use Slim\Slim;

class SlimHelper extends Slim
{
    public static function clearInstances()
    {
        self::$apps = [];
    }
}