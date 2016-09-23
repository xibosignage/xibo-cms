<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Longitude.php)
 */


namespace Xibo\Validation\Rules;


use Respect\Validation\Rules\AbstractRule;

/**
 * Class Longitude
 * @package Xibo\Validation\Rules
 */
class Longitude extends AbstractRule
{
    /**
     * @param $input
     * @return bool
     */
    public function validate($input)
    {
        if (preg_match("/^-?([1]?[1-7][1-9]|[1]?[1-8][0]|[1-9]?[0-9])\.{1}\d{1,6}$/", $input)) {
            return true;
        } else {
            return false;
        }
    }
}