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

use Xibo\Entity\Display;

/**
 * Event raised to find the most displays this CMS can authorise, and how many licence slots are in use. Without a
 * listener there is no limit, and every authorised display takes a slot.
 */
class DisplayLimitEvent extends Event
{
    public static $NAME = 'display.limit';

    /** The most displays that can be authorised, or null for no limit. */
    private ?int $limit = null;

    /** Whether displays over the limit can be un-authorised. */
    private bool $canUnauthorise = false;

    /** Licence slots in use, or null when every authorised display takes one. */
    private ?int $used = null;

    /** Whether something other than the CMS (the licensing server) decides if the display gets a slot. */
    private bool $isDecidedElsewhere = false;

    /**
     * @param Display|null $display the display being authorised, when there is one
     */
    public function __construct(private readonly ?Display $display = null)
    {
    }

    /**
     * The display being authorised, or null when only the limit and usage are wanted.
     */
    public function getDisplay(): ?Display
    {
        return $this->display;
    }

    /**
     * The most displays that can be authorised, or null for no limit.
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Whether displays over the limit can be un-authorised.
     */
    public function canUnauthorise(): bool
    {
        return $this->canUnauthorise;
    }

    /**
     * Sets the limit, and whether displays over it can be un-authorised.
     */
    public function setLimit(?int $limit, bool $canUnauthorise): void
    {
        $this->limit = $limit;
        $this->canUnauthorise = $canUnauthorise;
    }

    /**
     * Licence slots in use, or null when every authorised display takes one (count them).
     */
    public function getUsed(): ?int
    {
        return $this->used;
    }

    public function setUsed(?int $used): void
    {
        $this->used = $used;
    }

    /**
     * Whether something other than the CMS decides if the display being authorised gets a slot, so the CMS does
     * not check the limit for it.
     */
    public function isDecidedElsewhere(): bool
    {
        return $this->isDecidedElsewhere;
    }

    public function setDecidedElsewhere(bool $isDecidedElsewhere): void
    {
        $this->isDecidedElsewhere = $isDecidedElsewhere;
    }
}
