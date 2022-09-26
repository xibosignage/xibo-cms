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

namespace Xibo\Xmds\Entity;

/**
 * XMDS Depedency
 * represents a player dependency
 */
class Dependency
{
    public $fileType;
    public $id;
    public $path;
    public $size;
    public $md5;
    public $isAvailableOverHttp;
    
    public function __construct(
        string $fileType,
        int $id,
        string $path,
        int $size,
        string $md5,
        bool $isAvailableOverHttp = true
    ) {
        $this->fileType = $fileType;
        $this->id = $id;
        $this->path = $path;
        $this->size = $size;
        $this->md5 = $md5;
        $this->isAvailableOverHttp = $isAvailableOverHttp;
    }
}
