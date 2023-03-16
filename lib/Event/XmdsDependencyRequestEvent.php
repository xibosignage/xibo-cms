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

namespace Xibo\Event;

/**
 * Event raised when XMDS receives a request for a file.
 */
class XmdsDependencyRequestEvent extends Event
{
    public static $NAME = 'xmds.dependency.request';

    private $fileType;
    private $id;
    private $path;
    private $fullPath;
    /**
     * @var string|null
     */
    private $realId;

    /**
     * @param $fileType
     * @param $id
     * @param string|null $realId
     */
    public function __construct($fileType, $id, string $realId = null)
    {
        $this->fileType = $fileType;
        $this->id = $id;
        $this->realId = $realId;
    }

    /**
     * Get the relative path to this dependency, from the library folder forwards.
     * @param string $path
     * @return $this
     */
    public function setRelativePathToLibrary(string $path): XmdsDependencyRequestEvent
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set the full path to this dependency, including the library folder if applicable.
     * @param string $fullPath
     * @return $this
     */
    public function setFullPath(string $fullPath): XmdsDependencyRequestEvent
    {
        $this->fullPath = $fullPath;
        return $this;
    }

    public function getRelativePath(): ?string
    {
        return $this->path;
    }

    public function getFullPath(): ?string
    {
        return $this->fullPath;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRealId() : string
    {
        return $this->realId;
    }
}
