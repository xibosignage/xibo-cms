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

namespace Xibo\Widget\Definition;

/**
 * A class representing an instance of a group of elements
 * @SWG\Definition()
 */
class ElementGroup implements \JsonSerializable
{
    public $id;
    public $top;
    public $left;
    public $width;
    public $height;
    public $layer;
    public $title;
    public $slot;
    public $pinSlot;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'top' => $this->top,
            'left' => $this->left,
            'width' => $this->width,
            'height' => $this->height,
            'layer' => $this->layer,
            'title' => $this->title,
            'slot' => $this->slot,
            'pinSlot' => $this->pinSlot
        ];
    }
}
