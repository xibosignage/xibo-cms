<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Xibo\Entity\Widget;

/**
 * Widget Edit Event
 */
class SubPlaylistWidgetsEvent extends Event
{
    public static $NAME = 'widget.sub-playlist.widgets';

    /** @var \Xibo\Entity\Widget */
    protected $widget;

    /** @var Widget[] */
    private $widgets = [];

    /** @var int */
    private $tempId;

    /**
     * constructor.
     * @param \Xibo\Entity\Widget $widget
     * @param int|null $tempId
     */
    public function __construct(\Xibo\Entity\Widget $widget, ?int $tempId)
    {
        $this->widget = $widget;
        $this->tempId = $tempId ?? 0;
    }

    /**
     * @return \Xibo\Entity\Widget
     */
    public function getWidget(): Widget
    {
        return $this->widget;
    }

    /**
     * @return int
     */
    public function getTempId(): int
    {
        return $this->tempId;
    }

    /**
     * Get the duration
     * @return \Xibo\Entity\Widget[]
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    /**
     * @param Widget[] $widgets
     * @return $this
     */
    public function setWidgets(array $widgets): SubPlaylistWidgetsEvent
    {
        $this->widgets += $widgets;
        return $this;
    }
}
