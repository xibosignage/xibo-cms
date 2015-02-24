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

    public function __clone()
    {
        $this->tagId = null;
    }

    /**
     * Assign Layout
     * @param int $layoutId
     */
    public function assignLayout($layoutId)
    {
        if (!in_array($layoutId, $this->layoutIds))
            $this->layoutIds[] = $layoutId;
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Save
     */
    public function save()
    {
        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0)
            $this->add();

        // Manage the links to layouts and media
        $this->linkLayouts();
        $this->linkMedia();
    }

    /**
     * Remove Assignments
     */
    public function removeAssignments()
    {
        $this->unlinkLayouts();
        $this->unlinkMedia();
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = \PDOConnect::insert('INSERT INTO `tag` (tag) VALUES (:tag) ON DUPLICATE KEY UPDATE tag = tag', array('tag' => $this->tag));
    }

    /**
     * Link all assigned layouts
     */
    private function linkLayouts()
    {
        foreach ($this->layoutIds as $layoutId) {
            \PDOConnect::update('INSERT INTO `lktaglayout` (tagId, layoutId) VALUES (:tagId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = layoutId', array(
                'tagId' => $this->tagId,
                'layoutId' => $layoutId
            ));
        }
    }

    /**
     * Unlink all assigned Layouts
     */
    private function unlinkLayouts()
    {
        foreach ($this->layoutIds as $layoutId) {
            \PDOConnect::update('DELETE FROM `lktaglayout` WHERE tagId = :tagId AND layoutId =  :layoutId) ', array(
                'tagId' => $this->tagId,
                'layoutId' => $layoutId
            ));
        }
    }

    /**
     * Link all assigned media
     */
    private function linkMedia()
    {
        foreach ($this->mediaIds as $mediaId) {
            \PDOConnect::update('INSERT INTO `lktagmedia` (tagId, mediaId) VALUES (:tagId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = mediaId', array(
                'tagId' => $this->tagId,
                'mediaId' => $mediaId
            ));
        }
    }

    /**
     * Unlink all assigned media
     */
    private function unlinkMedia()
    {
        foreach ($this->mediaIds as $mediaId) {
            \PDOConnect::update('DELETE FROM `lktagmedia` WHERE tagId = :tagId AND mediaId = :mediaId', array(
                'tagId' => $this->tagId,
                'mediaId' => $mediaId
            ));
        }
    }
}