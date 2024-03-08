<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
