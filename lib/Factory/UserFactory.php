<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
    /** @var callable|null Gives the reserved userIds (see setReservedUserIdsResolver), none when null */
    private $reservedUserIdsResolver = null;

    /** @var bool Whether query() includes reserved users: only for getById(), and getByName() when asked */
    private bool $includeReserved = false;

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
        // Reserved users are found by id, as a signed-in user and as the owner of what they created.
        $users = $this->withReserved(fn () => $this->query(null, [
            'disableUserCheck' => $disableUserCheck ? 1 : 0,
            'userId' => $userId
        ]));

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
     * @param bool $includeReserved Find a reserved user too (only to check a name is free)
     * @return User
     * @throws NotFoundException if the user cannot be found
     */
    public function getByName(string $userName, bool $includeReserved = false): User
    {
        $filter = ['disableUserCheck' => 1, 'exactUserName' => $userName];
        $users = $includeReserved
            ? $this->withReserved(fn () => $this->query(null, $filter))
            : $this->query(null, $filter);

        if (count($users) <= 0) {
            throw new NotFoundException(__('User not found'));
        }

        return $users[0];
    }

    /**
     * Get the user a sign-in identifier points at: the username first, then an email only one user has.
     * Emails are compared trimmed and case-insensitive.
     * @param string $identifier
     * @return User
     * @throws NotFoundException if nothing matches, or the email is shared
     */
    public function getByLoginIdentifier(string $identifier): User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new NotFoundException(__('User not found'));
        }

        // One query finds username and email matches; a hit then loads the user by ID.
        $rows = $this->getStore()->select('
            SELECT `userId`, `userName` = :isUserName AS isUserName
              FROM `user`
             WHERE `userName` = :userName
                OR LOWER(TRIM(`email`)) = LOWER(:email)
        ', [
            'isUserName' => $identifier,
            'userName' => $identifier,
            'email' => $identifier,
        ]);

        foreach ($rows as $row) {
            if ((int)$row['isUserName'] === 1) {
                return $this->getById((int)$row['userId']);
            }
        }

        if (count($rows) === 1) {
            return $this->getById((int)$rows[0]['userId']);
        }

        throw new NotFoundException(__('User not found'));
    }

    /**
     * Does a user other than this one have this email? Compared trimmed and case-insensitive.
     * @param string $email
     * @param int|null $userId the user being saved, null for a new user
     * @return bool
     */
    public function isEmailUsedByOtherUser(string $email, ?int $userId): bool
    {
        $rows = $this->getStore()->select('
            SELECT `userId`
              FROM `user`
             WHERE LOWER(TRIM(`email`)) = LOWER(:email)
               AND `userId` <> :userId
             LIMIT 1
        ', [
            'email' => trim($email),
            'userId' => $userId ?? 0,
        ]);

        return count($rows) > 0;
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

        // Reserved users are left out of every list and lookup, other than by id.
        $reservedUserIds = $this->includeReserved ? [] : $this->getReservedUserIds();
        if (count($reservedUserIds) > 0) {
            $body .= ' AND `user`.userId NOT IN (' . implode(',', $reservedUserIds) . ') ';
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

        // Reserved users aren't counted.
        $reservedUserIds = $this->getReservedUserIds();
        if (count($reservedUserIds) > 0) {
            $sql .= (str_contains($sql, 'WHERE') ? ' AND ' : ' WHERE ')
                . '`user`.userId NOT IN (' . implode(',', $reservedUserIds) . ') ';
        }

        // Run the query
        $results = $this->getStore()->select($sql, $params);
        return intval($results[0]['countOf'] ?? 0);
    }

    /**
     * Reserves users, for an extension that needs an account nobody can sign in as or manage (CE reserves none).
     * The resolver gives the reserved userIds. A reserved user is left out of every user list, lookup and count,
     * except getById(), and the user and user group controllers refuse to change, delete, assign or sign it in.
     * @param callable(): int[] $resolver
     * @return $this
     */
    public function setReservedUserIdsResolver(callable $resolver): static
    {
        $this->reservedUserIdsResolver = $resolver;
        return $this;
    }

    /**
     * The reserved userIds, none unless an extension reserves some.
     * @return int[]
     */
    public function getReservedUserIds(): array
    {
        if ($this->reservedUserIdsResolver === null) {
            return [];
        }

        $userIds = [];
        foreach (($this->reservedUserIdsResolver)() as $userId) {
            if ((int)$userId > 0) {
                $userIds[] = (int)$userId;
            }
        }

        return $userIds;
    }

    /**
     * Is this user reserved?
     * @param int $userId
     * @return bool
     */
    public function isReserved(int $userId): bool
    {
        return $userId > 0 && in_array($userId, $this->getReservedUserIds(), true);
    }

    /**
     * Refuses a reserved user as if it didn't exist.
     * @param int $userId
     * @throws NotFoundException if the user is reserved
     */
    public function assertNotReserved(int $userId): void
    {
        if ($this->isReserved($userId)) {
            throw new NotFoundException(__('User not found'));
        }
    }

    /**
     * Is this group a reserved user's own (user specific) group? Recognised by its link to the reserved user, or by
     * its name (a user's own group is named after them, and nobody else can take a reserved user's name), so it stays
     * protected even if the link has gone.
     * @param int $groupId
     * @return bool
     */
    public function isReservedUserGroup(int $groupId): bool
    {
        $reservedUserIds = $this->getReservedUserIds();
        if (count($reservedUserIds) <= 0) {
            return false;
        }

        $results = $this->getStore()->select('
            SELECT COUNT(*) AS countOf
              FROM `group`
             WHERE `group`.groupId = :groupId
               AND `group`.isUserSpecific = 1
               AND (
                   `group`.groupId IN (
                       SELECT `lkusergroup`.groupId
                         FROM `lkusergroup`
                        WHERE `lkusergroup`.userId IN (' . implode(',', $reservedUserIds) . ')
                   )
                   OR `group`.`group` IN (
                       SELECT `user`.userName
                         FROM `user`
                        WHERE `user`.userId IN (' . implode(',', $reservedUserIds) . ')
                   )
               )
        ', ['groupId' => $groupId]);

        return intval($results[0]['countOf'] ?? 0) > 0;
    }

    /**
     * Runs a lookup that includes reserved users.
     * @param callable $lookup
     * @return mixed
     */
    private function withReserved(callable $lookup): mixed
    {
        $previous = $this->includeReserved;
        $this->includeReserved = true;
        try {
            return $lookup();
        } finally {
            $this->includeReserved = $previous;
        }
    }
}
