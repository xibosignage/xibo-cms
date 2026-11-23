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
use Xibo\Helper\ByteRange;

class ByteRangeTest extends TestCase
{
    public function testNoRangeMeansTheWholeFile()
    {
        $this->assertNull(ByteRange::parse('', 100));
        $this->assertNull(ByteRange::parse('items=0-5', 100));
    }

    public function testRangesOtherThanOneFirstToLastRangeAreIgnored()
    {
        $this->assertNull(ByteRange::parse('bytes=20-10', 100), 'last before first is invalid');
        $this->assertNull(ByteRange::parse('bytes=0-5,10-20', 100), 'several ranges');
        $this->assertNull(ByteRange::parse('bytes=-50', 100), 'a suffix range');
        $this->assertNull(ByteRange::parse('xbytes=0-5', 100));
        $this->assertNull(ByteRange::parse('bytes=0-5x', 100));
    }

    public function testOpenAndClosedRanges()
    {
        $this->assertSame([0, 99], ByteRange::parse('bytes=0-', 100));
        $this->assertSame([10, 19], ByteRange::parse('bytes=10-19', 100));
        $this->assertSame([99, 99], ByteRange::parse('bytes=99-', 100));
    }

    public function testAnEndPastTheFileIsClampedToIt()
    {
        $this->assertSame([90, 99], ByteRange::parse('bytes=90-500', 100));
        $this->assertSame([0, 99], ByteRange::parse('bytes=0-100', 100));
    }

    public function testAStartAtOrPastTheEndCannotBeSatisfied()
    {
        $this->assertFalse(ByteRange::parse('bytes=100-', 100));
        $this->assertFalse(ByteRange::parse('bytes=500-600', 100));
        $this->assertFalse(ByteRange::parse('bytes=0-', 0));
    }
}
