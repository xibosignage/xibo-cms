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

namespace Xibo\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Journal processing deletes only recorded files no media row refers to.

 */
class LibraryFileJournalTest extends TestCase
{
    /** @var array<array{0: string, 1: array}> journal writes: [sql, params] */
    private array $updates = [];

    /**
     * @param array<string, array> $journal state => rows
     * @param string[] $referenced keys a media row refers to
     * @param string[] $failing keys whose delete fails
     * @param array<array{id: int, storageKey: string, assetType: string, location: string}> $uploads pending upload
     *     records
     * @return array{0: array, 1: string[]} [processPending() result, keys deleted]
     */
    private function process(
        array $journal,
        array $referenced = [],
        array $failing = [],
        string $journalLocation = 'local:/library/',
        array $uploads = []
    ): array {
        $this->updates = [];
        $store = $this->createMock(StorageServiceInterface::class);
        // Rows are only ever selected for the store in use.
        $store->method('select')->willReturnCallback(
            fn ($sql, $params) => $params['location'] === $journalLocation ? ($journal[$params['state']] ?? []) : []
        );
        $store->method('exists')->willReturnCallback(
            fn ($sql, $params) => in_array($params['storedAs'] ?? '', $referenced, true)
                || $this->hasPendingUpload($uploads, $params)
        );
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            $this->updates[] = [$sql, $params];
            return 1;
        });

        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:/library/');
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted, $failing) {
            if (in_array($key, $failing, true)) {
                throw new GeneralException('Problem deleting: ' . $key);
            }
            $deleted[] = $key;
        });

        $result = (new LibraryFileJournal($store, $storage, LibraryAsset::MEDIA))->processPending(new NullLogger());

        return [$result, $deleted];
    }

    /**
     * Answers the journal's pending-upload lookup from $uploads, the pending upload records.
     * @param array<array{id: int, storageKey: string, assetType: string, location: string}> $uploads
     */
    private function hasPendingUpload(array $uploads, array $params): bool
    {
        if (($params['state'] ?? null) !== LibraryFileJournal::PENDING_UPLOAD || !isset($params['storageKey'])) {
            return false;
        }

        foreach ($uploads as $upload) {
            if ($upload['storageKey'] === $params['storageKey']
                && $upload['assetType'] === $params['assetType']
                && $upload['location'] === $params['location']
                && (!isset($params['deleteId']) || $upload['id'] > $params['deleteId'])
            ) {
                return true;
            }
        }
        return false;
    }

    private function upload(
        int $id,
        string $key,
        string $assetType = LibraryAsset::MEDIA,
        string $location = 'local:/library/'
    ): array {
        return ['id' => $id, 'storageKey' => $key, 'assetType' => $assetType, 'location' => $location];
    }

    private function row(int $id, string $key, int $attempts = 0): array
    {
        return ['id' => $id, 'storageKey' => $key, 'attempts' => $attempts];
    }

    public function testRecordedDeletesHappenUnlessTheFileIsReferencedAgain()
    {
        [$result, $deleted] = $this->process(
            [LibraryFileJournal::PENDING_DELETE => [$this->row(1, '7.jpg'), $this->row(2, 'feed.png')]],
            ['feed.png']
        );

        $this->assertSame(['7.jpg'], $deleted);
        $this->assertSame(['deleted' => 1, 'failed' => 0, 'stuck' => 0], $result);
        $this->assertSame([['id' => 1], ['id' => 2]], array_column($this->updates, 1));
    }

    public function testAnUploadThatNeverCommittedBecomesADeleteUnlessReferenced()
    {
        $this->process([LibraryFileJournal::PENDING_UPLOAD => [$this->row(3, '9.jpg'), $this->row(4, '5.jpg')]], [
            '5.jpg',
        ]);

        $this->assertSame(
            [['id' => 3, 'state' => LibraryFileJournal::PENDING_DELETE], ['id' => 4]],
            array_column($this->updates, 1)
        );
    }

    public function testAFailedDeleteIsKeptAndCountedAsStuckAfterRepeatedFailures()
    {
        [$result, $deleted] = $this->process([LibraryFileJournal::PENDING_DELETE => [
            $this->row(1, '7.jpg', LibraryFileJournal::STUCK_AFTER_ATTEMPTS - 1),
            $this->row(2, '8.jpg'),
        ]], [], ['7.jpg', '8.jpg']);

        $this->assertSame([], $deleted);
        $this->assertSame(['deleted' => 0, 'failed' => 2, 'stuck' => 1], $result);
        foreach ($this->updates as [$sql]) {
            $this->assertStringContainsString('`attempts` = `attempts` + 1', $sql);
        }
    }

    public function testRecordsMadeAgainstAnotherStoreAreLeftAlone()
    {
        // Recorded against another store, e.g. before switching backends.
        [$result, $deleted] = $this->process(
            [LibraryFileJournal::PENDING_DELETE => [$this->row(1, '7.jpg')]],
            [],
            [],
            'other:/media/',
        );

        $this->assertSame([], $deleted);
        $this->assertSame([], $this->updates);
        $this->assertSame(['deleted' => 0, 'failed' => 0, 'stuck' => 0], $result);
    }

    public function testRecordsAreTaggedWithTheirAssetTypeAndStore()
    {
        $inserts = [];
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('insert')->willReturnCallback(function ($sql, $params) use (&$inserts) {
            $inserts[] = $params;
            return count($inserts);
        });
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:/library/fonts/');

        $journal = new LibraryFileJournal($store, $storage, LibraryAsset::FONT);
        $journal->recordUpload('Lato.ttf');
        $journal->recordDelete('Old.ttf');

        $this->assertSame([LibraryAsset::FONT, LibraryAsset::FONT], array_column($inserts, 'assetType'));
        $this->assertSame(['local:/library/fonts/', 'local:/library/fonts/'], array_column($inserts, 'location'));
    }

    public function testRecordsAreCheckedAgainstTheTableThatOwnsTheirAssetType()
    {
        // A font row refers to Lato.ttf, which no media row ever would.
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturnCallback(
            fn ($sql, $params) => $params['state'] === LibraryFileJournal::PENDING_DELETE
                && $params['assetType'] === LibraryAsset::FONT
                ? [$this->row(1, 'Lato.ttf'), $this->row(2, 'Gone.ttf')]
                : []
        );
        $store->method('exists')->willReturnCallback(
            fn ($sql, $params) => str_contains($sql, '`fonts`') && ($params['key'] ?? '') === 'Lato.ttf'
        );

        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:/library/fonts/');
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted) {
            $deleted[] = $key;
        });

        (new LibraryFileJournal($store, $storage, LibraryAsset::FONT))->processPending(new NullLogger());

        $this->assertSame(['Gone.ttf'], $deleted);
    }

    public function testEveryAssetTypeIsProcessedAndOneUnreachableStoreDoesNotStopTheRest()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturnCallback(
            fn ($sql, $params) => $params['state'] === LibraryFileJournal::PENDING_DELETE
                ? [$this->row(1, $params['assetType'] . '-file')]
                : []
        );
        $store->method('exists')->willReturn(false);

        $deleted = [];
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->willReturnCallback(function (string $assetType) use (&$deleted) {
            $storage = $this->createMock(LibraryStorageServiceInterface::class);
            if ($assetType === LibraryAsset::SCREENSHOT) {
                $storage->method('getLocation')->willThrowException(new GeneralException('unreachable'));
            } else {
                $storage->method('getLocation')->willReturn('local:/library/' . $assetType . '/');
            }
            $storage->method('delete')->willReturnCallback(function (string $key) use (&$deleted) {
                $deleted[] = $key;
            });
            return $storage;
        });

        $result = LibraryFileJournal::processAllPending($store, $registry, new NullLogger());

        // Keys of an unrecognised shape are kept by the id-keyed assets, so only these are deleted.
        $this->assertEqualsCanonicalizing([
            'media-file',
            'savedreport-file',
            'attachment-file',
            'playersoftware-file',
            'fonts-file',
        ], $deleted);
        // Unreachable storage is logged, not counted as a failed record.
        $this->assertSame(['deleted' => 5, 'failed' => 0, 'stuck' => 0], $result);
    }

    /**
     * @param string[] $referenced keys a row refers to
     * @param string[] $events filled with what happens, in order
     */
    private function journalFor(
        string $assetType,
        array $referenced,
        bool|GeneralException $deleteFails,
        array &$events,
        array $uploads = [],
        bool $moveFails = false
    ): LibraryFileJournal {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) use (&$events) {
            $events[] = 'commit';
            $callback();
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) use (&$events) {
            $events[] = 'record:' . $params['state'] . ':' . $params['key'];
            return 9;
        });
        $store->method('exists')->willReturnCallback(
            fn ($sql, $params) => in_array($params['key'] ?? '', $referenced, true)
                || $this->hasPendingUpload($uploads, $params)
        );
        $store->method('update')->willReturnCallback(function ($sql, $params) use (&$events) {
            $events[] = preg_match('/^\s*(\w+)/', $sql, $m) ? strtolower($m[1]) . ':' . json_encode($params) : $sql;
            return 1;
        });

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('getLocation')->willReturn('local:/library/attachment/');
        $storage->method('delete')->willReturnCallback(function (string $key) use (&$events, $deleteFails) {
            $events[] = 'file:' . $key;
            if ($deleteFails instanceof GeneralException) {
                throw $deleteFails;
            }
            if ($deleteFails) {
                throw new GeneralException('outage');
            }
        });
        $storage->method('moveFromLocalPath')->willReturnCallback(
            function (string $localPath, string $key) use (&$events, $moveFails) {
                $events[] = 'move:' . $key;
                if ($moveFails) {
                    throw new GeneralException('outage');
                }
                unlink($localPath);
            }
        );

        return new LibraryFileJournal($store, $storage, $assetType);
    }

    public function testDeleteAfterCommitRecordsTheDeleteThenRemovesTheFileAndRecord()
    {
        $events = [];
        $this->journalFor(LibraryAsset::ATTACHMENT, [], false, $events)
            ->deleteAfterCommit('7_a.pdf', new NullLogger());

        $this->assertSame(
            ['record:pending_delete:7_a.pdf', 'commit', 'file:7_a.pdf', 'delete:{"id":9}'],
            $events
        );
    }

    public function testDeleteAfterCommitIgnoresAnEmptyKey()
    {
        // An empty key would resolve to the asset folder itself.
        $events = [];
        $this->journalFor(LibraryAsset::SAVED_REPORT, [], false, $events)
            ->deleteAfterCommit('', new NullLogger());

        $this->assertSame([], $events);
    }

    public function testDeleteAfterCommitKeepsAFileARowRefersToAgain()
    {
        $events = [];
        $this->journalFor(LibraryAsset::ATTACHMENT, ['7_a.pdf'], false, $events)
            ->deleteAfterCommit('7_a.pdf', new NullLogger());

        $this->assertSame(['record:pending_delete:7_a.pdf', 'commit', 'delete:{"id":9}'], $events);
    }

    public function testDeleteAfterCommitIgnoringReferencesDeletesAKeyARowRefersTo()
    {
        // e.g. a replaced draft's thumbnail, whose key the new draft takes over.
        $events = [];
        $this->journalFor(LibraryAsset::ATTACHMENT, ['7_a.pdf'], false, $events)
            ->deleteAfterCommit('7_a.pdf', new NullLogger(), true);

        $this->assertSame(
            ['record:pending_delete:7_a.pdf', 'commit', 'file:7_a.pdf', 'delete:{"id":9}'],
            $events
        );
    }

    public function testDeleteAfterCommitIgnoringReferencesStillKeepsAKeyUploadedAgainSince()
    {
        // e.g. a remote template storing its own thumbnail for the replacement draft in the same request.
        $events = [];
        $this->journalFor(LibraryAsset::LAYOUT_THUMBNAIL, [], false, $events, [
            $this->upload(12, '4_layout_thumb.png', LibraryAsset::LAYOUT_THUMBNAIL, 'local:/library/attachment/'),
        ])
            ->deleteAfterCommit('4_layout_thumb.png', new NullLogger(), true);

        $this->assertSame(['record:pending_delete:4_layout_thumb.png', 'commit'], $events);
    }

    public function testDeleteAfterCommitLeavesAKeyUploadedAgainSinceForTheNextTidy()
    {
        // e.g. a thumbnail regenerated under the same key by a request that hasn't committed its row yet.
        $events = [];
        $this->journalFor(LibraryAsset::LAYOUT_THUMBNAIL, [], false, $events, [
            $this->upload(12, '4_layout_thumb.png', LibraryAsset::LAYOUT_THUMBNAIL, 'local:/library/attachment/'),
        ])
            ->deleteAfterCommit('4_layout_thumb.png', new NullLogger());

        $this->assertSame(['record:pending_delete:4_layout_thumb.png', 'commit'], $events);

        // An upload recorded before the delete whose row isn't committed yet holds it up too.
        $events = [];
        $this->journalFor(LibraryAsset::LAYOUT_THUMBNAIL, [], false, $events, [
            $this->upload(5, '4_layout_thumb.png', LibraryAsset::LAYOUT_THUMBNAIL, 'local:/library/attachment/'),
        ])
            ->deleteAfterCommit('4_layout_thumb.png', new NullLogger());

        $this->assertSame(['record:pending_delete:4_layout_thumb.png', 'commit'], $events);
    }

    public function testATidyLeavesADeleteWhoseKeyHasAPendingUpload()
    {
        [$result, $deleted] = $this->process(
            [LibraryFileJournal::PENDING_DELETE => [
                $this->row(3, 'Lato.ttf'),
                $this->row(6, 'Old.ttf'),
                $this->row(7, 'Gone.ttf'),
            ]],
            [],
            [],
            'local:/library/',
            [$this->upload(4, 'Lato.ttf'), $this->upload(2, 'Old.ttf')]
        );

        $this->assertSame(['Gone.ttf'], $deleted);
        $this->assertSame([['id' => 7]], array_column($this->updates, 1), 'the records for uploaded keys are kept');
        $this->assertSame(['deleted' => 1, 'failed' => 0, 'stuck' => 0], $result);
    }

    public function testAnUploadOfTheKeyForAnotherAssetTypeOrStoreDoesNotHoldUpADelete()
    {
        [$result, $deleted] = $this->process(
            [LibraryFileJournal::PENDING_DELETE => [$this->row(3, '7.jpg')]],
            [],
            [],
            'local:/library/',
            [
                $this->upload(4, '7.jpg', LibraryAsset::LAYOUT_THUMBNAIL),
                $this->upload(5, '7.jpg', LibraryAsset::MEDIA, 'other:/media/'),
            ]
        );

        $this->assertSame(['7.jpg'], $deleted);
        $this->assertSame(['deleted' => 1, 'failed' => 0, 'stuck' => 0], $result);
    }

    public function testDeleteAfterCommitKeepsTheRecordForTheNextTidyWhenStorageFails()
    {
        $events = [];
        $this->journalFor(LibraryAsset::ATTACHMENT, [], true, $events)
            ->deleteAfterCommit('7_a.pdf', new NullLogger());

        $this->assertSame('file:7_a.pdf', $events[2]);
        $this->assertStringStartsWith('update:{"id":9,"lastError":"outage"}', $events[3]);
        $this->assertCount(4, $events);
    }

    public function testAKeyTheStorageRefusesIsDroppedRatherThanRetriedForever()
    {
        $events = [];
        $this->journalFor(
            LibraryAsset::ATTACHMENT,
            [],
            new InvalidArgumentException('Invalid relative path', 'key'),
            $events
        )->deleteAfterCommit('7_my..report.pdf', new NullLogger());

        $this->assertSame(
            ['record:pending_delete:7_my..report.pdf', 'commit', 'file:7_my..report.pdf', 'delete:{"id":9}'],
            $events
        );
    }

    public function testDiscardUploadRemovesTheRecordOnlyOnceTheFileIsGone()
    {
        $events = [];
        $this->journalFor(LibraryAsset::SAVED_REPORT, [], false, $events)
            ->discardUpload(4, 'rs_1.zip', new NullLogger());
        $this->assertSame(['file:rs_1.zip', 'delete:{"id":4}'], $events);

        $events = [];
        $this->journalFor(LibraryAsset::SAVED_REPORT, [], true, $events)
            ->discardUpload(4, 'rs_1.zip', new NullLogger());
        $this->assertSame(['file:rs_1.zip'], $events);
    }

    public function testStoreLocalFileRecordsTheUploadMovesTheFileAndClearsTheRecordOnCommit()
    {
        $events = [];
        $localPath = tempnam(sys_get_temp_dir(), 'journal');
        $id = $this->journalFor(LibraryAsset::FONT, [], false, $events)->storeLocalFile($localPath, 'Lato.ttf');

        $this->assertSame(9, $id);
        $this->assertSame(
            ['record:pending_upload:Lato.ttf', 'move:Lato.ttf', 'commit', 'delete:{"id":9}'],
            $events
        );
        $this->assertFileDoesNotExist($localPath);
    }

    public function testStoreLocalFileCanLeaveTheRecordForTheCallerToClear()
    {
        $events = [];
        $localPath = tempnam(sys_get_temp_dir(), 'journal');
        $id = $this->journalFor(LibraryAsset::ATTACHMENT, [], false, $events)
            ->storeLocalFile($localPath, '7_a.pdf', clearAfterCommit: false);

        $this->assertSame(9, $id);
        $this->assertSame(['record:pending_upload:7_a.pdf', 'move:7_a.pdf'], $events);
    }

    public function testAFailedStoreRemovesTheLocalFileAndLeavesTheRecordForTheTidy()
    {
        $events = [];
        $localPath = tempnam(sys_get_temp_dir(), 'journal');
        $journal = $this->journalFor(LibraryAsset::FONT, [], false, $events, [], true);

        try {
            $journal->storeLocalFile($localPath, 'Lato.ttf');
            $this->fail('The failed move should be thrown');
        } catch (GeneralException $exception) {
            $this->assertSame('outage', $exception->getMessage());
        }

        $this->assertSame(['record:pending_upload:Lato.ttf', 'move:Lato.ttf'], $events);
        $this->assertFileDoesNotExist($localPath);
    }

    public function testAFailedStoreCanDiscardTheUploadStraightAway()
    {
        $events = [];
        $localPath = tempnam(sys_get_temp_dir(), 'journal');
        $journal = $this->journalFor(LibraryAsset::SAVED_REPORT, [], false, $events, [], true);

        try {
            $journal->storeLocalFile($localPath, 'rs_1.zip', false, new NullLogger());
            $this->fail('The failed move should be thrown');
        } catch (GeneralException) {
        }

        $this->assertSame(
            ['record:pending_upload:rs_1.zip', 'move:rs_1.zip', 'file:rs_1.zip', 'delete:{"id":9}'],
            $events
        );
        $this->assertFileDoesNotExist($localPath);
    }

    public function testClearUploadsOfRemovesOnlyThisStoresUploadRecordsForTheKey()
    {
        $events = [];
        $this->journalFor(LibraryAsset::ATTACHMENT, [], false, $events)->clearPendingUploadsOf('.staged/abc_a.pdf');

        $this->assertSame(['delete:' . json_encode([
            'key' => '.staged/abc_a.pdf',
            'assetType' => LibraryAsset::ATTACHMENT,
            'location' => 'local:/library/attachment/',
            'state' => LibraryFileJournal::PENDING_UPLOAD,
        ])], $events);
    }
}
