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
 * A duration provider is used to return the duration for a Widget which has a media file
 */
interface DurationProviderInterface
{
    /**
     * Get property
     * Properties are set on Widgets and can be things like "feedUrl"
     *  the property must exist in module properties for this type of widget
     * @param string $property The property name
     * @param mixed $default An optional default value. The return will be cast to the datatype of this default value.
     * @return mixed
     */
    public function getProperty(string $property, $default = null);

    /**
     * Get the fully qualified path name of the file that needs its duration assessed
     * @return string the fully qualified path to the file
     */
    public function getFile(): string;

    /**
     * Set the duration in seconds
     * @param int $seconds the duration in seconds
     * @return \Xibo\Widget\Provider\DurationProviderInterface
     */
    public function setDuration(int $seconds): DurationProviderInterface;
}
