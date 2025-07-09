<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

namespace Xibo\Event;

class TagEditEvent extends Event
{
    public static $NAME = 'tag.edit.event';
    /**
     * @var int
     */
    private $tagId;

    /**
     * @var string
     */
    private $oldTag;

    /**
     * @var string
     */
    private $newTag;

    public function __construct(int $tagId, ?string $oldTag = null, ?string $newTag = null)
    {
        $this->tagId = $tagId;
        $this->oldTag = $oldTag;
        $this->newTag = $newTag;
    }

    /**
     * @return int
     */
    public function getTagId(): int
    {
        return $this->tagId;
    }

    /**
     * @return string
     */
    public function getOldTag(): string
    {
        return $this->oldTag;
    }

    /**
     * @return string
     */
    public function getNewTag(): string
    {
        return $this->newTag;
    }
}
