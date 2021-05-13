<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


use Xibo\Entity\Folder;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class FolderFactory extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param PermissionFactory $permissionFactory
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $sanitizerService, $permissionFactory, $user, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->permissionFactory = $permissionFactory;
    }

    /**
     * @return Folder
     */
    public function createEmpty()
    {
        return new Folder(
            $this->getStore(),
            $this->getLog(),
            $this,
            $this->permissionFactory
        );
    }

    /**
     * @param int $folderId
     * @return Folder
     * @throws NotFoundException
     */
    public function getById($folderId, $disableUserCheck = 0)
    {
        $folder = $this->query(null, ['folderId' => $folderId, 'disableUserCheck' => $disableUserCheck]);

        if (count($folder) <= 0) {
            throw new NotFoundException(__('Folder not found'));
        }

        return $folder[0];
    }

    /**
     * @param int $folderId
     * @return Folder
     * @throws NotFoundException
     */
    public function getByParentId($folderId)
    {
        $folder = $this->query(null, ['parentId' => $folderId]);

        if (count($folder) <= 0) {
            throw new NotFoundException(__('Folder not found'));
        }

        return $folder[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Folder[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $select = 'SELECT `folderId` as id, `folderName` as text, `parentId`, `isRoot`, `children`, `permissionsFolderId` ';

        $body = '
          FROM `folder`
         WHERE 1 = 1 ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Folder', $body, $params, '`folder`.folderId', null, $filterBy, 'folder.permissionsFolderId');

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= ' AND folder.folderId = :folderId ';
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        if ($sanitizedFilter->getInt('parentId') !== null) {
            $body .= ' AND folder.parentId = :parentId ';
            $params['parentId'] = $sanitizedFilter->getInt('parentId');
        }

        if ($sanitizedFilter->getString('folderName') !== null) {
            $body .= ' AND folder.folderName = :folderName ';
            $params['folderName'] = $sanitizedFilter->getString('folderName');
        }

        if ($sanitizedFilter->getInt('isRoot') !== null) {
            $body .= ' AND folder.isRoot = :isRoot ';
            $params['isRoot'] = $sanitizedFilter->getInt('isRoot');
        }

        // for the "grid" ie tree view, we need the root folder to keep the tree structure
        if ($sanitizedFilter->getInt('includeRoot') === 1) {
            $body .= 'OR folder.isRoot = 1';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit .= ' LIMIT ' . intval($sanitizedFilter->getInt('start')) . ', ' . $sanitizedFilter->getInt('length',['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isRoot']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}