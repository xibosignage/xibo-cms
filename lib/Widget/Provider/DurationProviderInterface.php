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
 * A duration provider is used to return the duration for a Widget which has a media file
 */
interface DurationProviderInterface
{
    /**
     * Get the Module
     * @return Module
     */
    public function getModule(): Module;

    /**
     * Get the Widget
     * @return Widget
     */
    public function getWidget(): Widget;

    /**
     * Get the duration
     * @return int the duration in seconds
     */
    public function getDuration(): int;

    /**
     * Set the duration in seconds
     * @param int $seconds the duration in seconds
     * @return \Xibo\Widget\Provider\DurationProviderInterface
     */
    public function setDuration(int $seconds): DurationProviderInterface;

    /**
     * @return bool true if the duration has been set
     */
    public function isDurationSet(): bool;
}
