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
use Xibo\Storage\PDOConnect;

class MenuFactory
{
    public static function getByMenu($menu)
    {
        return MenuFactory::query($menu);
    }

    public static function query($menu)
    {
        $entries = array();

        $SQL = "";
        $SQL .= "SELECT DISTINCT pages.name, ";
        $SQL .= "         menuitem.Args , ";
        $SQL .= "         menuitem.Text , ";
        $SQL .= "         menuitem.Class, ";
        $SQL .= "         menuitem.Img, ";
        $SQL .= "         menuitem.External, ";
        $SQL .= "         menuitem.menuItemId ";
        $SQL .= "FROM     menuitem ";
        $SQL .= "         INNER JOIN menu ";
        $SQL .= "         ON       menuitem.MenuID = menu.MenuID ";
        $SQL .= "         INNER JOIN pages ";
        $SQL .= "         ON       pages.pageID = menuitem.PageID ";
        $SQL .= "WHERE    menu.Menu = :menu ";
        $SQL .= " ORDER BY menuitem.Sequence";

        foreach (PDOConnect::select($SQL, array('menu' => $menu)) as $row) {
            $menu = new Menu();
            $menu->menuId = $row['menuItemId'];
            $menu->page = $row['name'];
            $menu->args = $row['Args'];
            $menu->title = $row['Text'];
            $menu->class = $row['Class'];
            $menu->img = $row['Img'];
            $menu->external = $row['External'];

            $entries[] = $menu;
        }

        return $entries;
    }
}