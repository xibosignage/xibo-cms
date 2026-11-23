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
 * A rule to apply to a property
 */
#[OA\Schema(schema: 'Rule')]
class Rule implements \JsonSerializable
{
    #[OA\Property()]
    public $onSave = true;

    #[OA\Property()]
    public $onStatus = true;

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Test'))]
    /** @var Test[] */
    public $tests;

    public function addRuleTest(Test $test): Rule
    {
        $this->tests[] = $test;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'onSave' => $this->onSave,
            'onStatus' => $this->onStatus,
            'tests' => $this->tests,
        ];
    }
}
