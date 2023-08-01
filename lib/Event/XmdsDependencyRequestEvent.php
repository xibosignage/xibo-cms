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

use Xibo\Entity\RequiredFile;

/**
 * Event raised when XMDS receives a request for a file.
 */
class XmdsDependencyRequestEvent extends Event
{
    public static $NAME = 'xmds.dependency.request';

    private $fileType;
    private $id;
    private $path;
    /**
     * @var string|null
     */
    private $realId;

    /**
     * @param RequiredFile $file
     */
    public function __construct(RequiredFile $file)
    {
        $this->fileType = $file->fileType;
        $this->id = $file->itemId;
        $this->realId = $file->realId;
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

    public function getRelativePath(): ?string
    {
        return $this->path;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * The Real ID of the dependency request
     *  this will always be the ID of the item (asset/font/etc.) regardless of XMDS schema version being used.
     * @return string
     */
    public function getRealId() : string
    {
        return $this->realId;
    }
}
