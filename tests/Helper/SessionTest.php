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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\Session;
use Xibo\Storage\PdoStorageService;

/**
 * Releasing the session lock early commits the request's work first and leaves the session unwritten.
 */
class SessionTest extends TestCase
{
    /** @var string[] */
    private array $events = [];

    private bool $inTransaction = false;

    protected function setUp(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturnCallback(fn () => $this->inTransaction);
        $pdo->method('commit')->willReturnCallback(function () {
            $this->events[] = 'commit:' . (session_status() === PHP_SESSION_ACTIVE ? 'locked' : 'released');
            $this->inTransaction = false;
            return true;
        });
        (new \ReflectionProperty(PdoStorageService::class, 'conn'))->setValue(null, ['default' => $pdo]);
        (new \ReflectionProperty(PdoStorageService::class, 'afterCommit'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(PdoStorageService::class, 'conn'))->setValue(null, []);
        (new \ReflectionProperty(PdoStorageService::class, 'afterCommit'))->setValue(null, []);
    }

    #[RunInSeparateProcess]
    public function testWithoutASessionItDoesNothing()
    {
        $this->inTransaction = true;

        Session::releaseLock();

        $this->assertSame([], $this->events);
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    #[RunInSeparateProcess]
    public function testItCommitsThenReleasesTheSessionWithoutWritingIt()
    {
        $handler = new class ($this->events) implements \SessionHandlerInterface {
            public function __construct(private array &$events)
            {
            }

            public function open(string $path, string $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                $this->events[] = 'close';
                return true;
            }

            public function read(string $id): string|false
            {
                return 'userId|i:5;';
            }

            public function write(string $id, string $data): bool
            {
                $this->events[] = 'write';
                return true;
            }

            public function destroy(string $id): bool
            {
                return true;
            }

            public function gc(int $max_lifetime): int|false
            {
                return 0;
            }
        };
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        session_set_save_handler($handler, false);
        session_start();
        $this->inTransaction = true;
        $ran = false;
        (new PdoStorageService())->afterCommit(function () use (&$ran) {
            $ran = true;
        });

        Session::releaseLock();

        $this->assertSame(['commit:locked', 'close'], $this->events);
        $this->assertTrue($ran, 'after-commit work runs rather than being lost');
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(5, $_SESSION['userId'], 'the session can still be read');
    }
}
