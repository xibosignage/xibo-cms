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

namespace Xibo\Widget\Provider;

use Xibo\Entity\Module;
use Xibo\Entity\Widget;

/**
 * Xibo default implementation of a Widget Data Provider
 */
class DataProvider implements DataProviderInterface
{
    /** @var \Xibo\Entity\Module */
    private $module;

    /** @var \Xibo\Entity\Widget */
    private $widget;

    /** @var boolean should we use the event? */
    private $isUseEvent = false;

    /** @var array the data */
    private $data = [];

    /**
     * Constructor
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     */
    public function __construct(Module $module, Widget $widget)
    {
        $this->module = $module;
        $this->widget = $widget;
    }

    /**
     * @inheritDoc
     */
    public function getProperty(string $property, $default = null)
    {
        $this->widget->getOptionValue($property, $default);
    }

    /**
     * @inheritDoc
     */
    public function getSetting(string $setting, $default = null)
    {
        return $this->module->settings[$setting] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setIsUseEvent(): DataProviderInterface
    {
        $this->isUseEvent = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function addItem(array $item): DataProviderInterface
    {
        $this->data[] = $item;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function clearData(): DataProviderInterface
    {
        $this->data = [];
        return $this;
    }

    /**
     * Should we use the event or not?
     * @return bool
     */
    public function isUseEvent(): bool
    {
        return $this->isUseEvent;
    }
}
