<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserTypeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\User;
use Xibo\Entity\UserType;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserTypeFactory
 * @package Xibo\Factory
 */
class UserTypeFactory extends BaseFactory
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
     * @return UserType
     */
    public function createEmpty()
    {
        return new UserType($this->getStore(), $this->getLog());
    }

    /**
     * @return User[]
     */
    public function getAllRoles()
    {
        return $this->query();
    }

    /**
     * @return User[]
     */
    public function getNonAdminRoles()
    {
        return $this->query(null, ['userOnly' => 1]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     * @throws NotFoundException
     */
    public function query($sortOrder = ['userType'], $filterBy = [])
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        try {
            $sql = '
            SELECT userTypeId, userType 
              FROM `usertype`
             WHERE 1 = 1
            ';

            if ($sanitizedFilter->getInt('userOnly') !== null) {
                $sql .= ' AND `userTypeId` = 3 ';
            }

            if ($sanitizedFilter->getString('userType') !== null) {
                $sql .= ' AND userType = :userType ';
                $params['userType'] = $sanitizedFilter->getString('userType');
            }

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $entries[] = $this->createEmpty()->hydrate($row);
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}