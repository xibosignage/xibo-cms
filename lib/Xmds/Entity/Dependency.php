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

namespace Xibo\Xmds\Entity;

/**
 * XMDS Depedency
 * represents a player dependency
 */
class Dependency
{
    const LEGACY_ID_OFFSET_FONT = 100000000;
    const LEGACY_ID_OFFSET_PLAYER_SOFTWARE = 200000000;
    const LEGACY_ID_OFFSET_ASSET = 300000000;
    const LEGACY_ID_OFFSET_DATA_CONNECTOR = 400000000;

    public $fileType;
    public $legacyId;
    public $id;
    public $path;
    public $size;
    public $md5;
    public $isAvailableOverHttp;

    /**
     * Prior versions of XMDS need to use a legacyId to download the file via GetFile.
     * This is a negative number in a range (to avoid collisions with existing IDs). Each dependency type should
     * resolve to a different negative number range.
     * The "real id" set on $this->id is saved in required files as the realId and used to resolve requests for this
     * type of file.
     * @param string $fileType
     * @param string|int $id
     * @param int $legacyId
     * @param string $path
     * @param int $size
     * @param string $md5
     * @param bool $isAvailableOverHttp
     */
    public function __construct(
        string $fileType,
        $id,
        int $legacyId,
        string $path,
        int $size,
        string $md5,
        bool $isAvailableOverHttp = true
    ) {
        $this->fileType = $fileType;
        $this->id = $id;
        $this->legacyId = $legacyId;
        $this->path = $path;
        $this->size = $size;
        $this->md5 = $md5;
        $this->isAvailableOverHttp = $isAvailableOverHttp;
    }
}
