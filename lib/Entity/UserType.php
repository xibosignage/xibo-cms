<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserType.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserType
 * @package Xibo\Entity
 *
 */
class UserType
{
    use EntityTrait;

    public $userTypeId;
    public $userType;

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
        return $this->userTypeId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}