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
