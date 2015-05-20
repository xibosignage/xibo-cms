<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfile.php)
 */


namespace Xibo\Entity;

class DisplayProfile
{
    use EntityTrait;
    public $displayProfileId;
    public $name;
    public $type;
    public $config;
    public $isDefault;
    public $userId;

    public function getId()
    {
        return $this->displayProfileId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }
}