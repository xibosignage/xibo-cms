<?php

namespace Xibo\Event;

use Xibo\Entity\Folder;

class FolderDeletingEvent extends Event
{
    public static $NAME = 'folder.deleting.event';
    /**
     * @var int
     */
    private $folderId;

    public function __construct(int $folderId)
    {
        $this->folderId = $folderId;
    }

    public function getFolderId(): int
    {
        return $this->folderId;
    }
}
