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

use OpenApi\Attributes as OA;

/**
 * Option: typically used when paired with a dropdown
 */
#[OA\Schema(schema: 'Option')]
class Option implements \JsonSerializable
{
    #[OA\Property(description: 'Name')]
    /**
     * @var string
     */
    public $name;

    #[OA\Property(description: 'Image: optional image asset')]
    /**
     * @var string
     */
    public $image;

    #[OA\Property(description: 'Set', type: 'array', items: new OA\Items(type: 'string'))]
    /**
     * @var string[]
     */
    public $set = [];

    #[OA\Property(description: 'Title: shown in the dropdown/select')]
    /**
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
