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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Xibo\Controller\Library;
use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Entity\User;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\JwtServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\PdoStorageService;

/**
 * The editor's media panel shows stored thumbnails, and previews and thumbnails don't hold the session lock.
 */
class LibraryPreviewTest extends TestCase
{
    /** The session status when the media was looked up. */
    private ?int $sessionAtLookup = null;

    private function media(string $type): Media
    {
        $media = $this->getMockBuilder(Media::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $media->mediaId = 5;
        $media->mediaType = $type;
        $media->name = 'logo';
        $media->storedAs = '5.' . ($type === 'image' ? 'png' : 'mp4');
        $media->thumbnailVersion = null;
        return $media;
    }

    private function module(string $type): Module
    {
        $module = $this->getMockBuilder(Module::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $module->type = $type;
        $module->hasThumbnail = 1;
        $module->allowPreview = 1;
        $module->regionSpecific = 0;
        return $module;
    }

    private function controller(Media $media, bool $viewable = true): Library
    {
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);
        $mediaFactory->method('getById')->willReturnCallback(function () use ($media) {
            $this->sessionAtLookup = session_status();
            return $media;
        });
        $mediaFactory->method('getStorageFor')->willReturn($this->createMock(LibraryStorageServiceInterface::class));
        $moduleFactory = $this->createMock(ModuleFactory::class);
        $moduleFactory->method('getByType')->willReturn($this->module($media->mediaType));

        $user = $this->createMock(User::class);
        $user->method('checkViewable')->willReturn($viewable);
        $base = $this->createMock(BaseDependenciesService::class);
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(fn (string $setting, $default = null) => match ($setting) {
            'LIBRARY_LOCATION' => '/tmp/library/',
            'SENDFILE_MODE' => 'Off',
            default => $default,
        });
        $base->method('getConfig')->willReturn($config);
        $base->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $base->method('getSanitizer')->willReturn(new SanitizerService());
        $base->method('getUser')->willReturn($user);

        $controller = new Library(
            null,
            $moduleFactory,
            null,
            $mediaFactory,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->createMock(JwtServiceInterface::class),
            $this->createMock(LibraryStorageServiceInterface::class)
        );
        $controller->useBaseDependenciesService($base);
        $mediaService = $this->createMock(MediaServiceInterface::class);
        $mediaService->method('setUser')->willReturnSelf();
        $controller->useMediaService($mediaService);
        return $controller;
    }

    private function response(): Response
    {
        return new Response(new PsrResponse(), new HttpFactory());
    }

    /**
     * The media panel's thumbnail URL for $media, checking which route it asks for.
     */
    private function panelThumbnail(Media $media, string $route, array $params): string
    {
        $routeParser = $this->createMock(RouteParserInterface::class);
        $routeParser->expects($this->once())
            ->method('urlFor')
            ->with($route, ['id' => 5], $params)
            ->willReturnCallback(fn (string $name) => '/' . $name . '/5');
        $request = (new ServerRequest(new PsrServerRequest('GET', '/library/search')))
            ->withAttribute(RouteContext::ROUTE_PARSER, $routeParser)
            ->withAttribute(RouteContext::ROUTING_RESULTS, $this->createMock(RoutingResults::class));

        $response = $this->controller($media)->search($request, $this->response());

        return json_decode((string)$response->getBody(), true)['data'][0]['thumbnail'];
    }

    public function testTheMediaPanelShowsTheStoredThumbnail()
    {
        $video = $this->media('video');
        $video->thumbnailVersion = 'v1';

        $this->assertSame(
            '/library.thumbnail/5',
            $this->panelThumbnail($this->media('image'), 'library.thumbnail', [])
        );
        $this->assertSame('/library.thumbnail/5', $this->panelThumbnail($video, 'library.thumbnail', []));
    }

    public function testAVideoWithoutACoverStillShowsTheBlankImage()
    {
        $this->assertSame('/library.download/5', $this->panelThumbnail(
            $this->media('video'),
            'library.download',
            ['preview' => 1, 'isThumb' => 1]
        ));
    }

    /**
     * Starts a session whose writes are counted.
     */
    private function startSession(int &$writes): void
    {
        $pdo = $this->createMock(\PDO::class);
        (new \ReflectionProperty(PdoStorageService::class, 'conn'))->setValue(null, ['default' => $pdo]);
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->method('open')->willReturn(true);
        $handler->method('close')->willReturn(true);
        $handler->method('read')->willReturn('');
        $handler->method('write')->willReturnCallback(function () use (&$writes) {
            $writes++;
            return true;
        });
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        session_set_save_handler($handler, false);
        session_start();
    }

    private function preview(Library $controller): Response
    {
        $request = new ServerRequest(new PsrServerRequest('GET', '/library/download/5?preview=1'));
        return $controller->download($request->withQueryParams(['preview' => 1]), $this->response(), 5);
    }

    #[RunInSeparateProcess]
    public function testAPreviewReleasesTheSessionLockWithoutWritingIt()
    {
        $writes = 0;
        $this->startSession($writes);

        $response = $this->preview($this->controller($this->media('video')));

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(0, $writes);
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
    }

    #[RunInSeparateProcess]
    public function testAPreviewOfMediaNotViewableIsBlankWithTheLockReleased()
    {
        $writes = 0;
        $this->startSession($writes);

        ob_start();
        $response = $this->preview($this->controller($this->media('image'), false));
        $body = ob_get_clean();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(0, $writes);
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $body);
    }

    #[RunInSeparateProcess]
    public function testAThumbnailReleasesTheSessionLockWithoutWritingIt()
    {
        $writes = 0;
        $this->startSession($writes);

        $response = $this->controller($this->media('video'))->thumbnail(
            new ServerRequest(new PsrServerRequest('GET', '/library/thumbnail/5')),
            $this->response(),
            5
        );

        $this->assertSame(PHP_SESSION_NONE, $this->sessionAtLookup);
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(0, $writes);
        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
    }
}
