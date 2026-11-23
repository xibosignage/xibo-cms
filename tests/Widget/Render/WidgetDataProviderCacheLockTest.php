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

namespace Xibo\Tests\Widget\Render;

use PHPUnit\Framework\TestCase;
use Stash\Driver\Ephemeral;
use Stash\Invalidation;
use Stash\Pool;
use Xibo\Support\Exception\GeneralException;
use Xibo\Widget\Provider\DataProvider;
use Xibo\Widget\Render\WidgetDataProviderCache;

/**
 * A preview never waits for the lock, and a process never refreshes or releases a lock it lost.

 */
class WidgetDataProviderCacheLockTest extends TestCase
{
    private function decorate(WidgetDataProviderCache $cache, bool $isWaitForLock = true): bool
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getDataType')->willReturn('test');

        return $cache->decorateWithCache($dataProvider, 'key', null, true, $isWaitForLock);
    }

    private function lockValue(Pool $pool): mixed
    {
        // Read as the lock is read, where a short TTL counts as a miss.
        $item = $pool->getItem('locks/concurrency/widget/test/' . md5('key'));
        $item->setInvalidationMethod(Invalidation::NONE);
        return $item->get();
    }

    public function testNoWaitCallerSeesRefreshingElsewhereInsteadOfBlocking()
    {
        $pool = new Pool(new Ephemeral());

        $owner = new WidgetDataProviderCache($pool);
        $this->decorate($owner);
        $this->assertFalse($owner->isRefreshingElsewhere());

        $preview = new WidgetDataProviderCache($pool);
        $this->decorate($preview, false);
        $this->assertTrue($preview->isRefreshingElsewhere());

        // The preview never held the lock, so finalising it must not release the owner's.
        $preview->finaliseCache();
        $this->assertIsString($this->lockValue($pool));

        $owner->finaliseCache();
        $this->assertFalse($this->lockValue($pool));

        $next = new WidgetDataProviderCache($pool);
        $this->decorate($next, false);
        $this->assertFalse($next->isRefreshingElsewhere());
    }

    public function testATakenOverLockIsNeitherRefreshedNorReleased()
    {
        $pool = new Pool(new Ephemeral());

        $first = new WidgetDataProviderCache($pool);
        $this->decorate($first);

        // Simulate the first lock expiring and a second process taking it.
        $item = $pool->getItem('locks/concurrency/widget/test/' . md5('key'));
        $item->set('someone-else');
        $item->expiresAfter(300);
        $pool->save($item);

        $first->refreshLock();
        $this->assertSame('someone-else', $this->lockValue($pool));

        $first->finaliseCache();
        $this->assertSame('someone-else', $this->lockValue($pool));
    }

    public function testWaitingCallerTimesOutWhileLocked()
    {
        $pool = new Pool(new Ephemeral());
        $this->decorate(new WidgetDataProviderCache($pool));

        $this->expectException(GeneralException::class);
        $cache = new WidgetDataProviderCache($pool);
        $method = new \ReflectionMethod($cache, 'concurrentRequestLock');
        $this->decorate($cache, false);
        // Still locked after two tries without waiting, so it throws.
        $method->invoke($cache, 300, 0, 2);
    }
}
