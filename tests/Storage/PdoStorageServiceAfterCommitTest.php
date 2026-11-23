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

namespace Xibo\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\PdoStorageService;

/**
 * Work that can't be rolled back (deleting a stored file whose row is being deleted) waits for the transaction to
 * commit, and is dropped if it rolls back.
 */
class PdoStorageServiceAfterCommitTest extends TestCase
{
    private bool $inTransaction = false;
    private LogServiceInterface $log;

    protected function setUp(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturnCallback(fn () => $this->inTransaction);
        $pdo->method('commit')->willReturnCallback(function () {
            $this->inTransaction = false;
            return true;
        });
        $pdo->method('rollBack')->willReturnCallback(function () {
            $this->inTransaction = false;
            return true;
        });

        $this->setStatic('conn', ['default' => $pdo]);
        $this->setStatic('afterCommit', []);
        $this->setStatic('fallbackLog', null);
        $this->log = $this->createMock(LogServiceInterface::class);
    }

    protected function tearDown(): void
    {
        $this->setStatic('conn', []);
        $this->setStatic('afterCommit', []);
        $this->setStatic('fallbackLog', null);
    }

    private function setStatic(string $name, $value): void
    {
        (new \ReflectionProperty(PdoStorageService::class, $name))->setValue(null, $value);
    }

    public function testWithoutATransactionItRunsStraightAway()
    {
        $ran = false;
        (new PdoStorageService($this->log))->afterCommit(function () use (&$ran) {
            $ran = true;
        });

        $this->assertTrue($ran);
    }

    public function testItWaitsForTheCommit()
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = 0;
        $store->afterCommit(function () use (&$ran) {
            $ran++;
        });
        $this->assertSame(0, $ran, 'must not run inside the transaction');

        $store->commitIfNecessary();
        $this->assertSame(1, $ran);

        // Once only
        $store->commitIfNecessary();
        $this->assertSame(1, $ran);
    }

    public function testItIsDroppedOnRollback()
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterCommit(function () use (&$ran) {
            $ran = true;
        });
        $store->rollBackIfNecessary();

        // A later transaction's commit must not run it either
        $this->inTransaction = true;
        $store->commitIfNecessary();

        $this->assertFalse($ran);
        $this->assertFalse($this->inTransaction);
    }

    public function testItIsDroppedWhenTheConnectionIsClosed()
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterCommit(function () use (&$ran) {
            $ran = true;
        });
        $store->close();

        // A new connection's commit must not run it
        $this->setUp();
        $this->inTransaction = true;
        $store->commitIfNecessary();

        $this->assertFalse($ran);
    }

    public function testAFailingCallbackIsLoggedAndTheRestStillRun()
    {
        $this->log->expects($this->once())->method('error')
            ->with($this->stringContains('outage'));
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterCommit(function () {
            throw new \RuntimeException('outage');
        });
        $store->afterCommit(function () use (&$ran) {
            $ran = true;
        });
        $store->commitIfNecessary();

        $this->assertTrue($ran);
    }

    public function testALoggerlessStoreLogsThroughAnEarlierStoresLogger()
    {
        $this->log->expects($this->once())->method('error')
            ->with($this->stringContains('outage'));
        new PdoStorageService($this->log);
        $store = new PdoStorageService();
        $this->inTransaction = true;

        $store->afterCommit(function () {
            throw new \RuntimeException('outage');
        });
        $store->commitIfNecessary();
    }
}
