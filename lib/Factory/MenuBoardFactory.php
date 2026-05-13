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

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\MenuBoard;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Menu Board Factory
 */
class MenuBoardFactory extends BaseFactory
{
    public function __construct(
        User $user,
        UserFactory $userFactory,
        private readonly ConfigServiceInterface $config,
        private readonly PoolInterface $pool,
        private readonly PermissionFactory $permissionFactory,
        private readonly MenuBoardCategoryFactory $menuBoardCategoryFactory,
        private readonly DisplayNotifyServiceInterface $displayNotifyService,
    ) {
        $this->setAclDependencies($user, $userFactory);
    }

    public function createEmpty(): MenuBoard
    {
        return new MenuBoard(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->getSanitizerService(),
            $this->pool,
            $this->config,
            $this->permissionFactory,
            $this->menuBoardCategoryFactory,
            $this->displayNotifyService
        );
    }

    public function create(string $name, ?string $description, ?string $code): MenuBoard
    {
        $menuBoard = $this->createEmpty();
        $menuBoard->name = $name;
        $menuBoard->description = $description;
        $menuBoard->code = $code;
        $menuBoard->userId = $this->getUser()->userId;

        return $menuBoard;
    }

    /**
     * @throws NotFoundException
     */
    public function getById(int $menuId, bool $disableUserCheck = true): MenuBoard
    {
        $this->getLog()->debug('MenuBoardFactory getById ' . $menuId);

        $menuBoards = $this->query(null, [
            'disableUserCheck' => $disableUserCheck ? 1 : 0,
            'menuId' => $menuId
        ]);

        if (count($menuBoards) <= 0) {
            $this->getLog()->debug('Menu Board not found with ID ' . $menuId);
            throw new NotFoundException(__('Menu Board not found'));
        }

        return $menuBoards[0];
    }

    /**
     * @return MenuBoard[]
     */
    public function getByOwnerId(int $userId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $userId]);
    }

    /**
     * @throws NotFoundException
     */
    public function getByMenuCategoryId(int $menuCategoryId, bool $disableUserCheck = true): MenuBoard
    {
        $menuBoards = $this->query(null, [
            'disableUserCheck' => $disableUserCheck ? 1 : 0,
            'menuCategoryId' => $menuCategoryId
        ]);

        if (count($menuBoards) <= 0) {
            $this->getLog()->debug('Menu Board not found with Menu Board Category ID ' . $menuCategoryId);
            throw new NotFoundException(__('Menu Board not found'));
        }

        return $menuBoards[0];
    }

    /**
     * @return MenuBoard[]
     */
    public function getByFolderId(int $folderId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'folderId' => $folderId]);
    }

    /**
     * @return MenuBoard[]
     * @throws NotFoundException
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT
               `menu_board`.menuId,
               `menu_board`.name,
               `menu_board`.description,
               `menu_board`.code,
               `menu_board`.modifiedDt,
               `menu_board`.userId,
               `user`.UserName AS owner,
               `menu_board`.folderId,
               `menu_board`.permissionsFolderId,
               `folder`.folderName,
               (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityForGroup
                            AND objectId = menu_board.menuId
                            AND view = 1
                        ) AS groupsWithPermissions
            ';
        $params['permissionEntityForGroup'] = 'Xibo\\Entity\\MenuBoard';

        $body = ' FROM menu_board
                     INNER JOIN `user` ON `user`.userId = `menu_board`.userId
                     LEFT OUTER JOIN `folder` ON `menu_board`.folderId = `folder`.folderId
        ';

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' INNER JOIN `menu_category` ON `menu_category`.menuId = `menu_board`.menuId ';
        }

        $body .= ' WHERE 1 = 1 ';
        $this->viewPermissionSql(
            'Xibo\Entity\MenuBoard',
            $body,
            $params,
            'menu_board.menuId',
            'menu_board.userId',
            $filterBy,
            '`menu_board`.permissionsFolderId'
        );

        if ($sanitizedFilter->getInt('menuId') !== null) {
            $body .= ' AND `menu_board`.menuId = :menuId ';
            $params['menuId'] = $sanitizedFilter->getInt('menuId');
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `menu_board`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'menu_board',
                'name',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= ' AND `menu_board`.folderId = :folderId ';
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' AND `menu_category`.menuCategoryId = :menuCategoryId ';
            $params['menuCategoryId'] = $sanitizedFilter->getInt('menuCategoryId');
        }

        if ($sanitizedFilter->getString('code') != '') {
            $body.= ' AND `menu_board`.code LIKE :code ';
            $params['code'] = '%' . $sanitizedFilter->getString('code') . '%';
        }

        if ($sanitizedFilter->getString('keyword') != null) {
            $body .= $this->buildSearchQuery(
                $sanitizedFilter->getString('keyword'),
                $params,
                ['menu_board.name'],
                ['menu_board.menuId']
            );
        }

        if ($sanitizedFilter->getDate('modifiedDateFrom') !== null) {
            $body .= ' AND `menu_board`.modifiedDt >= :modifiedDateFrom ';
            $params['modifiedDateFrom'] = strtotime($sanitizedFilter->getDate('modifiedDateFrom'));
        }

        if ($sanitizedFilter->getDate('modifiedDateTo') !== null) {
            $body .= ' AND `menu_board`.modifiedDt <= :modifiedDateTo ';
            $params['modifiedDateTo'] = strtotime($sanitizedFilter->getDate('modifiedDateTo'));
        }

        $allowedColumns = ['menuId', 'name', 'code', 'modifiedDt', 'owner', 'folderName'];
        $sortOrder = $this->buildSortQuery($sortOrder, $allowedColumns, [], ['name ASC']);
        $order = empty($sortOrder) ? '' : ' ORDER BY ' . implode(', ', $sortOrder);

        $limit = '';
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null &&
            $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' .
                $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
