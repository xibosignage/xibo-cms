<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardFactory extends BaseFactory
{
    /** @var  ConfigServiceInterface */
    private $config;

    /** @var PoolInterface */
    private $pool;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var MenuBoardCategoryFactory
     */
    private $menuBoardCategoryFactory;

    private $sanitizerService;

    /** @var DisplayFactory */
    private $displayFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param PermissionFactory $permissionFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct(
        $store,
        $log,
        $sanitizerService,
        $user,
        $userFactory,
        $config,
        $pool,
        $permissionFactory,
        $menuBoardCategoryFactory,
        $displayFactory
    ) {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->config = $config;
        $this->pool = $pool;

        $this->permissionFactory = $permissionFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * Create Empty
     * @return MenuBoard
     */
    public function createEmpty()
    {
        return new MenuBoard(
            $this->getStore(),
            $this->getLog(),
            $this->sanitizerService,
            $this->pool,
            $this->config,
            $this->permissionFactory,
            $this->menuBoardCategoryFactory,
            $this->displayFactory
        );
    }

    /**
     * Create a new action
     * @param string $name
     * @param string $description
     * @param int $folderId
     * @return MenuBoard
     */
    public function create($name, $description, $folderId)
    {
        $menuBoard = $this->createEmpty();
        $menuBoard->name = $name;
        $menuBoard->description = $description;
        $menuBoard->userId = $this->getUser()->userId;
        $menuBoard->folderId = $folderId;

        return $menuBoard;
    }

    /**
     * @param int $menuId
     * @return MenuBoard
     * @throws NotFoundException
     */
    public function getById(int $menuId)
    {
        $this->getLog()->debug('MenuBoardFactory getById ' . $menuId);

        $menuBoards = $this->query(null, ['disableUserCheck' => 1, 'menuId' => $menuId]);

        if (count($menuBoards) <= 0) {
            $this->getLog()->debug('Menu Board not found with ID ' . $menuId);
            throw new NotFoundException(__('Menu Board not found'));
        }

        return $menuBoards[0];
    }

    /**
     * @param int $menuCategoryId
     * @return MenuBoard
     * @throws NotFoundException
     */
    public function getByMenuCategoryId(int $menuCategoryId)
    {
        $menuBoards = $this->query(null, ['disableUserCheck' => 1, 'menuCategoryId' => $menuCategoryId]);

        if (count($menuBoards) <= 0) {
            $this->getLog()->debug('Menu Board not found with Menu Board Category ID ' . $menuCategoryId);
            throw new NotFoundException(__('Menu Board not found'));
        }

        return $menuBoards[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return MenuBoard[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['menuId DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT 
               `menu_board`.menuId,
               `menu_board`.name,
               `menu_board`.description,
               `menu_board`.modifiedDt,
               `menu_board`.userId,
               `user`.UserName AS owner,
               `menu_board`.folderId,
               `menu_board`.permissionsFolderId,
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
        ';

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' INNER JOIN `menu_category` ON `menu_category`.menuId = `menu_board`.menuId ';
        }

        $body .= ' WHERE 1 = 1 ';
        $this->viewPermissionSql('Xibo\Entity\MenuBoard', $body, $params, 'menu_board.menuId', 'menu_board.userId', $filterBy, '`menu_board`.permissionsFolderId');

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
            $this->nameFilter('menu_board', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= ' AND `menu_board`.folderId = :folderId ';
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' AND `menu_category`.menuCategoryId = :menuCategoryId ';
            $params['menuCategoryId'] = $sanitizedFilter->getInt('menuCategoryId');
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $menuBoard = $this->createEmpty()->hydrate($row);
            $entries[] = $menuBoard;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
