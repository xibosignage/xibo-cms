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


namespace Xibo\Entity;

use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DeadlockException;

/**
 * Class RequiredFile
 * @package Xibo\Entity
 */
class RequiredFile implements \JsonSerializable
{
    public static $TYPE_DEPENDENCY = 'P';
    public static $TYPE_LAYOUT = 'L';
    public static $TYPE_MEDIA = 'M';
    public static $TYPE_WIDGET_DATA = 'D';

    use EntityTrait;
    public $rfId;
    public $displayId;
    public $type;
    public $itemId;
    public $size = 0;
    public $path;
    public $bytesRequested = 0;
    public $complete = 0;
    public $released = 1;
    public $fileType;

    /** @var int 1 when the player was given a direct download link, so its first completion is counted */
    public $isDirect = 0;

    /** @var string|null The md5 of the version last handed out, so a new version is counted again */
    public $md5;

    /** @var string The realId of a dependency which we will use to resolve it */
    public $realId;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Save
     * @return $this
     */
    public function save($options = [])
    {
        if ($this->rfId == null) {
            $this->add();
        } else if ($this->hasPropertyChanged('bytesRequested')
            || $this->hasPropertyChanged('complete')
            || $this->hasPropertyChanged('isDirect')
            || $this->hasPropertyChanged('size')
            || $this->hasPropertyChanged('md5')
        ) {
            $this->edit($options);
        }

        return $this;
    }

    /**
     * Records the version handed out; a different one marks the file incomplete, so its download is counted again.
     */
    public function setMd5(?string $md5): void
    {
        if (empty($md5) || $md5 === $this->md5) {
            return;
        }

        if (!empty($this->md5)) {
            $this->complete = 0;
        }
        $this->md5 = $md5;
    }

    /**
     * Marks the file complete atomically; true only for the request that changed it, so overlapping ones count once.
     * Not retried on deadlock: MySQL rolls back the earlier claims too, so the request faults and the player resends.
     */
    public function claimComplete(): bool
    {
        $this->complete = 1;

        return $this->store->update('
            UPDATE `requiredfile` SET `complete` = 1 WHERE `rfId` = :rfId AND `complete` = 0
        ', ['rfId' => $this->rfId]) > 0;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->rfId = $this->store->insert('
            INSERT INTO `requiredfile` (`displayId`, `type`, `itemId`, `bytesRequested`, `complete`, `size`, `path`,
                `released`, `fileType`, `realId`, `isDirect`, `md5`)
              VALUES (:displayId, :type, :itemId, :bytesRequested, :complete, :size, :path,
                :released, :fileType, :realId, :isDirect, :md5)
        ', [
            'displayId' => $this->displayId,
            'type' => $this->type,
            'itemId' => $this->itemId,
            'bytesRequested' => $this->bytesRequested,
            'complete' => $this->complete,
            'size' => $this->size,
            'path' => $this->path,
            'released' => $this->released,
            'fileType' => $this->fileType,
            'realId' => $this->realId,
            'isDirect' => (int)$this->isDirect,
            'md5' => $this->md5,
        ]);
    }

    /**
     * Edit
     */
    private function edit($options)
    {
        $options = array_merge([
            'connection' => 'default',
            'useTransaction' => true,
        ], $options);

        try {
            $this->store->updateWithDeadlockLoop('
            UPDATE `requiredfile`
               SET complete = :complete, bytesRequested = :bytesRequested, isDirect = :isDirect, size = :size,
                   md5 = :md5
             WHERE rfId = :rfId
        ', [
                'rfId' => $this->rfId,
                'bytesRequested' => $this->bytesRequested,
                'complete' => $this->complete,
                'isDirect' => (int)$this->isDirect,
                'size' => $this->size,
                'md5' => $this->md5,
            ], $options['connection'], $options['useTransaction']);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Failed to update bytes requested on ' . $this->rfId . ' due to deadlock');
        }
    }
}
