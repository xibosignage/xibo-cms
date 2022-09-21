<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
    /** @var array */
    private $properties;

    /** @var int Duration in seconds */
    private $duration;

    /** @var bool Has the duration been set? */
    private $isDurationSet = false;

    /**
     * Constructor
     * @param int $duration
     * @param array $properties
     */
    public function __construct(int $duration, array $properties)
    {
        $this->duration = $duration;
        $this->properties = $properties;
    }

    /**
     * @inheritDoc
     */
    public function getProperty(string $property, $default = null)
    {
        return $this->properties[$property] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getFile(): string
    {
        return $this->getProperty('file');
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
        return $this->duration;
    }

    /**
     * @inheritDoc
     */
    public function isDurationSet(): bool
    {
        return $this->isDurationSet;
    }
}
