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

        if (\Kit::GetParam('enabled', $filterBy, _INT, -1) != -1) {
            $sql .= ' AND enabled = :enabled ';
            $params['enabled'] = \Kit::GetParam('enabled', $filterBy, _INT);
        }

        if (\Kit::GetParam('resolutionId', $filterBy, _INT) != 0) {
            $sql .= ' AND resolutionId = :resolutionId';
            $params['resolutionId'] = \Kit::GetParam('resolutionId', $filterBy, _INT);
        }

        if (\Kit::GetParam('width', $filterBy, _INT) != 0) {
            $sql .= ' AND intended_width = :width';
            $params['width'] = \Kit::GetParam('width', $filterBy, _INT);
        }

        if (\Kit::GetParam('height', $filterBy, _INT) != 0) {
            $sql .= ' AND intended_height = :height';
            $params['height'] = \Kit::GetParam('height', $filterBy, _INT);
        }

        \Xibo\Helper\Log::sql($sql, $params);

        foreach(\PDOConnect::select($sql, $params) as $record) {
            $resolution = new Resolution();
            $resolution->resolutionId = $record['resolutionID'];
            $resolution->resolution = $record['resolution'];
            $resolution->width = $record['intended_width'];
            $resolution->height = $record['intended_height'];
            $resolution->version = \Kit::ValidateParam($record['version'], _INT);
            $resolution->enabled = \Kit::ValidateParam($record['enabled'], _INT);

            $entities[] = $resolution;
        }

        return $entities;
    }
}