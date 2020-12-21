<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (RegionFactory.php) is part of Xibo.
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


use Xibo\Entity\Region;
use Xibo\Exception\NotFoundException;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RegionFactory
 * @package Xibo\Factory
 */
class RegionFactory extends BaseFactory
{
    /** @var DateServiceInterface */
    private $dateService;

    /**
     * @var RegionOptionFactory
     */
    private $regionOptionFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param RegionOptionFactory $regionOptionFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($store, $log, $sanitizerService, $date, $permissionFactory, $regionOptionFactory, $playlistFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->regionOptionFactory = $regionOptionFactory;
        $this->playlistFactory = $playlistFactory;
    }

    /**
     * @return Region
     */
    public function createEmpty()
    {
        return new Region(
            $this->getStore(),
            $this->getLog(),
            $this->dateService,
            $this,
            $this->permissionFactory,
            $this->regionOptionFactory,
            $this->playlistFactory
        );
    }

    /**
     * Create a new region
     * @param int $ownerId;
     * @param string $name
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @param int $zIndex
     * @return Region
     */
    public function create($ownerId, $name, $width, $height, $top, $left, $zIndex = 0)
    {
        // Validation
        if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left))
            throw new \InvalidArgumentException(__('Size and coordinates must be generic'));

        if ($width <= 0)
            throw new \InvalidArgumentException(__('Width must be greater than 0'));

        if ($height <= 0)
            throw new \InvalidArgumentException(__('Height must be greater than 0'));

        $region = $this->createEmpty();
        $region->ownerId = $ownerId;
        $region->name = $name;
        $region->width = $width;
        $region->height = $height;
        $region->top = $top;
        $region->left = $left;
        $region->zIndex = $zIndex;

        return $region;
    }

    /**
     * Get the regions for a layout
     * @param int $layoutId
     * @return array[\Xibo\Entity\Region]
     */
    public function getByLayoutId($layoutId)
    {
        // Get all regions for this layout
        return $this->query(array(), array('disableUserCheck' => 1, 'layoutId' => $layoutId));
    }

    /**
     * Get the regions for a playlist
     * @param int $playlistId
     * @return array[\Xibo\Entity\Region]
     */
    public function getByPlaylistId($playlistId)
    {
        // Get all regions for this layout
        return $this->query(array(), array('disableUserCheck' => 1, 'playlistId' => $playlistId));
    }

    /**
     * Load a region
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function loadByRegionId($regionId)
    {
        $region = $this->getById($regionId);
        $region->load();
        return $region;
    }

    /**
     * Get by RegionId
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getById($regionId)
    {
        // Get a region by its ID
        $regions = $this->query(array(), array('disableUserCheck' => 1, 'regionId' => $regionId));

        if (count($regions) <= 0)
            throw new NotFoundException(__('Region not found'));

        return $regions[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Region]
     */
    public function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        $params = array();
        $sql = '
          SELECT `region`.regionId,
              `region`.layoutId,
              `region`.ownerId,
              `region`.name,
              `region`.width,
              `region`.height,
              `region`.top,
              `region`.left,
              `region`.zIndex,
              `region`.duration
        ';

        $sql .= '
            FROM `region`
        ';

        $sql .= ' WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('regionId', $filterBy) != 0) {
            $sql .= ' AND regionId = :regionId ';
            $params['regionId'] = $this->getSanitizer()->getInt('regionId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutId', $filterBy) != 0) {
            $sql .= ' AND layoutId = :layoutId ';
            $params['layoutId'] = $this->getSanitizer()->getInt('layoutId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('playlistId', $filterBy) !== null) {
            $sql .= ' AND regionId IN (SELECT regionId FROM playlist WHERE playlistId = :playlistId) ';
            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', $filterBy);
        }

        // Order by Name
        $sql .= ' ORDER BY `region`.name ';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['zIndex']]);
        }

        return $entries;
    }
}