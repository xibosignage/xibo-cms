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
use Xibo\Entity\Media;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\ImageProcessingServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\XTR\ImageProcessingTask;

class ImageProcessingTaskTest extends TestCase
{
    private function newTask(
        StorageServiceInterface $store,
        MediaFactory $mediaFactory,
        DisplayFactory $displayFactory,
        ImageProcessingServiceInterface $imageProcessingService,
        LibraryStorageServiceInterface $libraryStorageService,
        int $resizeThreshold = 1920
    ): ImageProcessingTask {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('DEFAULT_RESIZE_THRESHOLD')->willReturn($resizeThreshold);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['mediaFactory', $mediaFactory],
            ['displayFactory', $displayFactory],
            ['imageProcessingService', $imageProcessingService],
            ['libraryStorageService', $libraryStorageService],
        ]);

        $task = new ImageProcessingTask();
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setStore($store);
        $task->setFactories($container);

        return $task;
    }

    private function newMedia(int $mediaId, string $storedAs): Media
    {
        $media = $this->createMock(Media::class);
        $media->mediaId = $mediaId;
        $media->storedAs = $storedAs;
        $media->expects($this->never())->method('release');

        return $media;
    }

    private function newRealImagePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xibo-image-processing-test-');
        $image = imagecreatetruecolor(4, 2);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function testReleasesUnchangedWhenTheLibraryFileIsMissing()
    {
        // It can never be processed, so it's released as it is rather than tried again every run.
        $media = $this->createMock(Media::class);
        $media->mediaId = 5;
        $media->storedAs = '5.jpg';
        $media->md5 = 'abc';
        $media->fileSize = 10;
        $media->width = 40;
        $media->height = 20;
        $media->expects($this->once())->method('release')->with('abc', 10, 20, 40);

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('downloadToTemp')
            ->willThrowException(new NotFoundException('Library file not found: 5.jpg'));
        $libraryStorageService->expects($this->never())->method('releaseTemp');

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->expects($this->never())->method('resizeImage');

        $task = $this->newTask(
            $this->createMock(StorageServiceInterface::class),
            $mediaFactory,
            $this->createMock(DisplayFactory::class),
            $imageProcessingService,
            $libraryStorageService
        );

        $task->run();

        $this->assertStringContainsString('Released and modified image count. 0', $task->getRunMessage());
    }

    public function testSkipsAndNeverReleasesWhenStorageIsUnavailable()
    {
        // Nothing was read, so trying again next run is cheap.
        $media = $this->newMedia(5, '5.jpg');

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('downloadToTemp')
            ->with('5.jpg')
            ->willThrowException(new GeneralException('Library storage is unavailable'));
        $libraryStorageService->expects($this->never())->method('releaseTemp');
        $libraryStorageService->expects($this->never())->method('storeFromTemp');

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->expects($this->never())->method('resizeImage');

        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->never())->method('update');

        $task = $this->newTask(
            $store,
            $mediaFactory,
            $this->createMock(DisplayFactory::class),
            $imageProcessingService,
            $libraryStorageService
        );

        $task->run();

        // Passing means release() was never called, which newMedia() asserts.
        $this->assertStringContainsString('Released and modified image count. 0', $task->getRunMessage());
    }

    /**
     * An image that will never decode is released unchanged, so it isn't picked up again every run.
     */
    private function assertReleasedUnchanged(string $tempCopyPath, ImageProcessingServiceInterface $service): void
    {
        $media = $this->createMock(Media::class);
        $media->mediaId = 7;
        $media->storedAs = '7.jpg';
        $media->width = 40;
        $media->height = 20;
        $media->expects($this->once())->method('release')->with(
            md5_file($tempCopyPath),
            filesize($tempCopyPath),
            20,
            40
        );

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        // Nothing changed, so nothing is stored back.
        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('downloadToTemp')->with('7.jpg')->willReturn($tempCopyPath);
        $libraryStorageService->expects($this->once())->method('releaseTemp')->with($tempCopyPath);
        $libraryStorageService->expects($this->never())->method('storeFromTemp');

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturn([]);

        $task = $this->newTask(
            $store,
            $mediaFactory,
            $this->createMock(DisplayFactory::class),
            $service,
            $libraryStorageService
        );

        $task->run();

        $this->assertStringContainsString('Released and modified image count. 1', $task->getRunMessage());
    }

    public function testReleasesUnchangedWhenTheFileIsNotAnImage()
    {
        $tempCopyPath = tempnam(sys_get_temp_dir(), 'xibo-image-processing-test-');
        file_put_contents($tempCopyPath, 'not an image');

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->expects($this->never())->method('resizeImage');

        try {
            $this->assertReleasedUnchanged($tempCopyPath, $imageProcessingService);
        } finally {
            @unlink($tempCopyPath);
        }
    }

    public function testReleasesUnchangedWhenResizeFails()
    {
        $tempCopyPath = $this->newRealImagePath();

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->method('resizeImage')->willReturn(null);

        try {
            $this->assertReleasedUnchanged($tempCopyPath, $imageProcessingService);
        } finally {
            @unlink($tempCopyPath);
        }
    }

    public function testResizedImageIsCommittedAndReleased()
    {
        $tempCopyPath = $this->newRealImagePath();
        $media = $this->createMock(Media::class);
        $media->mediaId = 7;
        $media->storedAs = '7.jpg';
        $media->expects($this->once())->method('release')->with(
            md5_file($tempCopyPath),
            filesize($tempCopyPath),
            2,
            4
        );

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('downloadToTemp')->with('7.jpg')->willReturn($tempCopyPath);
        $libraryStorageService->expects($this->once())
            ->method('storeFromTemp')
            ->with($tempCopyPath, '7.jpg');
        $libraryStorageService->expects($this->once())->method('releaseTemp')->with($tempCopyPath);

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->method('resizeImage')->willReturn([
            'filePath' => $tempCopyPath,
            'height' => 2,
            'width' => 4,
        ]);

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturn([]);

        try {
            $task = $this->newTask(
                $store,
                $mediaFactory,
                $this->createMock(DisplayFactory::class),
                $imageProcessingService,
                $libraryStorageService
            );

            $task->run();

            $this->assertStringContainsString('Released and modified image count. 1', $task->getRunMessage());
        } finally {
            @unlink($tempCopyPath);
        }
    }

    /**
     * A failed upload of the resized image must leave the media unreleased (so the next run
     * retries it), and must not touch requiredfile/layout rows, while still releasing the temporary copy.
     */
    public function testCommitFailureSkipsAndNeverReleases()
    {
        $tempCopyPath = $this->newRealImagePath();
        $media = $this->newMedia(9, '9.jpg');

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('downloadToTemp')->with('9.jpg')->willReturn($tempCopyPath);
        $libraryStorageService->method('storeFromTemp')
            ->willThrowException(new GeneralException('Problem uploading file to storage: 9.jpg'));
        $libraryStorageService->expects($this->once())->method('releaseTemp')->with($tempCopyPath);

        $imageProcessingService = $this->createMock(ImageProcessingServiceInterface::class);
        $imageProcessingService->method('resizeImage')->willReturn([
            'filePath' => $tempCopyPath,
            'height' => 2,
            'width' => 4,
        ]);

        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->never())->method('update');

        try {
            $task = $this->newTask(
                $store,
                $mediaFactory,
                $this->createMock(DisplayFactory::class),
                $imageProcessingService,
                $libraryStorageService
            );

            $task->run();

            $this->assertStringContainsString('Released and modified image count. 0', $task->getRunMessage());
        } finally {
            @unlink($tempCopyPath);
        }
    }
}
