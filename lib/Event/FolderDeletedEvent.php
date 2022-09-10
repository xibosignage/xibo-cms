<?php

namespace Xibo\Event;

use Xibo\Entity\Folder;

class FolderDeletedEvent
{
    public static $NAME = 'folder.deleted.event';
    /**
     * @var Folder
     */
    private $folder;

    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }
}
