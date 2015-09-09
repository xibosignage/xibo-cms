<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Nonce.php)
 */


namespace Xibo\Entity;


use Xibo\Exception\FormExpiredException;
use Xibo\Helper\Random;
use Xibo\Storage\PDOConnect;

class RequiredFile implements \JsonSerializable
{
    use EntityTrait;
    public $rfId;
    public $requestKey;
    public $nonce;
    public $expiry;
    public $lastUsed;
    public $displayId;
    public $size;
    public $storedAs;
    public $layoutId;
    public $regionId;
    public $mediaId;
    public $bytesRequested;
    public $complete;

    public function save($options = [])
    {
        $options = array_merge([
            'refreshNonce' => true
        ], $options);

        // Always update the nonce when we save
        if ($options['refreshNonce']) {
            $this->expiry = time() + 86400;
            $this->nonce = md5(Random::generateString() . SECRET_KEY . time() . $this->layoutId . $this->regionId . $this->mediaId);
        }

        if ($this->rfId == null || $this->rfId == 0) {
            $this->add();
        }
        else
            $this->edit();
    }

    public function isValid()
    {
        if ($this->lastUsed != 0 || $this->expiry < time())
            throw new FormExpiredException();

        $this->markUsed();
    }

    public function markUsed()
    {
        $this->lastUsed = time();
        $this->edit();
    }

    private function add()
    {
        $this->rfId = PDOConnect::insert('
            INSERT INTO `requiredfile` (requestKey, nonce, expiry, lastUsed, displayId, size, storedAs, layoutId, regionId, mediaId)
              VALUES (:requestKey, :nonce, :expiry, :lastUsed, :displayId, :size, :storedAs, :layoutId, :regionId, :mediaId)
        ', [
            'requestKey' => $this->requestKey,
            'nonce' => $this->nonce,
            'expiry' => $this->expiry,
            'lastUsed' => $this->lastUsed,
            'displayId' => $this->displayId,
            'size' => $this->size,
            'storedAs' => $this->storedAs,
            'layoutId' => $this->layoutId,
            'regionId' => $this->regionId,
            'mediaId' => $this->mediaId
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE `requiredfile` SET
                requestKey = :requestKey,
                nonce = :nonce,
                expiry = :expiry,
                lastUsed = :lastUsed,
                displayId = :displayId,
                size = :size,
                storedAs = :storedAs,
                layoutId = :layoutId,
                regionId = :regionId,
                mediaId = :mediaId,
                bytesRequested = :bytesRequested,
                complete = :complete
             WHERE rfId = :rfId
        ', [
            'rfId' => $this->rfId,
            'requestKey' => $this->requestKey,
            'nonce' => $this->nonce,
            'expiry' => $this->expiry,
            'lastUsed' => $this->lastUsed,
            'displayId' => $this->displayId,
            'size' => $this->size,
            'storedAs' => $this->storedAs,
            'layoutId' => $this->layoutId,
            'regionId' => $this->regionId,
            'mediaId' => $this->mediaId,
            'bytesRequested' => $this->bytesRequested,
            'complete' => $this->complete
        ]);
    }

    public static function removeUnusedForDisplay($displayId, $requestKey)
    {
        PDOConnect::update('DELETE FROM `requiredfile` WHERE displayId = :displayId AND requestKey <> :requestKey ', ['displayId' => $displayId, 'requestKey' => $requestKey]);
    }
}