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
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Stash\Interfaces\PoolInterface;
use Xibo\Controller\Display;
use Xibo\Entity\User;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\DisplayTypeFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * A display's screenshot is shown to users who may view the display, and one that is missing or cannot be read
 * answers with a status, not an image.
 */
class DisplayScreenShotTest extends TestCase
{
    /** @var string[] */
    private array $errors = [];

    private function screenShotResponse(
        LibraryStorageServiceInterface $storage,
        bool $canView = true
    ): ResponseInterface {
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::SCREENSHOT)->willReturn($storage);

        $display = $this->createMock(\Xibo\Entity\Display::class);
        $display->displayId = 5;
        $displayFactory = $this->createMock(DisplayFactory::class);
        $displayFactory->method('getById')->with(5)->willReturn($display);

        $user = $this->createMock(User::class);
        $user->method('checkViewable')->willReturn($canView);
        $user->method('featureEnabled')->with('displays.limitedView')->willReturn(false);
        $log = $this->createMock(LogServiceInterface::class);
        $log->method('error')->willReturnCallback(function ($message) {
            $this->errors[] = $message;
        });
        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getUser')->willReturn($user);
        $base->method('getLogger')->willReturn($log);

        $controller = new Display(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(PoolInterface::class),
            $this->createMock(PlayerActionServiceInterface::class),
            $displayFactory,
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(DisplayTypeFactory::class),
            $this->createMock(LayoutFactory::class),
            $this->createMock(DisplayProfileFactory::class),
            $this->createMock(DisplayEventFactory::class),
            $this->createMock(RequiredFileFactory::class),
            $this->createMock(TagFactory::class),
            $this->createMock(NotificationFactory::class),
            $this->createMock(UserGroupFactory::class),
            $this->createMock(PlayerVersionFactory::class),
            $this->createMock(DayPartFactory::class),
            $registry,
            $this->createMock(FolderFactory::class)
        );
        $controller->useBaseDependenciesService($base);

        return $controller->screenShot(
            new ServerRequest(new PsrServerRequest('GET', '/display/screenshot/5')),
            new Response(new PsrResponse(), new HttpFactory()),
            5
        );
    }

    private function failingStorage(\Throwable $readFailure): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->method('readStream')->with('5_screenshot.jpg')->willThrowException($readFailure);
        return $storage;
    }

    public function testAStoredScreenShotIsReturnedAsStored()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->method('readStream')->with('5_screenshot.jpg')->willReturn(Utils::streamFor('jpeg bytes'));

        $response = $this->screenShotResponse($storage);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('jpeg bytes', (string)$response->getBody());
    }

    public function testAScreenShotWithoutALocalFileIsRedirected()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('exists')->with('5_screenshot.jpg')->willReturn(true);
        $storage->method('getUserDownloadUrl')->with('5_screenshot.jpg', 'image/jpeg', 'inline')
            ->willReturn('https://storage.example.com/5_screenshot.jpg');
        $storage->expects($this->never())->method('readStream');

        $response = $this->screenShotResponse($storage);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/5_screenshot.jpg', $response->getHeaderLine('Location'));
    }

    public function testAScreenShotThatCannotBeLinkedIsUnavailable()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('exists')->willReturn(true);
        $storage->method('getUserDownloadUrl')->willThrowException(new DownloadUrlUnavailableException('no'));

        $this->assertSame(503, $this->screenShotResponse($storage)->getStatusCode());
    }

    public function testAScreenShotMissingFromStorageIsNotFound()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('exists')->with('5_screenshot.jpg')->willReturn(false);
        $storage->expects($this->never())->method('getUserDownloadUrl');

        $this->assertSame(404, $this->screenShotResponse($storage)->getStatusCode());
    }

    public function testStorageThatCannotBeCheckedIsUnavailable()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('exists')->willThrowException(new GeneralException('outage'));

        $this->assertSame(503, $this->screenShotResponse($storage)->getStatusCode());
    }

    public function testAUserWhoCannotViewTheDisplayIsDenied()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('readStream');

        $this->expectException(AccessDeniedException::class);
        $this->screenShotResponse($storage, false);
    }

    public function testAMissingScreenShotIsNotFound()
    {
        $response = $this->screenShotResponse($this->failingStorage(new NotFoundException('missing')));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('', (string)$response->getBody());
    }

    public function testAStorageErrorIsUnavailableAndLogged()
    {
        $response = $this->screenShotResponse($this->failingStorage(new GeneralException('outage')));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('', (string)$response->getBody());
        $this->assertCount(1, $this->errors);
    }
}
