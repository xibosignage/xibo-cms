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

namespace Xibo\Tests\Widget\Render;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\StashLock;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistry;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\Render\WidgetHtmlRenderer;

/**
 * Each region's render is stored once per version, and only the current version is kept.
 */
class WidgetHtmlRendererCacheTest extends TestCase
{
    private const PLAYLIST_DT = '2026-10-02 10:00:00';

    private string $libraryLocation;
    private Pool $pool;
    private LibraryStorageRegistry $registry;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/widget-cache-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->libraryLocation . 'temp', 0777, true);
        $this->pool = new Pool(new Ephemeral());
        $this->registry = new LibraryStorageRegistry($this->libraryLocation);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->libraryLocation));
    }

    private function renderer(?LibraryStorageRegistryInterface $registry = null): CountingWidgetHtmlRenderer
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // The version comes from the loaded widgets and playlist, without a query.
        $store->expects($this->never())->method('select');
        $store->method('insert')->willReturn(1);
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn (string $setting) => $setting === 'LIBRARY_LOCATION' ? $this->libraryLocation : null
        );

        return new CountingWidgetHtmlRenderer(
            $registry ?? $this->registry,
            $store,
            $this->pool,
            $this->createMock(Twig::class),
            $config,
            $this->createMock(ModuleFactory::class)
        );
    }

    private function widget(int $widgetId, int $modifiedDt, string $type = 'text'): Widget
    {
        $widget = $this->createMock(Widget::class);
        $widget->widgetId = $widgetId;
        $widget->modifiedDt = $modifiedDt;
        $widget->type = $type;
        return $widget;
    }

    private function region(int $regionId, string $type = 'zone'): Region
    {
        $playlist = $this->createMock(Playlist::class);
        $playlist->modifiedDt = self::PLAYLIST_DT;

        $region = $this->createMock(Region::class);
        $region->regionId = $regionId;
        $region->layoutId = 3;
        $region->type = $type;
        $region->method('getPlaylist')->willReturn($playlist);
        return $region;
    }

    /**
     * @return string[] the stored keys, sorted
     */
    private function storedKeys(): array
    {
        if (!is_dir($this->libraryLocation . 'widget')) {
            return [];
        }

        $keys = array_column(
            iterator_to_array($this->registry->get(LibraryAsset::WIDGET_HTML)->listKeys('', true), false),
            'key'
        );
        sort($keys);
        return $keys;
    }

    public function testTheVersionIsTheLatestOfTheWidgetsAndPlaylist(): void
    {
        $playlistTime = strtotime(self::PLAYLIST_DT);
        $this->assertSame(
            $playlistTime,
            WidgetHtmlRenderer::getRenderVersion([$this->widget(7, 100)], self::PLAYLIST_DT)
        );
        $this->assertSame(
            $playlistTime + 50,
            WidgetHtmlRenderer::getRenderVersion(
                [$this->widget(7, 100), $this->widget(8, $playlistTime + 50)],
                self::PLAYLIST_DT
            ),
            'any canvas widget changes the version'
        );
        $this->assertSame(100, WidgetHtmlRenderer::getRenderVersion([$this->widget(7, 100)], null));
    }

    public function testRendersOncePerVersionThenReadsTheStoredCopy(): void
    {
        $widget = $this->widget(7, 100);
        $version = strtotime(self::PLAYLIST_DT);

        $first = $this->renderer();
        $this->assertSame('render 1', $first->renderOrCache($this->region(1), [$widget], []));
        $this->assertSame(['7/1_' . $version . '.html'], $this->storedKeys());

        // Another server, with nothing of its own.
        $second = $this->renderer();
        $this->assertSame('render 1', $second->renderOrCache($this->region(1), [$widget], []));
        $this->assertSame(0, $second->renders);
    }

    public function testACanvasIsStoredUnderItsGlobalWidgetWithTheLatestVersion(): void
    {
        $later = strtotime(self::PLAYLIST_DT) + 50;

        $this->renderer()->renderOrCache(
            $this->region(1, 'canvas'),
            [$this->widget(8, 100), $this->widget(9, 100, 'global'), $this->widget(10, $later)],
            []
        );

        $this->assertSame(['9/1_' . $later . '.html'], $this->storedKeys());
    }

    public function testANewVersionReplacesOnlyThatRegionsOldOnes(): void
    {
        $storage = $this->registry->get(LibraryAsset::WIDGET_HTML);
        foreach (['7/1_100.html', '7/1_200.html', '7/2_100.html', '8/1_100.html'] as $key) {
            $path = $this->libraryLocation . 'temp/seed';
            file_put_contents($path, 'old');
            $storage->moveFromLocalPath($path, $key);
        }

        $this->renderer()->renderOrCache($this->region(1), [$this->widget(7, 100)], []);

        $this->assertSame(
            ['7/1_' . strtotime(self::PLAYLIST_DT) . '.html', '7/2_100.html', '8/1_100.html'],
            $this->storedKeys()
        );
    }

    public function testAWaiterThatTimesOutRendersWithoutStoring(): void
    {
        $widget = $this->widget(7, 100);
        $this->holdLock(7, 1);

        $renderer = $this->renderer();
        $this->assertSame('render 1', $renderer->renderOrCache($this->region(1), [$widget], []));
        $this->assertSame([], $this->storedKeys());
    }

    public function testAStaleRenderNeverDeletesANewerOne(): void
    {
        $storage = $this->registry->get(LibraryAsset::WIDGET_HTML);
        $newer = '7/1_' . (strtotime(self::PLAYLIST_DT) + 50) . '.html';
        $path = $this->libraryLocation . 'temp/seed';
        file_put_contents($path, 'newer');
        $storage->moveFromLocalPath($path, $newer);

        // A request that read the widget before an edit stores the older version.
        $this->renderer()->renderOrCache($this->region(1), [$this->widget(7, 100)], []);

        $this->assertSame(['7/1_' . strtotime(self::PLAYLIST_DT) . '.html', $newer], $this->storedKeys());
    }

    public function testAWaiterReadsTheCopyStoredWhileItWaited(): void
    {
        $this->holdLock(7, 1);

        $renderer = $this->renderer($this->registryStoredAfterFirstRead());
        $this->assertSame('stored', $renderer->renderOrCache($this->region(1), [$this->widget(7, 100)], []));
        $this->assertSame(0, $renderer->renders);
    }

    public function testTheLockHolderReadsACopyStoredBeforeItGotTheLock(): void
    {
        $renderer = $this->renderer($this->registryStoredAfterFirstRead());
        $this->assertSame('stored', $renderer->renderOrCache($this->region(1), [$this->widget(7, 100)], []));
        $this->assertSame(0, $renderer->renders);
    }

    /**
     * A registry whose render appears after it was first found missing, as when another request stores it.
     */
    private function registryStoredAfterFirstRead(): LibraryStorageRegistryInterface
    {
        $reads = 0;
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readStream')->willReturnCallback(function () use (&$reads) {
            if ($reads++ === 0) {
                throw new NotFoundException('missing');
            }
            return Utils::streamFor('stored');
        });
        $storage->expects($this->never())->method('moveFromLocalPath');

        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->willReturn($storage);
        return $registry;
    }

    private function holdLock(int $widgetId, int $regionId): void
    {
        $key = 'locks/widget-render/' . $widgetId . '_' . $regionId . '_' . strtotime(self::PLAYLIST_DT) . '.html';
        (new StashLock($this->pool, $key))->acquire(30, 1, 1);
    }

    public function testDeletingAWidgetRemovesEveryOneOfItsRenders(): void
    {
        $storage = $this->registry->get(LibraryAsset::WIDGET_HTML);
        foreach (['7/1_100.html', '7/2_200.html', '70/1_100.html'] as $key) {
            $path = $this->libraryLocation . 'temp/seed';
            file_put_contents($path, 'old');
            $storage->moveFromLocalPath($path, $key);
        }

        $this->renderer()->clearWidgetCache($this->widget(7, 100));

        $this->assertSame(['70/1_100.html'], $this->storedKeys());
    }

    public function testRendersAreKeptUnderTheWidgetFolderOfTheLibrary(): void
    {
        $this->renderer()->renderOrCache($this->region(1), [$this->widget(7, 100)], []);

        $this->assertFileExists(
            $this->libraryLocation . 'widget/7/1_' . strtotime(self::PLAYLIST_DT) . '.html'
        );
    }
}
