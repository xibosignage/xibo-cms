<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ResolutionFactory.php) is part of Xibo.
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


use Xibo\Entity\Resolution;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ResolutionFactory
 * @package Xibo\Factory
 */
class ResolutionFactory extends BaseFactory
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
     * Create Empty
     * @return Resolution
     */
    public function createEmpty()
    {
        return new Resolution($this->getStore(), $this->getLog());
    }

    /**
     * Create Resolution
     * @param $resolutionName
     * @param $width
     * @param $height
     * @return Resolution
     */
    public function create($resolutionName, $width, $height)
    {
        $resolution = $this->createEmpty();
        $resolution->resolution = $resolutionName;
        $resolution->width = $width;
        $resolution->height = $height;

        return $resolution;
    }

    /**
     * Load the Resolution by ID
     * @param int $resolutionId
     * @return Resolution
     * @throws NotFoundException
     */
    public function getById($resolutionId)
    {
        $resolutions = $this->query(null, array('disableUserCheck' => 1, 'resolutionId' => $resolutionId));

        if (count($resolutions) <= 0)
            throw new NotFoundException(null, 'Resolution');

        return $resolutions[0];
    }

    /**
     * Get Resolution by Dimensions
     * @param double $width
     * @param double $height
     * @return Resolution
     * @throws NotFoundException
     */
    public function getByDimensions($width, $height)
    {
        $resolutions = $this->query(null, array('disableUserCheck' => 1, 'width' => $width, 'height' => $height));

        if (count($resolutions) <= 0)
            throw new NotFoundException('Resolution not found');

        return $resolutions[0];
    }

    /**
     * Get Resolution by Dimensions
     * @param double $width
     * @param double $height
     * @return Resolution
     * @throws NotFoundException
     */
    public function getByDesignerDimensions($width, $height)
    {
        $resolutions = $this->query(null, array('disableUserCheck' => 1, 'designerWidth' => $width, 'designerHeight' => $height));

        if (count($resolutions) <= 0)
            throw new NotFoundException('Resolution not found');

        return $resolutions[0];
    }

    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['resolution'];

        $entities = array();

        $params = array();
        $select  = '
          SELECT `resolution`.resolutionId,
              `resolution`.resolution,
              `resolution`.intended_width AS width,
              `resolution`.intended_height AS height,
              `resolution`.width AS designerWidth,
              `resolution`.height AS designerHeight,
              `resolution`.version,
              `resolution`.enabled,
              `resolution`.userId
        ';

        $body = '
            FROM `resolution`
           WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getInt('enabled', -1, $filterBy) != -1) {
            $body .= ' AND enabled = :enabled ';
            $params['enabled'] = $this->getSanitizer()->getInt('enabled', $filterBy);
        }

        if ($this->getSanitizer()->getInt('resolutionId', $filterBy) !== null) {
            $body .= ' AND resolutionId = :resolutionId ';
            $params['resolutionId'] = $this->getSanitizer()->getInt('resolutionId', $filterBy);
        }

        if ($this->getSanitizer()->getString('resolution', $filterBy) != null) {
            $body .= ' AND resolution = :resolution ';
            $params['resolution'] = $this->getSanitizer()->getString('resolution', $filterBy);
        }

        if ($this->getSanitizer()->getInt('width', $filterBy) !== null) {
            $body .= ' AND intended_width = :width ';
            $params['width'] = $this->getSanitizer()->getInt('width', $filterBy);
        }

        if ($this->getSanitizer()->getInt('height', $filterBy) !== null) {
            $body .= ' AND intended_height = :height ';
            $params['height'] = $this->getSanitizer()->getInt('height', $filterBy);
        }

        if ($this->getSanitizer()->getInt('designerWidth', $filterBy) !== null) {
            $body .= ' AND width = :designerWidth ';
            $params['designerWidth'] = $this->getSanitizer()->getInt('designerWidth', $filterBy);
        }

        if ($this->getSanitizer()->getInt('designerHeight', $filterBy) !== null) {
            $body .= ' AND height = :designerHeight ';
            $params['designerHeight'] = $this->getSanitizer()->getInt('designerHeight', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . $this->getSanitizer()->getInt('start', 0) . ', ' . $this->getSanitizer()->getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;



        foreach($this->getStore()->select($sql, $params) as $record) {
            $entities[] = $this->createEmpty()->hydrate($record, ['intProperties' => ['width', 'height', 'version', 'enabled']]);
        }

        // Paging
        if ($limit != '' && count($entities) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entities;
    }
}