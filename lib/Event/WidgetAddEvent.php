<?php
/**
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

use Xibo\Entity\Module;
use Xibo\Entity\Widget;

/**
 * Widget Add
 * ----------
 * Call when a new non-file based widget is added to a Layout
 */
class WidgetAddEvent extends Event
{
    public static $NAME = 'widget.add';

    /** @var \Xibo\Entity\Module */
    protected $module;

    /** @var \Xibo\Entity\Widget */
    protected $widget;

    /**
     * WidgetEditEvent constructor.
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     */
    public function __construct(Module $module, Widget $widget)
    {
        $this->module = $module;
        $this->widget = $widget;
    }

    /**
     * @return \Xibo\Entity\Module
     */
    public function getModule(): Module
    {
        return $this->module;
    }

    /**
     * @return \Xibo\Entity\Widget
     */
    public function getWidget(): Widget
    {
        return $this->widget;
    }
}
