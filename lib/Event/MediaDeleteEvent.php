<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

use phpDocumentor\Reflection\Types\Boolean;
use Xibo\Entity\Media;

class MediaDeleteEvent extends Event
{
    public static $NAME = 'library.media.delete.event';

    /** @var Media */
    private $media;
    /**
     * @var Media|null
     */
    private $parentMedia;

    /** @var Boolean */
    private $purge;

    /**
     * MediaDeleteEvent constructor.
     * @param $media
     */
    public function __construct($media, $parentMedia = null, $purge = false)
    {
        $this->media = $media;
        $this->parentMedia = $parentMedia;
        $this->purge = $purge;
    }

    /**
     * @return Media
     */
    public function getMedia() : Media
    {
        return $this->media;
    }

    public function getParentMedia()
    {
        return $this->parentMedia;
    }

    public function isSetToPurge()
    {
        return $this->purge;
    }
}
