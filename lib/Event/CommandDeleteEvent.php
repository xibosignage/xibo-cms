<?php


namespace Xibo\Event;


use Xibo\Entity\Command;

class CommandDeleteEvent extends Event
{
    public static $NAME = 'command.delete.event';
    /**
     * @var Command
     */
    private $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function getCommand(): Command
    {
        return $this->command;
    }
}
