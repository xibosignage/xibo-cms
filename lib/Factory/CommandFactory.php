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

use Xibo\Entity\Command;
use Xibo\Entity\User;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CommandFactory
 * @package Xibo\Factory
 */
class CommandFactory extends BaseFactory
{
    public function __construct(User $user, UserFactory $userFactory)
    {
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * @return Command
     */
    public function createEmpty(): Command
    {
        return new Command($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param int $commandId
     * @return Command
     * @throws NotFoundException
     */
    public function getById(int $commandId): Command
    {
        $commands = $this->query(null, ['commandId' => $commandId]);

        if (count($commands) <= 0) {
            throw new NotFoundException();
        }

        return $commands[0];
    }

    /**
     * @param int $displayProfileId
     * @param string $type
     * @return Command[]
     * @throws NotFoundException
     */
    public function getByDisplayProfileId(int $displayProfileId, string $type): array
    {
        return $this->query(null, [
            'displayProfileId' => $displayProfileId,
            'type' => $type
        ]);
    }

    /**
     * @param int $ownerId
     * @return Command[]
     * @throws NotFoundException
     */
    public function getByOwnerId(int $ownerId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return Command[]
     * @throws NotFoundException
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];

        $params = [];
        $select = 'SELECT `command`.commandId,
            `command`.command,
            `command`.code,
            `command`.description,
            `command`.userId,
            `command`.availableOn,
            `command`.commandString,
            `command`.validationString,
            `command`.createAlertOn
        ';

        if ($sanitizedFilter->getInt('displayProfileId') !== null) {
            $select .= ',
                :displayProfileId AS displayProfileId,
                `lkcommanddisplayprofile`.commandString AS commandStringDisplayProfile,
                `lkcommanddisplayprofile`.validationString AS validationStringDisplayProfile,
                `lkcommanddisplayprofile`.createAlertOn AS createAlertOnDisplayProfile ';
        }

        $select .= ' , (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityForGroup
                            AND objectId = command.commandId
                            AND view = 1
                        ) AS groupsWithPermissions ';
        $params['permissionEntityForGroup'] = 'Xibo\\Entity\\Command';

        $body = ' FROM `command` ';

        if ($sanitizedFilter->getInt('displayProfileId') !== null) {
            $body .= '
                LEFT OUTER JOIN `lkcommanddisplayprofile`
                ON `lkcommanddisplayprofile`.commandId = `command`.commandId
                    AND `lkcommanddisplayprofile`.displayProfileId = :displayProfileId
            ';

            $params['displayProfileId'] = $sanitizedFilter->getInt('displayProfileId');
        }

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('commandId') !== null) {
            $body .= ' AND `command`.commandId = :commandId ';
            $params['commandId'] = $sanitizedFilter->getInt('commandId');
        }

        if ($sanitizedFilter->getString('command') != null) {
            $terms = explode(',', $sanitizedFilter->getString('command'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'command',
                'command',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getString('code') != null) {
            $terms = explode(',', $sanitizedFilter->getString('code'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorCode', ['default' => 'OR']);
            $this->nameFilter(
                'command',
                'code',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForCode') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getString('type') != null) {
            $body .= ' AND (IFNULL(`command`.availableOn, \'\') = \'\' OR `command`.availableOn LIKE :type) ';
            $params['type'] = '%' . $sanitizedFilter->getString('type') . '%';
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `command`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        $this->viewPermissionSql(
            'Xibo\Entity\Command',
            $body,
            $params,
            'command.commandId',
            'command.userId',
            $filterBy
        );

        $allowedColumns = [
            'commandId',
            'command',
            'code',
            'description',
            'availableOn',
            'createAlertOn',
            'groupsWithPermissions',
        ];
        $sortOrder = $this->buildSortQuery($sortOrder, $allowedColumns, [], ['command ASC'], 'commandId');
        $order = empty($sortOrder) ? '' : ' ORDER BY ' . implode(', ', $sortOrder);

        $limit = '';
        if ($sanitizedFilter->hasParam('start') && $sanitizedFilter->hasParam('length')) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0])
                . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
