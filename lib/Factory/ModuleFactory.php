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
use Xibo\Exception\NotFoundException;

class ModuleFactory
{
    /**
     * Create a Module
     * @param string $type
     * @return \Module
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
        /* @var \Module $type */
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
     * @return \Module
     * @throws NotFoundException
     */
    public static function createForWidget($type, $widgetId = 0, $ownerId = 0, $playlistId = 0, $regionId = 0)
    {
        $module = ModuleFactory::create($type);

        // Do we have a regionId
        if ($regionId != 0) {
            // Load the region and set
            $region = RegionFactory::getByRegionId($regionId);
            $module->setRegion($region);
        }

        // Do we have a widgetId
        if ($widgetId == 0) {
            // If we don't have a widget we must have a playlist
            if ($playlistId == 0)
                throw new \InvalidArgumentException(__('Playlist not provided'));

            // Create a new widget to use
            $module->setWidget(WidgetFactory::create($ownerId, $playlistId, $module->getModuleType(), 0));
        }
        else {
            // Load the widget
            $module->setWidget(WidgetFactory::loadByWidgetId($widgetId));
        }

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

    public static function query($sortOrder = array('Module'), $filterBy = array())
    {
        $entries = array();

        try {
            $dbh = \PDOConnect::init();

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

            if (\Kit::GetParam('id', $filterBy, _INT, 0) != 0) {
                $params['id'] = \Kit::GetParam('id', $filterBy, _INT);
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

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $module = new Module();
                $module->moduleId = \Kit::ValidateParam($row['ModuleID'], _INT);
                $module->name = \Kit::ValidateParam($row['Name'], _STRING);
                $module->description = \Kit::ValidateParam($row['Description'], _STRING);
                $module->validExtensions = \Kit::ValidateParam($row['ValidExtensions'], _STRING);
                $module->imageUri = \Kit::ValidateParam($row['ImageUri'], _STRING);
                $module->renderAs = \Kit::ValidateParam($row['render_as'], _STRING);
                $module->type = strtolower(\Kit::ValidateParam($row['Module'], _WORD));
                $module->enabled = \Kit::ValidateParam($row['Enabled'], _INT);
                $module->regionSpecific = \Kit::ValidateParam($row['RegionSpecific'], _INT);
                $module->previewEnabled = \Kit::ValidateParam($row['PreviewEnabled'], _INT);
                $module->assignable = \Kit::ValidateParam($row['assignable'], _INT);
                $module->schemaVersion = \Kit::ValidateParam($row['SchemaVersion'], _INT);

                $settings = \Kit::ValidateParam($row['settings'], _STRING);
                $module->settings = ($settings == '') ? array() : json_decode($settings, true);

                $entries[] = $module;
            }

            return $entries;
        }
        catch (\Exception $e) {

            \Debug::Error($e->getMessage());

            return array();
        }
    }
}