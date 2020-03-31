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
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

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
     * @param SanitizerService $sanitizerService
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
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['generatedOn DESC'];
        }
        
        $sanitizedFilter = $this->getSanitizer($filterBy);
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
        if ($sanitizedFilter->getString('saveAs') != '') {
            $terms = explode(',', $sanitizedFilter->getString('saveAs'));
            $this->nameFilter('saved_report', 'saveAs', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('savedReportId', ['default' => -1]) != -1) {
            $body .= " AND saved_report.savedReportId = :savedReportId ";
            $params['savedReportId'] = $sanitizedFilter->getInt('savedReportId');
        }

        if ($sanitizedFilter->getInt('reportScheduleId') != '') {
            $body .= " AND saved_report.reportScheduleId = :reportScheduleId ";
            $params['reportScheduleId'] = $sanitizedFilter->getInt('reportScheduleId');
        }

        if ($sanitizedFilter->getInt('generatedOn') != '') {
            $body .= " AND saved_report.generatedOn = :generatedOn ";
            $params['generatedOn'] = $sanitizedFilter->getInt('generatedOn');
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `saved_report`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // Report name
        if ($sanitizedFilter->getString('reportName') != '') {
            $body .= " AND reportschedule.reportName = :reportName ";
            $params['reportName'] = $sanitizedFilter->getString('reportName');
        }

        // User Group filter
        if ($sanitizedFilter->getInt('ownerUserGroupId', ['default' => 0]) != 0) {
            $body .= ' AND `saved_report`.userId IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $sanitizedFilter->getInt('ownerUserGroupId',  ['default' => 0]);
        }

        // by media ID
        if ($sanitizedFilter->getInt('mediaId',  ['default' => -1]) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        }

        // Owner filter
        if ($sanitizedFilter->getInt('userId',  ['default' => 0]) != 0) {
            $body .= " AND `saved_report`.userid = :userId ";
            $params['userId'] = $sanitizedFilter->getInt('userId',  ['default' => 0]);
        }

        if ( $sanitizedFilter->getCheckbox('onlyMyReport') == 1) {
            $body .= ' AND `saved_report`.userId = :currentUserId ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length',  ['default' => 10]);
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