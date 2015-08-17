<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroupFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\UserGroup;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class UserGroupFactory extends BaseFactory
{
    /**
     * Get by Group Id
     * @param int $groupId
     * @return UserGroup
     * @throws NotFoundException
     */
    public static function getById($groupId)
    {
        $groups = UserGroupFactory::query(null, ['disableUserCheck' => 1, 'groupId' => $groupId, 'isUserSpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get by Group Name
     * @param string $group
     * @return UserGroup
     * @throws NotFoundException
     */
    public static function getByName($group)
    {
        $groups = UserGroupFactory::query(null, ['disableUserCheck' => 1, 'group' => $group, 'isUserSpecific' => 0]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get Everyone Group
     * @return UserGroup
     * @throws NotFoundException
     */
    public static function getEveryone()
    {
        $groups = UserGroupFactory::query(null, ['disableUserCheck' => 1, 'isEveryone' => 1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get by User Id
     * @param int $userId
     * @return array[UserGroup]
     * @throws NotFoundException
     */
    public static function getByUserId($userId)
    {
        return UserGroupFactory::query(null, ['disableUserCheck' => 1, 'userId' => $userId, 'isUserSpecific' => 0]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[UserGroup]
     * @throws \Exception
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        try {
            $select = '
            SELECT 	`group`.group,
				`group`.groupId,
				`group`.isUserSpecific,
				`group`.isEveryone,
				`group`.libraryQuota ';

            $body = '
              FROM `group`
             WHERE 1 = 1
            ';

            // Permissions
            if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
                // Normal users can only see their group
                if (self::getUser()->userTypeId != 1) {
                    $body .= '
                    AND `group`.groupId IN (
                        SELECT `group`.groupId
                          FROM `lkusergroup`
                            INNER JOIN `group`
                            ON `group`.groupId = `lkusergroup`.groupId
                                AND `group`.isUserSpecific = 0
                         WHERE `lkusergroup`.userId = :currentUserId
                    )
                    ';
                    $params['currentUserId'] = self::getUser()->userId;
                }
            }

            // Filter by Group Id
            if (Sanitize::getInt('groupId', $filterBy) !== null) {
                $body .= ' AND `group`.groupId = :groupId ';
                $params['groupId'] = Sanitize::getInt('groupId', $filterBy);
            }

            // Filter by Group Name
            if (Sanitize::getString('group', $filterBy) != null) {
                $body .= ' AND `group`.group = :group ';
                $params['group'] = Sanitize::getString('group', $filterBy);
            }

            // Filter by User Id
            if (Sanitize::getInt('userId', $filterBy) !== null) {
                $body .= ' AND `group`.groupId IN (SELECT groupId FROM `lkusergroup` WHERE userId = :userId) ';
                $params['userId'] = Sanitize::getInt('userId', $filterBy);
            }

            if (Sanitize::getInt('isUserSpecific', $filterBy) != -1) {
                $body .= ' AND isUserSpecific = :isUserSpecific ';
                $params['isUserSpecific'] = Sanitize::getInt('isUserSpecific', 0, $filterBy);
            }

            if (Sanitize::getInt('isEveryone', $filterBy) != -1) {
                $body .= ' AND isEveryone = :isEveryone ';
                $params['isEveryone'] = Sanitize::getInt('isEveryone', 0, $filterBy);
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

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new UserGroup())->hydrate($row);
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
                self::$_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw $e;
        }
    }
}