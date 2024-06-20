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

namespace Xibo\Event;

/**
 * An event which triggers the provided task to Run Now (at the next XTR poll)
 *  optionally clears a cache key to provide further instructions to the task that's running
 */
class TriggerTaskEvent extends Event
{
    public static string $NAME = 'trigger.task.event';

    /**
     * @param string $className Class name of the task to be run
     * @param string $key Cache Key to be dropped
     */
    public function __construct(
        private readonly string $className,
        private readonly string $key = ''
    ) {
    }

    /**
     * Returns the class name for the task to be run
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Returns the cache key to be dropped
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
