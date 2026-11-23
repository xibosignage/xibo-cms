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

namespace Xibo\Tests\Service;

use PHPUnit\Framework\TestCase;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\FontFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\MediaService;
use Xibo\Storage\StorageServiceInterface;

/**
 * Local copies kept as image preview sources are removed once nobody has previewed them for a week.
 */
class MediaServiceRemoveTempFilesTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-media-service-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->libraryLocation);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->libraryLocation));
    }

    public function testPreviewSourcesNotUsedForAWeekAreRemoved()
    {
        $unused = $this->libraryLocation . 'tn_src_5.jpg_1000';
        $used = $this->libraryLocation . 'tn_src_6.jpg_1000';
        $thumbnail = $this->libraryLocation . 'tn_5.jpg_abc';
        foreach ([$unused, $used, $thumbnail] as $path) {
            file_put_contents($path, 'x');
        }
        touch($unused, time() - 8 * 86400);
        touch($thumbnail, time() - 8 * 86400);

        $this->mediaService(false)->removeTempFiles();

        $this->assertFileDoesNotExist($unused);
        $this->assertFileExists($used);
        $this->assertFileExists($thumbnail, 'thumbnails are small and kept');
    }

    public function testALocalLibraryHasNoPreviewSourcesToLookFor()
    {
        // Previews read local library files directly, so a name like this is never a cached copy.
        $file = $this->libraryLocation . 'tn_src_5.jpg_1000';
        file_put_contents($file, 'x');
        touch($file, time() - 8 * 86400);

        $this->mediaService(true)->removeTempFiles();

        $this->assertFileExists($file);
    }

    public function testOldNotificationUploadsAreRemovedFromEachUsersFolder()
    {
        $active = AttachmentUploadHandler::getUserUploadDir($this->libraryLocation, 7);
        $abandoned = AttachmentUploadHandler::getUserUploadDir($this->libraryLocation, 8);
        mkdir($active, 0777, true);
        mkdir($abandoned, 0777, true);
        file_put_contents($active . 'new.pdf', 'x');
        file_put_contents($active . 'old.pdf', 'x');
        file_put_contents($abandoned . 'old.pdf', 'x');
        touch($active . 'old.pdf', time() - 2 * 86400);
        touch($abandoned . 'old.pdf', time() - 2 * 86400);

        $this->mediaService(true)->removeTempFiles();

        $this->assertFileExists($active . 'new.pdf');
        $this->assertFileDoesNotExist($active . 'old.pdf');
        $this->assertDirectoryDoesNotExist($abandoned, 'an emptied folder is removed');
    }

    private function mediaService(bool $hasLocalFiles): MediaService
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('LIBRARY_LOCATION')->willReturn($this->libraryLocation);
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn($hasLocalFiles);
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getStorage')->willReturn($storage);

        return new MediaService(
            $config,
            $this->createMock(LogServiceInterface::class),
            $this->createMock(StorageServiceInterface::class),
            new SanitizerService(),
            $this->createMock(PoolInterface::class),
            $mediaFactory,
            $this->createMock(FontFactory::class)
        );
    }
}
