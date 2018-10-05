<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (BaseException.php)
 */


namespace Xibo\Exception;

/**
 * Class XiboException
 * @package Xibo\Exception
 */
class XiboException extends \Exception
{
    public $httpStatusCode = 400;
    public $handledException = false;

    /**
     * @return bool
     */
    public function handledException()
    {
        return $this->handledException;
    }
}