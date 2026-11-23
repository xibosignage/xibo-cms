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
 * Work done outside the database during a transaction (taking a licence slot) is undone if the transaction rolls
 * back, and kept if it commits.
 */
class PdoStorageServiceAfterRollbackTest extends TestCase
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
        $this->setStatic('afterRollback', []);
        $this->setStatic('fallbackLog', null);
        $this->log = $this->createMock(LogServiceInterface::class);
    }

    protected function tearDown(): void
    {
        $this->setStatic('conn', []);
        $this->setStatic('afterCommit', []);
        $this->setStatic('afterRollback', []);
        $this->setStatic('fallbackLog', null);
    }

    private function setStatic(string $name, $value): void
    {
        (new \ReflectionProperty(PdoStorageService::class, $name))->setValue(null, $value);
    }

    public function testItRunsAfterTheRollback(): void
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ranInTransaction = null;
        $store->afterRollback(function () use (&$ranInTransaction) {
            $ranInTransaction = $this->inTransaction;
        });
        $store->rollBackIfNecessary();

        $this->assertFalse($ranInTransaction, 'must run once the transaction is rolled back');

        // Once only
        $ranInTransaction = null;
        $store->rollBackIfNecessary();
        $this->assertNull($ranInTransaction);
    }

    public function testItIsDroppedOnCommit(): void
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterRollback(function () use (&$ran) {
            $ran = true;
        });
        $store->commitIfNecessary();

        // A later transaction's rollback must not run it
        $this->inTransaction = true;
        $store->rollBackIfNecessary();

        $this->assertFalse($ran);
    }

    public function testWithoutATransactionOneIsStarted(): void
    {
        $started = false;
        $pdo = (new \ReflectionProperty(PdoStorageService::class, 'conn'))->getValue()['default'];
        $pdo->method('beginTransaction')->willReturnCallback(function () use (&$started) {
            $started = true;
            $this->inTransaction = true;
            return true;
        });
        $store = new PdoStorageService($this->log);

        $ran = false;
        $store->afterRollback(function () use (&$ran) {
            $ran = true;
        });
        $this->assertTrue($started, 'the writes that follow must be in a transaction');

        $store->rollBackIfNecessary();
        $this->assertTrue($ran);
    }

    public function testClosingWithTheTransactionOpenRollsBackAndRunsItOnTheSameConnection(): void
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;
        $pdo = (new \ReflectionProperty(PdoStorageService::class, 'conn'))->getValue()['default'];

        $seen = null;
        $store->afterRollback(function () use (&$seen, $store, $pdo) {
            $seen = [
                'inTransaction' => $this->inTransaction,
                'sameConnection' => $store->getConnection() === $pdo,
            ];
        });
        $store->close();

        $this->assertSame(['inTransaction' => false, 'sameConnection' => true], $seen);
        $this->assertSame([], (new \ReflectionProperty(PdoStorageService::class, 'conn'))->getValue());
    }

    public function testClosingOneConnectionLeavesTheOthersUndoWork(): void
    {
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterRollback(function () use (&$ran) {
            $ran = true;
        });
        $store->close('isolated');

        $this->assertFalse($ran);
        $store->rollBackIfNecessary();
        $this->assertTrue($ran);
    }

    public function testAFailingCallbackIsLoggedAndTheRestStillRun(): void
    {
        $this->log->expects($this->once())->method('error')
            ->with($this->stringContains('After-rollback callback failed: outage'));
        $store = new PdoStorageService($this->log);
        $this->inTransaction = true;

        $ran = false;
        $store->afterRollback(function () {
            throw new \RuntimeException('outage');
        });
        $store->afterRollback(function () use (&$ran) {
            $ran = true;
        });
        $store->rollBackIfNecessary();

        $this->assertTrue($ran);
    }
}
