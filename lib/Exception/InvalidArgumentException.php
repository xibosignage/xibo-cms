<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (InvalidArgumentException.php)
 */


namespace Xibo\Exception;

/**
 * Class InvalidArgumentException
 * @package Xibo\Exception
 */
class InvalidArgumentException extends XiboException
{
    public $httpStatusCode = 422;
    public $handledException = true;
    public $property = null;

    /**
     * InvalidArgumentException constructor.
     * @param string $message
     * @param string $property
     */
    public function __construct($message, $property)
    {
        $this->property = $property;

        parent::__construct($message);
    }

    /**
     * @return array
     */
    public function getErrorData()
    {
        return ['property' => $this->property];
    }
}