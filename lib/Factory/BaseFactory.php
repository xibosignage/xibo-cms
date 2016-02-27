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
    /**
     * @var Slim $app
     */
    private $app;

    /**
     * Count records last query
     * @var int
     */
    protected $_countLast = 0;

    /**
     * BaseFactory constructor.
     * @param Slim $app
     */
    public function __construct($app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Count of records returned for the last query.
     * @return int
     */
    public function countLast()
    {
        return $this->_countLast;
    }

    /**
     * Get App
     * @return Slim
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Get User
     * @return User
     * @throws \RuntimeException
     */
    public function getUser()
    {
        if ($this->app == null)
            throw new \RuntimeException(__('Factory application not set'));

        return $this->app->user;
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
    public function viewPermissionSql($entity, &$sql, &$params, $idColumn, $ownerColumn = null, $filterBy = [])
    {
        $user = (Sanitize::getInt('userCheckUserId', $filterBy) !== null) ? (new UserFactory($this->app))->getById(Sanitize::getInt('userCheckUserId', $filterBy)) : $this->getUser();

        $permissionSql = '';

        if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0 && $user->userTypeId != 1) {
            $permissionSql .= '
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
                $permissionSql .= ' OR ' . $ownerColumn . ' = :currentUserId2';
                $params['currentUserId2'] = $user->userId;
            }

            // Group Admin?
            if ($user->userTypeId == 2 && $ownerColumn != null) {
                // OR the group admin and the owner of the media are in the same group
                $permissionSql .= '
                    OR (
                        SELECT COUNT(lkUserGroupId)
                          FROM `lkusergroup`
                         WHERE userId = ' . $ownerColumn . '
                            AND groupId IN (
                                SELECT groupId
                                  FROM `lkusergroup`
                                 WHERE userId = :currentUserId3
                            )
                    ) > 0
                ';

                $params['currentUserId3'] = $user->userId;
            }

            $permissionSql .= ' )';

            //Log::debug('Permission SQL = %s', $permissionSql);
        }

        // Set out params
        $sql = $sql . $permissionSql;
    }
}