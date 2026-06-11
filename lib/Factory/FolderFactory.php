<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Xibo\Entity\Folder;
use Xibo\Entity\User;
use Xibo\Helper\ByteFormatter;
use Xibo\Support\Exception\NotFoundException;

class FolderFactory extends BaseFactory
{
    public function __construct(
        private readonly PermissionFactory $permissionFactory,
        User $user,
        UserFactory $userFactory,
    ) {
        $this->setAclDependencies($user, $userFactory);
    }

    public function createEmpty(): Folder
    {
        return new Folder(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this,
            $this->permissionFactory
        );
    }

    /**
     * @throws NotFoundException
     */
    public function getById(int $folderId, int $disableUserCheck = 1): Folder
    {
        $folder = $this->query(null, ['folderId' => $folderId, 'disableUserCheck' => $disableUserCheck]);

        if (count($folder) <= 0) {
            throw new NotFoundException(__('Folder not found'));
        }

        return $folder[0];
    }

    /**
     * @throws NotFoundException
     */
    public function getByParentId(int $folderId): Folder
    {
        $folder = $this->query(null, ['parentId' => $folderId]);

        if (count($folder) <= 0) {
            throw new NotFoundException(__('Folder not found'));
        }

        return $folder[0];
    }

    /**
     * @return Folder[]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $select = 'SELECT `folderId`,
            `folderName`,
            `folderId` AS id,
            IF(`isRoot`=1, \'Root Folder\', `folderName`) AS text,
            `parentId`,
            `isRoot`,
            `children`,
            `permissionsFolderId`,
            `createdDt`,
            `modifiedDt`
        ';

        $body = '
          FROM `folder`
         WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= ' AND folder.folderId = :folderId ';
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        if ($sanitizedFilter->getInt('parentId') !== null) {
            $body .= ' AND folder.parentId = :parentId ';
            $params['parentId'] = $sanitizedFilter->getInt('parentId');
        }

        if ($sanitizedFilter->getString('folderName') != null) {
            $terms = explode(',', $sanitizedFilter->getString('folderName'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'folder',
                'folderName',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getInt('isRoot') !== null) {
            $body .= ' AND folder.isRoot = :isRoot ';
            $params['isRoot'] = $sanitizedFilter->getInt('isRoot');
        }

        // for the "grid" ie tree view, we need the root folder to keep the tree structure
        if ($sanitizedFilter->getInt('includeRoot') === 1) {
            $body .= 'OR folder.isRoot = 1';
        }

        // get the exact match for the search functionality
        if ($sanitizedFilter->getInt('exactFolderName') === 1) {
            $body .= ' AND folder.folderName = :exactFolderName ';
            $params['exactFolderName'] = $sanitizedFilter->getString('folderName');
        }

        // View Permissions (home folder included in here)
        $this->viewPermissionSql(
            'Xibo\Entity\Folder',
            $body,
            $params,
            '`folder`.folderId',
            null,
            $filterBy,
            'folder.permissionsFolderId'
        );

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        if ($filterBy !== null &&
            $sanitizedFilter->getInt('start') !== null &&
            $sanitizedFilter->getInt('length') !== null
        ) {
            $limit .= ' LIMIT ' . $sanitizedFilter->getInt('start') .
                ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isRoot', 'homeFolderCount']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    public function decorateWithHomeFolderCount(Folder $folder): void
    {
        $results = $this->getStore()->select('
            SELECT COUNT(*) AS cnt
              FROM `user`
             WHERE `user`.homeFolderId = :folderId
                AND `user`.retired = 0
        ', [
            'folderId' => $folder->id,
        ]);

        $folder->setUnmatchedProperty('homeFolderCount', intval($results[0]['cnt'] ?? 0));
    }

    public function decorateWithSharing(Folder $folder): void
    {
        $results = $this->getStore()->select('
            SELECT `group`.group,
                   `group`.isUserSpecific,
                   `permission`.`view`,
                   `permission`.`edit`,
                   `permission`.`delete`
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
             WHERE entity = :permissionEntity
                AND objectId = :folderId
                AND (`view` = 1 OR `edit` = 1 OR `delete` = 1)
            ORDER BY `group`.isUserSpecific
        ', [
            'folderId' => $folder->id,
            'permissionEntity' => 'Xibo\Entity\Folder',
        ]);

        $sharing = [];
        foreach ($results as $row) {
            $sharing[] = [
                'name'    => $row['group'],
                'isGroup' => intval($row['isUserSpecific']) !== 1,
                'view'    => intval($row['view']),
                'edit'    => intval($row['edit']),
                'delete'  => intval($row['delete']),
            ];
        }
        $folder->setUnmatchedProperty('sharing', $sharing);
    }

    public function decorateWithUsage(Folder $folder): void
    {
        $usage = [];

        $results = $this->getStore()->select('
            SELECT \'Library\' AS `type`,
                COUNT(mediaId) AS cnt,
                SUM(fileSize) AS `size`
              FROM media
             WHERE folderId = :folderId
                AND moduleSystemFile = 0
            UNION ALL
            SELECT IF (campaign.isLayoutSpecific = 1, \'Layouts\', \'Campaigns\') AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM campaign
             WHERE campaign.folderId = :folderId
            GROUP BY campaign.isLayoutSpecific
            UNION ALL
            SELECT IF (displaygroup.isDisplaySpecific = 1, \'Displays\', \'Display Groups\') AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM displaygroup
             WHERE displaygroup.folderId = :folderId
            GROUP BY displaygroup.isDisplaySpecific
            UNION ALL
            SELECT \'DataSets\' AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM dataset
             WHERE dataset.folderId = :folderId
            UNION ALL
            SELECT \'Playlists\' AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM playlist
             WHERE playlist.folderId = :folderId
                AND IFNULL(playlist.regionId, 0) = 0
            UNION ALL
            SELECT \'Menu Boards\' AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM menu_board
             WHERE menu_board.folderId = :folderId
            UNION ALL
            SELECT \'Sync Groups\' AS `type`,
                COUNT(*) AS cnt,
                0 AS `size`
              FROM syncgroup
             WHERE syncgroup.folderId = :folderId
            ORDER BY 1
        ', [
            'folderId' => $folder->id,
        ]);

        foreach ($results as $row) {
            $count = intval($row['cnt'] ?? 0);
            if ($count > 0) {
                $usage[] = [
                    'type' => __($row['type']),
                    'count' => $count,
                    'sizeBytes' => intval($row['size'] ?? 0),
                    'size' => ByteFormatter::format(intval($row['size'] ?? 0)),
                ];
            }
        }

        $folder->setUnmatchedProperty('usage', $usage);
    }
}
