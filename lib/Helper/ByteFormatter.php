<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ByteFormatter.php)
 */


namespace Xibo\Helper;

/**
 * Class ByteFormatter
 * @package Xibo\Helper
 */
class ByteFormatter
{
    /**
     * Format Bytes
     * http://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     * @param int $size The file size in bytes
     * @param int $precision The precision to go to
     * @param bool $si Use SI units or not
     * @return string The Formatted string with suffix
     */
    public static function format($size, $precision = 2, $si = false)
    {
        if ($size == 0)
            return 0;

        if ($si === false) {
            // IEC prefixes (binary)
            $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
            $mod   = 1024;
            $base = log($size) / log($mod);
        } else {
            // SI prefixes (decimal)
            $suffixes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB');
            $mod   = 1000;
            $base = log($size) / log($mod);
        }

        return round(pow($mod, $base - floor($base)), $precision) . ' ' . $suffixes[(int)floor($base)];
    }

    /**
     * @param $val
     * @return int|string
     */
    public static function toBytes($val) {

        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = substr($val, 0, -1);

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