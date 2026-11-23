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

namespace Xibo\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Xibo\Factory\FontFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;

/**
 * A font imported with a layout is added under a storable name, and its local file never outlives the import.
 */
class FontImportTest extends TestCase
{
    private string $temp;

    /** @var string[] keys moved into font storage */
    private array $storedKeys = [];

    protected function setUp(): void
    {
        $this->temp = sys_get_temp_dir() . '/font-import-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->temp);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->temp));
    }

    /**
     * @param string[] $existingFileNames font file names already in the library
     */
    private function fontFactory(array $existingFileNames = []): FontFactory
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('insert')->willReturn(1);
        $store->method('select')->willReturnCallback(
            fn ($sql, $params) => in_array($params['fileName'] ?? null, $existingFileNames, true)
                ? [['id' => 2, 'fileName' => $params['fileName']]]
                : []
        );

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            $this->storedKeys[] = $key;
            unlink($path);
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::FONT)->willReturn($storage);

        $dependencies = $this->createMock(BaseDependenciesService::class);
        $dependencies->method('getStore')->willReturn($store);
        $dependencies->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $dependencies->method('getSanitizer')->willReturn(new SanitizerService());

        $factory = new FontFactory($registry);
        $factory->useBaseDependenciesService($dependencies);
        return $factory;
    }

    private function localFont(string $fileName): string
    {
        $path = $this->temp . $fileName;
        copy(PROJECT_ROOT . '/fonts/OpenSans-Regular.ttf', $path);
        return $path;
    }

    public function testRunsOfDotsInAnImportedFontFileNameAreCollapsed()
    {
        $localPath = $this->localFont('Open..Sans...Regular.ttf');

        $font = $this->fontFactory()->importFontFile($localPath, 'Open Sans', 'Open..Sans...Regular.ttf', 'admin');

        $this->assertSame('Open.Sans.Regular.ttf', $font->fileName);
        $this->assertSame(['Open.Sans.Regular.ttf'], $this->storedKeys);
    }

    public function testACollapsedNameMatchingAnExistingFontIsRefusedWithoutReplacingIt()
    {
        $localPath = $this->localFont('Open..Sans.ttf');

        try {
            $this->fontFactory(['Open.Sans.ttf'])->importFontFile($localPath, 'Open Sans', 'Open..Sans.ttf', 'admin');
            $this->fail('a font file name already in the library must be refused');
        } catch (DuplicateEntityException) {
            // Expected.
        }

        $this->assertSame([], $this->storedKeys);
        $this->assertFileDoesNotExist($localPath);
    }

    public function testTheLocalFileIsRemovedWhenTheFontCannotBeRead()
    {
        $localPath = $this->temp . 'Broken.ttf';
        file_put_contents($localPath, 'not a font');

        try {
            $this->fontFactory()->importFontFile($localPath, 'Broken', 'Broken.ttf', 'admin');
            $this->fail('an unreadable font must not be added');
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertSame([], $this->storedKeys);
        $this->assertFileDoesNotExist($localPath);
    }
}
