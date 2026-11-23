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

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Layout;
use Xibo\Factory\LayoutFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * A layout's XLF is stored under {layoutId}.xlf, with its md5 and size kept on the layout row.
 */
class LayoutXlfStorageTest extends TestCase
{
    private string $library;

    /** @var array<string, string> stored objects by key */
    private array $objects = [];

    /** @var array<array{0: string, 1: array}> SQL run: [sql, params] */
    private array $queries = [];

    /** @var array{xlfMd5: ?string, xlfSize: ?int} the layout row's XLF columns */
    private array $row = ['xlfMd5' => null, 'xlfSize' => null];

    private bool $storageFails = false;

    /** Whether the request transaction is open. */
    private bool $inTransaction = false;

    /** @var string[] keys read from storage */
    private array $reads = [];

    /** @var callable[] callbacks waiting for the request transaction to commit */
    private array $afterCommit = [];

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/xibo-layout-xlf-' . uniqid() . '/';
        mkdir($this->library . 'temp', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->library . 'temp/*') ?: []);
        rmdir($this->library . 'temp');
        rmdir($this->library);
    }

    private function store(): StorageServiceInterface
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturnCallback(function ($sql, $params) {
            $this->queries[] = [trim($sql), $params];
            return str_contains($sql, '`xlfMd5`') ? [$this->row] : [];
        });
        $store->method('update')->willReturnCallback(function ($sql, $params, $connection = 'default') {
            $this->queries[] = [trim($sql), $params, $connection];
            if (str_contains($sql, 'SET `xlfMd5` = :xlfMd5')) {
                $this->row = ['xlfMd5' => $params['xlfMd5'], 'xlfSize' => $params['xlfSize']];
            }
            return 1;
        });
        $store->method('insert')->willReturn(9);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            if ($this->inTransaction) {
                $this->afterCommit[] = $callback;
            } else {
                $callback();
            }
        });
        $connection = $this->createMock(\PDO::class);
        $connection->method('inTransaction')->willReturnCallback(fn () => $this->inTransaction);
        $store->method('getConnection')->willReturn($connection);
        return $store;
    }

    private function storage(): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:' . $this->library);
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            if ($this->storageFails) {
                throw new GeneralException('outage');
            }
            $this->objects[$key] = file_get_contents($path);
            unlink($path);
        });
        $storage->method('readStream')->willReturnCallback(function (string $key) {
            $this->reads[] = $key;
            if (!isset($this->objects[$key])) {
                throw new NotFoundException('missing ' . $key);
            }
            return Utils::streamFor($this->objects[$key]);
        });
        $storage->method('delete')->willReturnCallback(function (string $key) {
            unset($this->objects[$key]);
        });
        return $storage;
    }

    private function newLayout(int $status, ?Layout $layout = null): Layout
    {
        $layout ??= (new \ReflectionClass(Layout::class))->newInstanceWithoutConstructor();
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : null
        );
        $log = $this->createMock(LogServiceInterface::class);
        $log->method('getLoggerInterface')->willReturn(new \Psr\Log\NullLogger());
        $dependencies = [
            'store' => $this->store(),
            'log' => $log,
            'config' => $config,
            'libraryStorageService' => $this->storage(),
            'layoutFactory' => $this->createMock(LayoutFactory::class),
        ];
        foreach ($dependencies as $name => $value) {
            (new \ReflectionProperty(Layout::class, $name))->setValue($layout, $value);
        }
        $layout->layoutId = 5;
        $layout->status = $status;
        $layout->duration = 30;

        return $layout;
    }

    /**
     * A Layout whose build produces $xlf (or fails with $xlf as the exception) without loading anything.
     */
    private function newBuildingLayout(int $status, string|\Exception $xlf, int &$saves = 0): Layout
    {
        $layout = $this->getMockBuilder(Layout::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load', 'toXlf', 'save', 'audit'])
            ->getMock();
        if ($xlf instanceof \Exception) {
            $layout->method('toXlf')->willThrowException($xlf);
        } else {
            $layout->method('toXlf')->willReturn($xlf);
        }
        $layout->method('save')->willReturnCallback(function () use (&$saves) {
            $saves++;
        });

        return $this->newLayout($status, $layout);
    }

    /**
     * @return string[] the SQL run that writes the layout row's XLF columns
     */
    private function columnWrites(): array
    {
        return array_values(array_filter(
            array_column($this->queries, 0),
            fn ($sql) => str_contains($sql, 'SET `xlfMd5` = :xlfMd5')
        ));
    }

    private function tempFiles(): array
    {
        return glob($this->library . 'temp/*') ?: [];
    }

    public function testABuildStoresTheXlfUnderItsKeyAndSavesItsMd5AndSize(): void
    {
        $saves = 0;
        $layout = $this->newBuildingLayout(3, '<layout/>', $saves);

        $layout->buildXlf(['notify' => false]);

        $this->assertSame(['5.xlf' => '<layout/>'], $this->objects);
        $this->assertSame(1, $saves);
        $this->assertCount(1, $this->columnWrites());
        $this->assertSame(['xlfMd5' => md5('<layout/>'), 'xlfSize' => 9], $this->row);
        $this->assertSame(['md5' => md5('<layout/>'), 'size' => 9], $layout->getXlfDetails());
        $this->assertSame('<layout/>', $layout->getXlf());
        $this->assertSame([], $this->reads, 'the XLF just built is served from memory');
        $this->assertSame([], $this->tempFiles());
        $this->assertTrue($layout->hasBuilt());
    }

    public function testAStorageFailureSavesNothingAndLeavesTheLayoutToBeBuiltAgain(): void
    {
        $saves = 0;
        $layout = $this->newBuildingLayout(3, '<layout/>', $saves);
        $layout->setStoredXlfDetails(md5('<old/>'), 6);
        $this->storageFails = true;

        try {
            $layout->buildXlf(['notify' => false]);
            $this->fail('A storage failure is thrown');
        } catch (GeneralException) {
        }

        $this->assertSame(0, $saves, 'status 4 is never saved for a storage failure');
        $this->assertSame([], $this->columnWrites());
        $this->assertSame(3, $layout->status);
        $this->assertSame(30, $layout->duration);
        $this->assertSame(['md5' => md5('<old/>'), 'size' => 6], $layout->getXlfDetails());
        $this->assertSame([], $this->tempFiles());
        $this->assertTrue($layout->hasBuilt(), 'so the caller releases the build lock');
    }

    public function testABuildRejectedByExceptionOnErrorLeavesTheStoredXlfInPlace(): void
    {
        $saves = 0;
        // The mocked toXlf() never marks the layout valid, so the build is rejected as invalid.
        $layout = $this->newBuildingLayout(3, '<invalid/>', $saves);
        $this->objects['5.xlf'] = '<old/>';

        try {
            $layout->buildXlf(['notify' => false, 'exceptionOnError' => true]);
            $this->fail('An invalid build is thrown');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(['5.xlf' => '<old/>'], $this->objects);
        $this->assertSame(0, $saves);
        $this->assertSame([], $this->columnWrites());
        $this->assertSame([], $this->tempFiles());
    }

    public function testAnXlfThatCannotBeBuiltIsSavedAsInvalidWithoutWritingStorage(): void
    {
        $saves = 0;
        $layout = $this->newBuildingLayout(3, new \Exception('broken widget'), $saves);

        $layout->buildXlf(['notify' => false]);

        $this->assertSame(4, $layout->status);
        $this->assertSame(1, $saves);
        $this->assertSame([], $this->objects);
        $this->assertSame([], $this->columnWrites());
    }

    public function testStoredDetailsNeedNoStorageCallAndNoBuild(): void
    {
        $layout = $this->newLayout(1);
        $layout->setStoredXlfDetails('abc', 12);

        $this->assertFalse($layout->isBuildRequired());
        $this->assertSame(['md5' => 'abc', 'size' => 12], $layout->getXlfDetails());
        $this->assertSame([], $this->reads);
        $this->assertSame([], $this->queries);
    }

    public function testAStoredXlfWithoutDetailsIsHashedOnceAndNotRebuilt(): void
    {
        // A local install upgraded with {layoutId}.xlf already in its library.
        $this->objects['5.xlf'] = '<existing/>';
        $layout = $this->newLayout(1);

        $this->assertFalse($layout->isBuildRequired());
        $this->assertSame(['md5' => md5('<existing/>'), 'size' => 11], $layout->getXlfDetails());
        $this->assertSame(['xlfMd5' => md5('<existing/>'), 'xlfSize' => 11], $this->row);
        $this->assertSame(['5.xlf'], $this->reads, 'hashed once');

        // Committed at once, and never over the columns of a build that finished meanwhile.
        [$sql, , $connection] = $this->queries[array_key_last($this->queries)];
        $this->assertStringContainsString('`xlfMd5` IS NULL', $sql);
        $this->assertSame(StorageServiceInterface::ISOLATED_CONNECTION, $connection);
    }

    public function testAStoredXlfWithoutDetailsIsSavedOnceTheTransactionCommits(): void
    {
        // The isolated connection would wait on a layout row lock the request transaction holds.
        $this->objects['5.xlf'] = '<existing/>';
        $this->inTransaction = true;
        $layout = $this->newLayout(1);

        $this->assertFalse($layout->isBuildRequired());
        $this->assertSame(['md5' => md5('<existing/>'), 'size' => 11], $layout->getXlfDetails());
        $this->assertSame([], $this->columnWrites());

        $this->inTransaction = false;
        array_map(fn (callable $callback) => $callback(), $this->afterCommit);

        $this->assertSame(['xlfMd5' => md5('<existing/>'), 'xlfSize' => 11], $this->row);
        [$sql, , $connection] = $this->queries[array_key_last($this->queries)];
        $this->assertStringContainsString('`xlfMd5` IS NULL', $sql);
        $this->assertSame(StorageServiceInterface::ISOLATED_CONNECTION, $connection);
    }

    public function testDetailsAreReadFromTheRowWhenTheLayoutWasNotLoadedWithThem(): void
    {
        $this->row = ['xlfMd5' => 'abc', 'xlfSize' => 12];
        $layout = $this->newLayout(1);

        $this->assertSame(['md5' => 'abc', 'size' => 12], $layout->getXlfDetails());
        $this->assertSame([], $this->reads);
    }

    public function testAMissingXlfWithoutDetailsNeedsABuild(): void
    {
        $layout = $this->newLayout(1);

        $this->assertTrue($layout->isBuildRequired());
        $this->assertNull($layout->getXlfDetails());
    }

    public function testAFlaggedLayoutNeedsABuildEvenWithDetails(): void
    {
        $layout = $this->newLayout(3);
        $layout->setStoredXlfDetails('abc', 12);

        $this->assertTrue($layout->isBuildRequired());
    }

    public function testTheXlfIsReadFromStorage(): void
    {
        $this->objects['5.xlf'] = '<stored/>';
        $layout = $this->newLayout(1);
        $layout->setStoredXlfDetails(md5('<stored/>'), 9);

        $this->assertSame('<stored/>', $layout->getXlf());
    }

    public function testAMissingXlfIsNotFound(): void
    {
        $layout = $this->newLayout(1);
        $layout->setStoredXlfDetails('abc', 12);

        $this->expectException(NotFoundException::class);
        $layout->getXlf();
    }

    public function testForgettingTheStoredXlfMakesTheLayoutBuildAgain(): void
    {
        $layout = $this->newLayout(1);
        $layout->setStoredXlfDetails('abc', 12);

        $layout->forgetStoredXlf();

        $this->assertTrue($layout->isBuildRequired());
    }

    public function testACopyBuildsItsOwnXlf(): void
    {
        $layout = $this->newLayout(1);
        $layout->setStoredXlfDetails('abc', 12);

        $copy = clone $layout;
        $copy->layoutId = 6;
        $copy->status = 1;

        $this->assertNull($copy->getXlfDetails());
    }

    public function testDeletingTheLayoutFilesDeletesItsXlfAfterCommit(): void
    {
        $layout = $this->newLayout(1);
        $layout->campaignId = 7;
        $factory = $this->createMock(LayoutFactory::class);
        $factory->expects($this->once())->method('deleteXlfFileAfterCommit')->with('5.xlf');
        (new \ReflectionProperty(Layout::class, 'layoutFactory'))->setValue($layout, $factory);

        (new \ReflectionMethod(Layout::class, 'deleteFiles'))->invoke($layout);
    }
}
