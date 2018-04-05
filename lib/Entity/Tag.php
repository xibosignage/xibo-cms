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
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class Tag
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Tag implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Tag ID")
     * @var int
     */
    public $tagId;

    /**
     * @SWG\Property(description="The Tag Name")
     * @var string
     */
    public $tag;

    /**
     * @SWG\Property(description="An array of layoutIDs with this Tag")
     * @var int[]
     */
    public $layoutIds = [];
    
    /**
     * @SWG\Property(description="An array of campaignIDs with this Tag")
     * @var int[]
     */
    public $campaignIds = [];    

    /**
     * @SWG\Property(description="An array of mediaIds with this Tag")
     * @var int[]
     */
    public $mediaIds = [];

    /**
     * @SWG\Property(description="An array of displayGroupIds with this Tag")
     * @var int[]
     */
    public $displayGroupIds = [];

    private $originalLayoutIds = [];
    private $originalMediaIds = [];
    private $originalCampaignIds = [];
    private $originalDisplayGroupIds = [];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
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
        $this->load();

        if (!in_array($layoutId, $this->layoutIds))
            $this->layoutIds[] = $layoutId;
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     */
    public function unassignLayout($layoutId)
    {
        $this->load();

        $this->layoutIds = array_diff($this->layoutIds, [$layoutId]);
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        $this->load();

        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }
    
    /**
     * Assign Campaign
     * @param int $campaignId
     */
    public function assignCampaign($campaignId)
    {
        $this->load();

        if (!in_array($campaignId, $this->campaignIds))
            $this->campaignIds[] = $campaignId;
    }

    /**
     * Unassign Campaign
     * @param int $campaignId
     */
    public function unassignCampaign($campaignId)
    {
        $this->load();

        $this->campaignIds = array_diff($this->campaignIds, [$campaignId]);
    }

    /**
     * Assign DisplayGroup
     * @param int $displayGroupId
     */
    public function assignDisplayGroup($displayGroupId)
    {
        $this->load();

        if (!in_array($displayGroupId, $this->displayGroupIds))
            $this->displayGroupIds[] = $displayGroupId;
    }

    /**
     * Unassign DisplayGroup
     * @param int $displayGroupId
     */
    public function unassignDisplayGroup($displayGroupId)
    {
        $this->load();

        $this->displayGroupIds = array_diff($this->displayGroupIds, [$displayGroupId]);
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->tagId == null || $this->loaded)
            return;

        $this->layoutIds = [];
        foreach ($this->getStore()->select('SELECT layoutId FROM `lktaglayout` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->layoutIds[] = $row['layoutId'];
        }

        if (DBVERSION >= 129) {
            $this->campaignIds = [];
            foreach ($this->getStore()->select('SELECT campaignId FROM `lktagcampaign` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
                $this->campaignIds[] = $row['campaignId'];
            }
        }

        $this->mediaIds = [];
        foreach ($this->getStore()->select('SELECT mediaId FROM `lktagmedia` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->mediaIds[] = $row['mediaId'];
        }

        $this->displayGroupIds = [];
        // Didn't exist before 134
        if (DBVERSION >= 134) {
            foreach ($this->getStore()->select('SELECT displayGroupId FROM `lktagdisplaygroup` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
                $this->displayGroupIds[] = $row['displayGroupId'];
            }
        }

        // Set the originals
        $this->originalLayoutIds = $this->layoutIds;
        $this->originalCampaignIds = $this->campaignIds;
        $this->originalMediaIds = $this->mediaIds;
        $this->originalDisplayGroupIds = $this->displayGroupIds;

        $this->loaded = true;
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
        $this->linkCampaigns();
        $this->linkMedia();
        $this->linkDisplayGroups();
        $this->removeAssignments();

        $this->getLog()->debug('Saving Tag: %s, %d', $this->tag, $this->tagId);
    }

    /**
     * Remove Assignments
     */
    public function removeAssignments()
    {
        $this->unlinkLayouts();
        $this->unlinkCampaigns();
        $this->unlinkMedia();
        $this->unlinkDisplayGroups();
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = $this->getStore()->insert('INSERT INTO `tag` (tag) VALUES (:tag) ON DUPLICATE KEY UPDATE tag = tag', array('tag' => $this->tag));
    }

    /**
     * Link all assigned layouts
     */
    private function linkLayouts()
    {
        $layoutsToLink = array_diff($this->layoutIds, $this->originalLayoutIds);

        $this->getLog()->debug('Linking %d layouts to Tag %s', count($layoutsToLink), $this->tag);

        // Layouts that are in layoutIds but not in originalLayoutIds
        foreach ($layoutsToLink as $layoutId) {
            $this->getStore()->update('INSERT INTO `lktaglayout` (tagId, layoutId) VALUES (:tagId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = layoutId', array(
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
        // Layouts that are in the originalLayoutIds but not in the current layoutIds
        $layoutsToUnlink = array_diff($this->originalLayoutIds, $this->layoutIds);

        $this->getLog()->debug('Unlinking %d layouts from Tag %s', count($layoutsToUnlink), $this->tag);

        if (count($layoutsToUnlink) <= 0)
            return;

        // Unlink any layouts that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktaglayout` WHERE tagId = :tagId AND layoutId IN (0';

        $i = 0;
        foreach ($layoutsToUnlink as $layoutId) {
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layoutId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }
    
    
    /**
     * Link all assigned campaigns
     */
    private function linkCampaigns()
    {
        // Didn't exist before 129
        if (DBVERSION < 129)
            return;

        $campaignsToLink = array_diff($this->campaignIds, $this->originalCampaignIds);

        $this->getLog()->debug('Linking %d campaigns to Tag %s', count($campaignsToLink), $this->tag);

        // Campaigns that are in campaignIds but not in originalCampaignIds
        foreach ($campaignsToLink as $campaignId) {
            $this->getStore()->update('INSERT INTO `lktagcampaign` (tagId, CampaignId) VALUES (:tagId, :CampaignId) ON DUPLICATE KEY UPDATE campaignId = campaignId', array(
                'tagId' => $this->tagId,
                'CampaignId' => $campaignId
            ));
        }
    }

    /**
     * Unlink all assigned campaigns
     */
    private function unlinkCampaigns()
    {
        // Didn't exist before 129
        if (DBVERSION < 129)
            return;

        // Campaigns that are in the originalCampaignIds but not in the current campaignIds
        $campaignsToUnlink = array_diff($this->originalCampaignIds, $this->campaignIds);

        $this->getLog()->debug('Unlinking %d campaigns from Tag %s', count($campaignsToUnlink), $this->tag);

        if (count($campaignsToUnlink) <= 0)
            return;

        // Unlink any campaigns that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagcampaign` WHERE tagId = :tagId AND CampaignId IN (0';

        $i = 0;
        foreach ($campaignsToUnlink as $campaignId) {
            $i++;
            $sql .= ',:CampaignId' . $i;
            $params['CampaignId' . $i] = $campaignId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }

    /**
     * Link all assigned media
     */
    private function linkMedia()
    {
        $mediaToLink = array_diff($this->mediaIds, $this->originalMediaIds);

        $this->getLog()->debug('Linking %d media to Tag %s', count($mediaToLink), $this->tag);

        foreach ($mediaToLink as $mediaId) {
            $this->getStore()->update('INSERT INTO `lktagmedia` (tagId, mediaId) VALUES (:tagId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = mediaId', array(
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
        $mediaToUnlink = array_diff($this->originalMediaIds, $this->mediaIds);

        $this->getLog()->debug('Unlinking %d media from Tag %s', count($mediaToUnlink), $this->tag);

        // Unlink any layouts that are NOT in the collection
        if (count($mediaToUnlink) <= 0)
            return;

        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagmedia` WHERE tagId = :tagId AND mediaId IN (0';

        $i = 0;
        foreach ($mediaToUnlink as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }


    /**
     * Link all assigned displayGroups
     */
    private function linkDisplayGroups()
    {
        // Didn't exist before 134
        if (DBVERSION < 134)
            return;

        $displayGroupsToLink = array_diff($this->displayGroupIds, $this->originalDisplayGroupIds);

        $this->getLog()->debug('Linking ' . count($displayGroupsToLink) . ' displayGroups to Tag ' . $this->tag);

        // DisplayGroups that are in $this->displayGroupIds but not in $this->originalDisplayGroupIds
        foreach ($displayGroupsToLink as $displayGroupId) {
            $this->getStore()->update('INSERT INTO `lktagdisplaygroup` (tagId, displayGroupId) VALUES (:tagId, :displayGroupId) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId', array(
                'tagId' => $this->tagId,
                'displayGroupId' => $displayGroupId
            ));
        }
    }

    /**
     * Unlink all assigned displayGroups
     */
    private function unlinkDisplayGroups()
    {
        // Didn't exist before 134
        if (DBVERSION < 134)
            return;

        // DisplayGroups that are in the $this->originalDisplayGroupIds but not in the current $this->displayGroupIds
        $displayGroupsToUnlink = array_diff($this->originalDisplayGroupIds, $this->displayGroupIds);

        $this->getLog()->debug('Unlinking ' . count($displayGroupsToUnlink) . ' displayGroups from Tag ' . $this->tag);

        if (count($displayGroupsToUnlink) <= 0)
            return;

        // Unlink any displayGroups that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagdisplaygroup` WHERE tagId = :tagId AND displayGroupId IN (0';

        $i = 0;
        foreach ($displayGroupsToUnlink as $displayGroupId) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
}