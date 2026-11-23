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
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Helper\StashLock;

/**
 * Only one holder at a time, and a holder never extends or releases a lock it lost.
 */
class StashLockTest extends TestCase
{
    public function testASecondHolderWaitsThenGivesUp(): void
    {
        $pool = new Pool(new Ephemeral());
        $first = new StashLock($pool, 'locks/test');
        $second = new StashLock($pool, 'locks/test');

        $this->assertTrue($first->acquire(30, 1, 3));
        $this->assertFalse($second->acquire(30, 1, 3));
        $this->assertTrue($first->isOwner());
        $this->assertFalse($second->isOwner());
    }

    public function testReleasingLetsTheNextHolderIn(): void
    {
        $pool = new Pool(new Ephemeral());
        $first = new StashLock($pool, 'locks/test');
        $first->acquire(30, 1, 1);
        $first->release();

        $this->assertFalse($first->isOwner());
        $this->assertTrue((new StashLock($pool, 'locks/test'))->acquire(30, 1, 1));
    }

    public function testALostLockIsNeitherExtendedNorReleased(): void
    {
        $pool = new Pool(new Ephemeral());
        $first = new StashLock($pool, 'locks/test');
        $first->acquire(30, 1, 1);

        // Another process takes the lock over once the first one's has expired.
        $pool->deleteItem('locks/test');
        $second = new StashLock($pool, 'locks/test');
        $this->assertTrue($second->acquire(30, 1, 1));

        $first->extend(300);
        $first->release();

        $this->assertTrue($second->isOwner());
        $this->assertFalse((new StashLock($pool, 'locks/test'))->acquire(30, 1, 1));
    }

    public function testOtherKeysDoNotBlock(): void
    {
        $pool = new Pool(new Ephemeral());
        (new StashLock($pool, 'locks/a'))->acquire(30, 1, 1);

        $this->assertTrue((new StashLock($pool, 'locks/b'))->acquire(30, 1, 1));
    }
}
