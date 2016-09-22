<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BaseFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\User;
use Xibo\Service\FactoryServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class BaseFactory
 * @package Xibo\Factory
 */
class BaseFactory
{
    /**
     * Count records last query
     * @var int
     */
    protected $_countLast = 0;

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /**
     * @var User
     */
    private $user;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @return $this
     */
    protected function setCommonDependencies($store, $log, $sanitizerService)
    {
        $this->store = $store;
        $this->log = $log;
        $this->sanitizerService = $sanitizerService;

        return $this;
    }

    /**
     * Set Acl Dependencies
     * @param User $user
     * @param UserFactory $userFactory
     * @return $this
     */
    public function setAclDependencies($user, $userFactory)
    {
        $this->user = $user;
        $this->userFactory = $userFactory;
        return $this;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->log;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * Get User
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get User Factory
     * @return UserFactory
     */
    public function getUserFactory()
    {
        return $this->userFactory;
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
        $checkUserId = $this->getSanitizer()->getInt('userCheckUserId', $filterBy);

        if ($checkUserId !== null) {
            $this->getLog()->debug('Checking permissions against a specific user: %d', $checkUserId);
            $user = $this->getUserFactory()->getById($checkUserId);
        }
        else {
            $user = $this->getUser();

            $this->getLog()->debug('Checking permissions against the logged in user: ID: %d, Name: %s, UserType: %d', $user->userId, $user->userName, $user->userTypeId);
        }

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

    /**
     * @param $variable
     * @return array
     */
    protected function parseComparisonOperator($variable)
    {
        $operator = '=';
        $allowedOperators = [
            'less-than' => '<',
            'greater-than' => '>',
            'less-than-equal' => '<=',
            'greater-than-equal' => '>='
        ];

        if (stripos($variable, '|') !== false) {
            $variable = explode('|', $variable);

            if (array_key_exists($variable[0], $allowedOperators)) {
                $operator = $allowedOperators[$variable[0]];
            }

            $variable = $variable[1];
        }

        return [
            'operator' => $operator,
            'variable' => $variable
        ];
    }
}