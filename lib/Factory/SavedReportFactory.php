<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Factory;

use Xibo\Entity\SavedReport;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class SavedReportFactory
 * @package Xibo\Factory
 */
class SavedReportFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $mediaFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;

    }

    /**
     * Create Empty
     * @return SavedReport
     */
    public function createEmpty()
    {
        return new SavedReport($this->getStore(), $this->getLog(), $this->config, $this->mediaFactory, $this);
    }

    /**
     * Populate Saved Report table
     * @param string $saveAs
     * @param int $reportScheduleId
     * @param int $mediaId
     * @param int $generatedOn
     * @param int $userId
     * @return SavedReport
     */
    public function create($saveAs, $reportScheduleId, $mediaId, $generatedOn, $userId)
    {
        $savedReport = $this->createEmpty();
        $savedReport->saveAs = $saveAs;
        $savedReport->reportScheduleId = $reportScheduleId;
        $savedReport->mediaId = $mediaId;
        $savedReport->generatedOn = $generatedOn;
        $savedReport->userId = $userId;
        $savedReport->save();

        return $savedReport;
    }

    /**
     * Get by Version Id
     * @param int $savedReportId
     * @return SavedReport
     * @throws NotFoundException
     */
    public function getById($savedReportId)
    {
        $savedReports = $this->query(null, array('disableUserCheck' => 1, 'savedReportId' => $savedReportId));

        if (count($savedReports) <= 0)
            throw new NotFoundException(__('Cannot find saved report'));

        return $savedReports[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return SavedReport[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['generatedOn DESC'];

        $params = [];
        $entries = [];

        $select = '
            SELECT  
               saved_report.reportScheduleId,
               saved_report.savedReportId,
               saved_report.saveAs,
               saved_report.userId,
               reportschedule.name AS reportScheduleName,
               reportschedule.reportName,
               saved_report.generatedOn,
               media.mediaId,
               media.originalFileName,
               media.storedAs,
               `user`.UserName AS owner
            ';

        $body = ' FROM saved_report 
                    INNER JOIN media
                    ON saved_report.mediaId = media.mediaId
                    INNER JOIN reportschedule
                    ON  saved_report.reportScheduleId = reportschedule.reportScheduleId
        ';

        // Media might be linked to the system user (userId 0)
        $body .= "   LEFT OUTER JOIN `user` ON `user`.userId = `saved_report`.userId ";

        $body .= " WHERE 1 = 1 ";

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\SavedReport', $body, $params, '`saved_report`.savedReportId', '`saved_report`.userId', $filterBy);

        // Like
        if ($this->getSanitizer()->getString('saveAs', $filterBy) != '') {
            $terms = explode(',', $this->getSanitizer()->getString('saveAs', $filterBy));
            $this->nameFilter('saved_report', 'saveAs', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getInt('savedReportId', -1, $filterBy) != -1) {
            $body .= " AND saved_report.savedReportId = :savedReportId ";
            $params['savedReportId'] = $this->getSanitizer()->getInt('savedReportId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('reportScheduleId', $filterBy) != '') {
            $body .= " AND saved_report.reportScheduleId = :reportScheduleId ";
            $params['reportScheduleId'] = $this->getSanitizer()->getInt('reportScheduleId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('generatedOn', $filterBy) != '') {
            $body .= " AND saved_report.generatedOn = :generatedOn ";
            $params['generatedOn'] = $this->getSanitizer()->getInt('generatedOn', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `saved_report`.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        // Report name
        if ($this->getSanitizer()->getString('reportName', $filterBy) != '') {
            $body .= " AND reportschedule.reportName = :reportName ";
            $params['reportName'] = $this->getSanitizer()->getString('reportName',  $filterBy);
        }

        // User Group filter
        if ($this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy) != 0) {
            $body .= ' AND `saved_report`.userId IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy);
        }

        // by media ID
        if ($this->getSanitizer()->getInt('mediaId', -1, $filterBy) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        // Owner filter
        if ($this->getSanitizer()->getInt('userId', 0, $filterBy) != 0) {
            $body .= " AND `saved_report`.userid = :userId ";
            $params['userId'] = $this->getSanitizer()->getInt('userId', 0, $filterBy);
        }

        if ( $this->getSanitizer()->getCheckbox('onlyMyReport') == 1) {
            $body .= ' AND `saved_report`.userId = :currentUserId ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'mediaId', 'reportScheduleId', 'generatedOn'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;


    }


}