<?php
/*
 * Copyright (c) 2023  Xibo Signage Ltd
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
 *
 */

namespace Xibo\Event;

use Xibo\Entity\Widget;

/**
 * An event fired by a widget when presenting its properties
 * should be used by a connector to provide additional options to a dropdown
 */
class WidgetEditOptionRequestEvent extends Event
{
    public static $NAME = 'widget.edit.option.event';

    /** @var \Xibo\Entity\Widget  */
    private $widget;

    /** @var string  */
    private $propertyId;

    /** @var mixed  */
    private $propertyValue;

    /** @var array|null */
    private $options;

    public function __construct(Widget $widget, string $propertyId, $propertyValue)
    {
        $this->widget = $widget;
        $this->propertyId = $propertyId;
        $this->propertyValue = $propertyValue;
    }

    /**
     * @return \Xibo\Entity\Widget|null
     */
    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    /**
     * Which property is making this request?
     * @return string|null The ID of the property `id=""`
     */
    public function getPropertyId(): ?string
    {
        return $this->propertyId;
    }

    /**
     * @return mixed
     */
    public function getPropertyValue()
    {
        return $this->propertyValue;
    }

    /**
     * Get the options array
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            $this->options = [];
        }

        return $this->options;
    }

    /**
     * Set a new options array
     * @return $this
     */
    public function setOptions(array $options): WidgetEditOptionRequestEvent
    {
        $this->options = $options;
        return $this;
    }
}
