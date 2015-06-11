<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (MenuFactory.php) is part of Xibo.
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


use Xibo\Entity\Menu;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class MenuFactory
{
    public static function getById($menuItemId)
    {
        $menuItem = MenuFactory::query(null, ['menuItemId' => $menuItemId]);

        return $menuItem[0];
    }

    public static function getByMenu($menu)
    {
        return MenuFactory::query(['sequence'], ['menu' => $menu]);
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $SQL = "";
        $SQL .= "SELECT DISTINCT pages.name AS page, ";
        $SQL .= "         menuitem.args , ";
        $SQL .= "         menuitem.text AS title, ";
        $SQL .= "         menuitem.class, ";
        $SQL .= "         menuitem.img, ";
        $SQL .= "         menuitem.external, ";
        $SQL .= "         menuitem.menuItemId, ";
        $SQL .= "         menuitem.menuId, ";
        $SQL .= "         menu.menu ";
        $SQL .= "FROM     menuitem ";
        $SQL .= "         INNER JOIN menu ";
        $SQL .= "         ON       menuitem.MenuID = menu.MenuID ";
        $SQL .= "         INNER JOIN pages ";
        $SQL .= "         ON       pages.pageID = menuitem.PageID ";
        $SQL .= "WHERE  1=1 ";

        if (Sanitize::getString('menu', $filterBy) != null) {
            $SQL .= ' AND menu.Menu = :menu ';
            $params['menu'] = Sanitize::getString('menu', $filterBy);
        }

        if (Sanitize::getInt('menuItemId', $filterBy) != null) {
            $SQL .= ' AND menuItem.menuItemId = :menuItemId ';
            $params['menuItemId'] = Sanitize::getInt('menuItemId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($SQL, $params);

        foreach (PDOConnect::select($SQL, $params) as $row) {
            $entries[] = (new Menu())->hydrate($row);
        }

        return $entries;
    }
}