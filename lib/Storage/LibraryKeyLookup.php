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

namespace Xibo\Storage;

use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Checks which keys exist for one request, listing the storage once for many keys and treating a storage error as
 * none.
 */
class LibraryKeyLookup
{
    /**
     * Above this many keys, storage without local files is listed once rather than checked key by key. A page of a
     * grid is checked key by key (cheap, and cached), and a long or unpaged list doesn't make a check per row.
     */
    public const LIST_ABOVE_KEYS = 50;

    private bool $listsKeys;

    /** @var array<string, true>|null */
    private ?array $listedKeys = null;

    private bool $storageFailed = false;

    public function __construct(
        private readonly LibraryStorageServiceInterface $storage,
        private readonly LogServiceInterface $log,
        private readonly string $description,
        int $keyCount
    ) {
        $this->listsKeys = $keyCount > self::LIST_ABOVE_KEYS && !$storage->hasLocalFiles();
    }

    public function has(string $key): bool
    {
        if ($this->storageFailed) {
            return false;
        }

        try {
            if (!$this->listsKeys) {
                return $this->storage->exists($key);
            }

            if ($this->listedKeys === null) {
                $listedKeys = [];
                foreach ($this->storage->listKeys() as $item) {
                    $listedKeys[$item['key']] = true;
                }
                $this->listedKeys = $listedKeys;
            }
            return isset($this->listedKeys[$key]);
        } catch (GeneralException $exception) {
            $this->storageFailed = true;
            $this->log->error('Unable to check ' . $this->description . ': ' . $exception->getMessage());
            return false;
        }
    }
}
