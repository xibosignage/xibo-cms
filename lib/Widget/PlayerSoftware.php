<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlayerVersionFactory;

/**
 * Class PlayerSoftware
 * @package Xibo\Widget
 */
class PlayerSoftware extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Player Software';
            $module->type = 'playersoftware';
            $module->class = 'Xibo\Widget\PlayerSoftware';
            $module->description = 'A module for managing Player Versions';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 0;
            $module->regionSpecific = 0;
            $module->renderAs = null;
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 10;
            $module->validExtensions = 'apk,ipk,wgt';
            $module->installName = 'playersoftware';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritdoc */
    public function edit()
    {
        // Non-editable
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $this->download();
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Yes
        return 1;
    }

    /**
     * @return PlayerVersionFactory
     */
    private function getPlayerVersionFactory()
    {
        return $this->getApp()->container->get('playerVersionFactory');
    }

    public function postProcess($media)
    {
        $this->getLog()->debug('MEDIA IN POST PROCESS IS ' . json_encode($media, JSON_PRETTY_PRINT));
        $storedAs = $media->storedAs;
        $extension = strtolower(substr(strrchr($storedAs, '.'), 1));
        if ($extension == 'apk') {
            $type = 'android';
            $code = strtolower(substr(strrchr($media->fileName, 'R'), 1, 3));
        } elseif ($extension == 'ipk') {
            $type = 'lg';
            $code = strtolower(substr(strrchr($media->fileName, 'R'), 1, 2));
        } else {
            $type = 'sssp';
            $code = strtolower(substr(strrchr($media->fileName, 'R'), 1, 2));
        }

        $version = strtolower(substr(strrchr($media->fileName, 'v'), 1, 3));

        return $this->getPlayerVersionFactory()->create($type, $version, $code, $media->mediaId);
    }

    public function getValidExtensions()
    {
        return $this->module->validExtensions;
    }

}