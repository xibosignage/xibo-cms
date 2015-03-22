<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (UserFactory.php) is part of Xibo.
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


use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class UserFactory
{
    /**
     * Get User by ID
     * @param int $userId
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public static function getById($userId)
    {
        $users = UserFactory::query(null, array('userId' => $userId));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Get User by Name
     * @param string $userName
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public static function getByName($userName)
    {
        $users = UserFactory::query(null, array('userName' => $userName));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Query for users
     * @param array[mixed] $sortOrder
     * @param array[mixed] $filterBy
     * @return array[User]
     */
    public static function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        // Default sort order
        if (count($sortOrder) <= 0)
            $sortOrder = array('username');

        $params = array();
        $SQL  = 'SELECT userId, userName, userTypeId, loggedIn, email, homePage, lastAccessed, newUserWizard, retired, CSPRNG, UserPassword ';
        $SQL .= '  FROM `user` ';
        $SQL .= ' WHERE 1 = 1 ';

        // User Id Provided?
        if (\Kit::GetParam('userId', $filterBy, _INT) != 0) {
            $SQL .= " AND user.userId = :userId ";
            $params['userId'] = \Xibo\Helper\Sanitize::getInt('userId', $filterBy);
        }

        // User Type Provided
        if (\Kit::GetParam('userTypeId', $filterBy, _INT) != 0) {
            $SQL .= " AND user.userTypeId = :userTypeId ";
            $params['userTypeId'] = \Xibo\Helper\Sanitize::getInt('userTypeId', $filterBy);
        }

        // User Name Provided
        if (\Kit::GetParam('userName', $filterBy, _STRING) != 0) {
            $SQL .= " AND user.userName LIKE :userName ";
            $params['userName'] = '%' . \Kit::GetParam('userName', $filterBy, _STRING) . '%';
        }

        // Groups Provided
        $groups = \Kit::GetParam('groupIds', $filterBy, _ARRAY_INT);

        if (count($groups) > 0) {
            $SQL .= " AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupid IN (" . implode($groups, ',') . ")) ";
        }

        // Retired users?
        if (\Kit::GetParam('retired', $filterBy, _INT) != -1) {
            $SQL .= " AND user.retired = :retired ";
            $params['retired'] = \Xibo\Helper\Sanitize::getInt('retired', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($SQL, $params);

        foreach (PDOConnect::select($SQL, $params) as $row) {
            $user = new User();
            $user->userId = \Kit::ValidateParam($row['userId'], _INT);
            $user->userName = \Kit::ValidateParam($row['userName'], _STRING);
            $user->userTypeId = \Kit::ValidateParam($row['userTypeId'], _INT);
            $user->loggedIn = \Kit::ValidateParam($row['loggedIn'], _INT);
            $user->email = \Kit::ValidateParam($row['email'], _STRING);
            $user->homePage = \Kit::ValidateParam($row['homePage'], _STRING);
            $user->lastAccessed = \Kit::ValidateParam($row['lastAccessed'], _INT);
            $user->newUserWizard = \Kit::ValidateParam($row['newUserWizard'], _INT);
            $user->retired = \Kit::ValidateParam($row['retired'], _INT);

            // Set the user credentials (set privately)
            $user->setPassword(\Kit::ValidateParam($row['UserPassword'], _STRING), \Kit::ValidateParam($row['CSPRNG'], _INT));

            $entries[] = $user;
        }

        return $entries;
    }
}