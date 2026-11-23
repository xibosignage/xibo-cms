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

namespace Xibo\Storage;

use Psr\Log\LoggerInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Records library file writes and deletes, so a failed delete or rolled-back upload never orphans a file.
 */
class LibraryFileJournal
{
    public const PENDING_UPLOAD = 'pending_upload';
    public const PENDING_DELETE = 'pending_delete';

    /**
     * Upload records younger than this may belong to a transaction still open.
     */
    public const UPLOAD_GRACE = 86400;

    /** Failures after which a delete is reported as stuck. */
    public const STUCK_AFTER_ATTEMPTS = 5;

    /**
     * @param LibraryStorageServiceInterface $storage the store the recorded keys belong to: records are tagged with
     *     its location, and only records for the store in use are ever carried out
     * @param string $assetType the LibraryAsset type $storage holds, which decides what refers to a key
     */
    public function __construct(
        private readonly StorageServiceInterface $store,
        private readonly LibraryStorageServiceInterface $storage,
        private readonly string $assetType
    ) {
    }

    /**
     * Records an upload on the isolated connection, so the record survives a rollback.
     * @return int the record id
     */
    public function recordUpload(string $key): int
    {
        return $this->store->insert(
            'INSERT INTO `library_file_journal` (`storageKey`, `assetType`, `location`, `state`, `createdDt`)
                VALUES (:key, :assetType, :location, :state, :createdDt)',
            [
                'key' => $key,
                'assetType' => $this->assetType,
                'location' => $this->storage->getLocation(),
                'state' => self::PENDING_UPLOAD,
                'createdDt' => time(),
            ],
            StorageServiceInterface::ISOLATED_CONNECTION,
            false,
            false
        );
    }

    /**
     * Remove an upload record once the caller's transaction commits (straight away if there isn't one).
     */
    public function clearUploadAfterCommit(int $id): void
    {
        $this->store->afterCommit(function () use ($id) {
            $this->removeRecord($id);
        });
    }

    /**
     * Records an upload and moves a local file into storage under $key, removing the local file if it isn't stored.
     * @param bool $clearAfterCommit false when the caller clears the record itself, such as once its row is saved
     * @param LoggerInterface|null $discardLogger deletes a failed move's upload straight away, logging to it
     * @return int the record id
     * @throws GeneralException
     */
    public function storeLocalFile(
        string $localPath,
        string $key,
        bool $clearAfterCommit = true,
        ?LoggerInterface $discardLogger = null
    ): int {
        $id = null;
        try {
            $id = $this->recordUpload($key);
            $this->storage->moveFromLocalPath($localPath, $key);
        } catch (\Throwable $throwable) {
            @unlink($localPath);
            if ($id !== null && $discardLogger !== null) {
                $this->discardUpload($id, $key, $discardLogger);
            }
            throw $throwable;
        }

        if ($clearAfterCommit) {
            $this->clearUploadAfterCommit($id);
        }
        return $id;
    }

    /**
     * Records a delete in the caller's transaction, so it only stands if that commits.
     * @return int the record id
     */
    public function recordDelete(string $key): int
    {
        return $this->store->insert(
            'INSERT INTO `library_file_journal` (`storageKey`, `assetType`, `location`, `state`, `createdDt`)
                VALUES (:key, :assetType, :location, :state, :createdDt)',
            [
                'key' => $key,
                'assetType' => $this->assetType,
                'location' => $this->storage->getLocation(),
                'state' => self::PENDING_DELETE,
                'createdDt' => time(),
            ]
        );
    }

    /**
     * Deletes $key from $assetType's storage after the caller's transaction commits, logging rather than throwing
     * when that storage is unusable, so a row delete never fails because of its file.
     */
    public static function deleteFileAfterCommit(
        StorageServiceInterface $store,
        LibraryStorageRegistryInterface $registry,
        string $assetType,
        string $key,
        LoggerInterface $logger,
        bool $ignoreReferences = false,
        ?string $storageEngine = null
    ): void {
        // Nothing to delete, so storage isn't resolved.
        if ($key === '') {
            return;
        }

        try {
            (new self($store, $registry->get($assetType, $storageEngine), $assetType))
                ->deleteAfterCommit($key, $logger, $ignoreReferences);
        } catch (GeneralException $exception) {
            $logger->error('libraryFileJournal: cannot delete ' . $assetType . ' file ' . $key . '. '
                . $exception->getMessage());
        }
    }

    /**
     * Records a delete with the caller's transaction and performs it after commit, unless a row refers to the key
     * again by then; a failure, or a pending upload under the key, is left for the next tidy.
     * @param bool $ignoreReferences delete even though a row refers to the key, for a key a new row takes over whose
     *     file must not carry over (a replaced draft's thumbnail). The tidy doesn't know this flag, so a delete left to
     *     it keeps a referenced file: a replaced draft's thumbnail stays until the new draft saves its own.
     * @throws GeneralException if the storage location cannot be read
     */
    public function deleteAfterCommit(string $key, LoggerInterface $logger, bool $ignoreReferences = false): void
    {
        // An empty key would resolve to the asset folder.
        if ($key === '') {
            return;
        }

        $id = $this->recordDelete($key);
        $this->store->afterCommit(function () use ($id, $key, $logger, $ignoreReferences) {
            try {
                if ($this->hasPendingUpload($key)) {
                    $logger->debug('libraryFileJournal: ' . $key . ' is being uploaded, the next tidy will check it.');
                    return;
                }
                if ($ignoreReferences || !$this->isReferenced($key)) {
                    $this->storage->delete($key);
                }
                $this->removeRecord($id);
            } catch (InvalidArgumentException $exception) {
                $this->dropRefusedKey($id, $key, $exception, $logger);
            } catch (GeneralException $exception) {
                $logger->error('libraryFileJournal: cannot delete ' . $key . ', the next tidy will retry. '
                    . $exception->getMessage());
                $this->recordFailure($id, $exception->getMessage());
            }
        });
    }

    /**
     * Deletes an upload whose row was never saved, leaving its record for a later tidy if that fails.
     */
    public function discardUpload(int $id, string $key, LoggerInterface $logger): void
    {
        try {
            $this->storage->delete($key);
            $this->removeRecord($id);
        } catch (GeneralException $exception) {
            $logger->error('libraryFileJournal: cannot delete unused upload ' . $key . ', the next tidy will retry. '
                . $exception->getMessage());
        }
    }

    /**
     * Removes the pending upload records for $key, when a request other than the uploader's has claimed it.
     */
    public function clearPendingUploadsOf(string $key): void
    {
        $this->store->update(
            'DELETE FROM `library_file_journal`
              WHERE `storageKey` = :key AND `assetType` = :assetType AND `location` = :location AND `state` = :state',
            [
                'key' => $key,
                'assetType' => $this->assetType,
                'location' => $this->storage->getLocation(),
                'state' => self::PENDING_UPLOAD,
            ],
            StorageServiceInterface::ISOLATED_CONNECTION,
            false,
            false
        );
    }

    /**
     * Removes a finished record on the isolated connection.
     */
    public function removeRecord(int $id): void
    {
        $this->store->update(
            'DELETE FROM `library_file_journal` WHERE `id` = :id',
            ['id' => $id],
            StorageServiceInterface::ISOLATED_CONNECTION,
            false,
            false
        );
    }

    /**
     * Turn an upload record into a delete, once it's known nothing refers to the file.
     */
    private function markForDelete(int $id): void
    {
        $this->store->update(
            'UPDATE `library_file_journal` SET `state` = :state WHERE `id` = :id',
            ['id' => $id, 'state' => self::PENDING_DELETE],
            StorageServiceInterface::ISOLATED_CONNECTION,
            false,
            false
        );
    }

    /**
     * Note a failed attempt, keeping the record for the next run.
     */
    private function recordFailure(int $id, string $error): void
    {
        $this->store->update(
            'UPDATE `library_file_journal` SET `attempts` = `attempts` + 1, `lastError` = :lastError WHERE `id` = :id',
            ['id' => $id, 'lastError' => mb_substr($error, 0, 255)],
            StorageServiceInterface::ISOLATED_CONNECTION,
            false,
            false
        );
    }

    /**
     * Carries out pending work for every asset type, logging and skipping storage that can't be reached.
     * @return array{deleted: int, failed: int, stuck: int}
     */
    public static function processAllPending(
        StorageServiceInterface $store,
        LibraryStorageRegistryInterface $registry,
        LoggerInterface $logger
    ): array {
        $total = ['deleted' => 0, 'failed' => 0, 'stuck' => 0];
        foreach (LibraryAsset::all() as $assetType) {
            // Deletes of files still waiting to be copied are recorded against the local library.
            $locations = [];
            foreach ([null, LibraryStorageRegistryInterface::LOCAL_ENGINE] as $storageEngine) {
                try {
                    $storage = $registry->get($assetType, $storageEngine);
                    if (in_array($storage->getLocation(), $locations, true)) {
                        continue;
                    }
                    $locations[] = $storage->getLocation();
                    $result = (new self($store, $storage, $assetType))->processPending($logger);
                } catch (GeneralException $exception) {
                    $logger->error('libraryFileJournal: cannot process ' . $assetType . '. '
                        . $exception->getMessage());
                    continue;
                }

                foreach ($total as $outcome => $sum) {
                    $total[$outcome] = $sum + $result[$outcome];
                }
            }
        }

        return $total;
    }

    /**
     * Carries out pending uploads and deletes recorded for this store, outside any transaction holding journal rows.
     * @return array{deleted: int, failed: int, stuck: int}
     */
    public function processPending(LoggerInterface $logger): array
    {
        $storage = $this->storage;
        foreach ($this->getPending(self::PENDING_UPLOAD, time() - self::UPLOAD_GRACE) as $row) {
            if ($this->isReferenced($row['storageKey'])) {
                $this->removeRecord((int)$row['id']);
            } else {
                $this->markForDelete((int)$row['id']);
            }
        }

        $result = ['deleted' => 0, 'failed' => 0, 'stuck' => 0];
        foreach ($this->getPending(self::PENDING_DELETE, time() + 1) as $row) {
            $key = $row['storageKey'];
            if ($this->hasPendingUpload($key)) {
                // The upload's row may not be committed yet, so the key is checked again by a later tidy.
                continue;
            }
            try {
                if (!$this->isReferenced($key)) {
                    $logger->debug('libraryFileJournal: deleting journalled library file ' . $key);
                    $storage->delete($key);
                    $result['deleted']++;
                }
                $this->removeRecord((int)$row['id']);
            } catch (InvalidArgumentException $exception) {
                $this->dropRefusedKey((int)$row['id'], $key, $exception, $logger);
            } catch (GeneralException $exception) {
                $logger->error('libraryFileJournal: cannot delete ' . $key . '. ' . $exception->getMessage());
                $this->recordFailure((int)$row['id'], $exception->getMessage());
                $result['failed']++;
                if ((int)$row['attempts'] + 1 >= self::STUCK_AFTER_ATTEMPTS) {
                    $result['stuck']++;
                }
            }
        }

        return $result;
    }

    /**
     * Removes the record of a key the storage refuses (such as a legacy name containing '..'), which no retry fixes.
     */
    private function dropRefusedKey(
        int $id,
        string $key,
        InvalidArgumentException $exception,
        LoggerInterface $logger
    ): void {
        $logger->error('libraryFileJournal: storage refuses the key ' . $key . ', leaving the file in place. '
            . $exception->getMessage());
        $this->removeRecord($id);
    }

    /**
     * Whether $key has an upload whose row isReferenced() may not see until it commits, recorded before or after the
     * delete.
     */
    public function hasPendingUpload(string $key): bool
    {
        // Read on the connection upload records are written on, so a newer one is seen at once.
        return (bool)$this->store->exists(
            'SELECT `id` FROM `library_file_journal`
              WHERE `storageKey` = :storageKey AND `assetType` = :assetType AND `location` = :location
                AND `state` = :state
              LIMIT 1',
            [
                'storageKey' => $key,
                'assetType' => $this->assetType,
                'location' => $this->storage->getLocation(),
                'state' => self::PENDING_UPLOAD,
            ],
            StorageServiceInterface::ISOLATED_CONNECTION
        );
    }

    /**
     * Whether a database row still refers to $key, for this journal's asset type.
     */
    public function isReferenced(string $key): bool
    {
        return (new LibraryFileReferences($this->store))->isReferenced($this->assetType, $key);
    }

    /**
     * Records for this store in $state created before $before, fewest failed attempts first (so records that keep
     * failing never hold up the rest), then oldest first.
     * @return array<array{id: int, storageKey: string, attempts: int}>
     */
    private function getPending(string $state, int $before, int $limit = 1000): array
    {
        return $this->store->select(
            'SELECT `id`, `storageKey`, `attempts` FROM `library_file_journal`
              WHERE `state` = :state AND `assetType` = :assetType AND `location` = :location
                AND `createdDt` < :before
              ORDER BY `attempts`, `id`
              LIMIT ' . $limit,
            [
                'state' => $state,
                'assetType' => $this->assetType,
                'location' => $this->storage->getLocation(),
                'before' => $before,
            ]
        );
    }
}
