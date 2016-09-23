<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Latitude.php)
 */


namespace Xibo\Validation\Rules;


use Respect\Validation\Rules\AbstractRule;

/**
 * Class Latitude
 * @package Xibo\Validation\Rules
 */
class Latitude extends AbstractRule
{
    /** @inheritdoc */
    public function validate($input)
    {
        if (preg_match("/^-?([1-8]?[1-9]|[1-9]0)\.{1}\d{1,6}$/", $input)) {
            return true;
        } else {
            return false;
        }
    }
}