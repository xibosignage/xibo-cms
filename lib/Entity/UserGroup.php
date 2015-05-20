<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroup.php)
 */


namespace Xibo\Entity;


class UserGroup
{
    use EntityTrait;

    public $groupId;
    public $group;

    public function getId()
    {
        return $this->groupId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}