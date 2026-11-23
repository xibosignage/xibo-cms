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
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LocalLibraryStorageService;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\XTR\RemoveOldScreenshotsTask;

/**
 * Remove Old Screenshots lists and deletes screenshots through the storage service, so it works on either backend.
 */
class RemoveOldScreenshotsTaskTest extends TestCase
{
    private function runTask(LibraryStorageServiceInterface $storage, int $ttlDays): RemoveOldScreenshotsTask
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('DISPLAY_SCREENSHOT_TTL')->willReturn($ttlDays);

        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::SCREENSHOT)->willReturn($storage);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([['libraryStorageRegistry', $registry]]);

        $task = new RemoveOldScreenshotsTask();
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setFactories($container);
        $task->run();

        return $task;
    }

    public function testDeletesOnlyScreenshotsOlderThanTheTtl()
    {
        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('listKeys')->willReturn([
            ['key' => '1_screenshot.jpg', 'lastModified' => time() - 10 * 86400, 'size' => 10],
            ['key' => '2_screenshot.jpg', 'lastModified' => time() - 3600, 'size' => 10],
            // A screenshot with no modified time is kept.
            ['key' => '3_screenshot.jpg', 'lastModified' => null, 'size' => 10],
        ]);
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted) {
            $deleted[] = $key;
        });

        $task = $this->runTask($storage, 7);

        $this->assertSame(['1_screenshot.jpg'], $deleted);
        $this->assertStringContainsString('Removed 1 old Display screenshots', $task->getRunMessage());
    }

    public function testAStorageErrorOnOneScreenshotDoesNotStopTheRest()
    {
        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('listKeys')->willReturn([
            ['key' => '1_screenshot.jpg', 'lastModified' => time() - 10 * 86400, 'size' => 10],
            ['key' => '2_screenshot.jpg', 'lastModified' => time() - 10 * 86400, 'size' => 10],
        ]);
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted) {
            if ($key === '1_screenshot.jpg') {
                throw new GeneralException('outage');
            }
            $deleted[] = $key;
        });

        $task = $this->runTask($storage, 7);

        $this->assertSame(['2_screenshot.jpg'], $deleted);
        $this->assertStringContainsString('Removed 1 old Display screenshots', $task->getRunMessage());
    }

    public function testTtlOfZeroRemovesNothing()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('listKeys');
        $storage->expects($this->never())->method('delete');

        $task = $this->runTask($storage, 0);

        $this->assertStringContainsString('nothing to remove', $task->getRunMessage());
    }

    public function testLocalBackendRemovesOldFilesFromTheScreenshotsFolder()
    {
        $folder = sys_get_temp_dir() . '/xibo-screenshots-test-' . uniqid() . '/';
        mkdir($folder);
        file_put_contents($folder . '1_screenshot.jpg', 'old');
        touch($folder . '1_screenshot.jpg', time() - 10 * 86400);
        file_put_contents($folder . '2_screenshot.jpg', 'new');

        try {
            $this->runTask(new LocalLibraryStorageService($folder), 7);

            $this->assertFileDoesNotExist($folder . '1_screenshot.jpg');
            $this->assertFileExists($folder . '2_screenshot.jpg');
        } finally {
            @unlink($folder . '1_screenshot.jpg');
            @unlink($folder . '2_screenshot.jpg');
            @rmdir($folder);
        }
    }
}
