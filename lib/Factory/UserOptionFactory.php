<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (UserOptionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\UserOption;
use Xibo\Helper\Sanitize;

class UserOptionFactory extends BaseFactory
{
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
     * Create a user option
     * @param int $userId
     * @param string $option
     * @param mixed $value
     * @return UserOption
     */
    public function create($userId, $option, $value)
    {
        $userOption = new UserOption();
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
    public function query($sortOrder = null, $filterBy = null)
    {
        if (DBVERSION < 122)
            return [];

        $entries = array();

        $sql = 'SELECT * FROM `useroption` WHERE userId = :userId';

        foreach ($this->getStore()->select($sql, array('userId' => Sanitize::getInt('userId', $filterBy))) as $row) {
            $entries[] = (new UserOption())->hydrate($row)->setApp($this->getApp());
        }

        return $entries;
    }
}