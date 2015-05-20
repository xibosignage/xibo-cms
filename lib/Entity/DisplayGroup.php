<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


class DisplayGroup
{
    public $displayGroupId;
    public $displayGroup;
    public $description;
    public $isDisplaySpecific;

    public function getId()
    {
        return $this->displayGroupId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}