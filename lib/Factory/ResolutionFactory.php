<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
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
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ResolutionFactory
 * @package Xibo\Factory
 */
class ResolutionFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return Resolution
     */
    public function createEmpty()
    {
        return new Resolution($this->getStore(), $this->getLog(), $this->getDispatcher());
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
            throw new NotFoundException(__('Resolution not found'));

        return $resolutions[0];
    }

    /**
     * @param $width
     * @param $height
     * @return Resolution
     * @throws NotFoundException
     */
    public function getClosestMatchingResolution($width, $height): Resolution
    {
        $area = $width * $height;
        $sort = ['ABS(' . $area . ' - (`intended_width` * `intended_height`))'];
        $sort[] = $width > $height ? '`intended_width` DESC' : '`intended_height` DESC';

        $resolutions = $this->query(
            $sort,
            [
                'disableUserCheck' => 1,
                'enabled' => 1,
                'start' => 0,
                'length' => 1
            ]
        );

        if (count($resolutions) <= 0) {
            throw new NotFoundException(__('Resolution not found'));
        }

        return $resolutions[0];
    }

    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
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
            throw new NotFoundException(__('Resolution not found'));

        return $resolutions[0];
    }

    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder === null) {
            $sortOrder = ['resolution'];
        }

        $entities = [];

        $params = [];
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

        if ($parsedFilter->getInt('enabled', ['default' => -1]) != -1
            && $parsedFilter->getInt('withCurrent') !== null
        ) {
            $body .= ' AND ( enabled = :enabled OR `resolution`.resolutionId = :withCurrent) ';
            $params['enabled'] = $parsedFilter->getInt('enabled');
            $params['withCurrent'] = $parsedFilter->getInt('withCurrent');
        }

        if ($parsedFilter->getInt('enabled', ['default' => -1]) != -1
            && $parsedFilter->getInt('withCurrent') === null
        ) {
            $body .= ' AND enabled = :enabled ';
            $params['enabled'] = $parsedFilter->getInt('enabled');
        }

        if ($parsedFilter->getInt('resolutionId') !== null) {
            $body .= ' AND resolutionId = :resolutionId ';
            $params['resolutionId'] = $parsedFilter->getInt('resolutionId');
        }

        if ($parsedFilter->getString('resolution') != null) {
            $body .= ' AND resolution = :resolution ';
            $params['resolution'] = $parsedFilter->getString('resolution');
        }

        if ($parsedFilter->getString('partialResolution') != null) {
            $body .= ' AND resolution LIKE :partialResolution ';
            $params['partialResolution'] = '%' . $parsedFilter->getString('partialResolution') . '%';
        }

        if ($parsedFilter->getDouble('width') !== null) {
            $body .= ' AND intended_width = :width ';
            $params['width'] = $parsedFilter->getDouble('width');
        }

        if ($parsedFilter->getDouble('height') !== null) {
            $body .= ' AND intended_height = :height ';
            $params['height'] = $parsedFilter->getDouble('height');
        }

        if ($parsedFilter->getDouble('designerWidth') !== null) {
            $body .= ' AND width = :designerWidth ';
            $params['designerWidth'] = $parsedFilter->getDouble('designerWidth');
        }

        if ($parsedFilter->getDouble('designerHeight') !== null) {
            $body .= ' AND height = :designerHeight ';
            $params['designerHeight'] = $parsedFilter->getDouble('designerHeight');
        }

        if ($parsedFilter->getString('orientation') !== null) {
            if ($parsedFilter->getString('orientation') === 'portrait') {
                $body .= ' AND intended_width <= intended_height ';
            } else {
                $body .= ' AND intended_width > intended_height ';
            }
        }

        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND `resolution`.userId = :userId ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
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