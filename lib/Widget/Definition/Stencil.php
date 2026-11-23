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
 * A Stencil is a template which is rendered in the server and/or client
 * it can optionally have properties and/or elements
 */
#[OA\Schema(schema: 'Stencil')]
class Stencil implements \JsonSerializable
{
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Element'))]
    /** @var \Xibo\Widget\Definition\Element[] */
    public $elements = [];

    #[OA\Property()]
    /** @var string|null */
    public $twig;

    #[OA\Property()]
    /** @var string|null */
    public $hbs;

    #[OA\Property()]
    /** @var string|null */
    public $head;

    #[OA\Property()]
    /** @var string|null */
    public $style;

    #[OA\Property()]
    /** @var string|null */
    public $hbsId;

    #[OA\Property()]
    /** @var double Optional positional information if contained as part of an element group */
    public $width;

    #[OA\Property()]
    /** @var double Optional positional information if contained as part of an element group */
    public $height;

    #[OA\Property()]
    /** @var double Optional positional information if contained as part of an element group */
    public $gapBetweenHbs;

    #[OA\Property(description: 'An array of element groups', type: 'array', items: new OA\Items(ref: '#/components/schemas/ElementGroup'))]
    /**
     * @var \Xibo\Widget\Definition\ElementGroup[]
     */
    public $elementGroups = [];

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'twig' => $this->twig,
            'hbsId' => $this->hbsId,
            'hbs' => $this->hbs,
            'head' => $this->head,
            'style' => $this->style,
            'width' => $this->width,
            'height' => $this->height,
            'gapBetweenHbs' => $this->gapBetweenHbs,
            'elements' => $this->elements,
            'elementGroups' => $this->elementGroups
        ];
    }
}
