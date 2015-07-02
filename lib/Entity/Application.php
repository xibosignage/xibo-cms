<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Application.php)
 */


namespace Xibo\Entity;


class Application
{
    use EntityTrait;

    public $key;
    public $secret;
    public $name;

    public $expires;
}