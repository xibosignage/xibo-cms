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

namespace Xibo\Tests\Xmds;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Display;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Xmds\Soap;

class RequiredFilesIsDirectTest extends TestCase
{
    private function soap(StorageServiceInterface $store): Soap
    {
        $display = $this->getMockBuilder(Display::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $display->displayId = 7;

        return new class ($store, $display) extends Soap {
            public function __construct(private $fakeStore, $display)
            {
                $this->display = $display;
            }

            protected function getStore()
            {
                return $this->fakeStore;
            }

            public function sync(array $isDirectByMediaId): void
            {
                $this->syncMediaIsDirect($isDirectByMediaId);
            }
        };
    }

    public function testOnlyRowsWhoseLinkModeChangedAreUpdated()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->once())->method('select')->willReturn([
            ['itemId' => 1, 'isDirect' => 0, 'complete' => 0, 'bytesRequested' => 0],
            ['itemId' => 2, 'isDirect' => 1, 'complete' => 1, 'bytesRequested' => 0],
            ['itemId' => 3, 'isDirect' => 1, 'complete' => 0, 'bytesRequested' => 0],
            ['itemId' => 4, 'isDirect' => 0, 'complete' => 1, 'bytesRequested' => 10],
            ['itemId' => 6, 'isDirect' => 0, 'complete' => 0, 'bytesRequested' => 10],
        ]);

        $updates = [];
        $store->method('update')->willReturnCallback(function ($sql, $params) use (&$updates) {
            preg_match('/IN \(([^)]*)\)/', $sql, $matches);
            $updates[$params['isDirect']] = $matches[1];
            $this->assertSame(7, $params['displayId']);
            return 1;
        });

        // 1 was copied, 3 went back to the CMS, 2 and 4 unchanged, 5 has no row, and 6 was copied while the CMS was
        // already sending it, so that send is its count.
        $this->soap($store)->sync([1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1, 6 => 1]);

        $this->assertEquals([0 => '3', 1 => '1'], $updates);
    }

    public function testNothingIsUpdatedWhenNoLinkModeChanged()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')
            ->willReturn([['itemId' => 1, 'isDirect' => 1, 'complete' => 0, 'bytesRequested' => 0]]);
        $store->expects($this->never())->method('update');

        $this->soap($store)->sync([1 => 1]);
    }

    public function testEveryXmdsVersionCanMakePlayerLinks()
    {
        foreach (['Soap3', 'Soap4', 'Soap5', 'Soap6', 'Soap7'] as $version) {
            $this->assertTrue(
                (new \ReflectionClass('Xibo\\Xmds\\' . $version))->hasMethod('getPlayerFileLink'),
                $version
            );
        }
    }

    public function testNoMediaMeansNoQuery()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->never())->method('select');

        $this->soap($store)->sync([]);
    }
}
