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


namespace Xibo\Entity;
use OpenApi\Attributes as OA;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Permission
 * @package Xibo\Entity
 */
#[OA\Schema]
class Permission implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The ID of this Permission Record')]
    public $permissionId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Entity ID that this Permission refers to')]
    public $entityId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The User Group ID that this permission refers to')]
    public $groupId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The object ID that this permission refers to')]
    public $objectId;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether the groupId refers to a user specific group')]
    public $isUser;

    /**
     * @var string
     */
    #[OA\Property(description: 'The entity name that this refers to')]
    public $entity;

    /**
     * @var string
     */
    #[OA\Property(description: 'Legacy for when the Object ID is a string')]
    public $objectIdString;

    /**
     * @var string
     */
    #[OA\Property(description: 'The group name that this refers to')]
    public $group;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether view permission is granted')]
    public $view;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether edit permission is granted')]
    public $edit;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether delete permission is granted')]
    public $delete;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether modify permission permission is granted.')]
    public $modifyPermissions;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone()
    {
        $this->permissionId = null;
    }

    /**
     * Save this permission
     * @return void
     */
    public function save(): void
    {
        if ($this->permissionId == 0) {
            // Check there is something to add
            if ($this->view != 0 || $this->edit != 0 || $this->delete != 0) {
                $this->getLog()->debug(sprintf(
                    'save: Adding Permission for %s, %d. GroupId: %d - View = %d, Edit = %d, Delete = %d',
                    $this->entity,
                    $this->objectId,
                    $this->groupId,
                    $this->view,
                    $this->edit,
                    $this->delete,
                ));

                $this->add();
            }
        } else {
            $this->getLog()->debug(sprintf(
                'save: Editing Permission for %s, %d. GroupId: %d - View = %d, Edit = %d, Delete = %d',
                $this->entity,
                $this->objectId,
                $this->groupId,
                $this->view,
                $this->edit,
                $this->delete,
            ));

            // If all permissions are set to 0, then we delete the record to tidy up
            if ($this->view == 0 && $this->edit == 0 && $this->delete == 0) {
                $this->delete();
            } else if (count($this->getChangedProperties()) > 0) {
                // Something has changed, so run the update.
                $this->update();
            }
        }
    }

    private function add()
    {
        $this->permissionId = $this->getStore()->insert('INSERT INTO `permission` (`entityId`, `groupId`, `objectId`, `view`, `edit`, `delete`) VALUES (:entityId, :groupId, :objectId, :view, :edit, :delete)', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId,
            'view' => $this->view,
            'edit' => $this->edit,
            'delete' => $this->delete,
        ));
    }

    private function update()
    {
        $this->getStore()->update('UPDATE `permission` SET `view` = :view, `edit` = :edit, `delete` = :delete WHERE `entityId` = :entityId AND `groupId` = :groupId AND `objectId` = :objectId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId,
            'view' => $this->view,
            'edit' => $this->edit,
            'delete' => $this->delete,
        ));
    }

    public function delete()
    {
        $this->getLog()->debug(sprintf('Deleting Permission for %s, %d', $this->entity, $this->objectId));
        $this->getStore()->update('DELETE FROM `permission` WHERE entityId = :entityId AND objectId = :objectId AND groupId = :groupId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId
        ));
    }

    public function deleteAll()
    {
        $this->getStore()->update('DELETE FROM `permission` WHERE entityId = :entityId AND objectId = :objectId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
        ));
    }
}