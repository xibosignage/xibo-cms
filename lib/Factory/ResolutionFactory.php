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
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ResolutionFactory extends BaseFactory
{
    /**
     * Load the Resolution by ID
     * @param int $resolutionId
     * @return Resolution
     * @throws NotFoundException
     */
    public static function getById($resolutionId)
    {
        $resolutions = ResolutionFactory::query(null, array('disableUserCheck' => 1, 'resolutionId' => $resolutionId));

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
        $resolutions = ResolutionFactory::query(null, array('disableUserCheck' => 1, 'width' => $width, 'height' => $height));

        if (count($resolutions) <= 0)
            throw new NotFoundException('Resolution not found');

        return $resolutions[0];
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
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
              `resolution`.enabled
        ';

        $body = '
            FROM `resolution`
           WHERE 1 = 1
        ';

        if (Sanitize::getInt('enabled', -1, $filterBy) != -1) {
            $body .= ' AND enabled = :enabled ';
            $params['enabled'] = Sanitize::getInt('enabled', $filterBy);
        }

        if (Sanitize::getInt('resolutionId', $filterBy) !== null) {
            $body .= ' AND resolutionId = :resolutionId';
            $params['resolutionId'] = Sanitize::getInt('resolutionId', $filterBy);
        }

        if (Sanitize::getInt('width', $filterBy) !== null) {
            $body .= ' AND intended_width = :width';
            $params['width'] = Sanitize::getInt('width', $filterBy);
        }

        if (Sanitize::getInt('height', $filterBy) !== null) {
            $body .= ' AND intended_height = :height';
            $params['height'] = Sanitize::getInt('height', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . Sanitize::getInt('start', 0) . ', ' . Sanitize::getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach(PDOConnect::select($sql, $params) as $record) {
            $entities[] = (new Resolution())->hydrate($record, ['intProperties' => ['width', 'height', 'version', 'enabled']]);
        }

        // Paging
        if ($limit != '' && count($entities) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entities;
    }
}