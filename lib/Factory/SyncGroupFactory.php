<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Xibo\Entity\Display;
use Xibo\Entity\SyncGroup;
use Xibo\Entity\User;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SyncGroupFactory
 * @package Xibo\Factory
 */
class SyncGroupFactory extends BaseFactory
{
    private DisplayFactory $displayFactory;
    private PermissionFactory $permissionFactory;
    private ScheduleFactory $scheduleFactory;

    public function __construct(
        User $user,
        UserFactory $userFactory,
        PermissionFactory $permissionFactory,
        DisplayFactory $displayFactory,
        ScheduleFactory $scheduleFactory
    ) {
        $this->setAclDependencies($user, $userFactory);
        $this->displayFactory = $displayFactory;
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
    }
    
    /**
     * @return SyncGroup
     */
    public function createEmpty(): SyncGroup
    {
        return new SyncGroup(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this,
            $this->displayFactory,
            $this->permissionFactory,
            $this->scheduleFactory
        );
    }

    /**
     * @param int $id
     * @return SyncGroup
     * @throws NotFoundException
     */
    public function getById(int $id): SyncGroup
    {
        $syncGroups = $this->query(null, ['syncGroupId' => $id]);

        if (count($syncGroups) <= 0) {
            Throw new NotFoundException(__('Sync Group not found'));
        }

        return $syncGroups[0];
    }

    /**
     * @param int $userId
     * @return SyncGroup[]
     */
    public function getByOwnerId(int $userId): array
    {
        return $this->query(null, ['ownerId' => $userId]);
    }

    /**
     * @param int $folderId
     * @return SyncGroup[]
     */
    public function getByFolderId(int $folderId): array
    {
        return $this->query(null, ['folderId' => $folderId]);
    }

    /**
     * @param int $id
     * @return Display
     * @throws NotFoundException
     */
    public function getLeadDisplay(int $id): \Xibo\Entity\Display
    {
        return $this->displayFactory->getById($id);
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return SyncGroup[]
     */
    public function query($sortOrder = null, $filterBy = []): array
    {
        $parsedBody = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['name'];
        }

        $entries = [];
        $params = [];

        $select = 'SELECT 
                `syncgroup`.syncGroupId,
                `syncgroup`.name,
                `syncgroup`.createdDt,
                `syncgroup`.modifiedDt,
                `syncgroup`.ownerId,
                `syncgroup`.modifiedBy,
                `syncgroup`.syncPublisherPort,
                `syncgroup`.syncSwitchDelay,
                `syncgroup`.syncVideoPauseDelay,
                `syncgroup`.leadDisplayId,
                `syncgroup`.folderId,
                `syncgroup`.permissionsFolderId,
                `user`.userName as owner,
                modifiedBy.userName AS modifiedByName,
                (
                    SELECT GROUP_CONCAT(DISTINCT `group`.group)
                        FROM `permission`
                        INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                        INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                        WHERE entity = :entity
                            AND objectId = `syncgroup`.syncGroupId
                            AND view = 1
                ) AS groupsWithPermissions
        ';

        $params['entity'] = 'Xibo\\Entity\\SyncGroup';

        $body = '
              FROM `syncgroup`
              INNER JOIN `user`
              ON `user`.userId = `syncgroup`.ownerId
              LEFT OUTER JOIN `user` modifiedBy
              ON modifiedBy.userId = `syncgroup`.modifiedBy 
              WHERE 1 = 1
        ';

        if ($parsedBody->getInt('syncGroupId') !== null) {
            $body .= ' AND `syncgroup`.syncGroupId = :syncGroupId ';
            $params['syncGroupId'] = $parsedBody->getInt('syncGroupId');
        }

        if ($parsedBody->getInt('ownerId') !== null) {
            $body .= ' AND `syncgroup`.ownerId = :ownerId ';
            $params['ownerId'] = $parsedBody->getInt('ownerId');
        }

        // Filter by SyncGroup Name?
        if ($parsedBody->getString('name') != null) {
            $terms = explode(',', $parsedBody->getString('name'));
            $logicalOperator = $parsedBody->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'syncgroup',
                'name',
                $terms,
                $body,
                $params,
                ($parsedBody->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($parsedBody->getInt('folderId') !== null) {
            $body .= ' AND `syncgroup`.folderId = :folderId ';
            $params['folderId'] = $parsedBody->getInt('folderId');
        }

        if ($parsedBody->getInt('leadDisplayId') !== null) {
            $body .= ' AND `syncgroup`.leadDisplayId = :leadDisplayId ';
            $params['leadDisplayId'] = $parsedBody->getInt('leadDisplayId');
        }

        // View Permissions
        $this->viewPermissionSql(
            'Xibo\Entity\SyncGroup',
            $body,
            $params,
            '`syncgroup`.syncGroupId',
            '`syncgroup`.ownerId',
            $filterBy,
            '`syncgroup`.permissionsFolderId'
        );

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($parsedBody->hasParam('start') && $parsedBody->hasParam('length')) {
            $limit = ' LIMIT ' . $parsedBody->getInt('start', ['default' => 0])
                . ', ' . $parsedBody->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
