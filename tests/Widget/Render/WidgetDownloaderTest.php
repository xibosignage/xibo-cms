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

namespace Xibo\Tests\Widget\Render;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Media;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Render\WidgetDownloader;

/**
 * WidgetDownloader's test
 */
class WidgetDownloaderTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-widgetdownloader-test-' . uniqid() . '/';
        mkdir($this->libraryLocation, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemove($this->libraryLocation);
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->recursiveRemove($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function newDownloader(LibraryStorageServiceInterface $storage): WidgetDownloader
    {
        $downloader = new WidgetDownloader($this->libraryLocation, 'Off', 0, $storage);
        return $downloader->useLogger($this->createMock(LoggerInterface::class));
    }

    private function newMedia(): Media
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        return new Media(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(LogServiceInterface::class),
            null,
            $config,
            $this->createMock(MediaFactory::class),
            $this->createMock(PermissionFactory::class),
            $this->createMock(LibraryStorageServiceInterface::class)
        );
    }

    private function newResponse(): Response
    {
        $psr17 = new Psr17Factory();
        return new Response($psr17->createResponse(200), $psr17);
    }

    /** A valid source image larger than the thumbnail. */
    private function writeRealSourceImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xibo-source-');
        $img = imagecreatetruecolor(640, 480);
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    private function newImage(int $mediaId, string $md5): Media
    {
        $media = $this->newMedia();
        $media->mediaId = $mediaId;
        $media->mediaType = 'image';
        $media->storedAs = $mediaId . '.jpg';
        $media->md5 = $md5;
        return $media;
    }

    private function newVideo(int $mediaId): Media
    {
        $media = $this->newMedia();
        $media->mediaId = $mediaId;
        $media->mediaType = 'video';
        $media->storedAs = $mediaId . '.mp4';
        return $media;
    }

    private function storedThumbnail(string $key): LibraryStorageServiceInterface&MockObject
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->method('readStream')->with($key)
            ->willReturn((new Psr17Factory())->createStream('thumbnail bytes'));
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->never())->method('moveFromLocalPath');
        return $storage;
    }

    public function testAStoredThumbnailIsStreamedWithItsVersionAsTheEtag()
    {
        $media = $this->newImage(5, 'abc');
        $media->thumbnailVersion = 'abc';

        $response = $this->newDownloader($this->storedThumbnail('tn_5.jpg_abc'))
            ->thumbnail($media, $this->newResponse(), new Pool(new Ephemeral()));

        $this->assertSame('thumbnail bytes', (string)$response->getBody());
        $this->assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
        $this->assertSame('"abc"', $response->getHeaderLine('ETag'));
    }

    public function testAThumbnailWithoutALocalFileIsRedirectedToItsCacheableUrl()
    {
        $media = $this->newImage(5, 'abc');
        $media->thumbnailVersion = 'abc';
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->once())->method('getUserDownloadUrl')
            ->with(
                'tn_5.jpg_abc',
                'image/jpeg',
                'inline',
                CacheableUrlWindow::start(time()),
                CacheableUrlWindow::cacheControl(),
                null
            )
            ->willReturn('https://storage.example.com/tn_5.jpg_abc');

        $response = $this->newDownloader($storage)->thumbnail($media, $this->newResponse(), new Pool(new Ephemeral()));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/tn_5.jpg_abc', $response->getHeaderLine('Location'));
    }

    public function testAThumbnailThatCannotBeLinkedIsA503RatherThanTheErrorImage()
    {
        $media = $this->newImage(5, 'abc');
        $media->thumbnailVersion = 'abc';
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('getUserDownloadUrl')->willThrowException(new DownloadUrlUnavailableException('no'));

        $this->expectException(DownloadUrlUnavailableException::class);
        $this->newDownloader($storage)->thumbnail(
            $media,
            $this->newResponse(),
            new Pool(new Ephemeral()),
            PROJECT_ROOT . '/web/img/error.png'
        );
    }

    public function testAStoredCoverThumbnailIsAPng()
    {
        $media = $this->newVideo(9);
        $media->thumbnailVersion = 'def';

        $response = $this->newDownloader($this->storedThumbnail('tn_9_videocover.png_def'))
            ->thumbnail($media, $this->newResponse(), new Pool(new Ephemeral()));

        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
    }

    public function testAVideoWithoutACoverShowsTheErrorImageWithoutTouchingStorage()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method($this->anything());

        $response = $this->newDownloader($storage)->thumbnail(
            $this->newVideo(9),
            $this->newResponse(),
            new Pool(new Ephemeral()),
            PROJECT_ROOT . '/web/img/error.png'
        );

        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', $response->getHeaderLine('ETag'));
    }

    public function testANonImageWithoutAThumbnailIsNotDownloadedToMakeOne()
    {
        $media = $this->createPartialMock(Media::class, ['fillThumbnail']);
        $media->mediaId = 7;
        $media->mediaType = 'pdf';
        $media->storedAs = '7.pdf';
        $media->expects($this->never())->method('fillThumbnail');
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method($this->anything());

        $response = $this->newDownloader($storage)
            ->thumbnail($media, $this->newResponse(), new Pool(new Ephemeral()), PROJECT_ROOT . '/web/img/error.png');

        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', $response->getHeaderLine('ETag'));
    }

    public function testAMissingThumbnailIsFilledThenStreamed()
    {
        $media = $this->createPartialMock(Media::class, ['fillThumbnail']);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.jpg';
        $pool = new Pool(new Ephemeral());
        $media->expects($this->once())->method('fillThumbnail')->with($pool, null)
            ->willReturnCallback(function () use ($media) {
                $media->thumbnailVersion = 'abc';
                return true;
            });

        $response = $this->newDownloader($this->storedThumbnail('tn_5.jpg_abc'))
            ->thumbnail($media, $this->newResponse(), $pool);

        $this->assertSame('thumbnail bytes', (string)$response->getBody());
    }

    public function testAThumbnailAnotherRequestIsMakingShowsTheErrorImage()
    {
        $media = $this->createPartialMock(Media::class, ['fillThumbnail']);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.jpg';
        $media->method('fillThumbnail')->willReturn(false);
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('readStream');

        $response = $this->newDownloader($storage)
            ->thumbnail($media, $this->newResponse(), new Pool(new Ephemeral()), PROJECT_ROOT . '/web/img/error.png');

        $this->assertSame('image/png', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', $response->getHeaderLine('ETag'));
    }

    public function testAThumbnailMissingFromStorageIsFilledAgain()
    {
        $media = $this->createPartialMock(Media::class, ['fillThumbnail']);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.jpg';
        $media->thumbnailVersion = 'abc';
        $pool = new Pool(new Ephemeral());
        $media->expects($this->once())->method('fillThumbnail')->with($pool, 'abc')->willReturn(true);

        $reads = 0;
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->method('readStream')->with('tn_5.jpg_abc')->willReturnCallback(function () use (&$reads) {
            if ($reads++ === 0) {
                throw new NotFoundException();
            }
            return (new Psr17Factory())->createStream('thumbnail bytes');
        });

        $response = $this->newDownloader($storage)->thumbnail($media, $this->newResponse(), $pool);

        $this->assertSame('thumbnail bytes', (string)$response->getBody());
    }

    public function testPreviewSourcesAreDownloadedOncePerVersionWhenFilesAreNotLocal()
    {
        $versions = [1000, 1000, 2000];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('lastModified')->with('5.jpg')->willReturnCallback(function () use (&$versions) {
            return array_shift($versions);
        });
        // Downloaded once per version, with the repeat served from the local copy.
        $storage->expects($this->exactly(2))->method('downloadToTemp')->with('5.jpg')
            ->willReturnCallback(fn () => $this->writeRealSourceImage());

        $params = (new SanitizerService())->getSanitizer(['width' => 10, 'height' => 10]);
        $downloader = $this->newDownloader($storage);

        $downloader->imagePreview($params, '5.jpg', $this->newResponse());
        $downloader->imagePreview($params, '5.jpg', $this->newResponse());
        $this->assertFileExists($this->libraryLocation . 'tn_src_5.jpg_1000');

        $downloader->imagePreview($params, '5.jpg', $this->newResponse());
        $this->assertFileExists($this->libraryLocation . 'tn_src_5.jpg_2000');
        $this->assertFileDoesNotExist($this->libraryLocation . 'tn_src_5.jpg_1000', 'old versions are removed');
    }

    public function testANewPreviewSourceLeavesACopyAnotherRequestIsWritingAlone()
    {
        $writing = $this->libraryLocation . 'tn_src_6.jpg_2000.1a2b3c4d.part';
        file_put_contents($writing, 'partial');
        $abandoned = $this->libraryLocation . 'tn_src_6.jpg_1000.9f8e7d6c.part';
        file_put_contents($abandoned, 'partial');
        touch($abandoned, time() - 7200);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('lastModified')->willReturn(2000);
        $storage->method('downloadToTemp')->willReturnCallback(fn () => $this->writeRealSourceImage());

        $params = (new SanitizerService())->getSanitizer(['width' => 10, 'height' => 10]);
        $this->newDownloader($storage)->imagePreview($params, '6.jpg', $this->newResponse());

        $this->assertFileExists($this->libraryLocation . 'tn_src_6.jpg_2000');
        $this->assertFileExists($writing);
        $this->assertFileDoesNotExist($abandoned, 'an abandoned partial copy is removed');
    }

    private function newRequest(): ServerRequest
    {
        return new ServerRequest((new Psr17Factory())->createServerRequest('GET', '/library/download/5'));
    }

    private function storedFile(?string $userUrl): LibraryStorageServiceInterface&MockObject
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('size')->willReturn(4);
        $storage->method('hasLocalFiles')->willReturn($userUrl === null);
        $storage->method('getUserDownloadUrl')->willReturn($userUrl);
        return $storage;
    }

    public function testDownloadRedirectsToStorageWithTheAttachmentHeaders()
    {
        $storage = $this->storedFile('https://storage.example.com/5.jpg?signature=x');
        $storage->expects($this->once())->method('getUserDownloadUrl')
            ->with(
                '5.jpg',
                'application/octet-stream',
                'attachment; filename="photo.jpg"; filename*=UTF-8\'\'photo.jpg'
            );
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->never())->method('readRange');

        $response = $this->newDownloader($storage)
            ->download($this->newImage(5, 'abc'), $this->newRequest(), $this->newResponse(), null, 'photo.jpg');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://storage.example.com/5.jpg?signature=x', $response->getHeaderLine('Location'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertFalse($response->hasHeader('ETag'));
    }

    public function testStreamedAttachmentNameIsSanitised()
    {
        $storage = $this->storedFile(null);
        $storage->method('readStream')->willReturn(\GuzzleHttp\Psr7\Utils::streamFor('data'));

        $response = $this->newDownloader($storage)
            ->download($this->newImage(5, 'abc'), $this->newRequest(), $this->newResponse(), null, "a\"b\r\n.jpg");

        $this->assertSame(
            'attachment; filename="a_b__.jpg"; filename*=UTF-8\'\'a%22b%0D%0A.jpg',
            $response->getHeaderLine('Content-disposition')
        );
    }

    public function testStreamingPlaybackRedirectsInlineUntilTheGivenTime()
    {
        $storage = $this->storedFile('https://storage.example.com/5.jpg');
        $storage->expects($this->once())->method('getUserDownloadUrl')
            ->with('5.jpg', 'video/mp4', 'inline', null, null, 1_900_000_000);

        $response = $this->newDownloader($storage)->download(
            $this->newImage(5, 'abc'),
            $this->newRequest(),
            $this->newResponse(),
            'video/mp4',
            null,
            1_900_000_000
        );

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testAUrlThatCannotBeMadeIsThrownRatherThanStreamed()
    {
        $storage = $this->storedFile('unused');
        $storage->method('getUserDownloadUrl')->willThrowException(new DownloadUrlUnavailableException('no'));
        $storage->expects($this->never())->method('readStream');

        $this->expectException(DownloadUrlUnavailableException::class);
        $this->newDownloader($storage)
            ->download($this->newImage(5, 'abc'), $this->newRequest(), $this->newResponse());
    }

    public function testALocalFileServesARangeWhenSendFileIsOff()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('size')->with('5.mp4')->willReturn(100);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->expects($this->never())->method('getUserDownloadUrl');
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->once())->method('readRange')->with('5.mp4', 10, 20)
            ->willReturn(\GuzzleHttp\Psr7\Utils::streamFor(str_repeat('x', 20)));

        $request = $this->newRequest()->withHeader('Range', 'bytes=10-29');
        $response = $this->newDownloader($storage)
            ->download($this->newVideo(5), $request, $this->newResponse(), 'video/mp4');

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 10-29/100', $response->getHeaderLine('Content-Range'));
        $this->assertSame('20', $response->getHeaderLine('Content-Length'));
        $this->assertSame('video/mp4', $response->getHeaderLine('Content-Type'));
        $this->assertSame(str_repeat('x', 20), (string)$response->getBody());
    }

    public function testDownloadOfAMissingFileIsNotFound()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('size')->willThrowException(new NotFoundException());
        $storage->expects($this->never())->method('exists');
        $storage->expects($this->never())->method('getUserDownloadUrl');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('The requested media file could not be found in the library.');
        $this->newDownloader($storage)
            ->download($this->newImage(5, 'abc'), $this->newRequest(), $this->newResponse());
    }
}
