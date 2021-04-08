<?php

namespace Xibo\Entity;


use Xibo\Exception\XiboException;
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
     * @SWG\Property(description="Media Id")
     * @var int
     */
    public $mediaId;

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
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param SavedReportFactory $savedReportFactory
     */
    public function __construct($store, $log, $config, $mediaFactory, $savedReportFactory)
    {
        $this->setCommonDependencies($store, $log);

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
            INSERT INTO `saved_report` (`saveAs`, `reportName`, `reportScheduleId`, `mediaId`, `generatedOn`, `userId`)
              VALUES (:saveAs, :reportName, :reportScheduleId, :mediaId, :generatedOn, :userId)
        ', [
            'saveAs' => $this->saveAs,
            'reportName' => '',
            'reportScheduleId' => $this->reportScheduleId,
            'mediaId' => $this->mediaId,
            'generatedOn' => $this->generatedOn,
            'userId' => $this->userId
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
                `reportName` = :reportName,
                `reportScheduleId` = :reportScheduleId,
                `mediaId` = :mediaId,
                `generatedOn` = :generatedOn,
                `userId` = :userId
           WHERE savedReportId = :savedReportId
        ';

        $params = [
            'saveAs' => $this->saveAs,
            'reportName' => '',
            'reportScheduleId' => $this->reportScheduleId,
            'mediaId' => $this->mediaId,
            'generatedOn' => $this->generatedOn,
            'userId' => $this->userId,
            'savedReportId' => $this->savedReportId,

        ];

        $this->getStore()->update($sql, $params);
    }


    /**
     * Delete
     * @throws XiboException
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
     * @param array $options
     */
    public function save()
    {
        if ($this->savedReportId == null || $this->savedReportId == 0)
            $this->add();
        else
            $this->edit();
    }
}