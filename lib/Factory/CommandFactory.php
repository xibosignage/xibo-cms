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

use Xibo\Entity\Command;
use Xibo\Entity\User;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CommandFactory
 * @package Xibo\Factory
 */
class CommandFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($user, $userFactory)
    {
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * Create Command
     * @return Command
     */
    public function create()
    {
        return new Command($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get by Id
     * @param $commandId
     * @return Command
     * @throws NotFoundException
     */
    public function getById($commandId)
    {
        $commands = $this->query(null, ['commandId' => $commandId]);

        if (count($commands) <= 0) {
            throw new NotFoundException();
        }

        return $commands[0];
    }

    /**
     * Get by Display Profile Id
     * @param int $displayProfileId
     * @param string $type
     * @return Command[]
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByDisplayProfileId($displayProfileId, $type)
    {
        return $this->query(null, [
            'displayProfileId' => $displayProfileId,
            'type' => $type
        ]);
    }

    /**
     * @param $ownerId
     * @return Command[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Command[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];

        if ($sortOrder == null) {
            $sortOrder = ['command'];
        }

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

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start', $filterBy) !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Command($this->getStore(), $this->getLog(), $this->getDispatcher()))->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
