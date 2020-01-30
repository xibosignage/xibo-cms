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

namespace Xibo\Widget;

use Jenssegers\Date\Date;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

/**
 * Interface ModuleInterface
 * @package Xibo\Widget
 */
interface ModuleInterface
{
    /**
     * Add Widget
     * @return mixed
     * @throws XiboException
     */
    public function add();

    /**
     * Edit Widget
     * @return mixed
     * @throws XiboException
     */
    public function edit();

    /**
     * Delete Widget
     * @return mixed
     * @throws XiboException
     */
    public function delete();

    /**
     * Return the name of the media as input by the user
     * @return string
     */
    public function getName();

    /**
     * @param $setting
     * @param null $default
     * @return string
     */
    public function getSetting($setting, $default = NULL);

    /**
     * Get dynamic content for a specified tab
     * @param string $tab
     * @return mixed
     */
    public function getTab($tab);

    /**
     * Preview this module
     * @param $width
     * @param $height
     * @param int $scaleOverride
     * @return mixed
     */
    public function preview($width, $height, $scaleOverride = 0);

    /**
     * Is the Module Valid?
     * @return int (1 = Yes, 2 = Player Dependent)
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function isValid();

    /**
     * Install or Upgrade this module
     *    Expects $this->codeSchemaVersion to be set by the module.
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory);

    /**
     * Install module
     * @return mixed
     */
    public function installModule();

    /**
     * Module Settings
     * @return mixed
     */
    public function settings();

    /**
     * Get the Modified Date of this Widget
     * @param int $displayId The displayId, or 0 for preview
     * @return Date the date this widgets was modified
     */
    public function getModifiedDate($displayId);

    /**
     * Get the Cache Date for this Widget using the cache key
     * @param int $displayId The displayId we're requesting for, or 0 for preview
     * @return Date
     */
    public function getCacheDate($displayId);

    /**
     * Set the Cache Date using the cache key
     * @param int $displayId The displayId we're requesting for, or 0 for preview
     */
    public function setCacheDate($displayId);

    /**
     * Get Cache Key
     * @param int $displayId The displayId we're requesting for, or 0 for preview
     * @return string
     */
    public function getCacheKey($displayId);

    /**
     * Get the lock key for this widget.
     * should return the most unique lock key required to prevent concurrent access
     * normally the default is fine, unless the module fetches some external images
     * @return string
     */
    public function getLockKey();

    /**
     * Get Cache Duration
     * @return int the number of seconds the widget should be cached.
     */
    public function getCacheDuration();

    /**
     * Is the Cache for this Module display specific, or can we assume that if we've calculated the
     * cache for 1 display, we've calculated for all.
     * this should reflect the cacheKey
     * @return bool
     */
    public function isCacheDisplaySpecific();

    /**
     * Get Resource or Cache
     * @param int $displayId The displayId we're requesting for, or 0 for preview
     * @return string
     * @throws XiboException
     */
    public function getResourceOrCache($displayId);

    /**
     * Get Resource
     * @param int $displayId The displayId we're requesting for, or 0 for preview
     * @return string
     */
    public function getResource($displayId);

    /**
     * Check if status message is not empty
     * @return bool
     */
    public function hasStatusMessage();

    /**
     * Gets the Module status message
     * @return string
     */
    public function getStatusMessage();
}
