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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Xibo\Entity\PlayerVersion;
use Xibo\Entity\RequiredFile;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Xmds\Listeners\XmdsPlayerVersionListener;
use Xibo\Xmds\Soap;

/**
 * Player software packages are deleted and served to players through player software storage.
 */
class PlayerVersionStorageTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/player-version-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->libraryLocation);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->libraryLocation);
    }

    /**
     * @param string[] $events collects the SQL, journal records and storage calls, in order
     */
    private function newVersion(LibraryStorageServiceInterface $storage, array &$events): PlayerVersion
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('update')->willReturnCallback(function ($sql, $params = []) use (&$events) {
            if (str_contains($sql, 'SET `attempts` = `attempts` + 1')) {
                $events[] = 'journal:failure:' . $params['lastError'];
            } else if (!str_contains($sql, 'library_file_journal')) {
                $events[] = 'sql';
            }
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) use (&$events) {
            $events[] = 'journal:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            return 1;
        });

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('LIBRARY_LOCATION')->willReturn($this->libraryLocation);

        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::PLAYER_SOFTWARE)->willReturn($storage);

        $version = new PlayerVersion(
            $store,
            $this->createMock(LogServiceInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $config,
            $registry
        );
        $version->versionId = 3;
        $version->fileName = 'xibo-android_v4.apk';

        return $version;
    }

    /**
     * @param string[] $events
     */
    private function recordingStorage(array &$events, bool $fails = false): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$events, $fails) {
            $events[] = 'file:' . $key;
            if ($fails) {
                throw new GeneralException('outage');
            }
        });
        return $storage;
    }

    public function testDeleteJournalsAndRemovesThePackageAfterTheRow()
    {
        $events = [];
        $this->newVersion($this->recordingStorage($events), $events)->delete();

        $this->assertSame(
            ['sql', 'journal:pending_delete:' . LibraryAsset::PLAYER_SOFTWARE . ':xibo-android_v4.apk',
                'file:xibo-android_v4.apk'],
            $events
        );
    }

    public function testDeleteSurvivesAStorageErrorAndLeavesItForTheTidy()
    {
        $events = [];
        $this->newVersion($this->recordingStorage($events, true), $events)->delete();

        $this->assertSame(['file:xibo-android_v4.apk', 'journal:failure:outage'], array_slice($events, -2));
    }

    public function testDependencyRequestIsServedFromPlayerSoftwareStorage()
    {
        $events = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $version = $this->newVersion($storage, $events);

        $factory = $this->createMock(PlayerVersionFactory::class);
        $factory->method('getById')->with(3)->willReturn($version);
        $factory->method('getStorage')->willReturn($storage);

        $requiredFile = $this->createMock(RequiredFile::class);
        $requiredFile->fileType = 'playersoftware';
        $requiredFile->itemId = 3;
        $requiredFile->realId = '3';
        $event = new XmdsDependencyRequestEvent($requiredFile);

        (new XmdsPlayerVersionListener($factory))->onDependencyRequest($event);

        $this->assertSame($storage, $event->getStorage());
        $this->assertSame('xibo-android_v4.apk', $event->getStorageKey());
        // Still recorded, for the local X-Sendfile path in xmds.php.
        $this->assertSame('playersoftware/xibo-android_v4.apk', $event->getRelativePath());
    }

    public function testOtherDependenciesHaveNoStorage()
    {
        $requiredFile = $this->createMock(RequiredFile::class);
        $requiredFile->fileType = 'font';
        $requiredFile->itemId = 1;
        $requiredFile->realId = '1';
        $event = new XmdsDependencyRequestEvent($requiredFile);

        (new XmdsPlayerVersionListener($this->createMock(PlayerVersionFactory::class)))->onDependencyRequest($event);

        $this->assertNull($event->getStorage());
    }

    private function chunkReader(): Soap
    {
        return new class extends Soap {
            public function __construct()
            {
            }

            public function read(XmdsDependencyRequestEvent $event, string $libraryLocation, int $offset, int $length)
            {
                return $this->readDependencyChunk($event, $libraryLocation, $offset, $length);
            }
        };
    }

    public function testDependencyChunkIsReadFromStorageWhenSet()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())->method('readRange')->with('xibo-android_v4.apk', 4, 3)
            ->willReturn(Utils::streamFor('abc'));

        $event = new XmdsDependencyRequestEvent($this->createMock(RequiredFile::class));
        $event->setStorage($storage, 'xibo-android_v4.apk');

        $this->assertSame('abc', $this->chunkReader()->read($event, $this->libraryLocation, 4, 3));
    }

    public function testDependencyChunkIsReadFromTheLibraryWithoutStorage()
    {
        mkdir($this->libraryLocation . 'assets');
        file_put_contents($this->libraryLocation . 'assets/bundle.js', '0123456789');

        $event = new XmdsDependencyRequestEvent($this->createMock(RequiredFile::class));
        $event->setRelativePathToLibrary('assets/bundle.js');

        $this->assertSame('456', $this->chunkReader()->read($event, $this->libraryLocation, 4, 3));
    }
}
