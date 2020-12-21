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
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserFactory
 *
 * @package Xibo\Factory
 */
class UserFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserOptionFactory
     */
    private $userOptionFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ConfigServiceInterface $configService
     * @param PermissionFactory $permissionFactory
     * @param UserOptionFactory $userOptionFactory
     * @param ApplicationScopeFactory $applicationScopeFactory
     */
    public function __construct($store, $log, $sanitizerService,
                                $configService,
                                $permissionFactory,
                                $userOptionFactory,
                                $applicationScopeFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->configService = $configService;
        $this->permissionFactory = $permissionFactory;
        $this->userOptionFactory = $userOptionFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
    }

    /**
     * Create a user
     * @return User
     */
    public function create()
    {
        return new User($this->getStore(),
            $this->getLog(),
            $this->configService,
            $this,
            $this->permissionFactory,
            $this->userOptionFactory,
            $this->applicationScopeFactory
        );
    }

    /**
     * Get User by ID
     * @param int $userId
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getById($userId)
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'userId' => $userId));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Load by client Id
     * @param string $clientId
     * @throws NotFoundException
     */
    public function loadByClientId($clientId)
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'clientId' => $clientId));

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
    public function getByName($userName)
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'exactUserName' => $userName));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));

        return $users[0];
    }

    /**
     * Get by email
     * @param string $email
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getByEmail($email) {
        $users = $this->query(null, array('disableUserCheck' => 1, 'email' => $email));

        if (count($users) <= 0)
            throw new NotFoundException(__('User not found'));
        return $users[0];
    }

    /**
     * Get by groupId
     * @param int $groupId
     * @return array[User]
     */
    public function getByGroupId($groupId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'groupIds' => [$groupId]));
    }

    /**
     * Get Super Admins
     * @return User[]
     */
    public function getSuperAdmins()
    {
        return $this->query(null, array('disableUserCheck' => 1, 'userTypeId' => 1));
    }

    /**
     * Get Dooh user
     * @return User[]
     */
    public function getDoohUsers()
    {
        return $this->query(null, array('disableUserCheck' => 1, 'userTypeId' => 4));
    }

    /**
     * Get system user
     * @return User
     */
    public function getSystemUser()
    {
        $user = $this->create();
        $user->userId = 1;
        $user->userName = 'system';
        $user->userTypeId = 1;
        $user->email = $this->configService->getSetting('mail_to');

        return $user;
    }

    /**
     * Query for users
     * @param array[mixed] $sortOrder
     * @param array[mixed] $filterBy
     * @return array[User]
     */
    public function query($sortOrder = [], $filterBy = [])
    {
        $entries = [];

        // Default sort order
        if ($sortOrder === null || count($sortOrder) <= 0)
            $sortOrder = ['userName'];

        $params = [];
        $select = '
            SELECT `user`.userId,
                userName,
                userTypeId,
                email,
                lastAccessed,
                newUserWizard,
                retired,
                CSPRNG,
                UserPassword AS password,
                group.groupId,
                group.group,
                `pages`.pageId AS homePageId,
                `pages`.title AS homePage,
                `user`.firstName,
                `user`.lastName,
                `user`.phone,
                `user`.ref1,
                `user`.ref2,
                `user`.ref3,
                `user`.ref4,
                `user`.ref5,
                IFNULL(group.libraryQuota, 0) AS libraryQuota,
                `group`.isSystemNotification,
                `group`.isDisplayNotification, 
                `user`.isPasswordChangeRequired,
                `user`.twoFactorTypeId,
                `user`.twoFactorSecret,
                `user`.twoFactorRecoveryCodes,
                `user`.showContentFrom
            ';

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

        if ($this->getSanitizer()->getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
            // Normal users can only see themselves
            if ($this->getUser()->userTypeId == 3) {
                $filterBy['userId'] = $this->getUser()->userId;
            }
            // Group admins can only see users from their groups.
            else if ($this->getUser()->userTypeId == 2) {
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
                $params['currentUserId'] = $this->getUser()->userId;
            }
        }

        if ($this->getSanitizer()->getInt('notUserId', $filterBy) !== null) {
            $body .= ' AND user.userId <> :notUserId ';
            $params['notUserId'] = $this->getSanitizer()->getInt('notUserId', $filterBy);
        }

        // User Id Provided?
        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= " AND user.userId = :userId ";
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        // Groups Provided
        $groups = $this->getSanitizer()->getParam('groupIds', $filterBy);

        if ($groups !== null && count($groups) > 0) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId IN (' . implode($groups, ',') . ')) ';
        }

        // User Type Provided
        if ($this->getSanitizer()->getInt('userTypeId', $filterBy) !== null) {
            $body .= " AND user.userTypeId = :userTypeId ";
            $params['userTypeId'] = $this->getSanitizer()->getInt('userTypeId', $filterBy);
        }

        // User Name Provided
        if ($this->getSanitizer()->getString('exactUserName', $filterBy) != null) {
            $body .= " AND user.userName = :exactUserName ";
            $params['exactUserName'] = $this->getSanitizer()->getString('exactUserName', $filterBy);
        }

        if ($this->getSanitizer()->getString('userName', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('userName', $filterBy));
            $this->nameFilter('user', 'userName', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        // Email Provided
        if ($this->getSanitizer()->getString('email', $filterBy) != null) {
            $body .= " AND user.email = :email ";
            $params['email'] = $this->getSanitizer()->getString('email', $filterBy);
        }

        // Retired users?
        if ($this->getSanitizer()->getInt('retired', $filterBy) !== null) {
            $body .= " AND user.retired = :retired ";
            $params['retired'] = $this->getSanitizer()->getInt('retired', $filterBy);
        }

        if ($this->getSanitizer()->getString('clientId', $filterBy) != null) {
            $body .= ' AND user.userId = (SELECT userId FROM `oauth_clients` WHERE id = :clientId) ';
            $params['clientId'] = $this->getSanitizer()->getString('clientId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->create()->hydrate($row, ['intProperties' => ['libraryQuota', 'isPasswordChangeRequired', 'retired']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}