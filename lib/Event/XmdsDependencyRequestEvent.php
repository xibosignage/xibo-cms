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

use Xibo\Entity\RequiredFile;
use Xibo\Service\LibraryStorageServiceInterface;

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

    private ?LibraryStorageServiceInterface $storage = null;
    private ?string $storageKey = null;

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

    /**
     * Sets the storage and key that readers use instead of the relative path.
     */
    public function setStorage(LibraryStorageServiceInterface $storage, string $key): XmdsDependencyRequestEvent
    {
        $this->storage = $storage;
        $this->storageKey = $key;
        return $this;
    }

    public function getStorage(): ?LibraryStorageServiceInterface
    {
        return $this->storage;
    }

    public function getStorageKey(): ?string
    {
        return $this->storageKey;
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
