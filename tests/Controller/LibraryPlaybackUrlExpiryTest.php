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

namespace Xibo\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Xibo\Controller\Library;
use Xibo\Entity\Media;

class LibraryPlaybackUrlExpiryTest extends TestCase
{
    private function media(int $duration): Media
    {
        $media = $this->getMockBuilder(Media::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $media->duration = $duration;
        return $media;
    }

    public function testPlaybackUrlsLastTwiceTheDurationWithinAFloorAndACap()
    {
        $now = 1_800_000_000;

        $this->assertSame($now + 900, Library::getPlaybackUrlExpiry($this->media(10), $now));
        $this->assertSame($now + 7200, Library::getPlaybackUrlExpiry($this->media(3600), $now));
        $this->assertSame($now + 604800, Library::getPlaybackUrlExpiry($this->media(500000), $now));
    }
}
