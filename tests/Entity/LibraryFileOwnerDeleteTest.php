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

namespace Xibo\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Notification;
use Xibo\Entity\SavedReport;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Notifications and saved reports own their library file: deleting the row journals the delete and removes the file
 * afterwards, and a storage error never stops the row delete.
 */
class LibraryFileOwnerDeleteTest extends TestCase
{
    /**
     * @param string[] $events
     */
    private function recordingStore(array &$events): StorageServiceInterface
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('update')->willReturnCallback(function ($sql) use (&$events) {
            // Journal bookkeeping after the file delete isn't what these tests are about.
            if (!str_contains($sql, 'library_file_journal')) {
                $events[] = 'sql';
            }
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) use (&$events) {
            $events[] = 'journal:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            return 1;
        });
        return $store;
    }

    /**
     * @param string[] $events
     */
    private function recordingStorage(array &$events, bool $fails = false): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$events, $fails) {
            $events[] = 'file:' . $key;
            if ($fails) {
                throw new GeneralException('outage');
            }
        });
        return $storage;
    }

    private function registryFor(
        string $assetType,
        LibraryStorageServiceInterface $storage
    ): LibraryStorageRegistryInterface {
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with($assetType)->willReturn($storage);
        return $registry;
    }

    private function newNotification(
        StorageServiceInterface $store,
        LibraryStorageServiceInterface $storage
    ): Notification {
        return new Notification(
            $store,
            $this->createMock(LogServiceInterface::class),
            null,
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayGroupFactory::class),
            $this->registryFor(LibraryAsset::ATTACHMENT, $storage)
        );
    }

    public function testNotificationDeleteRemovesTheAttachmentAfterTheRows()
    {
        $events = [];
        $notification = $this->newNotification($this->recordingStore($events), $this->recordingStorage($events));
        $notification->notificationId = 7;
        $notification->filename = '7_report.pdf';

        $notification->delete();

        $this->assertSame(
            ['sql', 'journal:pending_delete:attachment:7_report.pdf', 'file:7_report.pdf'],
            array_slice($events, -3)
        );
    }

    public function testNotificationDeleteSurvivesAStorageError()
    {
        $events = [];
        $notification = $this->newNotification(
            $this->recordingStore($events),
            $this->recordingStorage($events, true)
        );
        $notification->notificationId = 7;
        $notification->filename = '7_report.pdf';

        $notification->delete();

        $this->assertContains('file:7_report.pdf', $events);
    }

    public function testNotificationWithoutAnAttachmentNeverResolvesStorage()
    {
        $events = [];
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->expects($this->never())->method('get');
        $notification = new Notification(
            $this->recordingStore($events),
            $this->createMock(LogServiceInterface::class),
            null,
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayGroupFactory::class),
            $registry
        );
        $notification->notificationId = 7;
        $notification->filename = '';

        $notification->delete();

        $this->assertSame([], array_values(array_filter($events, fn ($e) => str_starts_with($e, 'file:'))));
    }

    public function testSavedReportDeleteRemovesTheZipAndSurvivesAStorageError()
    {
        $events = [];
        $savedReport = $this->getMockBuilder(SavedReport::class)
            ->setConstructorArgs([
                $this->recordingStore($events),
                $this->createMock(LogServiceInterface::class),
                null,
                $this->createMock(MediaFactory::class),
                $this->createMock(SavedReportFactory::class),
                $this->registryFor(LibraryAsset::SAVED_REPORT, $this->recordingStorage($events, true)),
            ])
            ->onlyMethods(['load'])
            ->getMock();
        $savedReport->savedReportId = 3;
        $savedReport->reportScheduleId = 1;
        $savedReport->fileName = 'rs_1_100.zip';

        $savedReport->delete();

        $this->assertSame(
            ['journal:pending_delete:savedreport:rs_1_100.zip', 'file:rs_1_100.zip'],
            array_slice($events, -2)
        );
    }
}
