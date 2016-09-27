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
        if (!is_numeric($input))
            return false;

        $longitude = doubleval($input);

        return ($longitude >= -180 && $longitude <= 180);
    }
}