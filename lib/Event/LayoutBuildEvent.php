<?php
/**
 * Copyright (C) 2016-2018 Spring Signage Ltd
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

use Xibo\Entity\Layout;

/**
 * Class LayoutBuildEvent
 * @package Xibo\Event
 */
class LayoutBuildEvent extends Event
{
    const NAME = 'layout.build';

    /** @var  Layout */
    protected $layout;

    /** @var  \DOMDocument */
    protected $document;

    /**
     * LayoutBuildEvent constructor.
     * @param $layout
     * @param $document
     */
    public function __construct($layout, $document)
    {
        $this->layout = $layout;
        $this->document = $document;
    }

    /**
     * @return \DOMDocument
     */
    public function getDocument()
    {
        return $this->document;
    }
}