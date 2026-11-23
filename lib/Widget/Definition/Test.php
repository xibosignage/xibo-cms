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
 * Represents a test/group of conditions
 */
#[OA\Schema(schema: 'Test')]
class Test implements \JsonSerializable
{
    #[OA\Property()]
    /** @var string */
    public $type;

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Condition'))]
    /** @var Condition[]  */
    public $conditions;

    #[OA\Property()]
    /** @var string|null */
    public $message;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'conditions' => $this->conditions,
        ];
    }
}
