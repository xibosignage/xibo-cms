<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
 *
 * This file is part of Xibo.
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
 *
 * A very simple file cache
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Cache {
    
    private static $_data;
    private static $_location;

    private function __construct() {}

    public static function put($key, $value, $expires) {
        if (!self::$_data)
            self::load();

        $expires = time() + $expires;

        self::$_data[$key] = array('value' => $value, 'expires' => $expires);

        self::save();
    }

    public static function get($key, $default = NULL) {
        if (!self::$_data)
            self::load();

        if (!Cache::has($key))
            return $default;

        $data = self::$_data[$key];

        if ($data['expires'] < time()) {
            unset(self::$_data['key']);
            return $default;
        }
        else
            return $data['value'];
    }

    public static function has($key) {
        if (!self::$_data)
            self::load();

        return (isset(self::$_data[$key]));
    }

    private static function load() {
        self::$_location = Config::GetSetting('LIBRARY_LOCATION') . 'cache/cache';

        if (!file_exists(self::$_location))
            self::$_data = array();
        else
            self::$_data = unserialize(file_get_contents(self::$_location));
    }

    private static function save() {
        self::$_location = Config::GetSetting('LIBRARY_LOCATION') . 'cache/cache';

        file_put_contents(self::$_location, serialize(self::$_data));
    }
}
?>
