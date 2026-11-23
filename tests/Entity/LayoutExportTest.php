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

namespace Xibo\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Font;
use Xibo\Entity\Layout;
use Xibo\Entity\Media;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Entity\WidgetOption;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Layout export reads its media through the library.
 */
class LayoutExportTest extends TestCase
{
    private string $zipPath;
    private string $presentPath;

    protected function setUp(): void
    {
        $this->zipPath = tempnam(sys_get_temp_dir(), 'xibo-export-test-');
        $this->presentPath = tempnam(sys_get_temp_dir(), 'xibo-export-media-');
        file_put_contents($this->presentPath, 'present bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->zipPath);
        @unlink($this->presentPath);
    }

    private function newMedia(int $mediaId, string $fileName): Media
    {
        $media = $this->createMock(Media::class);
        $media->mediaId = $mediaId;
        $media->name = $fileName;
        $media->fileName = $fileName;
        $media->storedAs = $mediaId . '.jpg';
        $media->mediaType = 'image';
        $media->duration = 10;
        $media->tags = [];
        return $media;
    }

    public static function missingFileExceptions(): array
    {
        return [
            'not found' => [NotFoundException::class],
            'invalid key' => [InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider missingFileExceptions
     */
    public function testAMissingMediaFileIsLeftOutButKeepsItsMapping(string $exception)
    {
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getByLayoutId')->willReturn([
            $this->newMedia(5, 'present.jpg'),
            $this->newMedia(6, 'missing.jpg'),
        ]);
        $mediaFactory->method('getById')->with(7)->willReturn($this->newMedia(7, 'background.jpg'));

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->willReturnCallback(function ($key) use ($exception) {
            if ($key === '5.jpg') {
                return $this->presentPath;
            }
            throw new $exception();
        });
        $storage->expects($this->once())->method('releaseTemp')->with($this->presentPath);

        $other = $this->createMock(LibraryStorageServiceInterface::class);
        $other->method('downloadToTemp')->willThrowException(new NotFoundException());
        $layoutFactory = $this->createMock(LayoutFactory::class);
        $layoutFactory->method('getThumbnailStorage')->willReturn($other);
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('getStorage')->willReturn($other);

        $missingLogged = [];
        $log = $this->createMock(LogServiceInterface::class);
        $log->method('error')->willReturnCallback(function ($message) use (&$missingLogged) {
            $missingLogged[] = $message;
        });

        $layout = (new \ReflectionClass(Layout::class))->newInstanceWithoutConstructor();
        $dependencies = [
            'mediaFactory' => $mediaFactory,
            'layoutFactory' => $layoutFactory,
            'fontFactory' => $fontFactory,
            'libraryStorageService' => $storage,
            'log' => $log,
            'loaded' => true,
        ];
        foreach ($dependencies as $name => $value) {
            (new \ReflectionProperty(Layout::class, $name))->setValue($layout, $value);
        }
        $layout->layoutId = 1;
        $layout->campaignId = 2;
        $layout->backgroundImageId = 7;

        $layout->toZip(
            $this->createMock(DataSetFactory::class),
            $this->createMock(WidgetDataFactory::class),
            $this->zipPath
        );

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->zipPath));
        $this->assertSame('present bytes', $zip->getFromName('library/present.jpg'));
        $this->assertFalse($zip->getFromName('library/missing.jpg'));
        $this->assertFalse($zip->getFromName('library/background.jpg'));
        $mappings = json_decode($zip->getFromName('mapping.json'), true);
        $zip->close();

        $this->assertSame(
            [['present.jpg', 5, 0], ['missing.jpg', 6, 0], ['background.jpg', 7, 1]],
            array_map(fn ($mapping) => [$mapping['file'], $mapping['mediaid'], $mapping['background']], $mappings)
        );
        $this->assertCount(2, $missingLogged);
        $this->assertStringContainsString('layoutId 1', $missingLogged[0]);
        $this->assertStringContainsString('mediaId 6', $missingLogged[0]);
        $this->assertStringContainsString('6.jpg', $missingLogged[0]);
        $this->assertStringContainsString('mediaId 7', $missingLogged[1]);
    }

    /**
     * @dataProvider missingFileExceptions
     */
    public function testAMissingFontFileIsLeftOutOfTheExport(string $exception)
    {
        $option = $this->createMock(WidgetOption::class);
        $option->type = 'raw';
        $option->option = 'css';
        $option->value = 'p { font-family: Gone; }';
        $widget = $this->createMock(Widget::class);
        $widget->type = 'text';
        $widget->widgetOptions = [$option];
        $playlist = $this->createMock(Playlist::class);
        $playlist->widgets = [$widget];
        $region = $this->createMock(Region::class);
        $region->regionId = 3;
        $region->name = 'main';
        $region->method('getPlaylist')->willReturn($playlist);

        $font = $this->createMock(Font::class);
        $font->id = 4;
        $font->name = 'Gone';
        $font->fileName = 'gone.ttf';
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('getByName')->with('Gone')->willReturn([$font]);
        $fontStorage = $this->createMock(LibraryStorageServiceInterface::class);
        $fontStorage->method('downloadToTemp')->willThrowException(new $exception());
        $fontFactory->method('getStorage')->willReturn($fontStorage);

        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getByLayoutId')->willReturn([]);
        $layoutFactory = $this->createMock(LayoutFactory::class);
        $layoutFactory->method('getThumbnailStorage')->willReturn($fontStorage);

        $errors = [];
        $log = $this->createMock(LogServiceInterface::class);
        $log->method('error')->willReturnCallback(function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $layout = (new \ReflectionClass(Layout::class))->newInstanceWithoutConstructor();
        $dependencies = [
            'mediaFactory' => $mediaFactory,
            'layoutFactory' => $layoutFactory,
            'fontFactory' => $fontFactory,
            'libraryStorageService' => $this->createMock(LibraryStorageServiceInterface::class),
            'log' => $log,
            'loaded' => true,
        ];
        foreach ($dependencies as $name => $value) {
            (new \ReflectionProperty(Layout::class, $name))->setValue($layout, $value);
        }
        $layout->layoutId = 1;
        $layout->campaignId = 2;
        $layout->regions = [$region];

        $layout->toZip(
            $this->createMock(DataSetFactory::class),
            $this->createMock(WidgetDataFactory::class),
            $this->zipPath
        );

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->zipPath));
        $this->assertFalse($zip->getFromName('library/gone.ttf'));
        $this->assertSame([], json_decode($zip->getFromName('mapping.json'), true));
        $zip->close();
        $this->assertSame(['Font file missing during export: gone.ttf'], $errors);
    }
}
