<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\UserType;
use Xibo\Exception\NotFoundException;

class UserTypeFactory extends BaseFactory
{
    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     * @throws NotFoundException
     */
    public function query($sortOrder = ['userType'], $filterBy = null)
    {
        $entries = array();
        $params = array();

        try {
            $sql = '
            SELECT userTypeId, userType FROM `usertype`
            ';

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $entries[] = (new UserType())->hydrate($row)->setApp($this->getApp());
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}