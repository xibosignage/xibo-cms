<?php

namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;

interface OnUserDeleteInterface
{
    /**
     * Listen to the UserDeleteEvent
     *
     * @param UserDeleteEvent $event
     * @return mixed
     */
    function __invoke(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher);

    /**
     * Delete Objects owned by the User we want to delete
     *
     * @param User $user
     * @return mixed
     */
    function deleteChildren(User $user, EventDispatcherInterface $dispatcher);

    /**
     * Reassign objects to a new User
     *
     * @param User $user
     * @param User $newUser
     * @return mixed
     */
    function reassignAllTo(User $user, User $newUser);

    /**
     * Count Children, return count of objects owned by the User we want to delete
     *
     * @param User $user
     * @return mixed
     */
    function countChildren(User $user);
}
