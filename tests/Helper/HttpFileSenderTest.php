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

namespace Xibo\Tests\Helper;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Entity\Layout;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Xmds\HttpFileSender;
use Xibo\Xmds\PlayerFileLink;

class HttpFileSenderTest extends TestCase
{
    private string $libraryLocation;
    private RecordingOutput $output;

    /** @var array<int, array{0: string, 1: int}> */
    private array $bandwidth = [];

    private LayoutFactory&MockObject $layoutFactory;
    private RequiredFileFactory&MockObject $requiredFileFactory;
    private PlayerFileLink&MockObject $playerFileLink;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-sender-test-' . uniqid() . '/';
        mkdir($this->libraryLocation, 0777, true);
        $this->output = new RecordingOutput();
        $this->layoutFactory = $this->createMock(LayoutFactory::class);
        $this->requiredFileFactory = $this->createMock(RequiredFileFactory::class);
        $this->playerFileLink = $this->createMock(PlayerFileLink::class);
        $this->logger = new NullLogger();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->libraryLocation . '*'));
        rmdir($this->libraryLocation);
    }

    private function sender(
        LibraryStorageServiceInterface $mediaStorage,
        string $sendFileMode = 'Apache',
        ?LibraryStorageRegistryInterface $registry = null,
        ?StorageServiceInterface $store = null,
        ?PlayerFileLink $playerFileLink = null
    ): HttpFileSender {
        $bandwidthFactory = $this->createMock(BandwidthFactory::class);
        $bandwidthFactory->method('createAndSave')->willReturnCallback(function ($type, $displayId, $size) {
            $this->bandwidth[] = [$type, $size];
            return null;
        });

        return new HttpFileSender(
            $this->output,
            $mediaStorage,
            $this->layoutFactory,
            $this->requiredFileFactory,
            $playerFileLink ?? $this->playerFileLink,
            $bandwidthFactory,
            $store ?? $this->createMock(StorageServiceInterface::class),
            $this->logger,
            $sendFileMode,
            $this->libraryLocation,
            $registry ?? $this->createConfiguredMock(LibraryStorageRegistryInterface::class, ['get' => $mediaStorage])
        );
    }

    private function display(string $clientType = 'android', int $clientCode = 400): Display
    {
        $display = $this->getMockBuilder(Display::class)->disableOriginalConstructor()
            ->onlyMethods(['getSetting'])->getMock();
        $display->displayId = 7;
        $display->clientType = $clientType;
        $display->clientCode = $clientCode;
        return $display;
    }

    private function file(
        string $type = 'M',
        string $path = '5.txt',
        int $size = 10,
        ?string $fileType = null
    ): RequiredFile {
        $file = $this->getMockBuilder(RequiredFile::class)->disableOriginalConstructor()
            ->onlyMethods(['save'])->getMock();
        $file->rfId = 3;
        $file->displayId = 7;
        $file->type = $type;
        $file->itemId = 5;
        $file->path = $path;
        $file->size = $size;
        $file->fileType = $fileType;
        return $file;
    }

    private function localStorage(): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->expects($this->never())->method('getDirectDownloadUrl');
        return $storage;
    }

    /**
     * Storage without local files, whose files players are linked to directly.
     */
    private function storage(): LibraryStorageServiceInterface&MockObject
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->expects($this->never())->method('getDirectDownloadUrl');
        $storage->expects($this->never())->method('readRange');
        $storage->expects($this->never())->method('readStream');
        return $storage;
    }

    /**
     * A service without local files holding one file, whose stream doesn't know its size.
     */
    private function storageHolding(string $key, string $contents): LibraryStorageServiceInterface&MockObject
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->expects($this->never())->method('getDirectDownloadUrl');
        $storage->method('size')->with($key)->willReturn(strlen($contents));
        $storage->method('readStream')->with($key)
            ->willReturn(FnStream::decorate(Utils::streamFor($contents), ['getSize' => fn () => null]));
        $storage->method('readRange')->willReturnCallback(
            fn (string $rangeKey, int $offset, int $length) => Utils::streamFor(substr($contents, $offset, $length))
        );
        return $storage;
    }

    public function testLocalFilesAreSentByApache()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');

        $this->sender($this->localStorage())->send($this->file(), $this->display());

        $this->assertContains('X-Sendfile: ' . $this->libraryLocation . '5.txt', $this->output->headers);
        $this->assertContains('Content-Type: text/plain', $this->output->headers);
        $this->assertSame([[Bandwidth::$GETFILE, 10]], $this->bandwidth);
    }

    public function testLocalFilesAreSentByNginx()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');

        $this->sender($this->localStorage(), 'Nginx')->send($this->file(), $this->display());

        $this->assertContains('X-Accel-Redirect: /download/5.txt', $this->output->headers);
    }

    public function testAStoredFileIsStreamedToEveryPlayer()
    {
        // A link handed out before the file was copied to storage, or before the switch.
        $players = [['android', 400], ['windows', 400], ['linux', 109], ['linux', 400], ['chromeOS', 1]];
        foreach ($players as [$type, $code]) {
            $this->output = new RecordingOutput();

            $this->sender($this->storageHolding('5.txt', 'abc'))->send($this->file(), $this->display($type, $code));

            $this->assertSame(200, $this->output->status, $type . ' ' . $code);
            $this->assertSame('abc', $this->output->body, $type . ' ' . $code);
        }
        $this->assertCount(5, $this->bandwidth);
    }

    public function testARangeOfAStoredFileIsReadAndCountedAlone()
    {
        $this->sender($this->storageHolding('5.mp4', '0123456789'))
            ->send($this->file('M', '5.mp4'), $this->display(), ['HTTP_RANGE' => 'bytes=2-5']);

        $this->assertSame(206, $this->output->status);
        $this->assertSame('2345', $this->output->body);
        $this->assertContains('Content-Range: bytes 2-5/10', $this->output->headers);
        $this->assertContains('Content-Length: 4', $this->output->headers);
        $this->assertContains('Accept-Ranges: bytes', $this->output->headers);
        $this->assertSame([[Bandwidth::$GETFILE, 4]], $this->bandwidth);
    }

    public function testARangePastTheEndIsNotSatisfiable()
    {
        $this->sender($this->storageHolding('5.mp4', '0123456789'))
            ->send($this->file('M', '5.mp4'), $this->display(), ['HTTP_RANGE' => 'bytes=10-']);

        $this->assertSame(416, $this->output->status);
        $this->assertContains('Content-Range: bytes */10', $this->output->headers);
        $this->assertSame('', $this->output->body);
        $this->assertSame([], $this->bandwidth);
    }

    public function testAConditionalRangeGetsTheWholeFile()
    {
        $this->sender($this->storageHolding('5.mp4', '0123456789'))->send(
            $this->file('M', '5.mp4'),
            $this->display(),
            ['HTTP_RANGE' => 'bytes=2-5', 'HTTP_IF_RANGE' => '"abc"']
        );

        $this->assertSame(200, $this->output->status);
        $this->assertSame('0123456789', $this->output->body);
    }

    public function testAPlayerThatGoesMidStreamIsCountedForWhatItReceived()
    {
        $this->output = new class extends RecordingOutput {
            public function write(string $bytes): void
            {
                parent::write($bytes);
                $this->aborted = true;
            }
        };
        $contents = str_repeat('v', 3 * 1024 * 1024);

        $this->sender($this->storageHolding('5.mp4', $contents))->send($this->file('M', '5.mp4'), $this->display());

        $this->assertSame(1024 * 1024, strlen($this->output->body), 'nothing more is read once it has gone');
        $this->assertSame([[Bandwidth::$GETFILE, 1024 * 1024]], $this->bandwidth);
    }

    public function testAOneChunkFileIsSentWholeWithoutAskingIfThePlayerHasGone()
    {
        // Asking flushes the response, which would stop the web server compressing it with a length.
        $this->output = new class extends RecordingOutput {
            public int $asked = 0;

            public function isAborted(): bool
            {
                $this->asked++;
                return false;
            }
        };

        $this->sender($this->storageHolding('5.xlf', '<layout/>'))->send($this->file('M', '5.xlf'), $this->display());

        $this->assertSame('<layout/>', $this->output->body);
        $this->assertSame(0, $this->output->asked);
    }

    public function testAFileThatCannotBeReadIsNotGivenItsLength()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('size')->willReturn(10);
        $storage->method('readStream')->willThrowException(new \RuntimeException('gone'));

        try {
            $this->sender($storage)->send($this->file('M', '5.mp4'), $this->display());
            $this->fail('the failure is passed on');
        } catch (\RuntimeException) {
        }
        $this->assertNotContains('Content-Length: 10', $this->output->headers);
        $this->assertSame([], $this->bandwidth);
    }

    public function testAHeadRequestIsNotReadOrCounted()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('size')->willReturn(10);
        $storage->expects($this->never())->method('readStream');
        $storage->expects($this->never())->method('readRange');

        $this->sender($storage)->send($this->file('M', '5.mp4'), $this->display(), ['REQUEST_METHOD' => 'HEAD']);

        $this->assertSame(200, $this->output->status);
        $this->assertContains('Content-Length: 10', $this->output->headers);
        $this->assertSame('', $this->output->body);
        $this->assertSame([], $this->bandwidth);
    }

    public function testMediaWaitingForALibraryMoveIsSentFromTheLocalLibrary()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturn([['storageEngine' => 'local']]);
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('hasFilesToMove')->willReturn(true);
        $registry->expects($this->once())->method('get')->with('media', 'local')->willReturn($this->localStorage());

        $this->sender($this->storage(), 'Apache', $registry, $store)->send($this->file(), $this->display());

        $this->assertContains('X-Sendfile: ' . $this->libraryLocation . '5.txt', $this->output->headers);
    }

    public function testMediaIsNotLookedUpOnceNothingWaitsToMove()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->never())->method('select');
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('hasFilesToMove')->willReturn(false);

        $this->sender($this->storageHolding('5.txt', 'abc'), 'Apache', $registry, $store)
            ->send($this->file(), $this->display());

        $this->assertSame('abc', $this->output->body);
    }

    public function testAStoredDependencyIsStreamedFromItsOwnStorage()
    {
        $file = $this->file('P', 'fonts/Railway.ttf', 10, 'font');
        $file->setUnmatchedProperty('storage', $this->storageHolding('Railway.ttf', 'font-bytes'));
        $file->setUnmatchedProperty('storageKey', 'Railway.ttf');

        $this->sender($this->localStorage())->send($file, $this->display());

        $this->assertSame('font-bytes', $this->output->body);
        $this->assertSame([[Bandwidth::$GETFILE, 10]], $this->bandwidth);
    }

    public function testAStoredLayoutIsStreamedWithoutBuildingIt()
    {
        $storage = $this->storageHolding('5.xlf', '<layout/>');
        $storage->method('exists')->with('5.xlf')->willReturn(true);
        $this->layoutFactory->expects($this->never())->method('buildXlfLocked');
        $this->layoutFactory->expects($this->never())->method('rebuildMissingXlf');

        $this->sender($storage)->send($this->file('L', '5.xlf'), $this->display());

        $this->assertSame('<layout/>', $this->output->body);
    }

    public function testALocalLayoutIsSentWithoutBuildingIt()
    {
        file_put_contents($this->libraryLocation . '5.xlf', '<layout/>');
        $this->layoutFactory->expects($this->never())->method('buildXlfLocked');
        $this->layoutFactory->expects($this->never())->method('rebuildMissingXlf');

        $this->sender($this->localStorage())->send($this->file('L', '5.xlf'), $this->display());

        $this->assertContains('X-Sendfile: ' . $this->libraryLocation . '5.xlf', $this->output->headers);
    }

    public function testALocalLayoutMissingItsFileIsRebuiltThenSent()
    {
        $layout = $this->createMock(Layout::class);
        $this->layoutFactory->method('getById')->with(5)->willReturn($layout);
        $this->layoutFactory->expects($this->never())->method('buildXlfLocked');
        $this->layoutFactory->expects($this->once())->method('rebuildMissingXlf')->willReturnCallback(
            function ($layout) {
                file_put_contents($this->libraryLocation . '5.xlf', '<layout/>');
                return $layout;
            }
        );

        $this->sender($this->localStorage())->send($this->file('L', '5.xlf'), $this->display());

        $this->assertContains('X-Sendfile: ' . $this->libraryLocation . '5.xlf', $this->output->headers);
    }

    public function testAStoredFileIsStreamedToAPwa()
    {
        $contents = str_repeat('v', 2 * 1024 * 1024 + 5);

        $this->sender($this->storageHolding('5.mp4', $contents))
            ->send($this->file('M', '5.mp4'), $this->display('chromeOS'));

        $this->assertSame(200, $this->output->status);
        $this->assertSame($contents, $this->output->body);
        $this->assertContains('Content-Type: video/mp4', $this->output->headers);
        $this->assertContains('Content-Length: ' . strlen($contents), $this->output->headers);
        $this->assertSame([[Bandwidth::$GETFILE, strlen($contents)]], $this->bandwidth, 'the bytes sent');
    }

    public function testAStoredFileThatFailsMidStreamIsLoggedWithTheBytesSent()
    {
        $reads = 0;
        $stream = FnStream::decorate(Utils::streamFor(''), [
            'eof' => fn () => false,
            'read' => function () use (&$reads) {
                if (++$reads > 1) {
                    throw new \RuntimeException('connection reset');
                }
                return str_repeat('v', 1024 * 1024);
            },
        ]);
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('size')->willReturn(5 * 1024 * 1024);
        $storage->method('readStream')->willReturn($stream);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('HTTP GetFile: streaming 5.mp4 failed after 1048576 bytes. connection reset');
        $this->logger = $logger;

        try {
            $this->sender($storage)->send($this->file('M', '5.mp4'), $this->display('chromeOS'));
            $this->fail('the failure is passed on');
        } catch (\RuntimeException $exception) {
            $this->assertSame('connection reset', $exception->getMessage());
        }
        $this->assertSame([[Bandwidth::$GETFILE, 1048576]], $this->bandwidth, 'the bytes sent before it failed');
    }

    public function testAStoredDependencyIsStreamedToAPwaFromItsOwnStorage()
    {
        $file = $this->file('P', 'fonts/Railway.ttf', 10, 'font');
        $file->setUnmatchedProperty('storage', $this->storageHolding('Railway.ttf', 'font-bytes'));
        $file->setUnmatchedProperty('storageKey', 'Railway.ttf');

        $this->sender($this->localStorage())->send($file, $this->display('chromeOS'));

        $this->assertSame('font-bytes', $this->output->body);
        $this->assertContains('Content-Type: font/ttf', $this->output->headers);
    }

    public function testAStoredLayoutIsStreamedToAPwaWithoutBuildingIt()
    {
        $storage = $this->storageHolding('5.xlf', '<layout/>');
        $storage->method('exists')->with('5.xlf')->willReturn(true);
        $this->layoutFactory->expects($this->never())->method('rebuildMissingXlf');

        $this->sender($storage)->send($this->file('L', '5.xlf'), $this->display('chromeOS'));

        $this->assertSame('<layout/>', $this->output->body);
        $this->assertContains('Content-Type: text/xml', $this->output->headers);
    }

    public function testAStoredLayoutMissingFromStorageIsRebuiltThenStreamedToAPwa()
    {
        $storage = $this->storageHolding('5.xlf', '<layout/>');
        $storage->method('exists')->with('5.xlf')->willReturn(false);
        $this->layoutFactory->method('getById')->with(5)->willReturn($this->createMock(Layout::class));
        $this->layoutFactory->expects($this->once())->method('rebuildMissingXlf');

        $this->sender($storage)->send($this->file('L', '5.xlf'), $this->display('chromeOS'));

        $this->assertSame('<layout/>', $this->output->body);
    }

    public function testAPwaFontCssLinksEachStoredFontThroughTheCms()
    {
        $fonts = $this->storageHolding('fonts.css', "@font-face { src: url('Railway.ttf'); }");
        $file = $this->file('P', 'fonts/fonts.css', 10, 'fontCss');
        $file->setUnmatchedProperty('storage', $fonts);
        $file->setUnmatchedProperty('storageKey', 'fonts.css');

        $font = $this->file('P', 'Railway.ttf');
        $font->realId = 9;
        $this->requiredFileFactory->method('getByDisplayAndDependencyPath')->with(7, 'Railway.ttf')->willReturn($font);

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getApiKeyDetails')->willReturn(['encryptionKey' => 'secret']);
        $registry = $this->createConfiguredMock(LibraryStorageRegistryInterface::class, ['get' => $fonts]);
        $display = $this->display('chromeOS');
        $display->method('getSetting')->willReturnCallback(
            fn ($key, $default = null) => $key === 'collectInterval' ? 300 : $default
        );

        $this->sender($this->localStorage(), 'Apache', null, null, new PlayerFileLink($config, $registry))
            ->send($file, $display);

        $this->assertStringContainsString(
            '/xmds.php?file=Railway.ttf&displayId=7&type=P&itemId=9&fileType=font&',
            $this->output->body
        );
        $this->assertContains('Content-Type: text/css', $this->output->headers);
    }
}
