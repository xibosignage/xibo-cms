<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
        } else if ($this->hasPropertyChanged('bytesRequested') || $this->hasPropertyChanged('complete')) {
            $this->edit($options);
        }

        return $this;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->rfId = $this->store->insert('
            INSERT INTO `requiredfile` (`displayId`, `type`, `itemId`, `bytesRequested`, `complete`, `size`, `path`, `released`, `fileType`, `realId`)
              VALUES (:displayId, :type, :itemId, :bytesRequested, :complete, :size, :path, :released, :fileType, :realId)
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
            UPDATE `requiredfile` SET complete = :complete, bytesRequested = :bytesRequested
             WHERE rfId = :rfId
        ', [
                'rfId' => $this->rfId,
                'bytesRequested' => $this->bytesRequested,
                'complete' => $this->complete
            ], $options['connection'], $options['useTransaction']);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Failed to update bytes requested on ' . $this->rfId . ' due to deadlock');
        }
    }
}
