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
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Media events
 */
class MediaListener
{
    use ListenerLoggerTrait;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    /**
     * @param MediaFactory $mediaFactory
     * @param StorageServiceInterface $storageService
     */
    public function __construct(
        MediaFactory $mediaFactory,
        StorageServiceInterface $storageService
    ) {
        $this->mediaFactory = $mediaFactory;
        $this->storageService = $storageService;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher) : MediaListener
    {
        $dispatcher->addListener(UserDeleteEvent::$NAME, [$this, 'onUserDelete']);
        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, [$this, 'onDisplayGroupLoad']);
        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'media', [$this, 'onParsePermissions']);
        $dispatcher->addListener(FolderMovingEvent::$NAME, [$this, 'onFolderMoving']);
        $dispatcher->addListener(TagDeleteEvent::$NAME, [$this, 'onTagDelete']);

        return $this;
    }

    /**
     * @param UserDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return void
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function onUserDelete(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();
        $systemUser = $event->getSystemUser();

        if ($function === 'delete') {
            // Delete any media
            foreach ($this->mediaFactory->getByOwnerId($user->userId, 1) as $media) {
                // If there is a parent, bring it back
                try {
                    $parentMedia = $this->mediaFactory->getParentById($media->mediaId);
                    $parentMedia->isEdited = 0;
                    $parentMedia->parentId = null;
                    $parentMedia->save(['validate' => false]);
                } catch (NotFoundException $e) {
                    // This is fine, no parent
                    $parentMedia = null;
                }

                // if this User owns any module files, reassign to systemUser instead of deleting.
                if ($media->mediaType === 'module') {
                    $media->setOwner($systemUser->userId);
                    $media->save();
                } else {
                    $dispatcher->dispatch(new MediaDeleteEvent($media, $parentMedia, true), MediaDeleteEvent::$NAME);
                    $media->delete();
                }
            }
        } else if ($function === 'reassignAll') {
            foreach ($this->mediaFactory->getByOwnerId($user->userId, 1) as $media) {
                ($media->mediaType === 'module') ? $media->setOwner($systemUser->userId) : $media->setOwner($newUser->getOwnerId());
                $media->save();
            }
        } else if ($function === 'countChildren') {
            $media = $this->mediaFactory->getByOwnerId($user->userId, 1);

            $count = count($media);
            $this->getLogger()->debug(
                sprintf(
                    'Counted Children Media on User ID %d, there are %d',
                    $user->userId,
                    $count
                )
            );

            $event->setReturnValue($event->getReturnValue() + $count);
        }
    }

    /**
     * @param DisplayGroupLoadEvent $event
     * @return void
     * @throws NotFoundException
     */
    public function onDisplayGroupLoad(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();

        $displayGroup->media = ($displayGroup->displayGroupId != null)
            ? $this->mediaFactory->getByDisplayGroupId($displayGroup->displayGroupId)
            : [];
    }

    /**
     * @param ParsePermissionEntityEvent $event
     * @return void
     * @throws NotFoundException
     */
    public function onParsePermissions(ParsePermissionEntityEvent $event)
    {
        $this->getLogger()->debug('onParsePermissions');
        $event->setObject($this->mediaFactory->getById($event->getObjectId()));
    }

    /**
     * @param FolderMovingEvent $event
     * @return void
     * @throws NotFoundException
     */
    public function onFolderMoving(FolderMovingEvent $event)
    {
        $folder = $event->getFolder();
        $newFolder = $event->getNewFolder();

        foreach ($this->mediaFactory->getByFolderId($folder->getId()) as $media) {
            $media->folderId = $newFolder->getId();
            $media->permissionsFolderId = $newFolder->getPermissionFolderIdOrThis();
            $media->updateFolders('media');
        }
    }

    /**
     * @param TagDeleteEvent $event
     * @return void
     */
    public function onTagDelete(TagDeleteEvent $event)
    {
        $this->storageService->update(
            'DELETE FROM `lktagmedia` WHERE `lktagmedia`.tagId = :tagId',
            ['tagId' => $event->getTagId()]
        );
    }
}
