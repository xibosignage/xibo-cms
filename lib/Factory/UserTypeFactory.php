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


use Xibo\Entity\User;
use Xibo\Entity\UserType;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class UserTypeFactory
 * @package Xibo\Factory
 */
class UserTypeFactory extends BaseFactory
{
    /**
     * @return UserType
     */
    public function createEmpty()
    {
        return new UserType($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @return User[]
     * @throws NotFoundException
     */
    public function getAllRoles()
    {
        return $this->query();
    }

    /**
     * @return User[]
     * @throws NotFoundException
     */
    public function getNonAdminRoles()
    {
        return $this->query(null, ['userOnly' => 1]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     * @throws NotFoundException
     */
    public function query($sortOrder = ['userType'], $filterBy = [])
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        try {
            $sql = '
            SELECT userTypeId, userType 
              FROM `usertype`
             WHERE 1 = 1
            ';

            if ($sanitizedFilter->getInt('userOnly') !== null) {
                $sql .= ' AND `userTypeId` = 3 ';
            }

            if ($sanitizedFilter->getString('userType') !== null) {
                $sql .= ' AND userType = :userType ';
                $params['userType'] = $sanitizedFilter->getString('userType');
            }

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $entries[] = $this->createEmpty()->hydrate($row);
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}