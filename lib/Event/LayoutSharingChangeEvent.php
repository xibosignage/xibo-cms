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

/**
 * Event raised when a Layout's sharing has been changed.
 */
class LayoutSharingChangeEvent extends Event
{
    public static string $NAME = 'layout.sharing.change.event';

    /** @var int[] */
    private array $canvasRegionIds;

    /**
     * LayoutSharingChangeEvent constructor.
     * @param int $campaignId
     */
    public function __construct(private readonly int $campaignId)
    {
        $this->canvasRegionIds = [];
    }

    /**
     * @return int
     */
    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    /**
     * Get the Canvas Region ID
     * @return int[]
     */
    public function getCanvasRegionIds(): array
    {
        return $this->canvasRegionIds;
    }

    /**
     * Set the Canvas Region ID
     */
    public function addCanvasRegionId(int $regionId): void
    {
        $this->canvasRegionIds[] = $regionId;
    }
}
