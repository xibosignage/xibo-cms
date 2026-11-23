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

namespace Xibo\Helper;

/**
 * The folders module definitions, data types and templates are loaded from.
 */
class ModuleFolders
{
    /**
     * @return array<array{path: string, ownership: string}>
     */
    public static function getAll(): array
    {
        $folders = [
            ['path' => PROJECT_ROOT . '/modules', 'ownership' => 'system'],
        ];

        $folders[] = ['path' => PROJECT_ROOT . '/custom/modules', 'ownership' => 'custom'];

        return $folders;
    }

    /**
     * Glob a pattern (relative to each module folder) across all module folders.
     * @param string $pattern e.g. '*.xml' or 'datatypes/*.xml'
     * @return string[]
     */
    public static function glob(string $pattern): array
    {
        $files = [];
        foreach (self::getAll() as $folder) {
            $files = array_merge($files, glob($folder['path'] . '/' . $pattern) ?: []);
        }

        return $files;
    }
}
