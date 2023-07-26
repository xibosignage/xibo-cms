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
 * Product DataType (primarily used for the Menu Board component)
 */
class Product implements \JsonSerializable, DataTypeInterface
{
    public $name;
    public $price;
    public $description;
    public $availability;
    public $allergyInfo;
    public $mediaId;
    public $productOptions;

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = 'Product';
        $dataType->name = __('Proudct');
        $dataType->addField('name', 'Name', 'string');
        $dataType->addField('price', 'Price', 'string');
        $dataType->addField('description', 'Description', 'string');
        $dataType->addField('availability', 'Availability', 'int');
        $dataType->addField('allergyInfo', 'allergyInfo', 'string');
        $dataType->addField('mediaId', 'mediaId', 'int');
        $dataType->addField('productOptions', 'productOptions', 'array');
        return $dataType;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo,
            'mediaId' => $this->mediaId,
            'productOptions' => $this->productOptions,
        ];
    }
}