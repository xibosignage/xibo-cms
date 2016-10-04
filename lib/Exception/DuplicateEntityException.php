<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DuplicateEntityException.php)
 */


namespace Xibo\Exception;

/**
 * Class DuplicateEntityException
 * @package Xibo\Exception
 */
class DuplicateEntityException extends XiboException
{
    public $httpStatusCode = 409;

    /**
     * @return bool
     */
    public function handledException()
    {
        return true;
    }
}