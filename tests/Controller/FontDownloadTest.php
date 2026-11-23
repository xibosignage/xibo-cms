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
use Xibo\Controller\Font;
use Xibo\Entity\Font as FontEntity;
use Xibo\Factory\FontFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;

/**
 * Downloading a font from storage without local files redirects to it.
 */
class FontDownloadTest extends TestCase
{
    /**
     * Downloads font 3 (Railway.ttf) from $storage.
     */
    private function download(LibraryStorageServiceInterface $storage): ResponseInterface
    {
        $font = $this->createMock(FontEntity::class);
        $font->fileName = 'Railway.ttf';

        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->expects($this->once())->method('getById')->with(3)->willReturn($font);
        $fontFactory->method('getStorage')->willReturn($storage);

        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());

        $controller = new Font($fontFactory);
        $controller->useBaseDependenciesService($base);

        return $controller->download(
            new ServerRequest(new PsrServerRequest('GET', '/fonts/download/3')),
            new Response(new PsrResponse(), new HttpFactory()),
            3
        );
    }

    public function testStorageWithoutLocalFilesAlwaysRedirects()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('getUserDownloadUrl')
            ->with(
                'Railway.ttf',
                'application/octet-stream',
                'attachment; filename="Railway.ttf"; filename*=UTF-8\'\'Railway.ttf'
            )
            ->willReturn('https://storage.example.com/Railway.ttf?signature=x');
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->never())->method('downloadToTemp');

        $response = $this->download($storage);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/Railway.ttf?signature=x', $response->getHeaderLine('Location'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testAUrlThatCannotBeMadeIsA503()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('getUserDownloadUrl')->willThrowException(new DownloadUrlUnavailableException('no'));
        $storage->expects($this->never())->method('readStream');

        try {
            $this->download($storage);
            $this->fail('expected the URL failure');
        } catch (DownloadUrlUnavailableException $exception) {
            $this->assertSame(503, $exception->getHttpStatusCode());
        }
    }
}
