<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Daniel Garner
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
namespace Xibo\Helper;


use Xibo\Controller\Library;

class Cache
{
    private static $_data;

    public static function put($key, $value, $expires)
    {
        Log::debug('Saving %s to the cache, expires in %d seconds', $key, $expires);

        // If the data store isn't there, then create it
        if (!self::$_data)
            self::$_data = array();

        // Set the expiry time
        $expires = time() + $expires;

        self::$_data[$key] = array('value' => $value, 'expires' => $expires);

        // Save the key
        self::save($key);
    }

    public static function get($key, $default = NULL)
    {
        // Load the key
        self::load($key);

        if (!Cache::has($key))
            return $default;

        return self::$_data[$key]['value'];
    }

    /**
     * Does the cache have the specified key
     * @param  string $key The Key
     * @return boolean True or False
     */
    public static function has($key)
    {
        // Load the key
        self::load($key);

        if ((isset(self::$_data[$key]) && self::$_data[$key] != null)) {
            // If the key has expired remove it
            if (self::$_data[$key]['expires'] < time()) {
                Log::debug($key . ' Expired: ' . Date::getLocalDate(self::$_data[$key]['expires']));

                // Remove it
                self::remove($key);
                return false;
            }

            Log::debug($key . ' present and in date');

            return true;
        }

        Log::debug($key . ' not present');
        return false;
    }

    /**
     * Loads the requested key
     * @param string $key The Key to Load
     */
    private static function load($key)
    {
        // If the data store isn't there, then create it
        if (!self::$_data)
            self::$_data = array();

        // Set the location for this key
        $location = Config::GetSetting('LIBRARY_LOCATION') . 'cache/cache_' . $key;

        // If the key isn't there already, do nothing. Otherwise load it.
        if (file_exists($location)) {
            self::$_data[$key] = unserialize(file_get_contents($location));
        }
    }

    /**
     * Saves the specified key
     * @param  string $key The key
     */
    private static function save($key)
    {
        Library::ensureLibraryExists();

        $location = Config::GetSetting('LIBRARY_LOCATION') . 'cache/cache_' . $key;

        file_put_contents($location, serialize(self::$_data[$key]));
    }

    private static function remove($key)
    {
        $location = Config::GetSetting('LIBRARY_LOCATION') . 'cache/cache_' . $key;

        if (file_exists($location)) {
            unset(self::$_data[$key]);
            unlink($location);
        }
    }
}

?>
