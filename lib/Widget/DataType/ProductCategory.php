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

namespace Xibo\Widget\DataType;

use Xibo\Widget\Definition\DataType;

/**
 * Product Category (primarily used for the Menu Board component)
 */
class ProductCategory implements \JsonSerializable, DataTypeInterface
{
    public $name;
    public $description;
    public $image;

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = 'product-category';
        $dataType->name = __('Product Category');
        $dataType->addField('name', __('Name'), 'string');
        $dataType->addField('description', __('Description'), 'string');
        $dataType->addField('image', __('Image'), 'int');
        return $dataType;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
        ];
    }
}
