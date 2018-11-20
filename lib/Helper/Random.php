<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Random.php)
 */


namespace Xibo\Helper;


class Random
{
    /**
     * @param int $length
     * @param string $prefix
     * @return string
     * @throws \Exception
     */
    public static function generateString($length = 10, $prefix = '')
    {
        if (function_exists('random_bytes')) {
            return $prefix . bin2hex(random_bytes($length));
        } else {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $prefix . $randomString;
        }
    }
}