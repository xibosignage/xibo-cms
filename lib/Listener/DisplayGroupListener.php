<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

namespace Xibo\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\TagEditEvent;
use Xibo\Event\TriggerTaskEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * DisplayGroup events
 */
class DisplayGroupListener
{
    use ListenerLoggerTrait;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    /**
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param StorageServiceInterface $storageService
     */
    public function __construct(
        DisplayGroupFactory $displayGroupFactory,
        DisplayFactory $displayFactory,
        StorageServiceInterface $storageService
    ) {
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->storageService = $storageService;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): DisplayGroupListener
    {
        $dispatcher->addListener(MediaDeleteEvent::$NAME, [$this, 'onMediaDelete']);
        $dispatcher->addListener(UserDeleteEvent::$NAME, [$this, 'onUserDelete']);
        $dispatcher->addListener(MediaFullLoadEvent::$NAME, [$this, 'onMediaLoad']);
        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'displayGroup', [$this, 'onParsePermissions']);
        $dispatcher->addListener(FolderMovingEvent::$NAME, [$this, 'onFolderMoving']);
        $dispatcher->addListener(TagDeleteEvent::$NAME, [$this, 'onTagDelete']);
        $dispatcher->addListener(TagEditEvent::$NAME, [$this, 'onTagEdit']);

        return $this;
    }

    /**
     * @param MediaDeleteEvent $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onMediaDelete(MediaDeleteEvent $event, string $eventName, EventDispatcherInterface $dispatcher)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->displayGroupFactory->getByMediaId($media->mediaId) as $displayGroup) {
            $dispatcher->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
            $displayGroup->load();
            $displayGroup->unassignMedia($media);
            if ($parentMedia != null) {
                $displayGroup->assignMedia($parentMedia);
            }

            $displayGroup->save(['validate' => false]);
        }
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
        $systemUser = $event->getSystemUser();

        if ($function === 'delete') {
            // we do not want to delete Display specific Display Groups, reassign to systemUser instead.
            foreach ($this->displayGroupFactory->getByOwnerId($user->userId, -1) as $displayGroup) {
                if ($displayGroup->isDisplaySpecific === 1) {
                    $displayGroup->setOwner($systemUser->userId);
                    $displayGroup->save(['saveTags' => false, 'manageDynamicDisplayLinks' => false]);
                } else {
                    $displayGroup->load();
                    $dispatcher->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
                    $displayGroup->delete();
                }
            }
        } else if ($function === 'reassignAll') {
            foreach ($this->displayGroupFactory->getByOwnerId($user->userId, -1) as $displayGroup) {
                ($displayGroup->isDisplaySpecific === 1) ? $displayGroup->setOwner($systemUser->userId) : $displayGroup->setOwner($newUser->getOwnerId());
                $displayGroup->save(['saveTags' => false, 'manageDynamicDisplayLinks' => false]);
            }
        } else if ($function === 'countChildren') {
            $displayGroups = $this->displayGroupFactory->getByOwnerId($user->userId, -1);

            $count = count($displayGroups);
            $this->getLogger()->debug(
                sprintf(
                    'Counted Children Display Groups on User ID %d, there are %d',
                    $user->userId,
                    $count
                )
            );

            $event->setReturnValue($event->getReturnValue() + $count);
        }
    }

    /**
     * @param MediaFullLoadEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onMediaLoad(MediaFullLoadEvent $event)
    {
        $media = $event->getMedia();

        $media->displayGroups = $this->displayGroupFactory->getByMediaId($media->mediaId);
    }

    /**
     * @param ParsePermissionEntityEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onParsePermissions(ParsePermissionEntityEvent $event)
    {
        $this->getLogger()->debug('onParsePermissions');
        $event->setObject($this->displayGroupFactory->getById($event->getObjectId()));
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

        foreach ($this->displayGroupFactory->getbyFolderId($folder->getId()) as $displayGroup) {
            $displayGroup->folderId = $newFolder->getId();
            $displayGroup->permissionsFolderId = $newFolder->getPermissionFolderIdOrThis();
            $displayGroup->updateFolders('displaygroup');
        }
    }

    /**
     * @param TagDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public function onTagDelete(TagDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $displays = $this->storageService->select('
            SELECT lktagdisplaygroup.displayGroupId 
                 FROM `lktagdisplaygroup` 
                     INNER JOIN `displaygroup`
                     ON `lktagdisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                         AND `displaygroup`.isDisplaySpecific = 1
                 WHERE `lktagdisplaygroup`.tagId = :tagId', [
                'tagId' => $event->getTagId()
            ]);

        $this->storageService->update(
            'DELETE FROM `lktagdisplaygroup` WHERE `lktagdisplaygroup`.tagId = :tagId',
            ['tagId' => $event->getTagId()]
        );

        if (count($displays) > 0) {
            $dispatcher->dispatch(
                new TriggerTaskEvent('\Xibo\XTR\MaintenanceRegularTask', 'DYNAMIC_DISPLAY_GROUP_ASSESSED'),
                TriggerTaskEvent::$NAME
            );
        }
    }

    /**
     * Update dynamic display groups' dynamicCriteriaTags when a tag is edited from the tag administration.
     *
     * @param TagEditEvent $event
     * @return void
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function onTagEdit(TagEditEvent $event): void
    {
        // Retrieve all dynamic display groups
        $displayGroups = $this->displayGroupFactory->getByIsDynamic(1);

        foreach ($displayGroups as $displayGroup) {
            // Convert the tag string into an array for easier processing
            $tags = explode(',', $displayGroup->dynamicCriteriaTags);

            $displayGroup->setDisplayFactory($this->displayFactory);

            foreach ($tags as &$tag) {
                // If the current tag matches the old tag, replace it with the new one
                if (trim($tag) == $event->getOldTag()) {
                    $tag = $event->getNewTag();

                    // Convert the updated tag array back to a string and update the field
                    $displayGroup->dynamicCriteriaTags = implode(',', $tags);
                    $displayGroup->save();
                }
            }
        }
    }
}
