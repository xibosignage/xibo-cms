<?php
/**
 * Copyright (C) 2018 Spring Signage Ltd
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
namespace Xibo\Event;

use Xibo\Entity\Media;
use Xibo\Widget\ModuleWidget;

class LibraryReplaceEvent extends Event
{
    public static $NAME = 'library.replace.event';

    /** @var ModuleWidget */
    protected $module;

    /** @var Media */
    protected $newMedia;

    /** @var Media */
    protected $oldMedia;

    /**
     * WidgetEditEvent constructor.
     * @param ModuleWidget $module
     * @param Media $newMedia
     * @param Media $oldMedia
     */
    public function __construct($module, $newMedia, $oldMedia)
    {
        $this->module = $module;
        $this->newMedia = $newMedia;
        $this->oldMedia = $oldMedia;
    }

    /**
     * @return ModuleWidget
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @return Media
     */
    public function getOldMedia()
    {
        return $this->oldMedia;
    }

    /**
     * @return Media
     */
    public function getNewMedia()
    {
        return $this->newMedia;
    }
}