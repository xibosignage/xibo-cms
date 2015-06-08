<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Sanitize.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Helper;


use Slim\Slim;

class Sanitize
{
    public static function getParam($param, $default, $source = null)
    {
        if (is_array($default)) {
            return isset($default[$param]) ? $default[$param] : null;
        }
        else if ($source == null) {
            $app = Slim::getInstance();
            switch ($app->request->getMethod()) {
                case 'GET':
                    return $app->request->get($param, $default);
                case 'POST':
                case 'DELETE':
                    return $app->request->post($param, $default);
                case 'PUT':
                    return $app->request->put($param, $default);
                default:
                    return $default;
            }
        }
        else
            return isset($source[$param]) ? $source[$param] : $default;
    }

    public static function getInt($param, $default = null, $source = null)
    {
        return Sanitize::int(Sanitize::getParam($param, $default, $source));
    }

    public static function int($param)
    {
        if ($param == null)
            return 0;

        return filter_var($param, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function getDouble($param, $default = null, $source = null)
    {
        return Sanitize::double(Sanitize::getParam($param, $default, $source));
    }

    public static function double($param)
    {
        if ($param == null)
            return 0;

        return filter_var($param, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function getString($param, $default = null, $source = null)
    {
        return Sanitize::string(Sanitize::getParam($param, $default, $source));
    }

    public static function string($param)
    {
        return filter_var($param, FILTER_SANITIZE_STRING);
    }

    public static function getUserName($param, $default = null, $source = null)
    {
        $param = filter_var(Sanitize::getParam($param, $default, $source), FILTER_SANITIZE_STRING);
        $param = (string) preg_replace( '/[\x00-\x1F\x7F<>"\'%&]/', '', $param);
        return strtolower($param);
    }

    public static function getPassword($param, $default = null, $source = null)
    {
        return Sanitize::getString($param, $default, $source);
    }

    public static function getCheckbox($param, $default = null, $source = null)
    {
        $checkbox = Sanitize::getParam($param, $default, $source);
        return ($checkbox === 'on' || $checkbox === 1 || $checkbox === 'true') ? 1 : 0;
    }

    public static function bool($param)
    {
        return filter_var($param, FILTER_VALIDATE_BOOLEAN);
    }

    public static function htmlString($param)
    {
        // decimal notation
        $return = preg_replace_callback('/&#(\d+);/m', function($m){
            return chr($m[1]);
        }, $param);

        // convert hex
        $return = preg_replace_callback('/&#x([a-f0-9]+);/mi', function($m){
            return chr("0x".$m[1]);
        }, $return);

        return (string) $return;
    }

    /**
     * Get an array of ints
     * @param string $param
     * @param mixed[Optional] $default
     * @param mixed[Optional] $source
     * @return array[mixed]|null
     */
    public static function getStringArray($param, $default = null, $source = null)
    {
        $array = Sanitize::getParam($param, $default, $source);

        if ($array == null)
            return [];

        return $array;
    }

    /**
     * Get an array of ints
     * @param string $param
     * @param mixed[Optional] $default
     * @param mixed[Optional] $source
     * @return array[mixed]|null
     */
    public static function getIntArray($param, $default = null, $source = null)
    {
        $array = Sanitize::getParam($param, $default, $source);

        if ($array == null)
            return [];

        return array_map('intval', $array);
    }
}