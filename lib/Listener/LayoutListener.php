<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Xibo\Entity\Layout;
use Xibo\Entity\Region;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\LayoutOwnerChangeEvent;
use Xibo\Event\LayoutSharingChangeEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\PlaylistDeleteEvent;
use Xibo\Event\RegionAddedEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Storage\StorageServiceInterface;

/**
 * Layout events
 */
class LayoutListener
{
    use ListenerLoggerTrait;

    /**
     * @param LayoutFactory $layoutFactory
     * @param StorageServiceInterface $storageService
     * @param \Xibo\Factory\PermissionFactory $permissionFactory
     */
    public function __construct(
        private readonly LayoutFactory $layoutFactory,
        private readonly StorageServiceInterface $storageService,
        private readonly PermissionFactory $permissionFactory
    ) {
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
        $dispatcher->addListener(PlaylistDeleteEvent::$NAME, [$this, 'onPlaylistDelete']);
        $dispatcher->addListener(LayoutSharingChangeEvent::$NAME, [$this, 'onLayoutSharingChange']);
        $dispatcher->addListener(RegionAddedEvent::$NAME, [$this, 'onRegionAdded']);
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

        // do we have any full screen Layout linked to this Media item?
        $linkedLayout = $this->layoutFactory->getLinkedFullScreenLayout('media', $media->mediaId);

        if (!empty($linkedLayout)) {
            $linkedLayout->delete();
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

    /**
     * @param PlaylistDeleteEvent $event
     * @return void
     */
    public function onPlaylistDelete(PlaylistDeleteEvent $event)
    {
        $playlist = $event->getPlaylist();

        // do we have any full screen Layout linked to this playlist?
        $layout = $this->layoutFactory->getLinkedFullScreenLayout('playlist', $playlist->playlistId);

        if (!empty($layout)) {
            $layout->delete();
        }
    }

    /**
     * @param \Xibo\Event\LayoutSharingChangeEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function onLayoutSharingChange(LayoutSharingChangeEvent $event): void
    {
        // Check to see if this Campaign has any Canvas regions
        $layouts = $this->layoutFactory->getByCampaignId($event->getCampaignId(), false, true);
        foreach ($layouts as $layout) {
            $layout->load([
                'loadPlaylists' => false,
                'loadPermissions' => false,
                'loadCampaigns' => false,
                'loadActions' => false,
            ]);

            foreach ($layout->regions as $region) {
                if ($region->type === 'canvas') {
                    $event->addCanvasRegionId($region->getId());
                }
            }
        }
    }

    /**
     * @param \Xibo\Event\RegionAddedEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function onRegionAdded(RegionAddedEvent $event): void
    {
        if ($event->getRegion()->type === 'canvas') {
            // Set this layout's permissions on the canvas region
            $entityId = $this->permissionFactory->getEntityId(Region::class);
            foreach ($event->getLayout()->permissions as $permission) {
                $new = clone $permission;
                $new->entityId = $entityId;
                $new->objectId = $event->getRegion()->getId();
                $new->save();
            }
        }
    }
}
