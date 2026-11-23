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

namespace Xibo\Xmds;

/**
 * Writes a download to the PHP response.
 */
class PhpHttpOutput implements HttpOutputInterface
{
    public function status(int $status): void
    {
        http_response_code($status);
    }

    public function header(string $header): void
    {
        header($header);
    }

    public function write(string $bytes): void
    {
        echo $bytes;
    }

    public function isAborted(): bool
    {
        // What's written so far is sent first, as a client that has gone is only noticed then.
        flush();
        return connection_aborted() === 1;
    }
}
