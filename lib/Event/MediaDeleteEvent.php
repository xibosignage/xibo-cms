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
