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

namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class Folder
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Folder")
     * @var int
     */
    public $id;

    /**
     * @SWG\Property(description="The name of this Folder")
     * @var string
     */
    public $text;

    /**
     * @SWG\Property(description="The folderId of the parent of this Folder")
     * @var int
     */
    public $parentId;

    /**
     * @SWG\Property(description="Flag indicating whether this is root Folder")
     * @var int
     */
    public $isRoot;

    /**
     * @SWG\Property(description="An array of children folderIds")
     * @var string
     */
    public $children;

    public $permissionsFolderId;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param FolderFactory $folderFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $folderFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->setPermissionsClass('Xibo\Entity\Folder');
        $this->folderFactory = $folderFactory;
        $this->permissionFactory = $permissionFactory;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId()
    {
        return null;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function isRoot()
    {
        return ($this->isRoot === 1) ? true : false;
    }

    public function getChildren()
    {
        return explode(',', $this->children);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->text)) {
            throw new InvalidArgumentException(__('Folder needs to have a name, between 1 and 254 characters.'), 'folderName');
        }
    }

    /**
     * @param bool $validate
     * @throws InvalidArgumentException
     */
    public function save($validate = true)
    {
        if ($validate) {
            $this->validate();
        }

        if ($this->id == null || $this->id == 0) {
            $this->add();
        } else {
            $this->edit();
        }


    }

    public function delete()
    {
        $this->manageChildren('delete');

        $this->getStore()->update('DELETE FROM `folders` WHERE folderId = :folderId', [
            'folderId' => $this->id
        ]);
    }

    private function add()
    {
        $this->id = $this->getStore()->insert('INSERT INTO `folders` (folderName, parentId, isRoot) VALUES (:folderName, :parentId, :isRoot)',
            [
                'folderName' => $this->text,
                'parentId' => $this->parentId,
                'isRoot' => 0
            ]);

        $this->manageChildren('add');
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `folders` SET folderName = :folderName, parentId = :parentId WHERE folderId = :folderId',
            [
                'folderId' => $this->id,
                'folderName' => $this->text,
                'parentId' => $this->parentId
            ]);
    }

    private function manageChildren($mode)
    {
        $parent = $this->folderFactory->getById($this->parentId);
        $parentChildren = array_filter(explode(',', $parent->children));
        $children = array_filter(explode(',', $this->children));

        if ($mode === 'delete') {

            // remove this folder from children of the parent
            foreach ($parentChildren as $index => $child) {
                if ((int)$child === (int)$this->id) {
                    unset($parentChildren[$index]);
                }
            }

            // remove this folder children
            foreach ($children as $child) {
                $childObject = $this->folderFactory->getById($child);
                $childObject->manageChildren('delete');
                $this->getStore()->update('DELETE FROM `folders` WHERE folderId = :folderId', [
                    'folderId' => $childObject->id
                ]);
            }
        } else {
            $parentChildren[] = $this->id;
        }

        $updatedChildren = implode(',', array_filter($parentChildren));

        $this->getStore()->update('UPDATE `folders` SET children = :children WHERE folderId = :folderId',
            [
                'folderId' => $this->parentId,
                'children' => $updatedChildren
            ]);
    }
}