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

namespace Xibo\Entity;

use OpenApi\Attributes as OA;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;

/**
* Class SavedReport
* @package Xibo\Entity
*/
#[OA\Schema]
class SavedReport implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'Saved report ID')]
    public $savedReportId;

    /**
     * @var string
     */
    #[OA\Property(description: 'Saved report name As')]
    public $saveAs;

    /**
     * @var int
     */
    #[OA\Property(description: 'Report schedule Id of the saved report')]
    public $reportScheduleId;

    /**
     * @var string
     */
    #[OA\Property(description: 'Report schedule name of the saved report')]
    public $reportScheduleName;

    /**
     * @var string
     */
    #[OA\Property(description: 'Report name')]
    public $reportName;

    /**
     * @var string
     */
    #[OA\Property(description: 'Saved report generated on')]
    public $generatedOn;

    /**
     * @var string
     */
    #[OA\Property(description: 'The username of the User that owns this saved report')]
    public $owner;

    /**
     * @var int
     */
    #[OA\Property(description: 'The ID of the User that owns this saved report')]
    public $userId;

    /**
     * @var string
     */
    #[OA\Property(description: 'Original name of the saved report media file')]
    public $originalFileName;

    /**
     * @var string
     */
    #[OA\Property(description: 'Stored As')]
    public $storedAs;

    /**
     * @var int
     */
    #[OA\Property(description: 'Schema Version')]
    public $schemaVersion = 2;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Saved Report file name')]
    public $fileName;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Saved Report file size in bytes')]
    public $size;

    /**
     * @var string
     */
    #[OA\Property(description: 'A MD5 checksum of the stored Saved Report file')]
    public $md5;

    /**
     * Where the file is while files are copied to new storage: 'local' until copied, else the configured store.
     * @var string|null
     */
    public $storageEngine;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param MediaFactory $mediaFactory
     * @param SavedReportFactory $savedReportFactory
     * @param LibraryStorageRegistryInterface $libraryStorageRegistry
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        $mediaFactory,
        $savedReportFactory,
        LibraryStorageRegistryInterface $libraryStorageRegistry
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->mediaFactory = $mediaFactory;
        $this->savedReportFactory = $savedReportFactory;
        $this->libraryStorageRegistry = $libraryStorageRegistry;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->savedReportId = $this->getStore()->insert('
            INSERT INTO `saved_report` (`saveAs`, `reportScheduleId`, `generatedOn`, `userId`, `schemaVersion`, `fileName`, `size`, `md5`)
              VALUES (:saveAs, :reportScheduleId, :generatedOn, :userId, :schemaVersion, :fileName, :size, :md5)
        ', [
            'saveAs' => $this->saveAs,
            'reportScheduleId' => $this->reportScheduleId,
            'generatedOn' => $this->generatedOn,
            'userId' => $this->userId,
            'schemaVersion' => $this->schemaVersion,
            'fileName' => $this->fileName,
            'size' => $this->size,
            'md5' => $this->md5
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $sql = '
          UPDATE `saved_report`
            SET `saveAs` = :saveAs,
                `reportScheduleId` = :reportScheduleId,
                `generatedOn` = :generatedOn,
                `userId` = :userId,
                `schemaVersion` = :schemaVersion,
                `fileName` = :fileName,
                `size` = :size,
                `md5` = :md5
           WHERE savedReportId = :savedReportId
        ';

        $params = [
            'saveAs' => $this->saveAs,
            'reportScheduleId' => $this->reportScheduleId,
            'generatedOn' => $this->generatedOn,
            'userId' => $this->userId,
            'schemaVersion' => $this->schemaVersion,
            'fileName' => $this->fileName,
            'size' => $this->size,
            'md5' => $this->md5,
            'savedReportId' => $this->savedReportId,
        ];

        $this->getStore()->update($sql, $params);
    }


    /**
     * Delete
     */
    public function delete()
    {
        $this->load();

        $this->getLog()->debug('Delete saved report: '.$this->saveAs.'. Generated on: '.$this->generatedOn);
        $this->getStore()->update('DELETE FROM `saved_report` WHERE `savedReportId` = :savedReportId', [
            'savedReportId' => $this->savedReportId
        ]);

        // Update last saved report in report schedule
        $this->getLog()->debug('Update last saved report in report schedule');
        $this->getStore()->update('
        UPDATE `reportschedule` SET lastSavedReportId = ( SELECT IFNULL(MAX(`savedReportId`), 0) FROM `saved_report` WHERE `reportScheduleId`= :reportScheduleId) 
        WHERE `reportScheduleId` = :reportScheduleId',
            [
            'reportScheduleId' => $this->reportScheduleId
            ]);

        LibraryFileJournal::deleteFileAfterCommit(
            $this->getStore(),
            $this->libraryStorageRegistry,
            LibraryAsset::SAVED_REPORT,
            (string)$this->fileName,
            $this->getLog()->getLoggerInterface(),
            false,
            $this->storageEngine
        );
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded || $this->savedReportId == null)
            return;

        $this->loaded = true;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->savedReportId;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->savedReportId == null || $this->savedReportId == 0)
            $this->add();
        else
            $this->edit();
    }
}
