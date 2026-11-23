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

namespace Xibo\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Display;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\PdoStorageService;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Tests for DisplayFactory::unauthoriseOverLimit: the least recently seen authorised displays, of any type, are
 * un-authorised by saving them until the rest fit the limit.
 */
class DisplayFactoryUnauthoriseOverLimitTest extends TestCase
{
    /** @var array<int, Display> by displayId, least recently seen first */
    private array $displays = [];

    /** @var int[] displayIds saved un-authorised, in order */
    private array $unauthorised = [];

    private string $sql = '';

    public function testLeastRecentlySeenOfAnyTypeAreUnauthorised(): void
    {
        $this->display(1, 'android');
        $this->display(2, 'chromeOS');
        $this->display(3, 'windows');
        $this->display(4, 'sssp');

        $result = $this->factory()->unauthoriseOverLimit(2, 'Over the limit.');

        $this->assertSame([1, 2], $this->unauthorised);
        $this->assertSame([1, 2], array_map(fn (Display $display) => $display->displayId, $result));
        $this->assertSame(0, $this->displays[1]->licensed);
        // Ties (mainly displays never seen, with no lastAccessed) are broken the same way every time.
        $this->assertStringContainsString('ORDER BY lastAccessed, displayId', $this->sql);
        $this->assertStringNotContainsString('client_type', $this->sql);
    }

    public function testWithinTheLimitNothingIsUnauthorised(): void
    {
        $this->display(1, 'windows');
        $this->display(2, 'android');

        $this->assertSame([], $this->factory()->unauthoriseOverLimit(2, 'Over the limit.'));
        $this->assertSame([], $this->unauthorised);
    }

    public function testDisplayThatCannotBeSavedStaysAuthorisedAndTheNextOneGoes(): void
    {
        $this->display(1, 'android', refuse: true);
        $this->display(2, 'linux');
        $this->display(3, 'windows');

        $this->factory()->unauthoriseOverLimit(2, 'Over the limit.');

        $this->assertSame([2], $this->unauthorised);
    }

    // A database error (not only a GeneralException) on one display doesn't stop the others.
    public function testDatabaseErrorOnOneDisplayDoesNotStopTheRest(): void
    {
        $this->display(1, 'android', error: new \PDOException('Deadlock found'));
        $this->display(2, 'linux');
        $this->display(3, 'windows');

        $this->factory()->unauthoriseOverLimit(2, 'Over the limit.');

        $this->assertSame([2], $this->unauthorised);
    }

    // At most maxUnauthorise go in one call; the rest wait for the next.
    public function testAtMostMaxUnauthoriseAreUnauthorised(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->display($i, 'android');
        }

        $result = $this->factory()->unauthoriseOverLimit(2, 'Over the limit.', maxUnauthorise: 3);

        $this->assertSame([1, 2, 3], $this->unauthorised);
        $this->assertCount(3, $result);
    }

    // The display making the request is never un-authorised; the next least recently seen goes instead.
    public function testTheKeptDisplayIsSkipped(): void
    {
        $this->display(1, 'windows');
        $this->display(2, 'android');
        $this->display(3, 'linux');

        $this->factory()->unauthoriseOverLimit(1, 'Over the limit.', keepDisplayId: 1);

        $this->assertSame([2, 3], $this->unauthorised);
    }

    public function testEachUnauthorisedDisplayIsAudited(): void
    {
        $this->display(1, 'android');
        $this->display(2, 'windows');

        $log = $this->createMock(LogServiceInterface::class);
        $log->expects($this->once())->method('audit')
            ->with('Display', 1, 'Over the limit.', ['display' => 'Display 1']);

        $this->factory($log)->unauthoriseOverLimit(1, 'Over the limit.');
    }

    private function display(
        int $displayId,
        string $clientType,
        bool $refuse = false,
        ?\Throwable $error = null
    ): void {
        $display = $this->createMock(Display::class);
        $display->displayId = $displayId;
        $display->display = 'Display ' . $displayId;
        $display->clientType = $clientType;
        $display->licensed = 1;
        $display->method('save')->willReturnCallback(function () use ($display, $refuse, $error) {
            if ($error !== null) {
                throw $error;
            }
            if ($refuse) {
                throw new InvalidArgumentException('Unable to un-authorise this display, please try again later.');
            }
            $this->unauthorised[] = $display->displayId;
        });

        $this->displays[$displayId] = $display;
    }

    private function factory(?LogServiceInterface $log = null): DisplayFactory
    {
        $store = $this->createMock(PdoStorageService::class);
        $store->method('select')->willReturnCallback(function (string $sql) {
            $this->sql = $sql;

            return array_map(fn (int $id) => ['displayId' => $id], array_keys($this->displays));
        });

        $dependencies = new BaseDependenciesService();
        $dependencies->setStore($store);
        $dependencies->setLogger($log ?? $this->createMock(LogServiceInterface::class));

        $factory = $this->getMockBuilder(DisplayFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById'])
            ->getMock();
        $factory->method('getById')->willReturnCallback(fn (int $id) => $this->displays[$id]);
        $factory->useBaseDependenciesService($dependencies);

        return $factory;
    }
}
