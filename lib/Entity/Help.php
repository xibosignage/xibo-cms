<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Help.php)
 */


namespace Xibo\Entity;


class Help
{
    use EntityTrait;

    public $helpId;
    public $topic;
    public $category;
    public $link;

    public function getId()
    {
        return $this->helpId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}