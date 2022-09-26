<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
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
namespace Xibo\Listener\OnFolderMoving;

use Xibo\Event\FolderMovingEvent;
use Xibo\Factory\UserFactory;
use Xibo\Storage\StorageServiceInterface;

class UserListener
{
    /**
     * @var UserFactory
     */
    private $userFactory;
    /**
     * @var StorageServiceInterface
     */
    private $store;

    public function __construct(UserFactory $userFactory, StorageServiceInterface $store)
    {
        $this->userFactory = $userFactory;
        $this->store = $store;
    }

    public function __invoke(FolderMovingEvent $event)
    {
        $folder = $event->getFolder();
        $newFolder = $event->getNewFolder();

        foreach ($this->userFactory->getByHomeFolderId($folder->getId()) as $user) {
            $this->store->update('UPDATE `user` SET homeFolderId = :newFolderId WHERE homeFolderId = :oldFolderId AND userId = :userId', [
                'newFolderId' => $newFolder->getId(),
                'oldFolderId' => $folder->getId(),
                'userId' => $user->getId()
            ]);
        }
    }
}
