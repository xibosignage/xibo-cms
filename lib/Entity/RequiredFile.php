<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Nonce.php)
 */


namespace Xibo\Entity;

use Xibo\Exception\DeadlockException;
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