<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Nonce.php)
 */


namespace Xibo\Entity;


use Xibo\Exception\FormExpiredException;
use Xibo\Storage\PDOConnect;

class XmdsNonce implements \JsonSerializable
{
    use EntityTrait;
    public $nonceId;
    public $nonce;
    public $expiry;
    public $lastUsed;
    public $displayId;
    public $fileId;
    public $size;
    public $storedAs;
    public $layoutId;
    public $regionId;
    public $mediaId;

    public function save()
    {
        if ($this->nonceId == null || $this->nonceId == 0) {
            $this->expiry = time() + 86400;
            $this->nonce = md5(uniqid() . SECRET_KEY . time() . $this->fileId . $this->layoutId . $this->regionId . $this->mediaId);
            $this->add();
        }
        else
            $this->edit();
    }

    public function isValid()
    {
        if ($this->lastUsed != 0 || $this->expiry < time())
            throw new FormExpiredException();

        $this->lastUsed = time();
        $this->edit();
    }

    private function add()
    {
        $this->nonceId = PDOConnect::insert('
            INSERT INTO `xmdsnonce` (nonce, expiry, lastUsed, displayId, fileId, size, storedAs, layoutId, regionId, mediaId)
              VALUES (:nonce, :expiry, :lastUsed, :displayId, :fileId, :size, :storedAs, :layoutId, :regionId, :mediaId)
        ', [
            'nonce' => $this->nonce,
            'expiry' => $this->expiry,
            'lastUsed' => $this->lastUsed,
            'displayId' => $this->displayId,
            'fileId' => $this->fileId,
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

        ', [
            'nonceId' => $this->nonceId,
            'nonce' => $this->nonce,
            'expiry' => $this->expiry,
            'lastUsed' => $this->lastUsed,
            'displayId' => $this->displayId,
            'fileId' => $this->fileId,
            'size' => $this->size,
            'storedAs' => $this->storedAs,
            'layoutId' => $this->layoutId,
            'regionId' => $this->regionId,
            'mediaId' => $this->mediaId
        ]);
    }

    public static function removeAllForDisplay($displayId)
    {
        PDOConnect::update('DELETE FROM `xmdsnonce` WHERE displayId = :displayId', ['displayId' => $displayId]);
    }
}