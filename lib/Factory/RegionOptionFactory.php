<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (RegionOptionFactory.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Factory;


use Xibo\Entity\RegionOption;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RegionOptionFactory
 * @package Xibo\Factory
 */
class RegionOptionFactory extends BaseFactory
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
     * @return RegionOption
     */
    public function createEmpty()
    {
        return new RegionOption($this->getStore(), $this->getLog());
    }

    /**
     * Load by Region Id
     * @param int $regionId
     * @return array[RegionOption]
     */
    public function getByRegionId($regionId)
    {
        return $this->query(null, array('regionId' => $regionId));
    }

    /**
     * Create a region option
     * @param int $regionId
     * @param string $option
     * @param mixed $value
     * @return RegionOption
     */
    public function create($regionId, $option, $value)
    {
        $regionOption = $this->createEmpty();
        $regionOption->regionId = $regionId;
        $regionOption->option = $option;
        $regionOption->value = $value;

        return $regionOption;
    }

    /**
     * Query Region options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[RegionOption]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        $sql = 'SELECT * FROM `regionoption` WHERE regionId = :regionId';

        foreach ($this->getStore()->select($sql, array('regionId' => $this->getSanitizer()->getInt('regionId', $filterBy))) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}