<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class ModuleFactory
{
    /**
     * @param string $type
     * @param Database $database
     * @param User $user
     * @return Module
     * @throws Exception
     */
    public static function create($type, $database = null, $user = null)
    {
        self::includeType($type);
        if ($database == null)
            $database = new Database();

        if ($user == null)
            $user = new User();

        if (!$module = new $type($database, $user))
            throw new Exception($module->GetErrorMessage());

        return $module;
    }

    public static function createForLibrary($type, $layoutId, $database = null, $user = null)
    {
        self::includeType($type);
        if ($database == null)
            $database = new Database();

        if ($user == null)
            $user = new User();

        if (!$module = new $type($database, $user, '', $layoutId, ''))
            throw new Exception($module->GetErrorMessage());

        return $module;
    }

    public static function createForLayout($type, $layoutId, $regionId, $database = null, $user = null)
    {
        self::includeType($type);
        if ($database == null)
            $database = new Database();

        if ($user == null)
            $user = new User();

        if (!$module = new $type($database, $user, '', $layoutId, $regionId))
            throw new Exception($module->GetErrorMessage());

        return $module;
    }

    public static function createForMedia($type, $mediaId, $database = null, $user = null)
    {
        self::includeType($type);
        if ($database == null)
            $database = new Database();

        if ($user == null)
            $user = new User();

        if (!$module = new $type($database, $user, $mediaId))
            throw new Exception($module->GetErrorMessage());

        return $module;
    }

    public static function load($type, $layoutId, $regionId, $mediaId, $linkId = null, $database = null, $user = null)
    {
        self::includeType($type);
        if ($database == null)
            $database = new Database();

        if ($user == null)
            $user = new User();

        if (!$module = new $type($database, $user, $mediaId, $layoutId, $regionId, $linkId))
            throw new Exception($module->GetErrorMessage());

        return $module;
    }

    /**
     * Include the type
     * @param $type string
     * @throws Exception
     */
    private static function includeType($type)
    {
        $type = ucfirst($type);

        if (!class_exists($type)) {
            $path = 'modules/' . strtolower($type) . '.module.php';

            if (!file_exists('modules/' . strtolower($type) . '.module.php'))
                throw new Exception(__(sprintf('Module %s does not exist', $type)));

            require_once($path);
        }
    }
}