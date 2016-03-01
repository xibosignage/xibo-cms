<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BaseFactory.php)
 */


namespace Xibo\Factory;


use Slim\Helper\Set;
use Xibo\Entity\User;
use Xibo\Helper\Config;
use Xibo\Helper\DateInterface;
use Xibo\Helper\Log;
use Xibo\Helper\SanitizerInterface;
use Xibo\Storage\StorageInterface;

/**
 * Class BaseFactory
 * @package Xibo\Factory
 */
class BaseFactory
{
    /**
     * @var Set $container
     */
    private $container;

    /**
     * Count records last query
     * @var int
     */
    protected $_countLast = 0;

    /**
     * BaseFactory constructor.
     * @param Set $container
     */
    public function __construct($container)
    {
        $this->container = $container;
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
     * @return Set
     */
    public function getContainer()
    {
        if ($this->container == null)
            throw new \RuntimeException(__('Factory Application not set'));

        return $this->container;
    }

    /**
     * Get User
     * @return User
     * @throws \RuntimeException
     */
    public function getUser()
    {
        return $this->getContainer()->user;
    }

    /**
     * Get Log
     * @return Log
     */
    protected function getLog()
    {
        return $this->getContainer()->logHelper;
    }

    /**
     * Get Store
     * @return StorageInterface
     */
    protected function getStore()
    {
        return $this->getContainer()->store;
    }

    /**
     * Get Date
     * @return DateInterface
     */
    protected function getDate()
    {
        return $this->getContainer()->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerInterface
     */
    protected function getSanitizer()
    {
        return $this->getContainer()->sanitizerService;
    }

    /**
     * Get Config
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getContainer()->configService;
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
        $user = ($this->getSanitizer()->getInt('userCheckUserId', $filterBy) !== null) ? (new UserFactory($this->getContainer()))->getById($this->getSanitizer()->getInt('userCheckUserId', $filterBy)) : $this->getUser();

        $permissionSql = '';

        if ($this->getSanitizer()->getCheckbox('disableUserCheck', 0, $filterBy) == 0 && $user->userTypeId != 1) {
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

            //$this->getLog()->debug('Permission SQL = %s', $permissionSql);
        }

        // Set out params
        $sql = $sql . $permissionSql;
    }
}