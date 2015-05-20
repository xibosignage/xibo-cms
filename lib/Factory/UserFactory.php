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
use Xibo\Helper\Sanitize;
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
        $SQL  = 'SELECT `user`.userId, userName, userTypeId, loggedIn, email, homePage, lastAccessed, newUserWizard, retired, CSPRNG, UserPassword, group.groupId, group.group ';
        $SQL .= '  FROM `user` ';
        $SQL .= '   INNER JOIN lkusergroup ON lkusergroup.userId = user.userId ';
        $SQL .= '   INNER JOIN `group` ON group.groupId = lkusergroup.groupId AND isUserSpecific = 1 ';
        $SQL .= ' WHERE 1 = 1 ';

        // User Id Provided?
        if (Sanitize::getInt('userId', $filterBy) != 0) {
            $SQL .= " AND user.userId = :userId ";
            $params['userId'] = Sanitize::getInt('userId', $filterBy);
        }

        // User Type Provided
        if (Sanitize::getInt('userTypeId', $filterBy) != 0) {
            $SQL .= " AND user.userTypeId = :userTypeId ";
            $params['userTypeId'] = Sanitize::getInt('userTypeId', $filterBy);
        }

        // User Name Provided
        if (Sanitize::getString('userName', $filterBy) != '') {
            $SQL .= " AND user.userName = :userName ";
            $params['userName'] = Sanitize::getString('userName', $filterBy);
        }

        // Groups Provided
        $groups = \Kit::GetParam('groupIds', $filterBy, _ARRAY_INT);

        if (count($groups) > 0) {
            $SQL .= " AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupid IN (" . implode($groups, ',') . ")) ";
        }

        // Retired users?
        if (Sanitize::getInt('retired', $filterBy) != -1) {
            $SQL .= " AND user.retired = :retired ";
            $params['retired'] = Sanitize::getInt('retired', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($SQL, $params);

        foreach (PDOConnect::select($SQL, $params) as $row) {
            $user = new User();
            $user->userId = Sanitize::int($row['userId']);
            $user->userName = Sanitize::string($row['userName']);
            $user->userTypeId = Sanitize::int($row['userTypeId']);
            $user->loggedIn = Sanitize::int($row['loggedIn']);
            $user->email = Sanitize::string($row['email']);
            $user->homePage = Sanitize::string($row['homePage']);
            $user->lastAccessed = Sanitize::int($row['lastAccessed']);
            $user->newUserWizard = Sanitize::int($row['newUserWizard']);
            $user->retired = Sanitize::int($row['retired']);

            // Set the user credentials (set privately)
            $user->setPassword(Sanitize::string($row['UserPassword']), Sanitize::int($row['CSPRNG']));

            $user->groupId = Sanitize::int($row['groupId']);
            $user->group = Sanitize::string($row['group']);

            $entries[] = $user;
        }

        return $entries;
    }
}