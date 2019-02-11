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
        $version = '';
        $code = null;
        $type = '';
        $explode = explode('_', $media->fileName);
        $explodeExt = explode('.', $media->fileName);
        $playerShowVersion = $explodeExt[0];

        // standard releases
        if (count($explode) === 5) {
            if (strpos($explode[4], '.') !== false) {
                $explodeExtension = explode('.', $explode[4]);
                $explode[4] = $explodeExtension[0];
            }

            if (strpos($explode[3], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (strpos($explode[4], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code;
            // for DSDevices specific apk
        } elseif (count($explode) === 6) {
            if (strpos($explode[5], '.') !== false) {
                $explodeExtension = explode('.', $explode[5]);
                $explode[5] = $explodeExtension[0];
            }
            if (strpos($explode[3], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (strpos($explode[4], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[5];
            // for white labels
        } elseif (count($explode) === 3) {
            if (strpos($explode[2], '.') !== false) {
                $explodeExtension = explode('.', $explode[2]);
                $explode[2] = $explodeExtension[0];
            }
            if (strpos($explode[1], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[1], 'v'), 1, 3)) ;
            }
            if (strpos($explode[2], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[2], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[0];
        } else {
            $this->getLog()->info('Exact matches to the file name pattern not found for file ' . $explodeExt[0] . ' Please adjust Version and Code on Player Version page.');
        }


        $storedAs = $media->storedAs;
        $extension = strtolower(substr(strrchr($storedAs, '.'), 1));

        if ($extension == 'apk') {
            $type = 'android';
        } elseif ($extension == 'ipk') {
            $type = 'lg';
        } elseif ($extension == 'wgt') {
            $type = 'sssp';
        }

        return $this->getPlayerVersionFactory()->create($type, $version, $code, $media->mediaId, $playerShowVersion);
    }

    public function getValidExtensions()
    {
        return $this->module->validExtensions;
    }

}