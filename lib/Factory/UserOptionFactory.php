<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (UserOptionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\UserOption;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserOptionFactory
 * @package Xibo\Factory
 */
class UserOptionFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * Load by User Id
     * @param int $userId
     * @return array[UserOption]
     */
    public function getByUserId($userId)
    {
        return $this->query(null, array('userId' => $userId));
    }

    /**
     * Create Empty
     * @return UserOption
     */
    public function createEmpty()
    {
        return new UserOption($this->getStore(), $this->getLog());
    }

    /**
     * Create a user option
     * @param int $userId
     * @param string $option
     * @param mixed $value
     * @return UserOption
     */
    public function create($userId, $option, $value)
    {
        $userOption = $this->createEmpty();
        $userOption->userId = $userId;
        $userOption->option = $option;
        $userOption->value = $value;

        return $userOption;
    }

    /**
     * Query User options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[UserOption]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        $sql = 'SELECT * FROM `useroption` WHERE userId = :userId';

        foreach ($this->getStore()->select($sql, array('userId' => $this->getSanitizer()->getInt('userId', $filterBy))) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}