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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\PlaylistFactory;
use Xibo\Listener\ListenerLoggerTrait;

class PlaylistListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /** @var PlaylistFactory */
    private $playlistFactory;

    public function __construct(PlaylistFactory $playlistFactory)
    {
        $this->playlistFactory = $playlistFactory;
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
    public function deleteChildren($user, EventDispatcherInterface $dispatcher, User $systemUser)
    {
        // Delete Playlists owned by this user
        foreach ($this->playlistFactory->getByOwnerId($user->userId) as $playlist) {
            $playlist->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        $this->getLogger()->debug(sprintf('There are %d Playlist children', $this->countChildren($user)));
        // Reassign playlists and widgets
        foreach ($this->playlistFactory->getByOwnerId($user->userId) as $playlist) {
            $playlist->setOwner($newUser->userId);
            $playlist->save();
        }

        $this->getLogger()->debug(sprintf('Finished reassign Playlist, there are %d children', $this->countChildren($user)));
    }

    /**
     * @inheritDoc
     */
    public function countChildren($user)
    {
        $playlists = $this->playlistFactory->getByOwnerId($user->userId);

        $count = count($playlists);
        $this->getLogger()->debug(sprintf('Counted Children Playlist on User ID %d, there are %d', $user->userId, $count));

        return $count;
    }
}
