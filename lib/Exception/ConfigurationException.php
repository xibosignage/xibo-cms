<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ConfigurationException.php)
 */


namespace Xibo\Exception;

/**
 * Class ConfigurationException
 * @package Xibo\Exception
 */
class ConfigurationException extends XiboException
{
    public $httpStatusCode = 500;

    public function handledException()
    {
        return true;
    }
}