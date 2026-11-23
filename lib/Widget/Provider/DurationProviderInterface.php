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
