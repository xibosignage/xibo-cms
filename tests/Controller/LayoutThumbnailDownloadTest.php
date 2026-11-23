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
use Xibo\Controller\Layout;
use Xibo\Entity\Layout as LayoutEntity;
use Xibo\Entity\User;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\JwtServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class LayoutThumbnailDownloadTest extends TestCase
{
    public function testAThumbnailWithoutALocalFileIsRedirectedToItsCacheableUrl()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        // Stored before this window started.
        $storage->method('lastModified')->with('12.png')->willReturn(CacheableUrlWindow::start(time()) - 60);
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->once())->method('getUserDownloadUrl')
            ->with(
                '12.png',
                'image/png',
                'inline',
                $this->callback(fn ($start) => $start === CacheableUrlWindow::start(time())),
                CacheableUrlWindow::cacheControl(),
                null
            )
            ->willReturn('https://storage.example.com/12.png');

        $response = $this->download($storage);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/12.png', $response->getHeaderLine('Location'));
    }

    public function testAThumbnailStoredAgainInThisWindowGetsANewUrl()
    {
        $storedAt = time() - 5;
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('lastModified')->with('12.png')->willReturn($storedAt);
        $storage->expects($this->once())->method('getUserDownloadUrl')
            ->with('12.png', 'image/png', 'inline', max(CacheableUrlWindow::start(time()), $storedAt))
            ->willReturn('https://storage.example.com/12.png?new');

        $response = $this->download($storage);

        $this->assertSame('https://storage.example.com/12.png?new', $response->getHeaderLine('Location'));
    }

    public function testAMissingThumbnailIsNotFoundRatherThanRedirected()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('lastModified')->willThrowException(new NotFoundException());
        $storage->expects($this->never())->method('getUserDownloadUrl');

        $this->expectException(NotFoundException::class);
        $this->download($storage);
    }

    private function download(LibraryStorageServiceInterface $storage): ResponseInterface
    {
        $layout = $this->createMock(LayoutEntity::class);
        $layout->method('getThumbnailKey')->willReturn('12.png');
        $layoutFactory = $this->createMock(LayoutFactory::class);
        $layoutFactory->method('getById')->with(12)->willReturn($layout);
        $layoutFactory->method('getThumbnailStorage')->willReturn($storage);

        $user = $this->createMock(User::class);
        $user->method('checkViewable')->willReturn(true);
        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getUser')->willReturn($user);
        $base->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));

        $controller = new Layout(
            null,
            null,
            null,
            $layoutFactory,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->createMock(MediaServiceInterface::class),
            $this->createMock(WidgetFactory::class),
            $this->createMock(WidgetDataFactory::class),
            $this->createMock(PlaylistFactory::class),
            $this->createMock(JwtServiceInterface::class),
            $this->createMock(LibraryStorageServiceInterface::class),
            $this->createMock(TaskFactory::class)
        );
        $controller->useBaseDependenciesService($base);

        return $controller->downloadThumbnail(
            new ServerRequest(new PsrServerRequest('GET', '/layout/thumbnail/12')),
            new Response(new PsrResponse(), new HttpFactory()),
            12
        );
    }
}
