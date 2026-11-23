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

use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\ScreenShotStamp;

class ScreenShotStampTest extends TestCase
{
    public function testTheCaptureTimeIsDrawnOnAWhiteLabelAndTheImageIsAJpeg()
    {
        $black = imagecreatetruecolor(200, 100);
        ob_start();
        imagepng($black);
        $png = ob_get_clean();

        $stamped = ScreenShotStamp::stamp(ImageManager::gd()->read($png), '2026-10-15 12:00:00');

        $image = imagecreatefromstring($stamped);
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($stamped)[2]);
        $this->assertGreaterThan(200, imagecolorat($image, 100, 2) & 0xFF, 'the label is white');
        $this->assertLessThan(50, imagecolorat($image, 150, 50) & 0xFF, 'the rest is unchanged');
    }
}
