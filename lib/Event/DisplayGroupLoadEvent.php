<?php


namespace Xibo\Event;


use Xibo\Entity\DisplayGroup;

class DisplayGroupLoadEvent extends Event
{
    public static $NAME = 'display.group.load.event';
    /**
     * @var DisplayGroup
     */
    private $displayGroup;

    public function __construct(DisplayGroup $displayGroup)
    {
        $this->displayGroup = $displayGroup;
    }

    public function getDisplayGroup()
    {
        return $this->displayGroup;
    }
}