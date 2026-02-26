<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
