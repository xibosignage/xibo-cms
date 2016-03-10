<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (UserOption.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class UserOption
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class UserOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The userId that this Option applies to")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="The option name")
     * @var string
     */
    public $option;

    /**
     * @SWG\Property(description="The option value")
     * @var string
     */
    public $value;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
        $this->excludeProperty('userId');
    }

    public function save()
    {
        $sql = 'INSERT INTO `useroption` (`userId`, `option`, `value`) VALUES (:userId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2';
        $this->getStore()->insert($sql, array(
            'userId' => $this->userId,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value,
        ));
    }

    public function delete()
    {
        $sql = 'DELETE FROM `useroption` WHERE `userId` = :userId AND `option` = :option';
        $this->getStore()->update($sql, array('userId' => $this->userId, 'option' => $this->option));
    }
}