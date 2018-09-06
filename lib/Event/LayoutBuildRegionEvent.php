<?php
/**
 * Copyright (C) 2016-2018 Xibo Signage Ltd
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