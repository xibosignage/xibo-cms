<?php
/*
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Entity;

/**
 * Class Homepage
 * @package Xibo\Entity
 */
class Homepage implements \JsonSerializable
{
    use EntityTrait;

    public $homepage;
    public $feature;
    public $title;
    public $description;

    /**
     * Homepage constructor.
     * @param $homepage
     * @param $feature
     * @param $title
     * @param $description
     */
    public function __construct($homepage, $feature, $title, $description)
    {
        $this->homepage = $homepage;
        $this->feature = $feature;
        $this->title = $title;
        $this->description = $description;
    }
}