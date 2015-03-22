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

class PageFactory
{
    /**
     * Get by Route
     * @param $route
     * @return Page
     * @throws NotFoundException if the page cannot be resolved from the provided route
     */
    public static function getByRoute($route)
    {
        Log::debug('Checking access for route ' . $route);
        $route = explode('/', ltrim($route, '/'));
        $pages = PageFactory::query(null, array('name' => $route[0]));

        if (count($pages) <= 0)
            throw new NotFoundException('Unknown Route');

        return $pages[0];
    }

    public static function query($sortOrder, $filterBy)
    {
        $entries = array();
        $params = array();
        $sql = 'SELECT pageId, name FROM `pages` WHERE 1 = 1 ';

        if (Sanitize::getString('name', $filterBy) != '') {
            $params['name'] = Sanitize::getString('name', $filterBy);
            $sql .= ' AND `name` = :name';
        }

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $page = new Page();
            $page->pageId = $row['pageId'];
            $page->page = Sanitize::getString($row['name']);

            $entries[] = $page;
        }

        return $entries;
    }
}