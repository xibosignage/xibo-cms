<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Setting.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class Setting
 * @package Xibo\Entity
 */
class Setting
{
    use EntityTrait;
    public $setting;
    public $value;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function save()
    {
        $this->getStore()->update('UPDATE `setting` SET `value` = :value WHERE `setting` = :setting', ['setting' => $this->setting, 'value' => $this->value]);
    }
}