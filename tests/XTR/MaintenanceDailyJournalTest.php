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
use Psr\Log\NullLogger;
use Xibo\Entity\Notification;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\XTR\MaintenanceDailyTask;

/**
 * Daily maintenance carries out pending journalled library file work, so it doesn't wait for a Library Tidy.
 */
class MaintenanceDailyJournalTest extends TestCase
{
    /** @var string[] commits and journal reads on the store, in order */
    private array $storeCalls = [];

    /**
     * @param int $attempts failed attempts already recorded against the pending media delete
     * @return array{0: string[], 1: string, 2: int} [keys deleted, run message, notifications raised]
     */
    private function processJournal(bool $storageFails, int $attempts = 0): array
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('commitIfNecessary')->willReturnCallback(function () {
            $this->storeCalls[] = 'commit';
        });
        $store->method('select')->willReturnCallback(function ($sql, $params) use ($attempts) {
            $this->storeCalls[] = 'select';
            return $params['state'] === LibraryFileJournal::PENDING_DELETE
                && $params['assetType'] === LibraryAsset::MEDIA
                ? [['id' => 1, 'storageKey' => '7.jpg', 'attempts' => $attempts]]
                : [];
        });
        $store->method('exists')->willReturn(false);

        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:/library/');
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted, $storageFails) {
            if ($storageFails) {
                throw new GeneralException('outage');
            }
            $deleted[] = $key;
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->willReturn($storage);

        $notifications = 0;
        $notificationFactory = $this->createMock(NotificationFactory::class);
        $notificationFactory->method('getBySubjectAndDate')->willReturn([]);
        $notificationFactory->method('createSystemNotification')->willReturnCallback(
            function () use (&$notifications) {
                $notifications++;
                return $this->createMock(Notification::class);
            }
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'libraryStorageRegistry' => $registry,
            'notificationFactory' => $notificationFactory,
            'playlistFactory' => $this->createMock(PlaylistFactory::class),
            'widgetFactory' => $this->createMock(WidgetFactory::class),
            'mediaFactory' => $this->createMock(MediaFactory::class),
            default => null,
        });

        $log = $this->createMock(LogServiceInterface::class);
        $log->method('getLoggerInterface')->willReturn(new NullLogger());

        $task = new MaintenanceDailyTask();
        $task->setLogger($log);
        $task->setStore($store);
        $task->setFactories($container);

        $method = new \ReflectionMethod($task, 'processLibraryJournal');
        $method->invoke($task);

        return [$deleted, (string)$task->getRunMessage(), $notifications];
    }

    public function testPendingDeletesAreCarriedOut()
    {
        [$deleted, $message, $notifications] = $this->processJournal(false);

        $this->assertSame(['7.jpg'], $deleted);
        $this->assertStringContainsString('Deleted 1 library files, 0 failed', $message);
        $this->assertSame(0, $notifications);
    }

    public function testTheTransactionLeftOpenEarlierIsCommittedBeforeTheJournalIsRead()
    {
        $this->processJournal(false);

        $this->assertSame('commit', $this->storeCalls[0] ?? null);
        $this->assertContains('select', $this->storeCalls);
    }

    public function testAFailedDeleteIsRetriedLaterWithoutANotification()
    {
        [$deleted, $message, $notifications] = $this->processJournal(true);

        $this->assertSame([], $deleted);
        $this->assertStringContainsString('Deleted 0 library files, 1 failed', $message);
        $this->assertSame(0, $notifications);
    }

    public function testAStuckDeleteRaisesANotification()
    {
        [, , $notifications] = $this->processJournal(true, LibraryFileJournal::STUCK_AFTER_ATTEMPTS - 1);

        $this->assertSame(1, $notifications);
    }
}
