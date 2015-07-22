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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class TagFactory extends BaseFactory
{
    /**
     * Get tags from a string
     * @param string $tagString
     * @return array[Tag]
     */
    public static function tagsFromString($tagString)
    {
        $tags = array();

        if ($tagString == '') {
            return $tags;
        }

        // Parse the tag string, create tags
        foreach(explode(',', $tagString) as $tagName) {
            $tagName = trim($tagName);

            $tags[] = TagFactory::tagFromString($tagName);
        }

        return $tags;
    }

    /**
     * Get Tag from String
     * @param string $tagString
     * @return Tag
     */
    public static function tagFromString($tagString)
    {
        // Add to the list
        try {
            $tag = TagFactory::getByTag($tagString);
        }
        catch (NotFoundException $e) {
            // New tag
            $tag = new Tag();
            $tag->tag = $tagString;
        }

        return $tag;
    }

    /**
     * Load tag by Tag Name
     * @param string $tagName
     * @return Tag
     * @throws NotFoundException
     */
    public static function getByTag($tagName)
    {
        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` WHERE tag.tag = :tag';

        $tags = \Xibo\Storage\PDOConnect::select($sql, array('tag' => $tagName));

        if (count($tags) <= 0)
            throw new NotFoundException(sprintf(__('Unable to find Tag %s'), $tagName));

        $row = $tags[0];
        $tag = new Tag();
        $tag->tagId = \Xibo\Helper\Sanitize::int($row['tagId']);
        $tag->tag = \Xibo\Helper\Sanitize::string($row['tag']);

        return $tag;
    }

    /**
     * Gets tags for a layout
     * @param $layoutId
     * @return array[Tag]
     */
    public static function loadByLayoutId($layoutId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktaglayout` ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = :layoutId';

        foreach (\Xibo\Storage\PDOConnect::select($sql, array('layoutId' => $layoutId)) as $row) {
            $tag = new Tag();
            $tag->tagId = \Xibo\Helper\Sanitize::int($row['tagId']);
            $tag->tag = \Xibo\Helper\Sanitize::string($row['tag']);
            $tag->assignLayout($layoutId);

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for media
     * @param $mediaId
     * @return array[Tag]
     */
    public static function loadByMediaId($mediaId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktagmedia` ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = :mediaId';

        foreach (PDOConnect::select($sql, array('mediaId' => $mediaId)) as $row) {
            $tag = new Tag();
            $tag->tagId = Sanitize::int($row['tagId']);
            $tag->tag = Sanitize::string($row['tag']);
            $tag->assignMedia($mediaId);

            $tags[] = $tag;
        }

        return $tags;
    }
}