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
 * Fixed windows of time that URLs are made as of, so a URL stays the same, and cached, for a whole window.
 */
class CacheableUrlWindow
{
    /** Window length, and how long browsers cache what a URL answers. */
    public const SECONDS = 518400;

    /**
     * The start of the window $now falls in.
     */
    public static function start(int $now): int
    {
        return intdiv($now, self::SECONDS) * self::SECONDS;
    }

    /**
     * The Cache-Control header a URL made for a window answers with.
     */
    public static function cacheControl(): string
    {
        return 'private, max-age=' . self::SECONDS;
    }
}
