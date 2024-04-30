<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
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
     * @var SanitizerService
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
     * @var BaseDependenciesService
     */
    private $baseDependenciesService;

    /**
     * @param BaseDependenciesService $baseDependenciesService
     */
    public function useBaseDependenciesService(BaseDependenciesService $baseDependenciesService)
    {
        $this->baseDependenciesService = $baseDependenciesService;
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
        return $this->baseDependenciesService->getStore();
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->baseDependenciesService->getLogger();
    }

    /**
     * @return SanitizerService
     */
    protected function getSanitizerService()
    {
        return $this->baseDependenciesService->getSanitizer();
    }

    /**
     * Get Sanitizer
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->getSanitizerService()->getSanitizer($array);
    }

    /**
     * @return \Xibo\Support\Validator\ValidatorInterface
     */
    protected function getValidator()
    {
        return $this->getSanitizerService()->getValidator();
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
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->baseDependenciesService->getDispatcher();
    }

    /**
     * @return \Xibo\Service\ConfigServiceInterface
     */
    public function getConfig(): ConfigServiceInterface
    {
        return $this->baseDependenciesService->getConfig();
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function viewPermissionSql(
        $entity,
        &$sql,
        &$params,
        $idColumn,
        $ownerColumn = null,
        $filterBy = [],
        $permissionFolderIdColumn = null
    ) {
        $parsedBody = $this->getSanitizer($filterBy);
        $checkUserId = $parsedBody->getInt('userCheckUserId');

        if ($checkUserId !== null) {
            $this->getLog()->debug(sprintf('Checking permissions against a specific user: %d', $checkUserId));
            $user = $this->getUserFactory()->getById($checkUserId);
        }
        else {
            $user = $this->getUser();

            if ($user !== null)
                $this->getLog()->debug(sprintf('Checking permissions against the logged in user: ID: %d, Name: %s, UserType: %d', $user->userId, $user->userName, $user->userTypeId));
        }

        $permissionSql = '';

        // Has the user check been disabled? 0 = no it hasn't
        $performUserCheck = $parsedBody->getCheckbox('disableUserCheck') == 0;

        if ($performUserCheck && !$user->isSuperAdmin()) {
            $permissionSql .= '
              AND (' . $idColumn . ' IN (
                SELECT `permission`.objectId
                  FROM `permission`
                    INNER JOIN `permissionentity`
                        ON `permissionentity`.entityId = `permission`.entityId
                    INNER JOIN `group`
                        ON `group`.groupId = `permission`.groupId
                    INNER JOIN `lkusergroup`
                        ON `lkusergroup`.groupId = `group`.groupId
                    INNER JOIN `user`
                        ON lkusergroup.UserID = `user`.UserID
                 WHERE `permissionentity`.entity = :permissionEntity
                    AND `user`.userId = :currentUserId
                    AND `permission`.view = 1
                 UNION ALL   
                 SELECT `permission`.objectId
                    FROM `permission`
                        INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = `permission`.entityId
                        INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                    WHERE `permissionentity`.entity = :permissionEntity
                        AND `group`.isEveryone = 1
                        AND `permission`.view = 1
              )
            ';

            $params['permissionEntity'] = $entity;
            $params['currentUserId'] = $user->userId;

            if ($ownerColumn != null) {
                $permissionSql .= ' OR ' . $ownerColumn . ' = :currentUserId2';
                $params['currentUserId2'] = $user->userId;
            }

            // Home folders (only for folder entity)
            if ($entity === 'Xibo\Entity\Folder') {
                $permissionSql .= ' OR folder.folderId = :permissionsHomeFolderId';
                $permissionSql .= ' OR folder.permissionsFolderId = :permissionsHomeFolderId';
                $params['permissionsHomeFolderId'] = $this->getUser()->homeFolderId;
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

            if ($permissionFolderIdColumn != null) {
                $permissionSql .= '
                    OR ' . $permissionFolderIdColumn . ' IN (
                        SELECT `permission`.objectId
                            FROM `permission`
                               INNER JOIN `permissionentity`
                                 ON `permissionentity`.entityId = `permission`.entityId
                               INNER JOIN `group`
                                 ON `group`.groupId = `permission`.groupId
                               INNER JOIN `lkusergroup`
                                 ON `lkusergroup`.groupId = `group`.groupId
                               INNER JOIN `user`
                                 ON lkusergroup.UserID = `user`.UserID
                            WHERE `permissionentity`.entity = :folderEntity
                              AND `permission`.view = 1
                              AND `user`.userId = :currentUserId
                        UNION ALL   
                         SELECT `permission`.objectId
                            FROM `permission`
                                INNER JOIN `permissionentity`
                                    ON `permissionentity`.entityId = `permission`.entityId
                                INNER JOIN `group`
                                    ON `group`.groupId = `permission`.groupId
                            WHERE `permissionentity`.entity = :folderEntity
                                AND `group`.isEveryone = 1
                                AND `permission`.view = 1
                    )
                ';

                $params['folderEntity'] = 'Xibo\Entity\Folder';
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

    /**
     * Sets the name filter for all factories to use.
     *
     * @param string $tableName Table name
     * @param string $tableColumn Column with the name
     * @param array $terms An Array exploded by "," of the search names
     * @param string $body Current SQL body passed by reference
     * @param array $params Array of parameters passed by reference
     * @param bool $useRegex flag to match against a regex pattern
     */
    public function nameFilter(
        $tableName,
        $tableColumn,
        $terms,
        &$body,
        &$params,
        $useRegex = false,
        $logicalOperator = 'OR'
    ) {
        $i = 0;

        $tableAndColumn = $tableName . '.' . $tableColumn;
        // filter empty array elements, in an attempt to better handle spaces after `,`.
        $filteredNames = array_filter($terms, function ($element) {
            return is_string($element) && '' !== trim($element);
        });

        foreach ($filteredNames as $searchName) {
            // Trim/Sanitise
            $searchName = trim($searchName);

            // Discard any incompatible
            if (empty(ltrim($searchName, '-')) || empty($searchName)) {
                continue;
            }

            // Validate the logical operator
            if (!in_array($logicalOperator, ['AND', 'OR'])) {
                $this->getLog()->error('Invalid logical operator ' . $logicalOperator);
                return;
            }

            // increase here, after we expect additional sql to be added.
            $i++;

            // Not like, or like?
            if (str_starts_with($searchName, '-')) {
                if ($i === 1) {
                    $body .= ' AND ( '.$tableAndColumn.' NOT RLIKE (:search'.$i.') ';
                } else {
                    $body .= ' ' . $logicalOperator . ' '.$tableAndColumn.' NOT RLIKE (:search'.$i.') ';
                }
                $params['search' . $i] = $useRegex ? ltrim(($searchName), '-') : preg_quote(ltrim(($searchName), '-'));
            } else {
                if ($i === 1) {
                    $body .= ' AND ( '.$tableAndColumn.' RLIKE (:search'.$i.') ';
                } else {
                    $body .= ' ' . $logicalOperator . ' '.$tableAndColumn.' RLIKE (:search'.$i.') ';
                }
                $params['search' . $i] = $useRegex ? $searchName : preg_quote($searchName);
            }
        }

        // append closing parenthesis only if we added any sql.
        if (!empty($filteredNames) && $i > 0) {
            $body .= ' ) ';
        }
    }

    /**
     * @param array $tags An array of tags
     * @param string $lkTagTable name of the lktag table
     * @param string $lkTagTableIdColumn name of the id column in the lktag table
     * @param string $idColumn name of the id column in main table
     * @param string $logicalOperator AND or OR logical operator passed from Factory
     * @param string $operator exactTags passed from factory, determines if the search is LIKE or =
     * @param string $body Current SQL body passed by reference
     * @param array $params Array of parameters passed by reference
     */
    public function tagFilter(
        $tags,
        $lkTagTable,
        $lkTagTableIdColumn,
        $idColumn,
        $logicalOperator,
        $operator,
        $notTags,
        &$body,
        &$params
    ) {
        $i = 0;
        $paramName = ($notTags) ? 'notTags' : 'tags';
        $paramValueName = ($notTags) ? 'notValue' : 'value';

        foreach ($tags as $tag) {
            $i++;

            $tagV = explode('|', $tag);

            // search tag without value
            if (!isset($tagV[1])) {
                if ($i == 1) {
                    $body .= ' WHERE `tag` ' . $operator . ' :'. $paramName . $i;
                } else {
                    $body .= ' OR ' . ' `tag` ' . $operator . ' :' . $paramName . $i;
                }

                if ($operator === '=') {
                    $params[$paramName . $i] = $tag;
                } else {
                    $params[$paramName . $i] = '%' . $tag . '%';
                }
                // search tag only by value
            } elseif ($tagV[0] == '') {
                if ($i == 1) {
                    $body .= ' WHERE `value` ' . $operator . ' :' . $paramValueName . $i;
                } else {
                    $body .= ' OR ' . ' `value` ' . $operator . ' :' . $paramValueName . $i;
                }

                if ($operator === '=') {
                    $params[$paramValueName . $i] = $tagV[1];
                } else {
                    $params[$paramValueName . $i] = '%' . $tagV[1] . '%';
                }
                // search tag by both tag and value
            } else {
                if ($i == 1) {
                    $body .= ' WHERE `tag` ' . $operator . ' :' . $paramName . $i .
                        ' AND value ' . $operator . ' :' . $paramValueName . $i;
                } else {
                    $body .= ' OR ' . ' `tag` ' . $operator . ' :' . $paramName . $i .
                        ' AND value ' . $operator . ' :' . $paramValueName . $i;
                }

                if ($operator === '=') {
                    $params[$paramName . $i] = $tagV[0];
                    $params[$paramValueName . $i] = $tagV[1];
                } else {
                    $params[$paramName . $i] = '%' . $tagV[0] . '%';
                    $params[$paramValueName . $i] = '%' . $tagV[1] . '%';
                }
            }
        }

        if ($logicalOperator === 'AND' && count($tags) > 1 && !$notTags) {
            $body .= ' GROUP BY ' . $lkTagTable . '.' . $idColumn . ' HAVING count(' . $lkTagTable .'.'. $lkTagTableIdColumn .') = ' . count($tags);//@phpcs:ignore
        }

        $body .= ' ) ';
    }
}
