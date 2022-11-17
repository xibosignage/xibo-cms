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
use Xibo\Event\DayPartDeleteEvent;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Campaign events
 */
class CampaignListener
{
    use ListenerLoggerTrait;

    /** @var \Xibo\Factory\CampaignFactory */
    private $campaignFactory;

    /** @var \Xibo\Storage\StorageServiceInterface  */
    private $storageService;

    public function __construct(
        CampaignFactory $campaignFactory,
        StorageServiceInterface $storageService
    ) {
        $this->campaignFactory = $campaignFactory;
        $this->storageService = $storageService;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): CampaignListener
    {
        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'campaign', [$this, 'onParsePermissions']);
        $dispatcher->addListener(FolderMovingEvent::$NAME, [$this, 'onFolderMoving']);
        $dispatcher->addListener(UserDeleteEvent::$NAME, [$this, 'onUserDelete']);
        $dispatcher->addListener(DayPartDeleteEvent::$NAME, [$this, 'onDayPartDelete']);
        $dispatcher->addListener(TagDeleteEvent::$NAME, [$this, 'onTagDelete']);
        return $this;
    }

    /**
     * Parse permissions
     * @param \Xibo\Event\ParsePermissionEntityEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onParsePermissions(ParsePermissionEntityEvent $event)
    {
        $this->getLogger()->debug('onParsePermissions');
        $event->setObject($this->campaignFactory->getById($event->getObjectId()));
    }

    /**
     * When we're moving a folder, update our folderId/permissions folder id
     * @param \Xibo\Event\FolderMovingEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onFolderMoving(FolderMovingEvent $event)
    {
        $folder = $event->getFolder();
        $newFolder = $event->getNewFolder();

        foreach ($this->campaignFactory->getByFolderId($folder->getId()) as $campaign) {
            // update campaign record
            $campaign->folderId = $newFolder->id;
            $campaign->permissionsFolderId = $newFolder->getPermissionFolderIdOrThis();
            $campaign->updateFolders('campaign');
        }
    }

    /**
     * User is being deleted, tidy up their campaigns
     * @param \Xibo\Event\UserDeleteEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onUserDelete(UserDeleteEvent $event)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();

        if ($function === 'delete') {
            // Delete any Campaigns
            foreach ($this->campaignFactory->getByOwnerId($user->userId) as $campaign) {
                $campaign->delete();
            }
        } else if ($function === 'reassignAll') {
            // Reassign campaigns
            $this->storageService->update('UPDATE `campaign` SET userId = :userId WHERE userId = :oldUserId', [
                'userId' => $newUser->userId,
                'oldUserId' => $user->userId
            ]);
        } else if ($function === 'countChildren') {
            $campaigns = $this->campaignFactory->getByOwnerId($user->userId);

            $count = count($campaigns);
            $this->getLogger()->debug(
                sprintf(
                    'Counted Children Campaign on User ID %d, there are %d',
                    $user->userId,
                    $count
                )
            );

            $event->setReturnValue($event->getReturnValue() + $count);
        }
    }

    /**
     * Days parts might be assigned to lkcampaignlayout records.
     * @param \Xibo\Event\DayPartDeleteEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function onDayPartDelete(DayPartDeleteEvent $event)
    {
        // We can't delete dayparts that are in-use on advertising campaigns.
        if ($this->storageService->exists('
            SELECT lkCampaignLayoutId
              FROM `lkcampaignlayout`
             WHERE dayPartId = :dayPartId
            LIMIT 1
        ', [
            'dayPartId' => $event->getDayPart()->dayPartId,
        ])) {
            throw new InvalidArgumentException(__('This is inuse and cannot be deleted.'), 'dayPartId');
        }
    }

    /**
     * When Tag gets deleted, remove any campaign links from it.
     * @param TagDeleteEvent $event
     * @return void
     */
    public function onTagDelete(TagDeleteEvent $event)
    {
        $this->storageService->update(
            'DELETE FROM `lktagcampaign` WHERE `lktagcampaign`.tagId = :tagId',
            ['tagId' => $event->getTagId()]
        );
    }
}
