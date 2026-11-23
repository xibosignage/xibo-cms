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

namespace Xibo\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Service\LibraryStorageServiceInterface;

class CacheableUrlWindowTest extends TestCase
{
    public function testEveryTimeInAWindowSharesItsStart()
    {
        $start = CacheableUrlWindow::start(1_800_000_000);

        $this->assertSame(0, $start % CacheableUrlWindow::SECONDS);
        $this->assertSame($start, CacheableUrlWindow::start($start));
        $this->assertSame($start, CacheableUrlWindow::start($start + CacheableUrlWindow::SECONDS - 1));
        $this->assertSame(
            $start + CacheableUrlWindow::SECONDS,
            CacheableUrlWindow::start($start + CacheableUrlWindow::SECONDS)
        );
    }

    public function testAUrlFromTheEndOfAWindowStillHasADayLeft()
    {
        $lastSecond = CacheableUrlWindow::start(1_800_000_000) + CacheableUrlWindow::SECONDS - 1;
        $expires = CacheableUrlWindow::start($lastSecond) + LibraryStorageServiceInterface::CACHEABLE_URL_TTL;

        $this->assertGreaterThan(86400, $expires - $lastSecond);
        $this->assertSame('private, max-age=518400', CacheableUrlWindow::cacheControl());
    }
}
