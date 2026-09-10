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

trait GroupsWithPermissionsTrait
{
    /**
     * Batch-load the names of groups/users with view permission for a set of entity IDs, and decorate
     * each entry with a comma-separated `groupsWithPermissions` (legacy display format) and a
     * `groupsWithPermissionsList` array (safe for group names containing commas).
     *
     * Runs a single query for the whole batch rather than a correlated subquery per row, so group names
     * are never joined into a single string and then re-split — each field is built directly from the
     * raw per-group rows, so no delimiter character occurring in a group name can corrupt either field.
     *
     * @param string $entityClass Fully-qualified entity class name, as stored in `permission`.entity
     * @param string $idColumn Property name on each entry holding its numeric object ID
     * @param int[] $entityIds
     * @param \Xibo\Entity\EntityTrait[] $entries
     * @param bool $viewOnly Restrict to permission rows with view = 1 (matches every existing
     *   per-factory query except DataSetFactory, whose original subquery had no such filter)
     */
    public function decorateWithGroupsWithPermissions(
        string $entityClass,
        string $idColumn,
        array $entityIds,
        array $entries,
        bool $viewOnly = true
    ): void {
        if (count($entityIds) <= 0) {
            return;
        }

        // Force every entity ID to int before concatenating into the IN-list.
        $entityIds = array_map('intval', $entityIds);

        $sql = 'SELECT DISTINCT `permission`.objectId, `group`.`group` AS groupName
                  FROM `permission`
                    INNER JOIN `permissionentity`
                    ON `permissionentity`.entityId = `permission`.entityId
                    INNER JOIN `group`
                    ON `group`.groupId = `permission`.groupId
                 WHERE entity = :entity
                    ' . ($viewOnly ? ' AND view = 1 ' : '') . '
                    AND `permission`.objectId IN (' . implode(',', $entityIds) . ')';

        $namesByObjectId = [];
        foreach ($this->getStore()->select($sql, ['entity' => $entityClass]) as $row) {
            $namesByObjectId[intval($row['objectId'])][] = $row['groupName'];
        }

        foreach ($entries as $entry) {
            $names = $namesByObjectId[$entry->$idColumn] ?? null;
            $entry->groupsWithPermissions = $names !== null ? implode(',', $names) : null;
            $entry->groupsWithPermissionsList = $names ?? [];
        }
    }

    /**
     * Build the raw correlated-subquery SQL used only as a `BaseFactory::buildSortQuery()` custom column,
     * so `ORDER BY` can sort by `groupsWithPermissions`/`groupsWithPermissionsList` even though neither is a
     * real SELECT-list column any more (their display values are built by
     * `decorateWithGroupsWithPermissions()` above, in PHP, from a separate batch query)
     *
     * @param string $entityClass Fully-qualified entity class name, as stored in `permission`.entity
     * @param string $objectIdExpr Raw SQL expression correlating to the outer query's row, e.g.
     *   `media.mediaId` or `campaign.CampaignID` — always a hardcoded literal from factory code
     * @param bool $viewOnly Restrict to permission rows with view = 1 (see decorateWithGroupsWithPermissions)
     * @param string|null $separator GROUP_CONCAT separator; null uses MySQL's default `,`
     */
    private function groupsWithPermissionsSortSql(
        string $entityClass,
        string $objectIdExpr,
        bool $viewOnly = true,
        ?string $separator = null
    ): string {
        $separatorSql = $separator !== null ? ' SEPARATOR \'' . $separator . '\'' : '';

        return '(SELECT GROUP_CONCAT(DISTINCT `group`.group' . $separatorSql . ')
                FROM `permission`
                    INNER JOIN `permissionentity` ON `permissionentity`.entityId = permission.entityId
                    INNER JOIN `group` ON `group`.groupId = `permission`.groupId
                    WHERE entity = \'' . addslashes($entityClass) . '\'
                        AND objectId = ' . $objectIdExpr .
                        ($viewOnly ? ' AND view = 1' : '') . ')';
    }
}
