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

namespace Xibo\Factory;

use Xibo\Entity\SavedReport;
use Xibo\Entity\User;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SavedReportFactory
 * @package Xibo\Factory
 */
class SavedReportFactory extends BaseFactory
{
    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param MediaFactory $mediaFactory
     * @param LibraryStorageRegistryInterface $libraryStorageRegistry
     */
    public function __construct(
        $user,
        $userFactory,
        $mediaFactory,
        private readonly LibraryStorageRegistryInterface $libraryStorageRegistry
    ) {
        $this->setAclDependencies($user, $userFactory);

        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Create Empty
     * @return SavedReport
     */
    public function createEmpty(): SavedReport
    {
        return new SavedReport(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->mediaFactory,
            $this,
            $this->libraryStorageRegistry
        );
    }

    /**
     * Populate Saved Report table
     * @param string $saveAs
     * @param int $reportScheduleId
     * @param int $generatedOn
     * @param int $userId
     * @param $fileName
     * @param $size
     * @param $md5
     * @return SavedReport
     */
    public function create(
        $saveAs,
        $reportScheduleId,
        $generatedOn,
        $userId,
        $fileName,
        $size,
        $md5): SavedReport
    {
        $savedReport = $this->createEmpty();
        $savedReport->saveAs = $saveAs;
        $savedReport->reportScheduleId = $reportScheduleId;
        $savedReport->generatedOn = $generatedOn;
        $savedReport->userId = $userId;
        $savedReport->fileName = $fileName;
        $savedReport->size = $size;
        $savedReport->md5 = $md5;
        $savedReport->save();

        return $savedReport;
    }

    /**
     * Get by Version ID
     * @param int $savedReportId
     * @return SavedReport
     * @throws NotFoundException
     */
    public function getById(int $savedReportId): SavedReport
    {
        $savedReports = $this->query(null, array('disableUserCheck' => 1, 'savedReportId' => $savedReportId));

        if (count($savedReports) <= 0) {
            throw new NotFoundException(__('Cannot find saved report'));
        }

        return $savedReports[0];
    }

    /**
     * @param $ownerId
     * @return SavedReport[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return SavedReport[]
     * @throws NotFoundException
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $params = [];
        $entries = [];

        $select = '
            SELECT  
               saved_report.reportScheduleId,
               saved_report.savedReportId,
               saved_report.saveAs,
               saved_report.userId,
               saved_report.schemaVersion,
               saved_report.fileName,
               saved_report.storageEngine,
               saved_report.size,
               saved_report.md5,
               reportschedule.name AS reportScheduleName,
               reportschedule.reportName,
               saved_report.generatedOn,
               `user`.UserName AS owner
            ';

        $body = ' FROM saved_report                    
                    INNER JOIN reportschedule
                    ON  saved_report.reportScheduleId = reportschedule.reportScheduleId
        ';

        // Media might be linked to the system user (userId 0)
        $body .= "   LEFT OUTER JOIN `user` ON `user`.userId = `saved_report`.userId ";

        $body .= " WHERE 1 = 1 ";

        // Like
        if ($sanitizedFilter->getString('saveAs') != '') {
            $terms = explode(',', $sanitizedFilter->getString('saveAs'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'saved_report',
                'saveAs',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
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

        // Owner filter
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
            $body .= ' AND `saved_report`.userId IN (SELECT DISTINCT userId FROM `lkusergroup` 
                WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $sanitizedFilter->getInt('ownerUserGroupId', ['default' => 0]);
        }

        if ($sanitizedFilter->getCheckbox('onlyMyReport') == 1) {
            $body .= ' AND `saved_report`.userId = :currentUserId ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // View Permissions
        $this->viewPermissionSql(
            'Xibo\Entity\SavedReport',
            $body,
            $params,
            '`saved_report`.savedReportId',
            '`saved_report`.userId',
            $filterBy
        );

        // Sorting?
        $allowedColumns = [
            'reportScheduleName',
            'saveAs',
            'reportName',
            'generatedOn',
            'owner',
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['generatedOn DESC'],
            uniqueColumn: 'savedReportId'
        );

        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null &&
            $sanitizedFilter->getInt('length') !== null
        ) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' .
                $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'reportScheduleId', 'generatedOn', 'schemaVersion', 'size'
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

    /**
     * Calculate the sum of the size column for all rows and count the total number of rows in the table.
     */
    public function getSizeAndCount()
    {
        return $this->getStore()->select('SELECT IFNULL(SUM(size), 0) AS SumSize, COUNT(*) 
            AS totalCount FROM `saved_report`', [])[0];
    }
}
