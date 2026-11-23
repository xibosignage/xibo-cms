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

namespace Xibo\Tests\XTR;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Xibo\Entity\Task;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\XTR\NotificationTidyTask;

/**
 * Notification Tidy journals and deletes the attachment files of the notifications it deletes, and only those.
 */
class NotificationTidyTaskTest extends TestCase
{
    /**
     * @return array{0: string[], 1: string[]} [SQL run in order, attachment keys deleted]
     */
    private function runTidy(int $readOnly, bool $storageFails = false): array
    {
        $events = [];
        $deleted = [];

        $store = $this->createMock(StorageServiceInterface::class);

        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('update')->willReturnCallback(function ($sql) use (&$events) {
            $events[] = trim($sql);
        });
        $store->method('insert')->willReturn(1);
        $store->method('select')->willReturnCallback(function ($sql) use (&$events) {
            $events[] = trim($sql);
            return [['filename' => '7_report.pdf'], ['filename' => ''], ['filename' => '8_other.pdf']];
        });

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('delete')->willReturnCallback(
            function (string $key) use (&$events, &$deleted, $storageFails) {
                $events[] = 'file:' . $key;
                $deleted[] = $key;
                if ($storageFails) {
                    throw new GeneralException('outage');
                }
            }
        );

        $container = $this->createMock(ContainerInterface::class);
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::ATTACHMENT)->willReturn($storage);
        $container->method('get')->willReturnMap([['libraryStorageRegistry', $registry]]);

        $task = $this->createMock(Task::class);
        $task->options = ['maxAgeDays' => 7, 'systemOnly' => 1, 'readOnly' => $readOnly];

        $tidy = new NotificationTidyTask();
        $tidy->setConfig($this->createMock(ConfigServiceInterface::class));
        $tidy->setLogger($this->createMock(LogServiceInterface::class));
        $tidy->setStore($store);
        $tidy->setTask($task);
        $tidy->setFactories($container);
        $tidy->run();

        return [$events, $deleted];
    }

    public function testFilesAreDeletedThroughStorageAfterTheRows()
    {
        [$events, $deleted] = $this->runTidy(0);

        $this->assertSame(['7_report.pdf', '8_other.pdf'], $deleted);

        $notificationDelete = array_search(true, array_map(
            fn ($e) => str_starts_with($e, 'DELETE FROM `notification`'),
            $events
        ), true);
        $this->assertNotFalse($notificationDelete);
        $this->assertSame('file:7_report.pdf', $events[$notificationDelete + 1]);
    }

    public function testReadOnlySelectsOnlyTheNotificationsItDeletes()
    {
        [$events] = $this->runTidy(1);

        $select = current(array_filter($events, fn ($e) => str_starts_with($e, 'SELECT filename')));
        $delete = current(array_filter($events, fn ($e) => str_starts_with($e, 'DELETE FROM `notification`')));

        $filter = 'NOT IN (SELECT `notificationId` FROM `lknotificationuser`)';
        $this->assertStringContainsString($filter, $select);
        $this->assertStringContainsString($filter, $delete);

        // Links go for every notification without an unread recipient, so the notification delete never hits them.
        $groupDelete = current(array_filter(
            $events,
            fn ($e) => str_starts_with($e, 'DELETE FROM `lknotificationgroup`')
        ));
        $this->assertMatchesRegularExpression(
            '/NOT IN\s+\(SELECT `notificationId` FROM `lknotificationuser` WHERE `read` = 0\)/',
            $groupDelete
        );
    }

    public function testAStorageErrorDoesNotStopTheTidy()
    {
        [, $deleted] = $this->runTidy(0, true);

        $this->assertSame(['7_report.pdf', '8_other.pdf'], $deleted);
    }
}
