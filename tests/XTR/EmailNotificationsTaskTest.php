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
use Slim\Views\Twig;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Notification;
use Xibo\Entity\UserNotification;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\XTR\EmailNotificationsTask;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * An attachment that can't be read is left off the email; one library storage failed to read is tried again on the
 * next run first, and an admin is told, once a day, when it was left off. Mail goes to a reserved domain through PHP's
 * mail(), which fails without a mail server.
 */
class EmailNotificationsTaskTest extends TestCase
{
    private function runQueue(
        ?\Throwable $readFailure,
        bool $expectsAdminNotification,
        bool $isSent = true,
        ?Pool $pool = null,
        bool $isLeftQueued = false
    ): void {
        $notification = $this->createMock(UserNotification::class);
        $notification->notificationId = 7;
        $notification->email = '';
        $notification->filename = '7_report.pdf';
        $notification->subject = 'Report';
        $notification->body = 'Attached';
        if ($isSent) {
            $notification->nonusers = 'nobody@example.invalid';
        } else {
            // A system notification without an address is discarded, not sent.
            $notification->isSystem = 1;
        }
        // Each recipient's row is marked emailed, unless it waits for the next run or is discarded.
        $notification->expects($isSent && !$isLeftQueued ? $this->exactly(2) : $this->never())
            ->method('setEmailed');

        $queue = $this->createMock(UserNotificationFactory::class);
        $queue->method('getEmailQueue')->willReturn([$notification, $notification]);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        // Once for both recipients' rows, and never for an email that isn't sent.
        $storage->expects($isSent ? $this->once() : $this->never())
            ->method('downloadToTemp')
            ->willThrowException($readFailure ?? new GeneralException('unused'));
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::ATTACHMENT)->willReturn($storage);

        $adminNotification = $this->createMock(Notification::class);
        $adminNotification->expects($expectsAdminNotification ? $this->once() : $this->never())->method('save');
        $notifications = $this->createMock(NotificationFactory::class);
        $notifications->method('getBySubjectAndDate')->willReturn([]);
        $notifications->expects($expectsAdminNotification ? $this->once() : $this->never())
            ->method('createSystemNotification')
            ->with('Notification emails sent without attachments')
            ->willReturn($adminNotification);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['view', $this->createMock(Twig::class)],
            ['userNotificationFactory', $queue],
            ['userGroupFactory', $this->createMock(UserGroupFactory::class)],
            ['libraryStorageRegistry', $registry],
            ['notificationFactory', $notifications],
        ]);

        $task = new EmailNotificationsTask();
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'mail_from' ? 'cms@example.invalid' : null
        );
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setPool($pool ?? new Pool(new Ephemeral()));
        $task->setFactories($container);
        $task->run();
    }

    private function newNotification(int $notificationId, string $filename): UserNotification
    {
        $notification = $this->createMock(UserNotification::class);
        $notification->notificationId = $notificationId;
        $notification->email = '';
        $notification->filename = $filename;
        $notification->subject = 'Report';
        $notification->body = 'Attached';
        $notification->nonusers = 'nobody@example.invalid';
        return $notification;
    }

    public function testEachAttachmentIsReleasedAfterItsNotificationsLastRecipient()
    {
        $first = $this->newNotification(7, '7_report.pdf');
        $second = $this->newNotification(8, '8_report.pdf');
        $queue = $this->createMock(UserNotificationFactory::class);
        $queue->method('getEmailQueue')->willReturn([$first, $second, $first]);

        $events = [];
        $tempPaths = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->willReturnCallback(function ($key) use (&$events, &$tempPaths) {
            $events[] = 'download:' . $key;
            $tempPaths[$key] = tempnam(sys_get_temp_dir(), 'xibo-attachment-');
            return $tempPaths[$key];
        });
        $storage->method('releaseTemp')->willReturnCallback(function ($path) use (&$events, &$tempPaths) {
            $events[] = 'release:' . array_search($path, $tempPaths, true);
            @unlink($path);
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::ATTACHMENT)->willReturn($storage);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['view', $this->createMock(Twig::class)],
            ['userNotificationFactory', $queue],
            ['userGroupFactory', $this->createMock(UserGroupFactory::class)],
            ['libraryStorageRegistry', $registry],
            ['notificationFactory', $this->createMock(NotificationFactory::class)],
        ]);

        $task = new EmailNotificationsTask();
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'mail_from' ? 'cms@example.invalid' : null
        );
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setPool(new Pool(new Ephemeral()));
        $task->setFactories($container);
        $task->run();

        // Each copy is downloaded once and released before the next is downloaded.
        $this->assertSame(
            ['download:7_report.pdf', 'release:7_report.pdf', 'download:8_report.pdf', 'release:8_report.pdf'],
            $events
        );
    }

    public function testAStorageFailureLeavesTheEmailQueuedForTheNextRun()
    {
        $this->runQueue(new GeneralException('outage'), false, true, null, true);
    }

    public function testAStorageFailureOnTheNextRunSendsWithoutTheAttachmentAndTellsAnAdminOnce()
    {
        $pool = new Pool(new Ephemeral());
        $this->runQueue(new GeneralException('outage'), false, true, $pool, true);
        $this->runQueue(new GeneralException('outage'), true, true, $pool);
    }

    public function testAMissingFileIsOnlyLogged()
    {
        $this->runQueue(new NotFoundException('missing'), false);
    }

    public function testADiscardedNotificationNeverFetchesItsAttachment()
    {
        $this->runQueue(null, false, false);
    }
}
