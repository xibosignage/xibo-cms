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

namespace Xibo\Tests\Controller;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\ServerRequest as PsrServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Controller\Notification;
use Xibo\Entity\Notification as NotificationEntity;
use Xibo\Entity\User;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Helper\NullSession;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;

/**
 * Clearing a notification's attachment and uploading one with the same name in the same edit keeps the new file,
 * whether the cleared file's delete runs straight away or once the transaction commits.
 */
class NotificationAttachmentReplaceTest extends TestCase
{
    private string $library;

    /** @var array<string, string> storage key => contents */
    private array $files = [];

    /** @var array<int, array{storageKey: string, state: string, assetType: string, createdDt: int}> */
    private array $journal = [];

    private int $nextJournalId = 1;

    /** @var string the notification row's committed filename */
    private string $rowFilename = '5_a.pdf';

    /** @var callable[] work waiting for the transaction to commit */
    private array $afterCommit = [];

    private bool $inTransaction = true;

    private ?StorageServiceInterface $store = null;

    private ?LibraryStorageServiceInterface $storage = null;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/notification-replace-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library . 'temp', 0777, true);
        $this->files = ['5_a.pdf' => 'old contents'];
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->library));
    }

    public static function storageAndCommit(): array
    {
        return [
            'local storage, deferred commit' => [true, true],
            'local storage, no transaction' => [true, false],
            'staged in storage, deferred commit' => [false, true],
            'staged in storage, no transaction' => [false, false],
        ];
    }

    #[DataProvider('storageAndCommit')]
    public function testClearingThenUploadingTheSameNameKeepsTheNewFile(bool $hasLocalFiles, bool $inTransaction)
    {
        $this->inTransaction = $inTransaction;
        $controller = $this->controller($hasLocalFiles);

        // The upload request leaves the new file in the user's upload folder, or stages it in storage.
        $uploadDir = AttachmentUploadHandler::getUserUploadDir($this->library, 7);
        mkdir($uploadDir, 0777, true);
        file_put_contents($uploadDir . 'a.pdf', 'new contents');
        if (!$hasLocalFiles) {
            (new \ReflectionMethod($controller, 'stageAttachment'))
                ->invoke($controller, $uploadDir . 'a.pdf', 'a.pdf');
        }

        $controller->edit(
            (new ServerRequest(new PsrServerRequest('PUT', '/notification/5')))->withParsedBody([
                'subject' => 'Subject',
                'clearAttachment' => 1,
                'attachedFilename' => 'a.pdf',
            ]),
            new Response(new PsrResponse(), new HttpFactory()),
            5
        );
        $this->commit();

        $this->assertSame(['5_a.pdf' => 'new contents'], $this->files);
        $this->assertSame('5_a.pdf', $this->rowFilename);

        // A delete left for the tidy keeps the file the row refers to again.
        (new LibraryFileJournal($this->store, $this->storage, LibraryAsset::ATTACHMENT))
            ->processPending(new NullLogger());
        $this->assertSame(['5_a.pdf' => 'new contents'], $this->files);
        $this->assertSame([], $this->journal);
    }

    private function commit(): void
    {
        while ($callback = array_shift($this->afterCommit)) {
            $callback();
        }
    }

    private function controller(bool $hasLocalFiles): Notification
    {
        $this->store = $this->fakeStore();
        $this->storage = $this->fakeStorage($hasLocalFiles);

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : null
        );
        $user = $this->createMock(User::class);
        $user->userId = 7;
        $user->method('checkEditable')->willReturn(true);
        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getConfig')->willReturn($config);
        $base->method('getUser')->willReturn($user);
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());

        $notification = $this->createMock(NotificationEntity::class);
        $notification->notificationId = 5;
        $notification->filename = $this->rowFilename;
        $notification->originalFileName = 'a.pdf';
        $notification->method('save')->willReturnCallback(function () use ($notification) {
            $this->rowFilename = $notification->filename;
        });
        $notification->method('deleteAttachmentFile')->willReturnCallback(function ($filename) {
            (new LibraryFileJournal($this->store, $this->storage, LibraryAsset::ATTACHMENT))
                ->deleteAfterCommit((string)$filename, new NullLogger());
        });
        $notificationFactory = $this->createMock(NotificationFactory::class);
        $notificationFactory->method('getById')->with(5)->willReturn($notification);

        $controller = new Notification(
            $notificationFactory,
            $this->createMock(UserNotificationFactory::class),
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayNotifyService::class),
            $this->createMock(NullSession::class),
            $this->storage,
            $this->store
        );
        $controller->useBaseDependenciesService($base);

        return $controller;
    }

    /**
     * An in-memory journal and notification row, with after-commit work held until commit() when in a transaction.
     */
    private function fakeStore(): StorageServiceInterface
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            if ($this->inTransaction) {
                $this->afterCommit[] = $callback;
            } else {
                $callback();
            }
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $id = $this->nextJournalId++;
            $this->journal[$id] = [
                'storageKey' => $params['key'],
                'state' => $params['state'],
                'assetType' => $params['assetType'],
                'createdDt' => $params['createdDt'],
            ];
            return $id;
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            if (str_starts_with($sql, 'UPDATE') && isset($params['state'])) {
                $this->journal[$params['id']]['state'] = $params['state'];
            } elseif (isset($params['id']) && !isset($params['lastError'])) {
                unset($this->journal[$params['id']]);
            } elseif (isset($params['key'])) {
                foreach ($this->journal as $id => $row) {
                    if ($row['storageKey'] === $params['key'] && $row['state'] === $params['state']) {
                        unset($this->journal[$id]);
                    }
                }
            }
            return 1;
        });
        $store->method('exists')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, '`notification`')) {
                return $this->rowFilename === $params['key'];
            }
            foreach ($this->journal as $row) {
                if ($row['storageKey'] === $params['storageKey'] && $row['state'] === $params['state']) {
                    return true;
                }
            }
            return false;
        });
        $store->method('select')->willReturnCallback(function ($sql, $params) {
            $rows = [];
            foreach ($this->journal as $id => $row) {
                if ($row['state'] === $params['state'] && $row['createdDt'] < $params['before']) {
                    $rows[] = ['id' => $id, 'storageKey' => $row['storageKey'], 'attempts' => 0];
                }
            }
            return $rows;
        });

        return $store;
    }

    private function fakeStorage(bool $hasLocalFiles): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn($hasLocalFiles);
        $storage->method('getLocation')->willReturn('test:/library/');
        $storage->method('exists')->willReturnCallback(fn (string $key) => isset($this->files[$key]));
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            $this->files[$key] = file_get_contents($path);
            unlink($path);
        });
        $storage->method('copy')->willReturnCallback(function (string $from, string $to) {
            $this->files[$to] = $this->files[$from];
        });
        $storage->method('delete')->willReturnCallback(function (string $key) {
            unset($this->files[$key]);
        });

        return $storage;
    }
}
