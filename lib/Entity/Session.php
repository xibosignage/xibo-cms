<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Session.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Session
 * @package Xibo\Entity
 */
class Session implements \JsonSerializable
{
    use EntityTrait;

    public $sessionId;
    public $userId;
    public $userName;
    public $isExpired;
    public $lastAccessed;
    public $remoteAddress;
    public $userAgent;
    public $expiresAt;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function getId()
    {
        return $this->userId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}