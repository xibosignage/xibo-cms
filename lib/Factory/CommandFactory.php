<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
        return new Command($this->getStore(), $this->getLog());
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
     * @return array[Command]
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
     * @param array $sortOrder
     * @param array $filterBy
     * @return array
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
            `command`.validationString ';

        if ($sanitizedFilter->getInt('displayProfileId') !== null) {
            $select .= ', 
                :displayProfileId AS displayProfileId, 
                `lkcommanddisplayprofile`.commandString AS commandStringDisplayProfile, 
                `lkcommanddisplayprofile`.validationString AS validationStringDisplayProfile ';
        }

        $select .= " , (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityForGroup
                            AND objectId = command.commandId
                            AND view = 1
                        ) AS groupsWithPermissions ";
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
            $body .= ' AND `command`.command = :command ';
            $params['command'] = $sanitizedFilter->getString('command');
        }

        if ($sanitizedFilter->getString('code') != null) {
            $body .= ' AND `command`.code = :code ';
            $params['code'] = $sanitizedFilter->getString('code');
        }

        if ($sanitizedFilter->getString('type') != null) {
            $body .= ' AND (IFNULL(`command`.availableOn, \'\') = \'\' OR `command`.availableOn LIKE :type) ';
            $params['type'] = '%' . $sanitizedFilter->getString('type') . '%';
        }

        $this->viewPermissionSql('Xibo\Entity\Command', $body, $params, 'command.commandId', 'command.userId', $filterBy);

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start', $filterBy) !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Command($this->getStore(), $this->getLog()))->hydrate($row);
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