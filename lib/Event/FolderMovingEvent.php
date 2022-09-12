<?php

namespace Xibo\Event;

use Xibo\Entity\Folder;

class FolderMovingEvent extends Event
{
    public static $NAME = 'folder.moving.event';
    /**
     * @var Folder
     */
    private $folder;
    /**
     * @var Folder
     */
    private $newFolder;
    /**
     * @var bool
     */
    private $merge;

    public function __construct(Folder $folder, Folder $newFolder, bool $merge)
    {
        $this->folder = $folder;
        $this->newFolder = $newFolder;
        $this->merge = $merge;
    }

    public function getFolder(): Folder
    {
        return $this->folder;
    }

    public function getNewFolder(): Folder
    {
        return $this->newFolder;
    }

    public function getIsMerge(): bool
    {
        return $this->merge;
    }
}
