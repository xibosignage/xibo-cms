<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Schedule;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\ScheduleLoadEvent;
use Xibo\Event\ScheduleSaveEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

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
        $dispatcher->addListener(ScheduleSaveEvent::$NAME, [$this, 'onScheduleSave']);
        $dispatcher->addListener(ScheduleLoadEvent::$NAME, [$this, 'onScheduleLoad']);

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

    /**
     * @param ScheduleSaveEvent $event
     * @return void
     * @throws NotFoundException
     */
    public function onScheduleSave(ScheduleSaveEvent $event): void
    {
        $scheduleEvent = $event->getEvent();
        $params = $event->getParams();
        if ($scheduleEvent->eventTypeId === Schedule::$SYNC_EVENT) {
            $syncGroup = $this->syncGroupFactory->getById($scheduleEvent->syncGroupId);

            foreach ($syncGroup->getSyncGroupMembers() as $display) {
                $this->storageService->insert('INSERT INTO `schedule_sync` (`eventId`, `displayId`, `layoutId`)
            VALUES(:eventId, :displayId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = :layoutId', [
                    'eventId' => $scheduleEvent->eventId,
                    'displayId' => $display->displayId,
                    'layoutId' => $params->getInt('layoutId_' . $display->displayId)
                ]);
            }
        }
    }

    public function onScheduleLoad(ScheduleLoadEvent $event)
    {
        $scheduleEvent = $event->getScheduleEvent();

        if ($scheduleEvent->eventTypeId === Schedule::$SYNC_EVENT) {
            $syncGroup = $this->syncGroupFactory->getById($scheduleEvent->syncGroupId);

            $scheduleEvent->setUnmatchedProperty(
                'displayGroupList',
                $syncGroup->name
            );
            $scheduleEvent->setUnmatchedProperty(
                'syncType',
                $syncGroup->getEventSyncType($scheduleEvent->eventId)
            );
        }
    }
}
