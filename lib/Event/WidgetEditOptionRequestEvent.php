<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Xibo\Entity\Widget;

/**
 * An event fired by a widget when presenting its properties
 * should be used by a connector to provide additional options to a dropdown
 *
 * TODO: this is pulled in from 3.2.0, but the code to fire the event doesn't exist in the new widget system
 *  this will need some thought
 */
class WidgetEditOptionRequestEvent extends Event
{
    public static $NAME = 'widget.edit.option.event';
    private $widget;
    private $options;

    public function __construct($widget)
    {
        $this->widget = $widget;
    }

    /**
     * @return \Xibo\Entity\Widget|null
     */
    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    /**
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            $this->options = [];
        }

        return $this->options;
    }

    /**
     * @return $this
     */
    public function setOptions(array $options): WidgetEditOptionRequestEvent
    {
        $this->options = $options;
        return $this;
    }
}
