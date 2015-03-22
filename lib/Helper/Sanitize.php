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


class Sanitize
{
    private static function parse($param, $name)
    {
        if ($name != null && is_array($param))
            return isset($param[$name]) ? $param[$name] : null;
        else
            return $param;
    }

    public static function int($param, $name = null)
    {
        return filter_var(Sanitize::parse($param, $name), FILTER_SANITIZE_NUMBER_INT);
    }

    public static function string($param, $name = null)
    {
        return filter_var(Sanitize::parse($param, $name), FILTER_SANITIZE_STRING);
    }

    public static function userName($param, $name = null)
    {
        $param = filter_var(Sanitize::parse($param, $name), FILTER_SANITIZE_STRING);
        $param = (string) preg_replace( '/[\x00-\x1F\x7F<>"\'%&]/', '', $param);
        return strtolower($param);
    }

    public static function password($param, $name = null)
    {
        return Sanitize::string($param, $name);
    }
}