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
use Xibo\Helper\CdnBandwidth;

class CdnBandwidthTest extends TestCase
{
    public function testTheReportedUsageIsCounted()
    {
        $this->assertSame(1234, CdnBandwidth::fromHeader('1234', 5000));
    }

    public function testAReportedZeroIsCounted()
    {
        $this->assertSame(0, CdnBandwidth::fromHeader('0', 5000));
    }

    public function testTheFileSizeIsCountedWithoutAUsableReport()
    {
        $this->assertSame(5000, CdnBandwidth::fromHeader(null, 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('abc', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('-1', 5000));
    }

    public function testOnlyWholeNumbersThatFitAnIntegerAreCounted()
    {
        $this->assertSame(12, CdnBandwidth::fromHeader(' 12 ', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('1.5', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('-0.5', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('1e3', 5000));
        $this->assertSame(5000, CdnBandwidth::fromHeader('99999999999999999999', 5000));
    }
}
