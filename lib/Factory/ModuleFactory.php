<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ModuleFactory.php) is part of Xibo.
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


namespace Xibo\Factory;


use Xibo\Entity\Module;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;

class ModuleFactory
{
    /**
     * Create a Module
     * @param string $type
     * @return \Widget\Module
     * @throws NotFoundException
     */
    public static function create($type)
    {
        $modules = ModuleFactory::query(null, array('type' => $type));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown type %s'), $type));

        // Create a module
        $module = $modules[0];

        $type = $module->type;

        $type = new $type();
        /* @var \Widget\Module $type */
        $type->setModule($module);

        return $type;
    }

    /**
     * Create a Module for a Widget and optionally a playlist/region
     * @param string $type
     * @param int $widgetId
     * @param int $ownerId
     * @param int $playlistId
     * @param int $regionId
     * @return \Widget\Module
     * @throws NotFoundException
     */
    public static function createForWidget($type, $widgetId = 0, $ownerId = 0, $playlistId = 0, $regionId = 0)
    {
        $module = ModuleFactory::create($type);

        // Do we have a regionId
        if ($regionId != 0) {
            // Load the region and set
            $region = RegionFactory::getById($regionId);
            $module->setRegion($region);
        }

        // Do we have a widgetId
        if ($widgetId == 0) {
            // If we don't have a widget we must have a playlist
            if ($playlistId == 0) {
                //throw new \InvalidArgumentException(__('Playlist not provided'));
                // TODO: Implement Playlists
                $playlistId = PlaylistFactory::getByRegionId($regionId)[0]->playlistId;
            }

            // Create a new widget to use
            $module->setWidget(WidgetFactory::create($ownerId, $playlistId, $module->getModuleType(), 0));
        }
        else {
            // Load the widget
            $module->setWidget(WidgetFactory::loadByWidgetId($widgetId));
        }

        return $module;
    }

    /**
     * Create a Module using a Widget
     * @param Widget $widget
     * @param Region $region
     * @return \Widget\Module
     */
    public static function createWithWidget($widget, $region)
    {
        $module = ModuleFactory::create($widget->type);
        $module->setWidget($widget);
        $module->setRegion($region);

        return $module;
    }

    public static function get($key = 'type')
    {
        $modules = ModuleFactory::query();

        if ($key != null && $key != '') {

            $keyed = array();
            foreach ($modules as $module) {
                /* @var Module $module */
                $keyed[$module->type] = $module;
            }

            return $keyed;
        }

        return $modules;
    }

    public static function getAssignableModules()
    {
        return ModuleFactory::query(null, array('assignable' => 1, 'enabled' => 1));
    }

    /**
     * Get module by extension
     * @param string $extension
     * @return Module
     * @throws NotFoundException
     */
    public static function getByExtension($extension)
    {
        $modules = ModuleFactory::query(null, array('extension' => $extension));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Extension %s does not match any enabled Module'), $extension));

        return $modules[0];
    }

    /**
     * Get Valid Extensions
     * @return array[string]
     */
    public static function getValidExtensions()
    {
        $modules = ModuleFactory::query();
        $extensions = array();

        foreach($modules as $module) {
            /* @var Module $module */
            if ($module->validExtensions != '') {
                foreach (explode(',', $module->validExtensions) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        return $extensions;
    }

    public static function query($sortOrder = null, $filterBy = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('Module');

        $entries = array();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $params = array();

            $SQL = '';
            $SQL .= 'SELECT ModuleID, ';
            $SQL .= '   Module, ';
            $SQL .= '   Name, ';
            $SQL .= '   Enabled, ';
            $SQL .= '   Description, ';
            $SQL .= '   render_as, ';
            $SQL .= '   settings, ';
            $SQL .= '   RegionSpecific, ';
            $SQL .= '   ValidExtensions, ';
            $SQL .= '   ImageUri, ';
            $SQL .= '   PreviewEnabled, ';
            $SQL .= '   assignable, ';
            $SQL .= '   SchemaVersion ';
            $SQL .= '  FROM `module` ';
            $SQL .= ' WHERE 1 = 1 ';

            if (\Xibo\Helper\Sanitize::int('id', 0, $filterBy) != 0) {
                $params['id'] = \Xibo\Helper\Sanitize::int('id', $filterBy);
                $SQL .= ' AND ModuleID = :id ';
            }

            if (\Kit::GetParam('name', $filterBy, _STRING) != '') {
                $params['name'] = \Kit::GetParam('name', $filterBy, _STRING);
                $SQL .= ' AND name = :name ';
            }

            if (\Kit::GetParam('type', $filterBy, _STRING) != '') {
                $params['type'] = \Kit::GetParam('type', $filterBy, _STRING);
                $SQL .= ' AND module = :type ';
            }

            if (\Kit::GetParam('extension', $filterBy, _STRING) != '') {
                $params['extension'] = '%' . \Kit::GetParam('extension', $filterBy, _STRING) . '%';
                $SQL .= ' AND ValidExtensions LIKE :extension ';
            }

            if (\Xibo\Helper\Sanitize::getInt('assignable', -1, $filterBy) != -1) {
                $SQL .= " AND assignable = :assignable ";
                $params['assignable'] = \Xibo\Helper\Sanitize::getInt('assignable', $filterBy);
            }

            if (\Xibo\Helper\Sanitize::getInt('enabled', -1, $filterBy) != -1) {
                $SQL .= " AND enabled = :enabled ";
                $params['enabled'] = \Xibo\Helper\Sanitize::getInt('enabled', $filterBy);
            }

            if (\Xibo\Helper\Sanitize::getInt('regionSpecific', -1, $filterBy) != -1) {
                $SQL .= " AND regionSpecific = :regionSpecific ";
                $params['regionSpecific'] = \Xibo\Helper\Sanitize::getInt('regionSpecific', $filterBy);
            }

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            Log::sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $module = new Module();
                $module->moduleId = \Xibo\Helper\Sanitize::int($row['ModuleID']);
                $module->name = \Xibo\Helper\Sanitize::string($row['Name']);
                $module->description = \Xibo\Helper\Sanitize::string($row['Description']);
                $module->validExtensions = \Xibo\Helper\Sanitize::string($row['ValidExtensions']);
                $module->imageUri = \Xibo\Helper\Sanitize::string($row['ImageUri']);
                $module->renderAs = \Xibo\Helper\Sanitize::string($row['render_as']);
                $module->type = strtolower(\Kit::ValidateParam($row['Module'], _WORD));
                $module->enabled = \Xibo\Helper\Sanitize::int($row['Enabled']);
                $module->regionSpecific = \Xibo\Helper\Sanitize::int($row['RegionSpecific']);
                $module->previewEnabled = \Xibo\Helper\Sanitize::int($row['PreviewEnabled']);
                $module->assignable = \Xibo\Helper\Sanitize::int($row['assignable']);
                $module->schemaVersion = \Xibo\Helper\Sanitize::int($row['SchemaVersion']);

                $settings = \Xibo\Helper\Sanitize::string($row['settings']);
                $module->settings = ($settings == '') ? array() : json_decode($settings, true);

                $entries[] = $module;
            }

            return $entries;
        }
        catch (\Exception $e) {

            \Xibo\Helper\Log::error($e->getMessage());

            return array();
        }
    }
}