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
 * Class LayoutBuildRegionEvent
 * @package Xibo\Event
 */
class LayoutBuildRegionEvent extends Event
{
    const NAME = 'layout.build.region';

    /** @var  int */
    protected $regionId;

    /** @var  \DOMElement */
    protected $regionNode;

    /**
     * LayoutBuildEvent constructor.
     * @param int $regionId
     * @param \DOMElement $regionNode
     */
    public function __construct($regionId, $regionNode)
    {
        $this->regionId = $regionId;
        $this->regionNode = $regionNode;
    }

    /**
     * @return \DOMElement
     */
    public function getRegionNode()
    {
        return $this->regionNode;
    }
}
