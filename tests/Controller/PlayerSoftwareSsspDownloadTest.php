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
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Controller\PlayerSoftware;
use Xibo\Entity\Display;
use Xibo\Entity\DisplayProfile;
use Xibo\Entity\PlayerVersion;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * SSSP displays are always sent the firmware by the CMS, while other downloads may redirect.
 */
class PlayerSoftwareSsspDownloadTest extends TestCase
{
    private Pool $pool;

    protected function setUp(): void
    {
        $this->pool = new Pool(new Ephemeral());
    }

    /**
     * Storage offering a download URL for version 3's file, which is streamed when sent.
     */
    private function storage(bool $redirectExpected): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('size')->with('sssp.wgt')->willReturn(8);
        $storage->method('readStream')->with('sssp.wgt')->willReturn(Utils::streamFor('firmware'));
        $storage->expects($redirectExpected ? $this->once() : $this->never())
            ->method('getUserDownloadUrl')
            ->willReturn('https://storage.example.com/sssp.wgt?signature=x');

        return $storage;
    }

    private function controller(LibraryStorageServiceInterface $storage): PlayerSoftware
    {
        $version = $this->createMock(PlayerVersion::class);
        $version->fileName = 'sssp.wgt';

        $playerVersionFactory = $this->createMock(PlayerVersionFactory::class);
        $playerVersionFactory->method('getById')->with(3)->willReturn($version);
        $playerVersionFactory->method('getStorage')->willReturn($storage);

        $profile = $this->createMock(DisplayProfile::class);
        $profile->method('getSetting')->with('versionMediaId')->willReturn(3);
        $displayProfileFactory = $this->createMock(DisplayProfileFactory::class);
        $displayProfileFactory->method('getDefaultByType')->with('sssp')->willReturn($profile);

        $display = $this->createMock(Display::class);
        $display->method('getSetting')->willReturn(3);
        $displayFactory = $this->createMock(DisplayFactory::class);
        $displayFactory->method('getById')->with(7)->willReturn($display);

        $base = $this->createMock(BaseDependenciesService::class);
        $base->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());

        $controller = new PlayerSoftware(
            $this->pool,
            $playerVersionFactory,
            $displayProfileFactory,
            $displayFactory,
            $this->createMock(MediaServiceInterface::class),
            $this->createMock(StorageServiceInterface::class)
        );
        $controller->useBaseDependenciesService($base);
        return $controller;
    }

    private function request(string $path): ServerRequest
    {
        // Asking for a redirect, which SSSP must still not get.
        return new ServerRequest(new PsrServerRequest('GET', $path . '?redirect=1'));
    }

    private function response(): Response
    {
        return new Response(new PsrResponse(), new HttpFactory());
    }

    private function assertStreamed(Response $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('firmware', (string)$response->getBody());
        $this->assertFalse($response->hasHeader('Location'));
    }

    public function testTheSsspInstallDownloadIsAlwaysStreamed()
    {
        $response = $this->controller($this->storage(false))
            ->getSsspInstallDownload($this->request('/sssp_dl.wgt'), $this->response());

        $this->assertStreamed($response);
    }

    public function testADisplaysSsspVersionFileIsAlwaysStreamed()
    {
        $item = $this->pool->getItem('/playerVersion/abc');
        $item->set(7);
        $this->pool->save($item);

        $response = $this->controller($this->storage(false))
            ->getVersionFile($this->request('/playersoftware/abc/sssp_dl.wgt'), $this->response(), 'abc');

        $this->assertStreamed($response);
    }

    public function testADownloadAlwaysRedirectsToStorage()
    {
        $request = (new ServerRequest(new PsrServerRequest('GET', '/playersoftware/download/3')))
            ->withAttribute('_entryPoint', 'api');
        $response = $this->controller($this->storage(true))->download($request, $this->response(), 3);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/sssp.wgt?signature=x', $response->getHeaderLine('Location'));
    }
}
