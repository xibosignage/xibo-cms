<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Xibo\Entity\DayPart;
use Xibo\Entity\User;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DayPartFactory
 * @package Xibo\Factory
 */
class DayPartFactory extends BaseFactory
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
     * Create Empty
     * @return DayPart
     */
    public function createEmpty()
    {
        return new DayPart(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher()
        );
    }

    /**
     * Get DayPart by Id
     * @param $dayPartId
     * @return DayPart
     * @throws NotFoundException
     */
    public function getById($dayPartId)
    {
        $dayParts = $this->query(null, ['dayPartId' => $dayPartId, 'disableUserCheck' => 1]);

        if (count($dayParts) <= 0) {
            throw new NotFoundException();
        }

        return $dayParts[0];
    }

    /**
     * Get the Always DayPart
     * @return DayPart
     * @throws NotFoundException
     */
    public function getAlwaysDayPart()
    {
        $dayParts = $this->query(null, ['disableUserCheck' => 1, 'isAlways' => 1]);

        if (count($dayParts) <= 0) {
            throw new NotFoundException();
        }

        return $dayParts[0];
    }

    /**
     * Get the Custom DayPart
     * @return DayPart
     * @throws NotFoundException
     */
    public function getCustomDayPart()
    {
        $dayParts = $this->query(null, ['disableUserCheck' => 1, 'isCustom' => 1]);

        if (count($dayParts) <= 0) {
            throw new NotFoundException();
        }

        return $dayParts[0];
    }

    /**
     * Get all dayparts with the system entries (always and custom)
     * @param array $filter
     * @return DayPart[]
     * @throws NotFoundException
     */
    public function allWithSystem($filter = [])
    {
        $dayParts = $this->query(['isAlways DESC', 'isCustom DESC', 'name'], $filter);

        return $dayParts;
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return DayPart[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['userId' => $ownerId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['name'];
        }

        $params = [];
        $select = 'SELECT `daypart`.dayPartId, `name`, `description`, `isRetired`, `userId`, `startTime`, `endTime`, `exceptions`, `isCustom`, `isAlways` ';

        $body = ' FROM `daypart` ';

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('dayPartId') !== null) {
            $body .= ' AND `daypart`.dayPartId = :dayPartId ';
            $params['dayPartId'] = $sanitizedFilter->getInt('dayPartId');
        }

        if ($sanitizedFilter->getInt('isAlways') !== null) {
            $body .= ' AND `daypart`.isAlways = :isAlways ';
            $params['isAlways'] = $sanitizedFilter->getInt('isAlways');
        }

        if ($sanitizedFilter->getInt('isCustom') !== null) {
            $body .= ' AND `daypart`.isCustom = :isCustom ';
            $params['isCustom'] = $sanitizedFilter->getInt('isCustom');
        }

        if ($sanitizedFilter->getInt('isRetired', ['default'=> -1]) == 1)
            $body .= ' AND daypart.isRetired = 1 ';

        if ($sanitizedFilter->getInt('isRetired', ['default'=> -1]) == 0)
            $body .= ' AND daypart.isRetired = 0 ';

        if ($sanitizedFilter->getString('name') != null) {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $this->nameFilter('daypart', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `daypart`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DayPart', $body, $params, '`daypart`.dayPartId', '`daypart`.userId', $filterBy);

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $dayPart = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['isAlways', 'isCustom']
            ]);
            $dayPart->exceptions = json_decode($dayPart->exceptions, true);

            $entries[] = $dayPart;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}