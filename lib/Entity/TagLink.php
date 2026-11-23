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

namespace Xibo\Entity;

use OpenApi\Attributes as OA;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * @package Xibo\Entity
 */
#[OA\Schema(schema: 'TagLink')]
class TagLink implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The Tag')]
    /**
     * @var string
     */
    public $tag;

    #[OA\Property(description: 'The Tag ID')]
    /**
     * @var int
     */
    public $tagId;

    #[OA\Property(description: 'The Tag Value')]
    /**
     * @var string
     */
    public $value = null;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function validateOptions(Tag $tag)
    {
        if ($tag->options) {
            if (!is_array($tag->options)) {
                $tag->options = json_decode($tag->options);
            }

            if (!empty($this->value) && !in_array($this->value, $tag->options)) {
                throw new InvalidArgumentException(
                    sprintf(
                        __('Provided tag value %s, not found in tag %s options, please select the correct value'),
                        $this->value,
                        $this->tag
                    ),
                    'tagValue'
                );
            }
        }

        if (empty($this->value) && $tag->isRequired === 1) {
            throw new InvalidArgumentException(
                sprintf(
                    __('Selected Tag %s requires a value, please enter the Tag in %s|Value format or provide Tag value in the dedicated field.'),
                    $this->tag,
                    $this->tag
                ),
                'tagValue'
            );
        }
    }
}
