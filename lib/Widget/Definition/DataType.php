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
