<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Widget.php) is part of Xibo.
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


namespace Xibo\Entity;


class Widget {

    public $widgetId;
    public $ownerId;

    public $type;
    public $duration;

    public $widgetOptions;

    // A widget might be linked to file based media
    public $media;

    public function __construct()
    {
        $this->widgetOptions = array();
    }

    public function __clone()
    {
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);

        // No need to clone the media
    }

    public function save()
    {
        if ($this->widgetId == null || $this->widgetId == 0)
            $this->add();
        else
            $this->update();

        foreach ($this->widgetOptions as $widgetOption) {
            /* @var \Xibo\Entity\WidgetOption $widgetOption */
            $widgetOption->save();
        }
    }

    public function delete()
    {

    }

    private function add()
    {

    }

    private function update()
    {

    }
}