<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (RegionOptions.php) is part of Xibo.
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


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RegionOption
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class RegionOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The regionId that this Option applies to")
     * @var int
     */
    public $regionId;

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
    }

    /**
     * Clone
     */
    public function __clone()
    {
        $this->regionId = null;
    }

    public function save()
    {
        $sql = 'INSERT INTO `regionoption` (`regionId`, `option`, `value`) VALUES (:regionId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2';
        $this->getStore()->insert($sql, array(
            'regionId' => $this->regionId,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value,
        ));
    }

    public function delete()
    {
        $sql = 'DELETE FROM `regionoption` WHERE `regionId` = :regionId AND `option` = :option';
        $this->getStore()->update($sql, array('regionId' => $this->regionId, 'option' => $this->option));
    }
}