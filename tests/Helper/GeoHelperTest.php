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
use Xibo\Helper\GeoHelper;

class GeoHelperTest extends TestCase
{
    public function testRoundsToDefaultPrecision()
    {
        $this->assertSame(51.51, GeoHelper::roundCoordinate(51.5073512));
        $this->assertSame(-0.13, GeoHelper::roundCoordinate(-0.1277583));
    }

    public function testNearbyCoordinatesResolveToTheSameValue()
    {
        // QA example: two displays a metre apart should round to the same lat/long.
        $this->assertSame(
            GeoHelper::roundCoordinate(51.5073512),
            GeoHelper::roundCoordinate(51.5073601)
        );
        $this->assertSame(
            GeoHelper::roundCoordinate(-0.1277583),
            GeoHelper::roundCoordinate(-0.1277590)
        );
    }

    public function testAcceptsStringInput()
    {
        $this->assertSame(51.51, GeoHelper::roundCoordinate('51.5073512'));
    }

    public function testRespectsCustomPrecision()
    {
        $this->assertSame(51.507, GeoHelper::roundCoordinate(51.5073512, 3));
    }

    public function testNullAndEmptyReturnNull()
    {
        $this->assertNull(GeoHelper::roundCoordinate(null));
        $this->assertNull(GeoHelper::roundCoordinate(''));
    }
}
