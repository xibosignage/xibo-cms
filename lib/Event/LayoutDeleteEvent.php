<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Xibo\Entity\Layout;

/**
 * Class LayoutBuildEvent
 * @package Xibo\Event
 */
class LayoutDeleteEvent extends Event
{
    public static $NAME = 'layout.delete.event';

    /** @var Layout */
    private Layout $layout;

    /**
     * LayoutDeleteEvent constructor.
     * @param $layout
     */
    public function __construct($layout)
    {
        $this->layout = $layout;
    }

    /**
     * @return Layout
     */
    public function getLayout() : Layout
    {
        return $this->layout;
    }
}
