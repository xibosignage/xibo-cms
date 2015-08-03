<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserType.php)
 */


namespace Xibo\Entity;

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

    public function getId()
    {
        return $this->userTypeId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}