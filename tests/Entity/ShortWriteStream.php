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

namespace Xibo\Tests\Entity;

/**
 * A stream that keeps all but the last byte of each write, as a full disk can.
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ShortWriteStream
{
    /** @var array<string, string> */
    public static array $files = [];

    public $context;

    private string $path;

    public function stream_open(string $path, string $mode): bool
    {
        $this->path = $path;
        self::$files[$path] = '';
        return true;
    }

    public function stream_write(string $data): int
    {
        $kept = max(0, strlen($data) - 1);
        self::$files[$this->path] .= substr($data, 0, $kept);
        return $kept;
    }

    public function stream_close(): void
    {
    }

    public function unlink(string $path): bool
    {
        unset(self::$files[$path]);
        return true;
    }

    /**
     * Any path without a file is a writable folder.
     */
    public function url_stat(string $path, int $flags): array|false
    {
        if (isset(self::$files[$path])) {
            return ['mode' => 0100666, 'size' => strlen(self::$files[$path])];
        }
        return ['mode' => 0040777];
    }
}
