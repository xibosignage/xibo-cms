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
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class TagFactory
 * @package Xibo\Factory
 */
class TagFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Tag
     */
    public function createEmpty()
    {
        return new Tag($this->getStore(), $this->getLog());
    }

    /**
     * Get tags from a string
     * @param string $tagString
     * @return array[Tag]
     */
    public function tagsFromString($tagString)
    {
        $tags = array();

        if ($tagString == '') {
            return $tags;
        }

        // Parse the tag string, create tags
        foreach(explode(',', $tagString) as $tagName) {
            $tagName = trim($tagName);

            $tags[] = $this->tagFromString($tagName);
        }

        return $tags;
    }

    /**
     * Get Tag from String
     * @param string $tagString
     * @return Tag
     */
    public function tagFromString($tagString)
    {
        // Trim the tag
        $tagString = trim($tagString);

        // Add to the list
        try {
            $tag = $this->getByTag($tagString);
        }
        catch (NotFoundException $e) {
            // New tag
            $tag = $this->createEmpty();
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
    public function getByTag($tagName)
    {
        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` WHERE tag.tag = :tag';

        $tags = $this->getStore()->select($sql, array('tag' => $tagName));

        if (count($tags) <= 0)
            throw new NotFoundException(sprintf(__('Unable to find Tag %s'), $tagName));

        $row = $tags[0];
        $tag = $this->createEmpty();
        $tag->tagId = $this->getSanitizer()->int($row['tagId']);
        $tag->tag = $this->getSanitizer()->string($row['tag']);

        return $tag;
    }

    /**
     * Gets tags for a layout
     * @param $layoutId
     * @return Tag[]
     */
    public function loadByLayoutId($layoutId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktaglayout` ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = :layoutId';

        foreach ($this->getStore()->select($sql, array('layoutId' => $layoutId)) as $row) {
            $tag = $this->createEmpty();
            $tag->tagId = $this->getSanitizer()->int($row['tagId']);
            $tag->tag = $this->getSanitizer()->string($row['tag']);
            $tag->assignLayout($layoutId);

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for a playlist
     * @param $playlistId
     * @return Tag[]
     */
    public function loadByPlaylistId($playlistId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktagplaylist` ON lktagplaylist.tagId = tag.tagId WHERE lktagplaylist.playlistId = :playlistId';

        foreach ($this->getStore()->select($sql, array('playlistId' => $playlistId)) as $row) {
            $tag = $this->createEmpty();
            $tag->tagId = $this->getSanitizer()->int($row['tagId']);
            $tag->tag = $this->getSanitizer()->string($row['tag']);
            $tag->assignPlaylist($playlistId);

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for a campaign
     * @param $campaignId
     * @return Tag[]
     */
    public function loadByCampaignId($campaignId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktagcampaign` ON lktagcampaign.tagId = tag.tagId WHERE lktagcampaign.campaignId = :campaignId';

        foreach ($this->getStore()->select($sql, array('campaignId' => $campaignId)) as $row) {
            $tag = $this->createEmpty();
            $tag->tagId = $this->getSanitizer()->int($row['tagId']);
            $tag->tag = $this->getSanitizer()->string($row['tag']);
            $tag->assignCampaign($campaignId);

            $tags[] = $tag;
        }

        return $tags;
    }
    
    /**
     * Gets tags for media
     * @param $mediaId
     * @return Tag[]
     */
    public function loadByMediaId($mediaId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktagmedia` ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = :mediaId';

        foreach ($this->getStore()->select($sql, array('mediaId' => $mediaId)) as $row) {
            $tag = $this->createEmpty();
            $tag->tagId = $this->getSanitizer()->int($row['tagId']);
            $tag->tag = $this->getSanitizer()->string($row['tag']);
            $tag->assignMedia($mediaId);

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for displayGroupId
     * @param $displayGroupId
     * @return Tag[]
     */
    public function loadByDisplayGroupId($displayGroupId)
    {
        $tags = array();

        $sql = 'SELECT tag.tagId, tag.tag FROM `tag` INNER JOIN `lktagdisplaygroup` ON lktagdisplaygroup.tagId = tag.tagId WHERE lktagdisplaygroup.displayGroupId = :displayGroupId';

        foreach ($this->getStore()->select($sql, array('displayGroupId' => $displayGroupId)) as $row) {
            $tag = $this->createEmpty();
            $tag->tagId = $this->getSanitizer()->int($row['tagId']);
            $tag->tag = $this->getSanitizer()->string($row['tag']);
            $tag->assignDisplayGroup($displayGroupId);

            $tags[] = $tag;
        }

        return $tags;
    }
}