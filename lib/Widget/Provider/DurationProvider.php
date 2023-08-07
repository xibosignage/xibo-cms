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

namespace Xibo\Widget\Provider;

use Xibo\Entity\Module;
use Xibo\Entity\Widget;

/**
 * Xibo's default implementation of the Duration Provider
 */
class DurationProvider implements DurationProviderInterface
{
    /** @var Module */
    private $module;

    /** @var Widget */
    private $widget;

    /** @var int Duration in seconds */
    private $duration;

    /** @var bool Has the duration been set? */
    private $isDurationSet = false;

    /**
     * Constructor
     * @param Module $module
     * @param Widget $widget
     */
    public function __construct(Module $module, Widget $widget)
    {
        $this->module = $module;
        $this->widget = $widget;
    }

    /**
     * @inheritDoc
     */
    public function setDuration(int $seconds): DurationProviderInterface
    {
        $this->isDurationSet = true;
        $this->duration = $seconds;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getDuration(): int
    {
        return $this->duration ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function isDurationSet(): bool
    {
        return $this->isDurationSet;
    }

    /**
     * @inheritDoc
     */
    public function getModule(): Module
    {
        return $this->module;
    }

    /**
     * @inheritDoc
     */
    public function getWidget(): Widget
    {
        return $this->widget;
    }
}
