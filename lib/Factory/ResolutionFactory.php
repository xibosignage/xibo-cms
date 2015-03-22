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
use Xibo\Storage\PDOConnect;

class ResolutionFactory
{
    /**
     * Load the Resolution by ID
     * @param int $resolutionId
     * @return Resolution
     * @throws NotFoundException
     */
    public static function getById($resolutionId)
    {
        $resolutions = ResolutionFactory::query(null, array('resolutionId' => $resolutionId));

        if (count($resolutions) <= 0)
            throw new NotFoundException;

        return $resolutions[0];
    }

    /**
     * Get Resolution by Dimensions
     * @param double $width
     * @param double $height
     * @return Resolution
     * @throws NotFoundException
     */
    public static function getByDimensions($width, $height)
    {
        $resolutions = ResolutionFactory::query(null, array('width' => $width, 'height' => $height));

        if (count($resolutions) <= 0)
            throw new NotFoundException('Resolution not found');

        return $resolutions[0];
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entities = array();

        $params = array();
        $sql  = 'SELECT * FROM `resolution` WHERE 1 = 1 ';

        if (\Xibo\Helper\Sanitize::getInt('enabled', -1, $filterBy) != -1) {
            $sql .= ' AND enabled = :enabled ';
            $params['enabled'] = \Xibo\Helper\Sanitize::getInt('enabled', $filterBy);
        }

        if (\Xibo\Helper\Sanitize::getInt('resolutionId', $filterBy) != 0) {
            $sql .= ' AND resolutionId = :resolutionId';
            $params['resolutionId'] = \Xibo\Helper\Sanitize::getInt('resolutionId', $filterBy);
        }

        if (\Xibo\Helper\Sanitize::getInt('width', $filterBy) != 0) {
            $sql .= ' AND intended_width = :width';
            $params['width'] = \Xibo\Helper\Sanitize::getInt('width', $filterBy);
        }

        if (\Xibo\Helper\Sanitize::getInt('height', $filterBy) != 0) {
            $sql .= ' AND intended_height = :height';
            $params['height'] = \Xibo\Helper\Sanitize::getInt('height', $filterBy);
        }

        \Xibo\Helper\Log::sql($sql, $params);

        foreach(PDOConnect::select($sql, $params) as $record) {
            $resolution = new Resolution();
            $resolution->resolutionId = $record['resolutionID'];
            $resolution->resolution = $record['resolution'];
            $resolution->width = $record['intended_width'];
            $resolution->height = $record['intended_height'];
            $resolution->version = \Xibo\Helper\Sanitize::int($record['version']);
            $resolution->enabled = \Xibo\Helper\Sanitize::int($record['enabled']);

            $entities[] = $resolution;
        }

        return $entities;
    }
}