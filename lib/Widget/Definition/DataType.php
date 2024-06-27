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
 * A module data type
 */
class DataType implements \JsonSerializable
{
    public $id;
    public $name;

    /** @var \Xibo\Widget\Definition\Field[] */
    public $fields = [];

    public function addField(string $id, string $title, string $type, bool $isRequired = false): DataType
    {
        $field = new Field();
        $field->id = $id;
        $field->type = $type;
        $field->title = $title;
        $field->isRequired = $isRequired;
        $this->fields[] = $field;
        return $this;
    }

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fields' => $this->fields,
        ];
    }
}
