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

namespace Xibo\Entity;

use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * @SWG\Definition()
 */
class TagLink implements \JsonSerializable
{
    use EntityTrait;
    /**
     * @SWG\Property(description="The Tag")
     * @var string
     */
    public $tag;
    /**
     * @SWG\Property(description="The Tag ID")
     * @var int
     */
    public $tagId;
    /**
     * @SWG\Property(description="The Tag Value")
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
