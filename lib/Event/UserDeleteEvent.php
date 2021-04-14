<?php


namespace Xibo\Event;


use Xibo\Entity\User;

class UserDeleteEvent extends Event
{
    public static $NAME = 'user.delete.event';

    /** @var User */
    private $user;

    /**
     * MediaDeleteEvent constructor.
     * @param $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser() : User
    {
        return $this->user;
    }
}