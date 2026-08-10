<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
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
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class UserFactory
 *
 * @package Xibo\Factory
 */
class UserFactory extends BaseFactory
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly PermissionFactory      $permissionFactory,
        private readonly UserOptionFactory      $userOptionFactory,
        private readonly ApplicationScopeFactory $applicationScopeFactory
    ) {
    }

    /**
     * Create a user
     * @return User
     */
    public function create(): User
    {
        return new User(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
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
     * @param bool $disableUserCheck
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getById(int $userId, bool $disableUserCheck = true): User
    {
        $users = $this->query(null, [
            'disableUserCheck' => $disableUserCheck ? 1 : 0,
            'userId' => $userId
        ]);

        if (count($users) <= 0) {
            throw new NotFoundException(__('User not found'));
        }

        return $users[0];
    }

    /**
     * Load by client Id
     * @param string $clientId
     * @return User
     * @throws NotFoundException
     */
    public function loadByClientId(string $clientId): User
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'clientId' => $clientId));

        if (count($users) <= 0) {
            throw new NotFoundException(sprintf('User not found'));
        }

        return $users[0];
    }

    /**
     * Get User by Name
     * @param string $userName
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getByName(string $userName): User
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'exactUserName' => $userName));

        if (count($users) <= 0) {
            throw new NotFoundException(__('User not found'));
        }

        return $users[0];
    }

    /**
     * Get by email
     * @param string $email
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getByEmail(string $email): User
    {
        $users = $this->query(null, array('disableUserCheck' => 1, 'email' => $email));

        if (count($users) <= 0) {
            throw new NotFoundException(__('User not found'));
        }

        return $users[0];
    }

    /**
     * Get by groupId
     * @param int $groupId
     * @return array[User]
     */
    public function getByGroupId($groupId): array
    {
        return $this->query(null, array('disableUserCheck' => 1, 'groupIds' => [$groupId]));
    }

    /**
     * Get Super Admins
     * @return User[]
     */
    public function getSuperAdmins(): array
    {
        return $this->query(null, array('disableUserCheck' => 1, 'userTypeId' => 1));
    }

    /**
     * Get system user
     * @return User
     * @throws NotFoundException
     */
    public function getSystemUser(): User
    {
        return $this->getById($this->configService->getSetting('SYSTEM_USER'));
    }

    /**
     * @param int $homeFolderId
     * @return User[]
     */
    public function getByHomeFolderId(int $homeFolderId): array
    {
        return $this->query(null, ['homeFolderId' => $homeFolderId]);
    }

    /**
     * Query for users
     * @param ?array $sortOrder
     * @param array $filterBy
     * @return array[User]
     */
    public function query(?array $sortOrder = [], array $filterBy = []): array
    {
        $entries = [];
        $parsedFilter = $this->getSanitizer($filterBy);

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
                `user`.homePageId,
                `user`.homeFolderId,
                `folder`.folderName AS homeFolder,
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
                `group`.isDataSetNotification,
                `group`.isLayoutNotification,
                `group`.isLibraryNotification,
                `group`.isReportNotification,
                `group`.isScheduleNotification,
                `group`.isCustomNotification,
                `user`.isPasswordChangeRequired,
                `user`.twoFactorTypeId,
                `user`.twoFactorSecret,
                `user`.twoFactorRecoveryCodes
            ';

        $body = '
              FROM `user`
                INNER JOIN lkusergroup
                ON lkusergroup.userId = user.userId
                INNER JOIN `folder`
                ON `folder`.folderId = `user`.homeFolderId
                INNER JOIN `group`
                ON `group`.groupId = lkusergroup.groupId
                  AND isUserSpecific = 1
             WHERE 1 = 1
         ';

        if ($parsedFilter->getCheckbox('disableUserCheck') == 0) {
            // Normal users can only see themselves
            if ($this->getUser()->userTypeId == 3) {
                $params['userId'] = $this->getUser()->userId;
            } else if ($this->getUser()->userTypeId == 2) {
                // Group admins can only see users from their groups.
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

        if ($parsedFilter->getInt('notUserId') !== null) {
            $body .= ' AND user.userId <> :notUserId ';
            $params['notUserId'] = $parsedFilter->getInt('notUserId');
        }

        // User Id Provided?
        if (isset($params['userId'])) {
            $body .= ' AND user.userId = :userId ';
        } else if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND user.userId = :userId ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        // Groups Provided
        $groups = $parsedFilter->getIntArray('groupIds');

        if ($groups !== null && count($groups) > 0) {
            $body .= '
                AND user.userId IN (
                    SELECT userId FROM `lkusergroup`
                     WHERE groupId IN (' . implode(',', $groups) . ')
                ) 
            ';
        }

        // User Type Provided
        if ($parsedFilter->getInt('userTypeId') !== null) {
            $body .= ' AND user.userTypeId = :userTypeId ';
            $params['userTypeId'] = $parsedFilter->getInt('userTypeId');
        }

        // Home Folder Id
        if ($parsedFilter->getInt('homeFolderId') !== null) {
            $body .= ' AND `user`.homeFolderId = :homeFolderId ';
            $params['homeFolderId'] = $parsedFilter->getInt('homeFolderId');
        }

        // User Name Provided
        if ($parsedFilter->getString('exactUserName') != null) {
            $body .= ' AND user.userName = :exactUserName ';
            $params['exactUserName'] = $parsedFilter->getString('exactUserName');
        }

        if ($parsedFilter->getString('userName') != null) {
            $terms = explode(',', $parsedFilter->getString('userName'));
            $logicalOperator = $parsedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'user',
                'userName',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        // Email Provided
        if ($parsedFilter->getString('email') != null) {
            $body .= ' AND user.email = :email ';
            $params['email'] = $parsedFilter->getString('email');
        }

        // First Name Provided
        if ($parsedFilter->getString('firstName') != null) {
            $body .= ' AND user.firstName LIKE :firstName ';
            $params['firstName'] = '%' . $parsedFilter->getString('firstName') . '%';
        }

        // Last Name Provided
        if ($parsedFilter->getString('lastName') != null) {
            $body .= ' AND user.lastName LIKE :lastName ';
            $params['lastName'] = '%' . $parsedFilter->getString('lastName') . '%';
        }

        // Retired users?
        if ($parsedFilter->getInt('retired') !== null) {
            $body .= ' AND user.retired = :retired ';
            $params['retired'] = $parsedFilter->getInt('retired');
        }

        if ($parsedFilter->getString('clientId') != null) {
            $body .= ' AND user.userId = (SELECT userId FROM `oauth_clients` WHERE id = :clientId) ';
            $params['clientId'] = $parsedFilter->getString('clientId');
        }

        // Home folderId
        if ($parsedFilter->getInt('homeFolderId') !== null) {
            $body .= ' AND user.homeFolderId = :homeFolderId ';
            $params['homeFolderId'] = $parsedFilter->getInt('homeFolderId');
        }

        if ($parsedFilter->getInt('userGroupIdMembers') !== null) {
            $body .= '
                AND user.userId IN (
                    SELECT userId FROM `lkusergroup`
                     WHERE groupId = :userGroupIdMembers
                )
            ';
            $params['userGroupIdMembers'] = $parsedFilter->getInt('userGroupIdMembers');
        }

        // Sorting
        $allowedColumns = [
            'userId',
            'userName',
            'firstName',
            'lastName',
            'email',
            'homeFolder',
            'lastAccessed',
            'retired',
            'phone',
            'ref1',
            'ref2',
            'ref3',
            'ref4',
            'ref5',
        ];

        $customColumns = [
            'libraryQuotaFormatted' => '`libraryQuota`',
            'twoFactorDescription' => '`twoFactorTypeId`',
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            $customColumns,
            defaultSort: ['userId ASC'],
            uniqueColumn: 'userId'
        );

        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        $limit = '';
        // Paging
        if ($parsedFilter->hasParam('start') && $parsedFilter->hasParam('length')) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0])
                . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->create()->hydrate($row, [
                'intProperties' => [
                    'libraryQuota',
                    'isPasswordChangeRequired',
                    'retired',
                    'isSystemNotification',
                    'isDisplayNotification',
                    'isDataSetNotification',
                    'isLayoutNotification',
                    'isReportNotification',
                    'isScheduleNotification',
                    'isCustomNotification',
                ],
                'stringProperties' => ['homePageId']
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * Get a count of users, with respect to the logged-in user
     * @return int
     */
    public function count(): int
    {
        $params = [];
        $sql = '
        SELECT COUNT(DISTINCT `user`.userId) AS countOf
          FROM `user`
            INNER JOIN `lkusergroup`
            ON `lkusergroup`.userId = `user`.userId
            INNER JOIN `folder`
            ON `folder`.folderId = `user`.homeFolderId
            INNER JOIN `group`
            ON `group`.groupId = `lkusergroup`.groupId
              AND `group`.isUserSpecific = 1
        ';

        // Super admins should get a count of all users in the system.
        if (!$this->getUser()->isSuperAdmin()) {
            // Non-super admins should only get a count of users in their group
            $sql .= '
                WHERE `user`.userId IN (
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

        // Run the query
        $results = $this->getStore()->select($sql, $params);
        return intval($results[0]['countOf'] ?? 0);
    }
}
