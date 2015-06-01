<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Menu.php) is part of Xibo.
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


namespace Xibo\Entity;


class Menu
{
    use EntityTrait;
    public $menuItemId;
    public $menuId;
    public $page;
    public $args;
    public $class;
    public $title;
    public $img;
    public $external;

    public $menu;

    public function getId()
    {
        return $this->menuItemId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    public function getName()
    {
        return sprintf('%s -> %s', $this->menu, $this->title);
    }
}