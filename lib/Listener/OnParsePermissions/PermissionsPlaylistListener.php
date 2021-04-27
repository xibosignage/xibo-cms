<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\PlaylistFactory;

class PermissionsPlaylistListener
{
    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    public function __construct(PlaylistFactory $playlistFactory)
    {
        $this->playlistFactory = $playlistFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->playlistFactory->getById($event->getObjectId()));
    }
}
