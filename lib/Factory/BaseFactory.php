<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BaseFactory.php)
 */


namespace Xibo\Factory;


use Slim\Slim;
use Xibo\Entity\User;
use Xibo\Helper\Sanitize;

class BaseFactory
{
    protected static $_countLast = 0;

    /**
     * Count of records returned for the last query.
     * @return int
     */
    public static function countLast()
    {
        return self::$_countLast;
    }

    /**
     * Get User
     * @return User
     */
    public static function getUser()
    {
        return Slim::getInstance()->user;
    }

    /**
     * View Permission SQL
     * @param $entity
     * @param $sql
     * @param $params
     * @param $idColumn
     * @param null $ownerColumn
     * @param array $filterBy
     */
    public static function viewPermissionSql($entity, &$sql, &$params, $idColumn, $ownerColumn = null, $filterBy = [])
    {
        $user = self::getUser();

        if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0 && $user->userTypeId != 1) {
            $sql .= '
              AND (' . $idColumn . ' IN (
                SELECT `permission`.objectId
                  FROM `permission`
                    INNER JOIN `permissionentity`
                    ON `permissionentity`.entityId = `permission`.entityId
                    INNER JOIN `group`
                    ON `group`.groupId = `permission`.groupId
                    LEFT OUTER JOIN `lkusergroup`
                    ON `lkusergroup`.groupId = `group`.groupId
                    LEFT OUTER JOIN `user`
                    ON lkusergroup.UserID = `user`.UserID
                      AND `user`.userId = :currentUserId
                 WHERE `permissionentity`.entity = :permissionEntity
                    AND `permission`.view = 1
                    AND (`user`.userId IS NOT NULL OR `group`.IsEveryone = 1)
              )
            ';

            $params['permissionEntity'] = $entity;
            $params['currentUserId'] = $user->userId;

            if ($ownerColumn != null) {
                $sql .= ' OR ' . $ownerColumn . ' = :currentUserId2';
                $params['currentUserId2'] = $user->userId;
            }

            $sql .= ' )';
        }
    }
}