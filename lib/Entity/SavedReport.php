<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Entity;

use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
* Class SavedReport
* @package Xibo\Entity
*
* @SWG\Definition()
*/
class SavedReport implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="Saved report ID")
     * @var int
     */
    public $savedReportId;

    /**
     * @SWG\Property(description="Saved report name As")
     * @var string
     */
    public $saveAs;

    /**
     * @SWG\Property(description="Report schedule Id of the saved report")
     * @var int
     */
    public $reportScheduleId;

    /**
     * @SWG\Property(description="Report schedule name of the saved report")
     * @var string
     */
    public $reportScheduleName;

    /**
     * @SWG\Property(description="Report name")
     * @var string
     */
    public $reportName;

    /**
     * @SWG\Property(description="Saved report generated on")
     * @var string
     */
    public $generatedOn;

    /**
     * @SWG\Property(description="The username of the User that owns this saved report")
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(description="The ID of the User that owns this saved report")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="Original name of the saved report media file")
     * @var string
     */
    public $originalFileName;

    /**
     * @SWG\Property(description="Stored As")
     * @var string
     */
    public $storedAs;

    /**
     * @SWG\Property(description="Schema Version")
     * @var int
     */
    public $schemaVersion = 2;

    /**
     * @SWG\Property(description="The Saved Report file name")
     * @var string
     */
    public $fileName;

    /**
     * @SWG\Property(description="The Saved Report file size in bytes")
     * @var int
     */
    public $size;

    /**
     * @SWG\Property(description="A MD5 checksum of the stored Saved Report file")
     * @var string
     */
    public $md5;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param SavedReportFactory $savedReportFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $mediaFactory, $savedReportFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;
        $this->savedReportFactory = $savedReportFactory;
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
                `schemaVersion` = :schemaVersion
           WHERE savedReportId = :savedReportId
        ';

        $params = [
            'saveAs' => $this->saveAs,
            'reportScheduleId' => $this->reportScheduleId,
            'generatedOn' => $this->generatedOn,
            'userId' => $this->userId,
            'schemaVersion' => $this->schemaVersion,
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

        // Library location
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');

        // delete file
        if (file_exists($libraryLocation . 'savedreport/'. $this->fileName)) {
            unlink($libraryLocation  . 'savedreport/'. $this->fileName);
        }
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