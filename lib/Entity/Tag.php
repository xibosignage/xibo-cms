<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Tag.php) is part of Xibo.
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


class Tag
{
    public $tagId;
    public $tag;

    public $layoutIds;
    public $mediaIds;

    public function __construct()
    {
        $this->layoutIds = array();
        $this->mediaIds = array();
    }

    public function assignLayout($layoutId)
    {
        if (!in_array($layoutId, $this->layoutIds))
            $this->layoutIds[] = $layoutId;
    }

    public function assignMedia($mediaId)
    {
        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    public function save()
    {
        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0)
            $this->add();
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = \PDOConnect::insert('INSERT INTO `tag` (tag) VALUES (:tag)', array('tag' => $this->tag));
    }
}