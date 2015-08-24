<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (PageFactory.php) is part of Xibo.
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


use Xibo\Entity\Page;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class PageFactory extends BaseFactory
{
    /**
     * Get by ID
     * @param int $pageId
     * @return Page
     * @throws NotFoundException if the page cannot be resolved from the provided route
     */
    public static function getById($pageId)
    {
        $pages = PageFactory::query(null, array('pageId' => $pageId, 'disableUserCheck' => 1));

        if (count($pages) <= 0)
            throw new NotFoundException('Unknown Route');

        return $pages[0];
    }

    public static function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null)
            $sortOrder = ['name'];

        $entries = array();
        $params = array();
        $sql = 'SELECT pageId, name, title, asHome FROM `pages` WHERE 1 = 1 ';

        // Logged in user view permissions
        self::viewPermissionSql('Xibo\Entity\Page', $sql, $params, 'pageId', null, $filterBy);

        if (Sanitize::getString('name', $filterBy) != null) {
            $params['name'] = Sanitize::getString('name', $filterBy);
            $sql .= ' AND `name` = :name ';
        }

        if (Sanitize::getInt('pageId', $filterBy) !== null) {
            $params['pageId'] = Sanitize::getString('pageId', $filterBy);
            $sql .= ' AND `pageId` = :pageId ';
        }

        if (Sanitize::getInt('asHome', $filterBy) !== null) {
            $params['asHome'] = Sanitize::getString('asHome', $filterBy);
            $sql .= ' AND `asHome` = :asHome ';
        }

        // Sorting?
        $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Page())->hydrate($row);
        }

        return $entries;
    }
}