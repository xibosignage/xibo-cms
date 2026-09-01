<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
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
