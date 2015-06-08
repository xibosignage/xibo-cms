<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ByteFormatter.php)
 */


namespace Xibo\Helper;


class ByteFormatter
{
    /**
     * Format Bytes
     * http://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     * @param  int $size The file size in bytes
     * @param  int $precision The precision to go to
     * @return string The Formatted string with suffix
     */
    public static function format($size, $precision = 2)
    {
        if ($size == 0)
            return 0;

        $base = log($size) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    public static function toBytes($val) {

        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}