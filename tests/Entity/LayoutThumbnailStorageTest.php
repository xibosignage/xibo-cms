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
use Xibo\Entity\Layout;
use Xibo\Factory\LayoutFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Layout thumbnails are stored, published, deleted and listed through thumbnail storage.
 */
class LayoutThumbnailStorageTest extends TestCase
{
    /** @var string[] */
    private array $events = [];

    private function recordingStore(): StorageServiceInterface
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        // The layout's XLF delete is journalled too; LayoutXlfStorageTest covers it, so it isn't recorded here.
        $xlfRecordId = 99;
        $store->method('update')->willReturnCallback(function ($sql, $params = []) use ($xlfRecordId) {
            if (($params['id'] ?? null) === $xlfRecordId) {
                return;
            }
            $this->events[] = str_contains($sql, 'library_file_journal') ? 'journal:cleared' : 'sql';
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) use ($xlfRecordId) {
            if (str_ends_with($params['key'], '.xlf')) {
                return $xlfRecordId;
            }
            $this->events[] = 'journal:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            return 1;
        });
        return $store;
    }

    /**
     * @param string[] $existing keys that exist in storage
     */
    private function recordingStorage(array $existing = [], bool $fails = false): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $fail = function () use ($fails) {
            if ($fails) {
                throw new GeneralException('outage');
            }
        };
        $storage->method('moveFromLocalPath')->willReturnCallback(function ($path, $key) use ($fail) {
            $this->events[] = 'move:' . $key;
            $fail();
        });
        $storage->method('copy')->willReturnCallback(function ($source, $dest) use ($fail) {
            $this->events[] = 'copy:' . $source . '>' . $dest;
            $fail();
        });
        $storage->method('delete')->willReturnCallback(function ($key) use ($fail) {
            $this->events[] = 'delete:' . $key;
            $fail();
        });
        $storage->method('exists')->willReturnCallback(function ($key) use ($existing, $fail) {
            $fail();
            return in_array($key, $existing, true);
        });
        return $storage;
    }

    private function newFactory(
        StorageServiceInterface $store,
        LibraryStorageServiceInterface $storage,
        ?LayoutFactory $factory = null
    ): LayoutFactory {
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        // Media storage holds the XLF, whose delete LayoutXlfStorageTest covers.
        $media = $this->createMock(LibraryStorageServiceInterface::class);
        $registry->method('get')->willReturnCallback(
            fn (string $assetType) => $assetType === LibraryAsset::LAYOUT_THUMBNAIL ? $storage : $media
        );

        $factory ??= (new \ReflectionClass(LayoutFactory::class))->newInstanceWithoutConstructor();
        \Closure::bind(function () use ($registry) {
            $this->libraryStorageRegistry = $registry;
        }, $factory, LayoutFactory::class)();

        $dependencies = $this->createMock(BaseDependenciesService::class);
        $dependencies->method('getStore')->willReturn($store);
        $dependencies->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));
        $factory->useBaseDependenciesService($dependencies);

        return $factory;
    }

    /**
     * A Layout with only what the thumbnail methods use.
     */
    private function newLayout(
        LayoutFactory $factory,
        StorageServiceInterface $store,
        int $campaignId,
        ?int $parentId,
        ?Layout $layout = null
    ): Layout {
        $layout ??= (new \ReflectionClass(Layout::class))->newInstanceWithoutConstructor();
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturn(sys_get_temp_dir() . '/xibo-no-such-library/');
        $dependencies = [
            'store' => $store,
            'layoutFactory' => $factory,
            'log' => $this->createMock(LogServiceInterface::class),
            'config' => $config,
        ];
        foreach ($dependencies as $name => $value) {
            (new \ReflectionProperty(Layout::class, $name))->setValue($layout, $value);
        }
        $layout->layoutId = 1;
        $layout->campaignId = $campaignId;
        $layout->parentId = $parentId;

        return $layout;
    }

    private function layoutWith(LibraryStorageServiceInterface $storage, ?int $parentId): Layout
    {
        $store = $this->recordingStore();
        return $this->newLayout($this->newFactory($store, $storage), $store, 7, $parentId);
    }

    public function testThumbnailKeyDependsOnDraftOrPublished()
    {
        $storage = $this->recordingStorage();

        $this->assertSame('7_layout_thumb.png', $this->layoutWith($storage, 3)->getThumbnailKey());
        $this->assertSame('7_campaign_thumb.png', $this->layoutWith($storage, null)->getThumbnailKey());
    }

    public function testAnImportedThumbnailIsJournalledUntilCommit()
    {
        $this->layoutWith($this->recordingStorage(), null)->storeThumbnail('/tmp/x');

        $this->assertSame(
            ['journal:pending_upload:thumbs:7_campaign_thumb.png', 'move:7_campaign_thumb.png', 'journal:cleared'],
            $this->events
        );
    }

    public function testAFailedImportStoreIsLoggedAndCleansUp()
    {
        $local = tempnam(sys_get_temp_dir(), 'thumb-');

        $this->layoutWith($this->recordingStorage([], true), null)->storeThumbnail($local);

        $this->assertFileDoesNotExist($local);
        $this->assertSame(
            ['journal:pending_upload:thumbs:7_campaign_thumb.png', 'move:7_campaign_thumb.png'],
            $this->events,
            'the upload record is left for the tidy'
        );
    }

    /**
     * Runs publishDraft() for draft layoutId 1 of campaignId 7, then the work it queued for after commit.
     */
    private function publishDraftWith(LibraryStorageServiceInterface $storage, ?string $uploadingKey = null): void
    {
        $queued = [];
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) use (&$queued) {
            $queued[] = $callback;
        });
        $store->method('update')->willReturnCallback(function ($sql) {
            if (str_contains($sql, 'library_file_journal')) {
                $this->events[] = 'journal:cleared';
            }
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'library_file_journal')) {
                $this->events[] = 'journal:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            }
            return 1;
        });
        $store->method('select')->willReturn([]);
        $store->method('exists')->willReturnCallback(
            fn ($sql, $params) => str_contains($sql, 'library_file_journal')
                && $params['storageKey'] === $uploadingKey
        );

        $parent = $this->getMockBuilder(Layout::class)->disableOriginalConstructor()->onlyMethods(['delete'])
            ->getMock();
        $parent->layoutId = 3;
        $parent->campaignId = 7;
        $parent->expects($this->once())->method('delete')->with(['keepDraftThumbnail' => true])
            ->willReturnCallback(function () use ($parent) {
                $this->assertSame(1, $parent->parentId, 'the replaced Layout is a draft when it is deleted');
            });

        $factory = $this->getMockBuilder(LayoutFactory::class)->disableOriginalConstructor()
            ->onlyMethods(['loadById'])->getMock();
        $factory->method('loadById')->with(3)->willReturn($parent);
        $this->newFactory($store, $storage, $factory);

        $draft = $this->getMockBuilder(Layout::class)->disableOriginalConstructor()->onlyMethods(['save'])
            ->getMock();
        $this->newLayout($factory, $store, 7, 3, $draft)->publishDraft();

        foreach ($queued as $callback) {
            $callback();
        }
    }

    public function testPublishingADraftMovesItsThumbnailToThePublishedKey()
    {
        $this->publishDraftWith($this->recordingStorage(['7_layout_thumb.png']));

        $this->assertSame(
            [
                'journal:pending_delete:thumbs:7_layout_thumb.png',
                'copy:7_layout_thumb.png>7_campaign_thumb.png',
                'delete:7_layout_thumb.png',
                'journal:cleared',
            ],
            $this->events
        );
    }

    public function testPublishLeavesADraftThumbnailBeingUploadedToTheTidy()
    {
        $this->publishDraftWith($this->recordingStorage(['7_layout_thumb.png']), '7_layout_thumb.png');

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'copy:7_layout_thumb.png>7_campaign_thumb.png'],
            $this->events,
            'the draft key and its delete record are kept until the upload is settled'
        );
    }

    public function testAFailedPublishCopyKeepsTheDraftThumbnailForTheTidy()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->willReturn(true);
        $storage->method('copy')->willThrowException(new GeneralException('outage'));
        $storage->method('delete')->willReturnCallback(function ($key) {
            $this->events[] = 'delete:' . $key;
        });

        $this->publishDraftWith($storage);

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'delete:7_campaign_thumb.png'],
            $this->events,
            'the draft thumbnail and its delete record are left for the tidy, and the stale published one is removed'
        );
    }

    public function testPublishWithoutADraftThumbnailRemovesTheStalePublishedOne()
    {
        $this->publishDraftWith($this->recordingStorage(['7_campaign_thumb.png']));

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'delete:7_campaign_thumb.png', 'journal:cleared'],
            $this->events
        );
    }

    public function testPublishWithoutADraftThumbnailKeepsAPublishedOneBeingUploaded()
    {
        $this->publishDraftWith($this->recordingStorage(['7_campaign_thumb.png']), '7_campaign_thumb.png');

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'journal:cleared'],
            $this->events
        );
    }

    public function testPublishLeavesTheDraftThumbnailToTheTidyOnAStorageError()
    {
        $this->publishDraftWith($this->recordingStorage(['7_layout_thumb.png'], true));

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'delete:7_campaign_thumb.png'],
            $this->events,
            'the failed removal of the published thumbnail is logged, not thrown'
        );
    }

    public function testTheLayoutReplacedOnPublishKeepsBothThumbnails()
    {
        $replaced = $this->layoutWith($this->recordingStorage(), 1);

        (new \ReflectionMethod(Layout::class, 'deleteFiles'))->invoke($replaced, true);

        $this->assertSame([], $this->events, 'the published thumbnail now belongs to the new version');
    }

    public function testDeletingADraftRemovesOnlyTheDraftThumbnail()
    {
        $draft = $this->layoutWith($this->recordingStorage(), 3);

        (new \ReflectionMethod(Layout::class, 'deleteFiles'))->invoke($draft);

        $this->assertSame(
            ['journal:pending_delete:thumbs:7_layout_thumb.png', 'delete:7_layout_thumb.png'],
            array_values(array_filter($this->events, fn ($event) => $event !== 'journal:cleared'))
        );
    }

    public function testDeletingAPublishedLayoutRemovesBothThumbnails()
    {
        $layout = $this->layoutWith($this->recordingStorage(), null);

        (new \ReflectionMethod(Layout::class, 'deleteFiles'))->invoke($layout);

        $this->assertSame(
            [
                'journal:pending_delete:thumbs:7_layout_thumb.png',
                'delete:7_layout_thumb.png',
                'journal:pending_delete:thumbs:7_campaign_thumb.png',
                'delete:7_campaign_thumb.png',
            ],
            array_values(array_filter($this->events, fn ($event) => $event !== 'journal:cleared'))
        );
    }

    public function testALookupChecksOnlyItsLayoutsKey()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->expects($this->never())->method('listKeys');
        $storage->method('exists')->willReturnCallback(fn ($key) => $key === '7_campaign_thumb.png');
        $store = $this->recordingStore();
        $factory = $this->newFactory($store, $storage);

        $this->assertTrue($factory->hasThumbnail($this->newLayout($factory, $store, 7, null)));
        $this->assertFalse($factory->hasThumbnail($this->newLayout($factory, $store, 8, null)));
        $this->assertFalse($factory->hasThumbnail($this->newLayout($factory, $store, 7, 3)), 'a draft has its own key');
    }

    public function testAStorageErrorShowsNoThumbnail()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->willThrowException(new GeneralException('outage'));
        $store = $this->recordingStore();
        $factory = $this->newFactory($store, $storage);

        $this->assertFalse($factory->hasThumbnail($this->newLayout($factory, $store, 7, null)));
    }
}
