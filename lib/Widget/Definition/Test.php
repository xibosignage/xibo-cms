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
 * Represents a test/group of conditions
 * @SWG\Definition()
 */
class Test implements \JsonSerializable
{
    /** @var string */
    public $type;

    /** @var Condition[]  */
    public $conditions;

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
