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
use Xibo\Entity\Task;
use Xibo\Factory\MediaFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\XTR\MediaOrientationTask;

/**
 * MediaOrientationTask reads each image through library storage and records its size and orientation, and leaves
 * the media untouched when the file isn't a readable image.
 */
class MediaOrientationTaskTest extends TestCase
{
    private function newTask(Media $media, LibraryStorageServiceInterface $libraryStorageService): MediaOrientationTask
    {
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('query')->willReturn([$media]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['mediaFactory', $mediaFactory],
            ['libraryStorageService', $libraryStorageService],
        ]);

        $task = $this->createMock(Task::class);
        $task->options = [];

        return (new MediaOrientationTask())
            ->setConfig($this->createMock(ConfigServiceInterface::class))
            ->setLogger($this->createMock(LogServiceInterface::class))
            ->setFactories($container)
            ->setTask($task);
    }

    private function newImage(): Media
    {
        $media = $this->createMock(Media::class);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        return $media;
    }

    public function testRecordsTheImageSizeAndOrientation()
    {
        $path = tempnam(sys_get_temp_dir(), 'xibo-orientation-test-');
        $image = imagecreatetruecolor(4, 2);
        imagepng($image, $path);
        imagedestroy($image);

        $media = $this->newImage();
        $media->expects($this->once())->method('save');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->with('5.png')->willReturn($path);
        $storage->expects($this->once())->method('releaseTemp')->with($path);

        try {
            $this->newTask($media, $storage)->run();
        } finally {
            @unlink($path);
        }

        $this->assertSame(4, $media->width);
        $this->assertSame(2, $media->height);
        $this->assertSame('landscape', $media->orientation);
    }

    public function testLeavesTheMediaUntouchedWhenTheFileIsNotAnImage()
    {
        $path = tempnam(sys_get_temp_dir(), 'xibo-orientation-test-');
        file_put_contents($path, 'not an image');

        $media = $this->newImage();
        $media->width = 1920;
        $media->height = 1080;
        $media->expects($this->never())->method('save');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->with('5.png')->willReturn($path);
        $storage->expects($this->once())->method('releaseTemp')->with($path);

        try {
            $this->newTask($media, $storage)->run();
        } finally {
            @unlink($path);
        }

        $this->assertSame(1920, $media->width);
        $this->assertSame(1080, $media->height);
    }
}
