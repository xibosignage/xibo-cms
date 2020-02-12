<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BaseFactory.php)
 */


namespace Xibo\Factory;


use Slim\Http\ServerRequest as Request;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
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
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
    }

    /**
     * Get User
     * @return User
     */
    public function getUser(Request $request = null)
    {
            if (isset($request)) {
                $this->user = $request->getAttribute('currentUser');
            }

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
    public function viewPermissionSql($entity, &$sql, &$params, $idColumn, $ownerColumn = null, $filterBy = [], Request $request = null)
    {
        $parsedBody = $this->getSanitizer($filterBy);
        $checkUserId = $parsedBody->getInt('userCheckUserId');

        if ($checkUserId !== null) {
            $this->getLog()->debug(sprintf('Checking permissions against a specific user: %d', $checkUserId));
            $user = $this->getUserFactory()->getById($checkUserId);
        }
        else {
            $user = $this->getUser($request);

            if ($user !== null)
                $this->getLog()->debug(sprintf('Checking permissions against the logged in user: ID: %d, Name: %s, UserType: %d', $user->userId, $user->userName, $user->userTypeId));
        }

        $permissionSql = '';

        if ($parsedBody->getCheckbox('disableUserCheck') == 0 && (!$user->isSuperAdmin())) {
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

    /**
     * Sets the name filter for all factories to use.
     *
     * @param string $tableName Table name
     * @param string $tableColumn Column with the name
     * @param array $terms An Array exploded by "," of the search names
     * @param string $body Current SQL body passed by reference
     * @param array $params Array of parameters passed by reference
     */
    public function nameFilter($tableName, $tableColumn, $terms, &$body, &$params)
    {
        $i = 0;
        $j = 0;
        $searchNames = [];
        $tableAndColumn = $tableName . '.' . $tableColumn;
        // Convert into commas
        foreach ($terms as $term) {
            // convert into a space delimited array
            $names = explode(' ', $term);
            // filter empty array elements, in an attempt to better handle spaces after `,`.
            $filteredNames = array_filter($names);

            foreach ($filteredNames as $searchName) {
                $i++;
                if (!isset($filteredNames[0])) {
                    $j = 1;
                }
                // store searchName array
                $searchNames[] = $searchName;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    if ($i == 1) {
                        $body .= " AND ( $tableAndColumn NOT RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote(ltrim(($searchName), '-'));
                    } elseif ( (count($filteredNames) > 1 && $filteredNames[$j] != $searchName) || strpos($searchNames[$i-1], '-') !== false ) {
                        $body .= " AND $tableAndColumn NOT RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote(ltrim(($searchName), '-'));
                    } else {
                        $body .= " OR $tableAndColumn NOT RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote(ltrim(($searchName), '-'));
                    }
                } else {
                    if ($i === 1) {
                        $body .= " AND ( $tableAndColumn RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote($searchName);
                    } elseif (count($filteredNames) > 1 && $filteredNames[$j] != $searchName) {
                        $body .= " AND $tableAndColumn RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote($searchName);
                    } else {
                        $body .= " OR  $tableAndColumn RLIKE (:search$i) ";
                        $params['search' . $i] = preg_quote($searchName);
                    }
                }
            }
        }
        $body .= ' ) ';
    }

    /**
     * @param array $tags An array of tags
     * @param string $operator exactTags passed from factory, determines if the search is LIKE or =
     * @param string $body Current SQL body passed by reference
     * @param array $params Array of parameters passed by reference
     */
    public function tagFilter($tags, $operator, &$body, &$params)
    {
        $i = 0;

        foreach ($tags as $tag) {
            $i++;

            $tagV = explode('|', $tag);

            // search tag without value
            if (!isset($tagV[1])) {
                if ($i == 1) {
                    $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                } else {
                    $body .= ' OR `tag` ' . $operator . ' :tags' . $i;
                }

                if ($operator === '=') {
                    $params['tags' . $i] = $tag;
                } else {
                    $params['tags' . $i] = '%' . $tag . '%';
                }
                // search tag only by value
            } elseif ($tagV[0] == '') {
                if ($i == 1) {
                    $body .= ' WHERE `value` ' . $operator . ' :value' . $i;
                } else {
                    $body .= ' OR `value` ' . $operator . ' :value' . $i;
                }

                if ($operator === '=') {
                    $params['value' . $i] = $tagV[1];
                } else {
                    $params['value' . $i] = '%' . $tagV[1] . '%';
                }
                // search tag by both tag and value
            } else {
                if ($i == 1) {
                    $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i .
                        ' AND value ' . $operator . ' :value' . $i;
                } else {
                    $body .= ' OR `tag` ' . $operator . ' :tags' . $i .
                        ' AND value ' . $operator . ' :value' . $i;
                }

                if ($operator === '=') {
                    $params['tags' . $i] = $tagV[0];
                    $params['value' . $i] = $tagV[1];
                } else {
                    $params['tags' . $i] = '%' . $tagV[0] . '%';
                    $params['value' . $i] = '%' . $tagV[1] . '%';
                }
            }
        }
        $body .= ' ) ';
    }
}