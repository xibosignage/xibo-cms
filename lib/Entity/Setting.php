<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Setting.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

/**
 * Class Setting
 * @package Xibo\Entity
 */
class Setting
{
    use EntityTrait;
    public $setting;
    public $value;

    public function save()
    {
        PDOConnect::update('UPDATE `setting` SET `value` = :value WHERE `setting` = :setting', ['setting' => $this->setting, 'value' => $this->value]);
    }
}