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

namespace Xibo\Widget\Provider;

/**
 * Xibo's default implementation of the Duration Provider
 */
class DurationProvider implements DurationProviderInterface
{
    /** @var string */
    private $file;

    /** @var int Duration in seconds */
    private $duration;

    /** @var bool Has the duration been set? */
    private $isDurationSet = false;

    /**
     * Constructor
     * @param string|null $file
     * @param int|null $duration
     */
    public function __construct(string $file, ?int $duration)
    {
        $this->file = $file;
        $this->duration = $duration;
    }

    /**
     * @inheritDoc
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @inheritDoc
     */
    public function setDuration(int $seconds): DurationProviderInterface
    {
        $this->isDurationSet = true;
        $this->duration = $seconds;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getDuration(): int
    {
        return $this->duration ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function isDurationSet(): bool
    {
        return $this->isDurationSet;
    }
}
