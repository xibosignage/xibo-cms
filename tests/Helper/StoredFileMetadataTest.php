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

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\DataSet;
use Xibo\Entity\RequiredFile;
use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Helper\StoredFileMetadata;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LocalLibraryStorageService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Listeners\XmdsDataConnectorListener;
use Xibo\Xmds\Listeners\XmdsFontsListener;

/**
 * RequiredFiles lists fonts.css and data connector scripts from cached metadata and serves them from their storage.
 */
class StoredFileMetadataTest extends TestCase
{
    public function testRememberedMetadataIsServedWithoutTouchingStorage()
    {
        $pool = new Pool(new Ephemeral());
        StoredFileMetadata::remember($pool, '/test/meta', 'body { }');

        $called = false;
        $metadata = StoredFileMetadata::get($pool, '/test/meta', function () use (&$called) {
            $called = true;
            return $this->createMock(LibraryStorageServiceInterface::class);
        }, 'fonts.css');

        $this->assertFalse($called, 'a cache hit must not even build the storage service');
        $this->assertSame(['size' => 8, 'md5' => md5('body { }')], $metadata);
    }

    public function testAMissReadsStorageOnceAndCachesTheResult()
    {
        $pool = new Pool(new Ephemeral());
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())->method('readStream')->with('fonts.css')
            ->willReturn(Utils::streamFor('css'));

        $first = StoredFileMetadata::get($pool, '/test/meta', fn () => $storage, 'fonts.css');
        $second = StoredFileMetadata::get($pool, '/test/meta', fn () => $storage, 'fonts.css');

        $this->assertSame(['size' => 3, 'md5' => md5('css')], $first);
        $this->assertSame($first, $second);
    }

    public function testAMissingFileIsNotFound()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willThrowException(new NotFoundException('missing'));

        $this->expectException(NotFoundException::class);
        StoredFileMetadata::get(new Pool(new Ephemeral()), '/test/meta', fn () => $storage, 'fonts.css');
    }

    public function testAMissingFileIsRememberedUntilItIsStored()
    {
        $pool = new Pool(new Ephemeral());
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())->method('readStream')
            ->willThrowException(new NotFoundException('missing'));

        foreach ([1, 2] as $attempt) {
            try {
                StoredFileMetadata::get($pool, '/test/meta', fn () => $storage, 'fonts.css');
                $this->fail('attempt ' . $attempt . ' must be not found');
            } catch (NotFoundException) {
                // Expected, from storage once and then from the cache.
            }
        }

        StoredFileMetadata::store($pool, '/test/meta', $storage, 'fonts.css', 'css', sys_get_temp_dir() . '/');

        $this->assertSame(
            ['size' => 3, 'md5' => md5('css')],
            StoredFileMetadata::get($pool, '/test/meta', fn () => $storage, 'fonts.css'),
            'storing replaces the remembered miss'
        );
    }

    public function testStoredMetadataExpiresWithinMinutesSoARacingStoreByAnotherWebServerHeals()
    {
        $pool = new Pool(new Ephemeral());
        StoredFileMetadata::store(
            $pool,
            '/test/meta',
            $this->createMock(LibraryStorageServiceInterface::class),
            'fonts.css',
            'css',
            sys_get_temp_dir() . '/'
        );

        $expiresIn = $pool->getItem('/test/meta')->getExpiration()->getTimestamp() - time();
        $this->assertGreaterThan(0, $expiresIn);
        $this->assertLessThanOrEqual(300, $expiresIn);
    }

    public function testFontsListenerListsFontsCssByNameDuringAnOutageWhenNoMetadataIsNeeded()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willThrowException(new GeneralException('outage'));
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('query')->willReturn([]);
        $fontFactory->method('getStorage')->willReturn($storage);

        $listener = new XmdsFontsListener($fontFactory, new Pool(new Ephemeral()));
        $listener->useLogger(new NullLogger());
        $listEvent = new XmdsDependencyListEvent($this->createMock(\Xibo\Entity\Display::class), false);
        $listener->onDependencyList($listEvent);

        $this->assertCount(1, $listEvent->getDependencies());
        $this->assertSame('fonts/fonts.css', $listEvent->getDependencies()[0]->path);
    }

    public function testFontsListenerListsFontsCssFromTheCacheAndServesFromFontStorage()
    {
        $pool = new Pool(new Ephemeral());
        StoredFileMetadata::remember($pool, FontFactory::FONTS_CSS_METADATA_CACHE_KEY, 'css contents');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('readStream');
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('query')->willReturn([]);
        $fontFactory->method('getStorage')->willReturn($storage);

        $listener = new XmdsFontsListener($fontFactory, $pool);

        $display = $this->createMock(\Xibo\Entity\Display::class);
        $listEvent = new XmdsDependencyListEvent($display);
        $listener->onDependencyList($listEvent);

        $dependencies = $listEvent->getDependencies();
        $this->assertCount(1, $dependencies);
        $this->assertSame('fontCss', $dependencies[0]->fileType);
        $this->assertSame(12, $dependencies[0]->size);
        $this->assertSame(md5('css contents'), $dependencies[0]->md5);

        $requiredFile = $this->createMock(RequiredFile::class);
        $requiredFile->fileType = 'fontCss';
        $requiredFile->itemId = 1;
        $requiredFile->realId = '1';
        $requestEvent = new XmdsDependencyRequestEvent($requiredFile);
        $listener->onDependencyRequest($requestEvent);

        $this->assertSame($storage, $requestEvent->getStorage());
        $this->assertSame(FontFactory::FONTS_CSS_KEY, $requestEvent->getStorageKey());
    }

    public function testDataConnectorDependencyComesFromTheCache()
    {
        $pool = new Pool(new Ephemeral());
        StoredFileMetadata::remember($pool, DataSet::getScriptMetadataCacheKey(5), 'script();');

        $dependency = XmdsDataConnectorListener::getDataConnectorDependency($pool, function () {
            $this->fail('a cache hit must not build the storage service');
        }, 5);

        $this->assertSame('data_connectors/dataSet_5.js', $dependency->path);
        $this->assertSame(9, $dependency->size);
        $this->assertSame(md5('script();'), $dependency->md5);
    }

    public function testDataConnectorDependencyForAMissingScriptIsNotFound()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willThrowException(new NotFoundException('missing'));

        $this->expectException(NotFoundException::class);
        XmdsDataConnectorListener::getDataConnectorDependency(new Pool(new Ephemeral()), fn () => $storage, 5);
    }

    /**
     * @dataProvider listModeProvider
     */
    public function testFontsListenerLeavesOutAMissingFontsCss(bool $needsFileMetadata)
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willThrowException(new NotFoundException('missing'));
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('query')->willReturn([]);
        $fontFactory->method('getStorage')->willReturn($storage);

        $listener = new XmdsFontsListener($fontFactory, new Pool(new Ephemeral()));
        $listener->useLogger(new NullLogger());
        $listEvent = new XmdsDependencyListEvent(
            $this->createMock(\Xibo\Entity\Display::class),
            $needsFileMetadata
        );
        $listener->onDependencyList($listEvent);

        $this->assertSame([], $listEvent->getDependencies());
    }

    public static function listModeProvider(): array
    {
        return ['RequiredFiles' => [true], 'Schedule' => [false]];
    }

    public function testFontsListenerFailsWhenStorageIsUnreachable()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willThrowException(new GeneralException('outage'));
        $fontFactory = $this->createMock(FontFactory::class);
        $fontFactory->method('query')->willReturn([]);
        $fontFactory->method('getStorage')->willReturn($storage);

        $listener = new XmdsFontsListener($fontFactory, new Pool(new Ephemeral()));
        $listener->useLogger(new NullLogger());

        $this->expectException(GeneralException::class);
        $listener->onDependencyList(new XmdsDependencyListEvent($this->createMock(\Xibo\Entity\Display::class)));
    }

    public function testDataConnectorRequestIsServedFromScriptStorage()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::DATA_CONNECTOR)->willReturn($storage);

        $requiredFile = $this->createMock(RequiredFile::class);
        $requiredFile->fileType = 'data_connector';
        $requiredFile->itemId = 5;
        $requiredFile->realId = '5';
        $requestEvent = new XmdsDependencyRequestEvent($requiredFile);
        (new XmdsDataConnectorListener($registry))->onDependencyRequest($requestEvent);

        $this->assertSame('data_connectors/dataSet_5.js', $requestEvent->getRelativePath());
        $this->assertSame($storage, $requestEvent->getStorage());
        $this->assertSame('dataSet_5.js', $requestEvent->getStorageKey());
    }

    public function testSaveScriptSkipsAnUnchangedScriptOnlyWhenAsked()
    {
        $library = sys_get_temp_dir() . '/xibo-dc-test-' . uniqid() . '/';
        mkdir($library . 'temp', 0777, true);
        mkdir($library . 'data_connectors', 0777, true);
        $storage = new LocalLibraryStorageService($library . 'data_connectors/');
        $pool = new Pool(new Ephemeral());

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $library : null
        );
        $factory = $this->createMock(DataSetFactory::class);
        $factory->method('getScriptStorage')->willReturn($storage);

        $dataSet = new DataSet(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(LogServiceInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            null,
            $config,
            $pool,
            $factory,
            null,
            null,
            null
        );
        $dataSet->dataSetId = 7;
        $dataSet->isRealTime = 1;
        $path = $library . 'data_connectors/dataSet_7.js';

        try {
            $dataSet->saveScript('first();');
            $this->assertSame('first();', file_get_contents($path));
            $this->assertSame('first();', $dataSet->getScript());

            // Unchanged: not written again by a save that skips unchanged scripts
            touch($path, time() - 3600);
            clearstatcache();
            $dataSet->saveScript('first();', true);
            clearstatcache();
            $this->assertLessThan(time() - 60, filemtime($path));

            // An explicit save always stores
            $dataSet->saveScript('first();');
            clearstatcache();
            $this->assertGreaterThan(time() - 60, filemtime($path));

            // Changed: written, and the cached metadata follows
            $dataSet->saveScript('second();', true);
            $this->assertSame('second();', file_get_contents($path));
            $this->assertSame(
                md5('second();'),
                XmdsDataConnectorListener::getDataConnectorDependency($pool, fn () => $storage, 7)->md5
            );
            $this->assertSame([], glob($library . 'temp/*'), 'no temp files left behind');
        } finally {
            @unlink($path);
            @rmdir($library . 'data_connectors');
            @rmdir($library . 'temp');
            @rmdir($library);
        }
    }
}
