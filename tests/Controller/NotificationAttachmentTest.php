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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Controller\Notification;
use Xibo\Entity\Notification as NotificationEntity;
use Xibo\Entity\User;
use Xibo\Entity\UserNotification;
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
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Saving a notification stores its uploaded attachment: moved from temp on local storage, or claimed from its staged
 * copy in storage when the upload may have reached another web server. Downloading it serves it from that storage.
 */
class NotificationAttachmentTest extends TestCase
{

    private string $library;

    private ?Notification $controller = null;

    /** @var string[] what happens, in order */
    private array $events = [];

    private ?string $movedFrom = null;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/notification-attachment-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library . 'temp', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->library));
    }

    private function save(
        bool $hasLocalFiles,
        string $attachedFilename,
        string $previousFilename,
        bool $isStaged = true
    ): NotificationEntity {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            $this->events[] = 'commit';
            $callback();
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'record:' . $params['state'] . ':' . $params['key'];
            return 4;
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'clear:' . ($params['id'] ?? $params['key']);
            return 1;
        });

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn($hasLocalFiles);
        $storage->method('exists')->willReturnCallback(fn (string $key) => $isStaged && $key === '.staged/7_a.pdf');
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            $this->movedFrom = $path;
            $this->events[] = 'move:' . basename($path) . '>' . $key;
        });
        $storage->method('copy')->willReturnCallback(function (string $from, string $to) {
            $this->events[] = 'copy:' . $from . '>' . $to;
        });
        $storage->method('delete')->willReturnCallback(function (string $key) {
            $this->events[] = 'delete:' . $key;
        });

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : null
        );
        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getConfig')->willReturn($config);
        $user = $this->createMock(User::class);
        $user->userId = 7;
        $base->method('getUser')->willReturn($user);
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());

        $controller = new Notification(
            $this->createMock(NotificationFactory::class),
            $this->createMock(UserNotificationFactory::class),
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayNotifyService::class),
            $this->createMock(NullSession::class),
            $storage,
            $store
        );
        $controller->useBaseDependenciesService($base);

        $notification = $this->createMock(NotificationEntity::class);
        $notification->notificationId = 5;
        $notification->filename = $previousFilename;
        $notification->method('save')->willReturnCallback(function () {
            $this->events[] = 'save';
        });
        $notification->method('deleteAttachmentFile')->willReturnCallback(function ($filename) {
            $this->events[] = 'deleteAttachment:' . $filename;
        });

        $this->controller = $controller;

        if ($attachedFilename === '') {
            return $notification;
        }

        $method = new \ReflectionMethod($controller, 'handleAttachmentUpload');
        $method->invoke(
            $controller,
            $notification,
            (new SanitizerService())->getSanitizer(['attachedFilename' => $attachedFilename])
        );

        return $notification;
    }

    public function testLocalStorageMovesTheUploadFromTempAndRemovesThePreviousFile()
    {
        $notification = $this->save(true, 'my..doc.pdf', '5_old.pdf');

        $this->assertSame([
            'record:pending_upload:5_my.doc.pdf',
            'move:my..doc.pdf>5_my.doc.pdf',
            'save',
            'commit',
            'clear:4',
            'deleteAttachment:5_old.pdf',
        ], $this->events);
        $this->assertSame('5_my.doc.pdf', $notification->filename);
        $this->assertSame('my..doc.pdf', $notification->originalFileName);
    }

    public function testLocalStorageOnlyClaimsFromTheUsersOwnUploadFolder()
    {
        $this->save(true, 'saved_report_1.pdf', '');

        $this->assertSame(
            AttachmentUploadHandler::getUserUploadDir($this->library, 7) . 'saved_report_1.pdf',
            $this->movedFrom
        );
    }

    public function testAStagedUploadIsClaimedAndItsStagedCopyRemovedAfterCommit()
    {
        $notification = $this->save(false, 'a.pdf', '');

        $this->assertSame([
            'record:pending_upload:5_a.pdf',
            'copy:.staged/7_a.pdf>5_a.pdf',
            'save',
            'commit',
            'clear:4',
            'commit',
            'delete:.staged/7_a.pdf',
            'clear:.staged/7_a.pdf',
            'deleteAttachment:',
        ], $this->events);
        $this->assertSame('a.pdf', $notification->originalFileName);
    }

    public function testReplacingWithTheSameNameKeepsTheFile()
    {
        $this->save(false, 'a.pdf', '5_a.pdf');

        $this->assertNotContains('deleteAttachment:5_a.pdf', $this->events);
    }

    public function testStagingReplacesTheUploadRecordOfAnEarlierUploadWithTheSameName()
    {
        $this->save(false, '', '');
        $method = new \ReflectionMethod($this->controller, 'stageAttachment');

        $method->invoke($this->controller, $this->library . 'temp/a.pdf', 'a.pdf');
        $this->assertSame([
            'clear:.staged/7_a.pdf',
            'record:pending_upload:.staged/7_a.pdf',
            'move:a.pdf>.staged/7_a.pdf',
        ], $this->events);
    }

    public function testANameThisUserNeverStagedIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->save(false, 'a.pdf', '', false);
    }

    /**
     * Downloads notification 5's attachment as a user who received it, unless $userNotificationFactory says otherwise.
     */
    private function export(
        LibraryStorageServiceInterface $storage,
        ?UserNotificationFactory $userNotificationFactory = null,
        string $entryPoint = 'web',
        string $query = ''
    ): ResponseInterface {
        if ($userNotificationFactory === null) {
            $userNotification = $this->createMock(UserNotification::class);
            $userNotification->filename = '5_a.pdf';
            $userNotificationFactory = $this->createMock(UserNotificationFactory::class);
            $userNotificationFactory->expects($this->once())->method('getByNotificationId')->with(5)
                ->willReturn($userNotification);
        }

        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());

        $controller = new Notification(
            $this->createMock(NotificationFactory::class),
            $userNotificationFactory,
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(UserGroupFactory::class),
            $this->createMock(DisplayNotifyService::class),
            $this->createMock(NullSession::class),
            $storage,
            $this->createMock(StorageServiceInterface::class)
        );
        $controller->useBaseDependenciesService($base);

        return $controller->exportAttachment(
            (new ServerRequest(new PsrServerRequest('GET', '/notification/export/5' . $query)))
                ->withAttribute('_entryPoint', $entryPoint),
            new Response(new PsrResponse(), new HttpFactory()),
            5
        );
    }

    public function testLocalStorageSendsTheAttachmentFromItsLocalPath()
    {
        $localPath = $this->library . '5_a.pdf';
        file_put_contents($localPath, 'pdf contents');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->method('downloadToTemp')->with('5_a.pdf')->willReturn($localPath);
        $storage->expects($this->once())->method('releaseTemp')->with($localPath);
        $storage->expects($this->never())->method('readStream');

        $response = $this->export($storage);

        $this->assertSame('pdf contents', (string)$response->getBody());
        $this->assertSame('attachment; filename=5_a.pdf', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('12', $response->getHeaderLine('Content-Length'));
    }

    public function testStorageWithoutLocalFilesRedirectsToTheAttachmentForWebAndApi()
    {
        foreach (['web', 'api'] as $entryPoint) {
            $storage = $this->createMock(LibraryStorageServiceInterface::class);
            $storage->method('hasLocalFiles')->willReturn(false);
            $storage->method('getUserDownloadUrl')
                ->with(
                    '5_a.pdf',
                    'application/octet-stream',
                    'attachment; filename="5_a.pdf"; filename*=UTF-8\'\'5_a.pdf'
                )
                ->willReturn('https://storage.example.com/5_a.pdf?signature=x');
            $storage->expects($this->never())->method('readStream');
            $storage->expects($this->never())->method('downloadToTemp');

            $response = $this->export($storage, null, $entryPoint);

            $this->assertSame(302, $response->getStatusCode(), $entryPoint);
            $this->assertSame('https://storage.example.com/5_a.pdf?signature=x', $response->getHeaderLine('Location'));
            $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
    }

    public function testAUserWhoDidNotReceiveTheNotificationCannotDownloadItsAttachment()
    {
        $userNotificationFactory = $this->createMock(UserNotificationFactory::class);
        $userNotificationFactory->method('getByNotificationId')->with(5)
            ->willThrowException(new AccessDeniedException());
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->never())->method('readStream');

        $this->expectException(AccessDeniedException::class);
        $this->export($storage, $userNotificationFactory);
    }

    public function testANotificationWithoutAnAttachmentIsNotFound()
    {
        $userNotification = $this->createMock(UserNotification::class);
        $userNotification->filename = '';
        $userNotificationFactory = $this->createMock(UserNotificationFactory::class);
        $userNotificationFactory->method('getByNotificationId')->willReturn($userNotification);
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->never())->method('readStream');

        $this->expectException(NotFoundException::class);
        $this->export($storage, $userNotificationFactory);
    }
}
