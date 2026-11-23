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
use Xibo\Entity\Display;
use Xibo\Helper\LinkSigner;

class LinkSignerTest extends TestCase
{
    private function display(int $collectInterval): Display
    {
        $display = $this->createMock(Display::class);
        $display->method('getSetting')->willReturnCallback(
            fn ($key, $default = null) => $key === 'collectInterval' ? $collectInterval : $default
        );
        $display->displayId = 7;
        return $display;
    }

    public function testLinksExpireAfterTwoCollectIntervals()
    {
        $this->assertEqualsWithDelta(time() + 7200, LinkSigner::getExpiry($this->display(3600)), 1);
        $this->assertEqualsWithDelta(time() + 172800, LinkSigner::getExpiry($this->display(86400)), 1);
    }

    public function testLinksLastAtLeastTenMinutesAndAtMostSixDays()
    {
        $this->assertEqualsWithDelta(time() + 600, LinkSigner::getExpiry($this->display(60)), 1);
        $this->assertEqualsWithDelta(time() + 600, LinkSigner::getExpiry($this->display(0)), 1);
        $this->assertEqualsWithDelta(time() + 518400, LinkSigner::getExpiry($this->display(604800)), 1);
    }

    public function testAnXmdsLinkIsBuiltAndSignedOnTheGivenAddress()
    {
        $url = LinkSigner::generateSignedLink(
            $this->display(300),
            'secret',
            null,
            'M',
            5,
            '5.mp4',
            null,
            false,
            'https://acme.displays.cloud/'
        );

        $this->assertStringStartsWith('https://acme.displays.cloud/xmds.php?file=5.mp4&displayId=7', $url);

        // As xmds.php checks it, on the host the download arrives on.
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(
            LinkSigner::getSignature(
                'acme.displays.cloud',
                LinkSigner::getXmdsResource('5.mp4', 7, 'M', 5, null),
                (int)$query['X-Amz-Expires'],
                'secret',
                $query['X-Amz-Date'],
                true
            ),
            $query['X-Amz-Signature']
        );
        $this->assertNotSame(
            LinkSigner::getSignature(
                'acme.xibo.cloud',
                LinkSigner::getXmdsResource('5.mp4', 7, 'M', 5, null),
                (int)$query['X-Amz-Expires'],
                'secret',
                $query['X-Amz-Date'],
                true
            ),
            $query['X-Amz-Signature']
        );
    }

    public function testAnXmdsLinkCannotBeChangedToAnotherDisplayOrFile()
    {
        $url = LinkSigner::generateSignedLink($this->display(300), 'secret', null, 'P', 3, 'font.ttf', 'font');
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        $verify = fn (array $changes) => LinkSigner::getSignature(
            (string)parse_url($url, PHP_URL_HOST),
            LinkSigner::getXmdsResource(
                $changes['file'] ?? $query['file'],
                $changes['displayId'] ?? $query['displayId'],
                $changes['type'] ?? $query['type'],
                $changes['itemId'] ?? $query['itemId'],
                $changes['fileType'] ?? $query['fileType'] ?? null
            ),
            (int)$query['X-Amz-Expires'],
            'secret',
            $query['X-Amz-Date'],
            true
        ) === $query['X-Amz-Signature'];

        $this->assertTrue($verify([]), 'the link as given');
        $this->assertFalse($verify(['displayId' => '8']), 'another display');
        $this->assertFalse($verify(['type' => 'M']), 'another type');
        $this->assertFalse($verify(['itemId' => '4']), 'another item');
        $this->assertFalse($verify(['fileType' => 'fontCss']), 'another file type');
        $this->assertFalse($verify(['file' => 'other.ttf']), 'another file');
    }

    public function testXmdsLinksLastTenMinutesWhateverTheCollectInterval()
    {
        $url = LinkSigner::generateSignedLink($this->display(3600), 'secret', null, 'M', 5, '5.mp4');

        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(time() + 600, (int)$query['X-Amz-Expires'], 1);
    }
}
