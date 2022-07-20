<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class MediaListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;
    /**
     * @var EventDispatcher
     */
    private $dispatcher;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService, MediaFactory $mediaFactory)
    {
        $this->storageService = $storageService;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();
        $systemUser = $event->getSystemUser();

        if ($function === 'delete') {
            $this->deleteChildren($user, $dispatcher, $systemUser);
        } elseif ($function === 'reassignAll') {
            $this->reassignAllTo($user, $newUser, $systemUser);
        } elseif ($function === 'countChildren') {
            $event->setReturnValue($event->getReturnValue() + $this->countChildren($user));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher, User $systemUser)
    {
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
                $dispatcher->dispatch(MediaDeleteEvent::$NAME, new MediaDeleteEvent($media, $parentMedia, true));
                $media->delete();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        foreach ($this->mediaFactory->getByOwnerId($user->userId, 1) as $media) {
            ($media->mediaType === 'module') ? $media->setOwner($systemUser->userId) : $media->setOwner($newUser->getOwnerId());
            $media->save();
        }
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $media = $this->mediaFactory->getByOwnerId($user->userId, 1);
        $count = count($media);
        $this->getLogger()->debug(sprintf('Counted Children Media on User ID %d, there are %d', $user->userId, $count));

        return $count;
    }
}
