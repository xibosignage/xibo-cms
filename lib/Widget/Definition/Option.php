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

namespace Xibo\Widget\Definition;

/**
 * Option: typically used when paired with a dropdown
 * @SWG\Definition()
 */
class Option implements \JsonSerializable
{
    /**
     * @SWG\Property(description="Name")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="Image: optional image asset")
     * @var string
     */
    public $image;

    /**
     * @SWG\Property(description="Set")
     * @var string[]
     */
    public $set = [];

    /**
     * * @SWG\Property(description="Title: shown in the dropdown/select")
     * @var string
     */
    public $title;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'image' => $this->image,
            'set' => $this->set,
            'title' => $this->title
        ];
    }
}
