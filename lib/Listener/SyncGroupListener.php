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

namespace Xibo\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Storage\StorageServiceInterface;

/**
 * SyncGroup events
 */
class SyncGroupListener
{
    use ListenerLoggerTrait;
    private SyncGroupFactory $syncGroupFactory;
    private StorageServiceInterface $storageService;

    /**
     * @param SyncGroupFactory $syncGroupFactory
     * @param StorageServiceInterface $storageService
     */
    public function __construct(
        SyncGroupFactory $syncGroupFactory,
        StorageServiceInterface $storageService
    ) {
        $this->syncGroupFactory = $syncGroupFactory;
        $this->storageService = $storageService;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): SyncGroupListener
    {
        $dispatcher->addListener(UserDeleteEvent::$NAME, [$this, 'onUserDelete']);
        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'syncGroup', [$this, 'onParsePermissions']);
        $dispatcher->addListener(FolderMovingEvent::$NAME, [$this, 'onFolderMoving']);

        return $this;
    }

    /**
     * @param UserDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onUserDelete(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();

        if ($function === 'delete') {
            // we do not want to delete Display specific Display Groups, reassign to systemUser instead.
            foreach ($this->syncGroupFactory->getByOwnerId($user->userId) as $syncGroup) {
                $syncGroup->delete();
            }
        } else if ($function === 'reassignAll') {
            foreach ($this->syncGroupFactory->getByOwnerId($user->userId) as $syncGroup) {
                $syncGroup->setOwner($newUser->getOwnerId());
                $syncGroup->save();
            }
        } else if ($function === 'countChildren') {
            $syncGroups = $this->syncGroupFactory->getByOwnerId($user->userId);

            $count = count($syncGroups);
            $this->getLogger()->debug(
                sprintf(
                    'Counted Children Sync Groups on User ID %d, there are %d',
                    $user->userId,
                    $count
                )
            );

            $event->setReturnValue($event->getReturnValue() + $count);
        }
    }

    /**
     * @param ParsePermissionEntityEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onParsePermissions(ParsePermissionEntityEvent $event)
    {
        $this->getLogger()->debug('onParsePermissions');
        $event->setObject($this->syncGroupFactory->getById($event->getObjectId()));
    }

    /**
     * @param FolderMovingEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onFolderMoving(FolderMovingEvent $event)
    {
        $folder = $event->getFolder();
        $newFolder = $event->getNewFolder();

        foreach ($this->syncGroupFactory->getByFolderId($folder->getId()) as $syncGroup) {
            $syncGroup->folderId = $newFolder->getId();
            $syncGroup->permissionsFolderId = $newFolder->getPermissionFolderIdOrThis();
            $syncGroup->updateFolders('syncgroup');
        }
    }
}
