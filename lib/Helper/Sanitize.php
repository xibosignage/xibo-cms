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
    private static function parse($param, $default, $source)
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
        return Sanitize::int(Sanitize::parse($param, $default, $source));
    }

    public static function int($param)
    {
        return filter_var($param, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function getDouble($param, $default = null, $source = null)
    {
        return Sanitize::double(Sanitize::parse($param, $default, $source));
    }

    public static function double($param)
    {
        return filter_var($param, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function getString($param, $default = null, $source = null)
    {
        return Sanitize::string(Sanitize::parse($param, $default, $source));
    }

    public static function string($param)
    {
        return filter_var($param, FILTER_SANITIZE_STRING);
    }

    public static function getUserName($param, $default = null, $source = null)
    {
        $param = filter_var(Sanitize::parse($param, $default, $source), FILTER_SANITIZE_STRING);
        $param = (string) preg_replace( '/[\x00-\x1F\x7F<>"\'%&]/', '', $param);
        return strtolower($param);
    }

    public static function getPassword($param, $default = null, $source = null)
    {
        return Sanitize::getString($param, $default, $source);
    }

    public static function getCheckbox($param, $default = null, $source = null)
    {
        return (Sanitize::parse($param, $default, $source) == 'on') ? 1 : 0;
    }
}