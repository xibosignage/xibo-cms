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
use Xibo\Entity\Layout;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\LayoutOwnerChangeEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Storage\StorageServiceInterface;

/**
 * Layout events
 */
class LayoutListener
{
    use ListenerLoggerTrait;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    /**
     * @param LayoutFactory $layoutFactory
     * @param StorageServiceInterface $storageService
     */
    public function __construct(
        LayoutFactory $layoutFactory,
        StorageServiceInterface $storageService
    ) {
        $this->layoutFactory = $layoutFactory;
        $this->storageService = $storageService;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher) : LayoutListener
    {
        $dispatcher->addListener(MediaDeleteEvent::$NAME, [$this, 'onMediaDelete']);
        $dispatcher->addListener(UserDeleteEvent::$NAME, [$this, 'onUserDelete']);
        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, [$this, 'onDisplayGroupLoad']);
        $dispatcher->addListener(MediaFullLoadEvent::$NAME, [$this, 'onMediaLoad']);
        $dispatcher->addListener(LayoutOwnerChangeEvent::$NAME, [$this, 'onOwnerChange']);
        $dispatcher->addListener(TagDeleteEvent::$NAME, [$this, 'onTagDelete']);
        return $this;
    }

    /**
     * @param MediaDeleteEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onMediaDelete(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->layoutFactory->getByBackgroundImageId($media->mediaId) as $layout) {
            if ($media->mediaType == 'image' && $parentMedia != null) {
                $this->getLogger()->debug(sprintf(
                    'Updating layouts with the old media %d as the background image.',
                    $media->mediaId
                ));
                $this->getLogger()->debug(sprintf(
                    'Found layout that needs updating. ID = %d. Setting background image id to %d',
                    $layout->layoutId,
                    $parentMedia->mediaId
                ));

                $layout->backgroundImageId = $parentMedia->mediaId;
            } else {
                $layout->backgroundImageId = null;
            }

            $layout->save(Layout::$saveOptionsMinimum);
        }
    }

    /**
     * @param UserDeleteEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onUserDelete(UserDeleteEvent $event)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();

        if ($function === 'delete') {
            // Delete any Layouts
            foreach ($this->layoutFactory->getByOwnerId($user->userId) as $layout) {
                $layout->delete();
            }
        } else if ($function === 'reassignAll') {
            // Reassign layouts, regions, region Playlists and Widgets.
            foreach ($this->layoutFactory->getByOwnerId($user->userId) as $layout) {
                $layout->setOwner($newUser->userId, true);
                $layout->save(['notify' => false, 'saveTags' => false, 'setBuildRequired' => false]);
            }
        } else if ($function === 'countChildren') {
            $layouts = $this->layoutFactory->getByOwnerId($user->userId);

            $count = count($layouts);
            $this->getLogger()->debug(
                sprintf(
                    'Counted Children Layouts on User ID %d, there are %d',
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onDisplayGroupLoad(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();

        $displayGroup->layouts = ($displayGroup->displayGroupId != null)
            ? $this->layoutFactory->getByDisplayGroupId($displayGroup->displayGroupId)
            : [];
    }

    /**
     * @param MediaFullLoadEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onMediaLoad(MediaFullLoadEvent $event)
    {
        $media = $event->getMedia();

        $media->layoutBackgroundImages = $this->layoutFactory->getByBackgroundImageId($media->mediaId);
    }

    /**
     * @param LayoutOwnerChangeEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onOwnerChange(LayoutOwnerChangeEvent $event)
    {
        $campaignId = $event->getCampaignId();
        $ownerId = $event->getOwnerId();

        foreach ($this->layoutFactory->getByCampaignId($campaignId, true, true) as $layout) {
            $layout->setOwner($ownerId, true);
            $layout->save(['notify' => false]);
        }
    }

    /**
     * @param TagDeleteEvent $event
     * @return void
     */
    public function onTagDelete(TagDeleteEvent $event)
    {
        $this->storageService->update(
            'DELETE FROM `lktaglayout` WHERE `lktaglayout`.tagId = :tagId',
            ['tagId' => $event->getTagId()]
        );
    }
}
