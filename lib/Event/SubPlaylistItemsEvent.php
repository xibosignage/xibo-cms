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
use Xibo\Widget\SubPlaylistItem;

/**
 * Widget Edit Event
 */
class SubPlaylistItemsEvent extends Event
{
    public static $NAME = 'widget.sub-playlist.items';

    /** @var \Xibo\Entity\Widget */
    protected $widget;

    /** @var SubPlaylistItem[] */
    private $items = [];

    /**
     * constructor.
     * @param \Xibo\Entity\Widget $widget
     */
    public function __construct(\Xibo\Entity\Widget $widget)
    {
        $this->widget = $widget;
    }

    /**
     * @return Widget
     */
    public function getWidget(): Widget
    {
        return $this->widget;
    }

    /**
     * @return SubPlaylistItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array $items
     * @return $this
     */
    public function setItems(array $items): SubPlaylistItemsEvent
    {
        $this->items += $items;
        return $this;
    }
}
