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
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class UserFactory extends BaseFactory
{
    /**
     * Get User by ID
     * @param int $userId
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public static function getById($userId)
    {
        $users = UserFactory::query(null, array('disableUserCheck' => 1, 'userId' => $userId));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Load User by ID
     * @param int $userId
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public static function loadById($userId)
    {
        $user = UserFactory::getById($userId);
        $user->load();

        return $user;
    }

    /**
     * Load by client Id
     * @param string $clientId
     * @throws NotFoundException
     */
    public static function loadByClientId($clientId)
    {
        $users = UserFactory::query(null, array('disableUserCheck' => 1, 'clientId' => $clientId));

        if (count($users) <= 0)
            throw new NotFoundException(sprintf('User not found'));

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
        $users = UserFactory::query(null, array('disableUserCheck' => 1, 'userName' => $userName));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Get by groupId
     * @param int $groupId
     * @return array[User]
     */
    public static function getByGroupId($groupId)
    {
        return UserFactory::query(null, array('disableUserCheck' => 1, 'groupIds' => [$groupId]));
    }

    /**
     * Get users by Display Group
     * @param $displayGroupId
     * @return array
     */
    public static function getByDisplayGroupId($displayGroupId)
    {
        return DisplayFactory::query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
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
            $sortOrder = array('userName');

        $params = array();
        $select = '
            SELECT `user`.userId,
                userName,
                userTypeId,
                loggedIn,
                email,
                `user`.homePageId,
                pages.title AS homePage,
                lastAccessed,
                newUserWizard,
                retired,
                CSPRNG,
                UserPassword AS password,
                group.groupId,
                group.group,
                IFNULL(group.libraryQuota, 0) AS libraryQuota ';

        $body = '
              FROM `user`
                INNER JOIN lkusergroup
                ON lkusergroup.userId = user.userId
                INNER JOIN `group`
                ON `group`.groupId = lkusergroup.groupId
                  AND isUserSpecific = 1
                LEFT OUTER JOIN `pages`
                ON pages.pageId = `user`.homePageId
             WHERE 1 = 1
         ';

        if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
            // Normal users can only see themselves
            if (self::getUser()->userTypeId == 3) {
                $filterBy['userId'] = self::getUser()->userId;
            }
            // Group admins can only see users from their groups.
            else if (self::getUser()->userTypeId == 2) {
                $body .= '
                    AND user.userId IN (
                        SELECT `otherUserLinks`.userId
                          FROM `lkusergroup`
                            INNER JOIN `group`
                            ON `group`.groupId = `lkusergroup`.groupId
                                AND `group`.isUserSpecific = 0
                            INNER JOIN `lkusergroup` `otherUserLinks`
                            ON `otherUserLinks`.groupId = `group`.groupId
                         WHERE `lkusergroup`.userId = :currentUserId
                    )
                ';
                $params['currentUserId'] = self::getUser()->userId;
            }
        }

        if (Sanitize::getInt('notUserId', $filterBy) !== null) {
            $body .= ' AND user.userId <> :notUserId ';
            $params['notUserId'] = Sanitize::getInt('notUserId', $filterBy);
        }

        // User Id Provided?
        if (Sanitize::getInt('userId', $filterBy) !== null) {
            $body .= " AND user.userId = :userId ";
            $params['userId'] = Sanitize::getInt('userId', $filterBy);
        }

        // Groups Provided
        $groups = Sanitize::getParam('groupIds', $filterBy);

        if (count($groups) > 0) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId IN (' . implode($groups, ',') . ')) ';
        }

        // User Type Provided
        if (Sanitize::getInt('userTypeId', $filterBy) !== null) {
            $body .= " AND user.userTypeId = :userTypeId ";
            $params['userTypeId'] = Sanitize::getInt('userTypeId', $filterBy);
        }

        // User Name Provided
        if (Sanitize::getString('userName', $filterBy) != null) {
            $body .= " AND user.userName = :userName ";
            $params['userName'] = Sanitize::getString('userName', $filterBy);
        }

        // Retired users?
        if (Sanitize::getInt('retired', $filterBy) !== null) {
            $body .= " AND user.retired = :retired ";
            $params['retired'] = Sanitize::getInt('retired', $filterBy);
        }

        if (Sanitize::getString('clientId', $filterBy) != null) {
            $body .= ' AND user.userId = (SELECT userId FROM `oauth_clients` WHERE id = :clientId) ';
            $params['clientId'] = Sanitize::getString('clientId', $filterBy);
        }

        if (Sanitize::getInt('displayGroupId', $filterBy) !== null) {
            $body .= ' AND user.userId IN (
                SELECT DISTINCT user.userId, user.userName, user.email
                  FROM `user`
                    INNER JOIN `lkusergroup`
                    ON lkusergroup.userId = user.userId
                    INNER JOIN `permission`
                    ON `permission`.groupId = `lkusergroup`.groupId
                    INNER JOIN `permissionentity`
                    ON `permissionentity`.entityId = permission.entityId
                        AND `permissionentity`.entity = \'Xibo\\Entity\\DisplayGroup\'
                 WHERE `permission`.objectId = :displayGroupId
            ) ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        \Xibo\Helper\Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new User())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}