<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
