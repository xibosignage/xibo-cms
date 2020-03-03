<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


namespace Xibo\Entity;

use Xibo\Support\Exception\DeadlockException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RequiredFile
 * @package Xibo\Entity
 */
class RequiredFile implements \JsonSerializable
{
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

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Save
     * @return $this
     */
    public function save()
    {
        if ($this->rfId == null)
            $this->add();
        else if ($this->hasPropertyChanged('bytesRequested') || $this->hasPropertyChanged('complete')) {
            $this->edit();
        }

        return $this;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->rfId = $this->store->insert('
            INSERT INTO `requiredfile` (`displayId`, `type`, `itemId`, `bytesRequested`, `complete`, `size`, `path`, `released`)
              VALUES (:displayId, :type, :itemId, :bytesRequested, :complete, :size, :path, :released)
        ', [
            'displayId' => $this->displayId,
            'type' => $this->type,
            'itemId' => $this->itemId,
            'bytesRequested' => $this->bytesRequested,
            'complete' => $this->complete,
            'size' => $this->size,
            'path' => $this->path,
            'released' => $this->released
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        try {
            $this->store->updateWithDeadlockLoop('
            UPDATE `requiredfile` SET complete = :complete, bytesRequested = :bytesRequested
             WHERE rfId = :rfId
        ', [
                'rfId' => $this->rfId,
                'bytesRequested' => $this->bytesRequested,
                'complete' => $this->complete
            ]);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Failed to update bytes requested on ' . $this->rfId . ' due to deadlock');
        }
    }
}