<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
