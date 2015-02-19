<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (TagFactory.php) is part of Xibo.
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


use Xibo\Entity\Tag;

class TagFactory
{
    /**
     * Get tags from a string
     * @param string $tagString
     * @return array[Tag]
     */
    public static function tagsFromString($tagString)
    {
        $tags = array();

        // Parse the tag string, create tags
        foreach(explode(',', $tagString) as $tagName) {
            $tag = new Tag();
            $tag->tag = $tagName;

            // Add to the list
            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for a layout
     * @param $layoutId
     * @return array[Tag]
     */
    public static function loadByLayoutId($layoutId)
    {
        $tags = array();

        $sql = 'SELECT tagId, tag FROM `tag` INNER JOIN `lktaglayout` ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = :layoutId';

        foreach (\PDOConnect::select($sql, array('layoutId' => $layoutId)) as $row) {
            $tag = new Tag();
            $tag->tagId = \Kit::ValidateParam($row['tagId'], _INT);
            $tag->tag = \Kit::ValidateParam($row['tag'], _STRING);
            $tag->assignLayout($layoutId);

            $tags[] = $tag;
        }

        return $tags;
    }
}