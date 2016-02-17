<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Session.php)
 */


namespace Xibo\Entity;

/**
 * Class Session
 * @package Xibo\Entity
 */
class Session implements \JsonSerializable
{
    use EntityTrait;

    public $userId;
    public $userName;
    public $isExpired;
    public $lastAccessed;
    public $remoteAddress;
    public $userAgent;

    public function getId()
    {
        return $this->userId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}