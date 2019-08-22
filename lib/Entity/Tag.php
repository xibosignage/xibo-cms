<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\TagFactory;
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
     * @SWG\Property(description="Flag, whether the tag is a system tag")
     * @var int
     */
    public $isSystem = 0;

    /**
     * @SWG\Property(description="Flag, whether the tag requires additional values")
     * @var int
     */
    public $isRequired = 0;

    /**
     * @SWG\Property(description="An array of options assigned to this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $options;

    /**
     * @SWG\Property(description="An array of layoutID and values pairs with this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $layouts = [];

    /**
     * @SWG\Property(description="An array of playlistIDs and values with this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $playlists = [];
    
    /**
     * @SWG\Property(description="An array of campaignIDs and values with this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $campaigns = [];

    /**
     * @SWG\Property(description="An array of mediaIds and values with this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $medias = [];

    /**
     * @SWG\Property(description="An array of displayGroupIds and values with this Tag", @SWG\Items(type="string"))
     * @var array
     */
    public $displayGroups = [];

    /**
     * @SWG\Property(description="The Tag Value")
     * @var string
     */
    public $value = null;

    private $originalLayouts = [];
    private $originalPlaylists = [];
    private $originalMedias = [];
    private $originalCampaigns = [];
    private $originalDisplayGroups = [];

    /** @var  TagFactory */
    private $tagFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->tagFactory = $tagFactory;
    }

    public function __clone()
    {
        $this->tagId = null;
    }

    /**
     * @throws InvalidArgumentException
     * @throws DuplicateEntityException
     */
    public function validate()
    {
        // Name Validation
        if (strlen($this->tag) > 50 || strlen($this->tag) < 1) {
            throw new InvalidArgumentException(__("Tag must be between 1 and 50 characters"), 'tag');
        }

        // Check for duplicates
        $duplicates = $this->tagFactory->query(null, [
            'tagExact' => $this->tag,
            'notTagId' => $this->tagId,
            'disableUserCheck' => 1
        ]);

        if (count($duplicates) > 0) {
            throw new DuplicateEntityException(sprintf(__("You already own a Tag called '%s'. Please choose another name."), $this->tag));
        }
    }

    /**
     * Assign Layout
     * @param int $layoutId
     * @throws InvalidArgumentException
     */
    public function assignLayout($layoutId)
    {
        $this->load();

        foreach ($this->layouts as $id => $value) {

            if ($id == $layoutId) {
                $v = implode("", $value);
            }
        }

        $this->validateTagOptions();

        if (!array_key_exists($layoutId, $this->layouts)) {
            $this->layouts[$layoutId][] = $this->value;
        } elseif (array_key_exists($layoutId, $this->layouts) && $v != $this->value) {
            $this->layouts[$layoutId] = [$this->value];
        }
        else {
            $this->getLog()->debug('Assignment already exists with the same value, no need to update');
        }
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     */
    public function unassignLayout($layoutId)
    {
        $this->load();

        if (array_key_exists($layoutId, $this->layouts)) {
            unset($this->layouts[$layoutId]);
        }
    }

    /**
     * Assign Playlist
     * @param int $playlistId
     * @throws InvalidArgumentException
     */
    public function assignPlaylist($playlistId)
    {
        $this->load();

        foreach ($this->playlists as $id => $value) {
            if ($id == $playlistId) {
                $v = implode("", $value);
            }
        }

        $this->validateTagOptions();


        if (!array_key_exists($playlistId, $this->playlists)) {
            $this->playlists[$playlistId][] = $this->value;
        } elseif (array_key_exists($playlistId, $this->playlists) && $v != $this->value) {
            $this->playlists[$playlistId] = [$this->value];
        }
        else {
            $this->getLog()->debug('Assignment already exists with the same value, no need to update');
        }
    }

    /**
     * Unassign Playlist
     * @param int $playlistId
     */
    public function unassignPlaylist($playlistId)
    {
        $this->load();

        if (array_key_exists($playlistId, $this->playlists)) {
            unset($this->playlists[$playlistId]);
        }
    }

    /**
     * Assign Media
     * @param int $mediaId
     * @throws InvalidArgumentException
     */
    public function assignMedia($mediaId)
    {
        $this->load();

        foreach ($this->medias as $id => $value) {
            if ($id == $mediaId) {
                $v = implode("", $value);
            }
        }

        $this->validateTagOptions();

        if (!array_key_exists($mediaId, $this->medias)) {
            $this->medias[$mediaId][] = $this->value;
        } elseif (array_key_exists($mediaId, $this->medias) && $v != $this->value) {
            $this->medias[$mediaId] = [$this->value];
        }
        else {
            $this->getLog()->debug('Assignment already exists with the same value, no need to update');
        }
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        if (array_key_exists($mediaId, $this->medias)) {
            unset($this->medias[$mediaId]);
        }
    }

    /**
     * Assign Campaign
     * @param int $campaignId
     * @throws InvalidArgumentException
     */
    public function assignCampaign($campaignId)
    {
        $this->load();

        foreach ($this->campaigns as $id => $value) {
            if ($id == $campaignId) {
                $v = implode("", $value);
            }
        }

        $this->validateTagOptions();

        if (!array_key_exists($campaignId, $this->campaigns)) {
            $this->campaigns[$campaignId][] = $this->value;
        } elseif (array_key_exists($campaignId, $this->campaigns) && $v != $this->value) {
            $this->campaigns[$campaignId] = [$this->value];
        }
        else {
            $this->getLog()->debug('Assignment already exists with the same value, no need to update');
        }
    }

    /**
     * Unassign Campaign
     * @param int $campaignId
     */
    public function unassignCampaign($campaignId)
    {
        $this->load();

        if (array_key_exists($campaignId, $this->campaigns)) {
            unset($this->campaigns[$campaignId]);
        }
    }

    /**
     * Assign DisplayGroup
     * @param int $displayGroupId
     * @throws InvalidArgumentException
     */
    public function assignDisplayGroup($displayGroupId)
    {
        $this->load();

        foreach ($this->displayGroups as $id => $value) {
            if ($id == $displayGroupId) {
                $v = implode("", $value);
            }
        }

        $this->validateTagOptions();

        if (!array_key_exists($displayGroupId, $this->displayGroups)) {
            $this->displayGroups[$displayGroupId][] = $this->value;
        } elseif (array_key_exists($displayGroupId, $this->displayGroups) && $v != $this->value) {
            $this->displayGroups[$displayGroupId] = [$this->value];
        }
        else {
            $this->getLog()->debug('Assignment already exists with the same value, no need to update');
        }
    }

    /**
     * Unassign DisplayGroup
     * @param int $displayGroupId
     */
    public function unassignDisplayGroup($displayGroupId)
    {
        $this->load();

        if (array_key_exists($displayGroupId, $this->displayGroups)) {
            unset($this->displayGroups[$displayGroupId]);
        }
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->tagId == null || $this->loaded)
            return;

        $this->layouts = [];
        foreach ($this->getStore()->select('SELECT layoutId, value FROM `lktaglayout` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->layouts[$row['layoutId']][] = $row['value'];
        }

        $this->campaigns = [];
        foreach ($this->getStore()->select('SELECT campaignId, value FROM `lktagcampaign` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->campaigns[$row['campaignId']][] = $row['value'];
        }

        $this->playlists = [];
        foreach ($this->getStore()->select('SELECT playlistId, value FROM `lktagplaylist` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->playlists[$row['playlistId']][] = $row['value'];
        }

        $this->medias = [];
        foreach ($this->getStore()->select('SELECT mediaId, value FROM `lktagmedia` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->medias[$row['mediaId']][] = $row['value'];
        }

        $this->displayGroups = [];
        foreach ($this->getStore()->select('SELECT displayGroupId, value FROM `lktagdisplaygroup` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->displayGroups[$row['displayGroupId']][] = $row['value'];
        }
    
        // Set the originals
        $this->originalLayouts = $this->layouts;
        $this->originalPlaylists = $this->playlists;
        $this->originalCampaigns = $this->campaigns;
        $this->originalMedias = $this->medias;
        $this->originalDisplayGroups = $this->displayGroups;


        $this->loaded = true;
    }

    /**
     * Save
     * @param array $options
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'validate' => false
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0) {
            $this->add();
        } else {
            $this->update();
        }

        // Manage the links to layouts and media
        $this->linkLayouts();
        $this->linkPlaylists();
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
        $this->unlinkPlaylists();
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
        $this->tagId = $this->getStore()->insert('INSERT INTO `tag` (tag, isRequired, options) VALUES (:tag, :isRequired, :options) ON DUPLICATE KEY UPDATE tag = tag', [
            'tag' => $this->tag,
            'isRequired' => $this->isRequired,
            'options' => ($this->options == null) ? null : $this->options
        ]);
    }

    /**
     * Update a Tag
     * @throws \PDOException
     */
    private function update()
    {
        $this->getStore()->update('UPDATE `tag` SET tag = :tag, isRequired = :isRequired, options = :options WHERE tagId = :tagId', [
            'tagId' => $this->tagId,
            'tag' => $this->tag,
            'isRequired' => $this->isRequired,
            'options' => ($this->options == null) ? null : $this->options
        ]);
    }

    /**
     * Delete Tag
     */
    public function delete()
    {
        $this->load();

        //remove assignments
        $this->removeAssignments();
        // Delete the Tag record
        $this->getStore()->update('DELETE FROM `tag` WHERE tagId = :tagId', ['tagId' => $this->tagId]);
    }

    /**
     * Link all assigned layouts
     */
    private function linkLayouts()
    {
        $layoutsToLink = $this->compareMultidimensionalArrays($this->layouts, $this->originalLayouts);

        $this->getLog()->debug('Linking %d layouts to Tag %s', count($layoutsToLink), $this->tag);

        // Layouts that are in layoutIds but not in originalLayoutIds
        foreach ($layoutsToLink as $layoutId => $value) {

            $this->getStore()->update('INSERT INTO `lktaglayout` (tagId, layoutId, value) VALUES (:tagId, :layoutId, :value) ON DUPLICATE KEY UPDATE layoutId = layoutId, value = :value',
                [
                    'tagId' => $this->tagId,
                    'layoutId' => $layoutId,
                    'value' => $this->value
                ]);
        }
    }

    /**
     * Unlink all assigned Layouts
     */
    private function unlinkLayouts()
    {
        // Layouts that are in the originalLayoutIds but not in the current layoutIds
        $layoutsToUnlink = $this->compareMultidimensionalArrays($this->originalLayouts, $this->layouts, false);

        if (count($layoutsToUnlink) <= 0) {
            return;
        }

        $this->getLog()->debug('Unlinking %d layouts from Tag %s', count($layoutsToUnlink), $this->tag);

        // Unlink any layouts that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktaglayout` WHERE tagId = :tagId AND layoutId IN (0';

        $i = 0;
        foreach ($layoutsToUnlink as $layoutId => $value) {
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layoutId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * Link all assigned playlists
     */
    private function linkPlaylists()
    {
        $playlistsToLink =  $this->compareMultidimensionalArrays($this->playlists, $this->originalPlaylists);

        $this->getLog()->debug('Linking %d playlists to Tag %s', count($playlistsToLink), $this->tag);

        // Playlists that are in playlistIds but not in originalLayoutIds
        foreach ($playlistsToLink as $playlistId => $value) {
            $this->getStore()->update('INSERT INTO `lktagplaylist` (tagId, playlistId, value) VALUES (:tagId, :playlistId, :value) ON DUPLICATE KEY UPDATE playlistId = playlistId, value = :value', [
                'tagId' => $this->tagId,
                'playlistId' => $playlistId,
                'value' => $this->value
            ]);
        }
    }

    /**
     * Unlink all assigned Playlists
     */
    private function unlinkPlaylists()
    {
        // Playlists that are in the originalLayoutIds but not in the current playlistIds
        $playlistsToUnlink = $this->compareMultidimensionalArrays($this->originalPlaylists, $this->playlists, false);

        $this->getLog()->debug('Unlinking %d playlists from Tag %s', count($playlistsToUnlink), $this->tag);

        if (count($playlistsToUnlink) <= 0)
            return;

        // Unlink any playlists that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagplaylist` WHERE tagId = :tagId AND playlistId IN (0';

        $i = 0;
        foreach ($playlistsToUnlink as $playlistId => $value) {
            $i++;
            $sql .= ',:playlistId' . $i;
            $params['playlistId' . $i] = $playlistId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
    
    
    /**
     * Link all assigned campaigns
     */
    private function linkCampaigns()
    {
        $campaignsToLink =$this->compareMultidimensionalArrays($this->campaigns, $this->originalCampaigns);

        $this->getLog()->debug('Linking %d campaigns to Tag %s', count($campaignsToLink), $this->tag);

        // Campaigns that are in campaignIds but not in originalCampaignIds
        foreach ($campaignsToLink as $campaignId => $value) {
            $this->getStore()->update('INSERT INTO `lktagcampaign` (tagId, CampaignId, value) VALUES (:tagId, :CampaignId, :value) ON DUPLICATE KEY UPDATE campaignId = campaignId, value = :value', [
                'tagId' => $this->tagId,
                'CampaignId' => $campaignId,
                'value' => $this->value
            ]);
        }
    }

    /**
     * Unlink all assigned campaigns
     */
    private function unlinkCampaigns()
    {
        // Campaigns that are in the originalCampaignIds but not in the current campaignIds
        $campaignsToUnlink = $this->compareMultidimensionalArrays($this->originalCampaigns, $this->campaigns, false);

        $this->getLog()->debug('Unlinking %d campaigns from Tag %s', count($campaignsToUnlink), $this->tag);

        if (count($campaignsToUnlink) <= 0)
            return;

        // Unlink any campaigns that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagcampaign` WHERE tagId = :tagId AND CampaignId IN (0';

        $i = 0;
        foreach ($campaignsToUnlink as $campaignId => $value) {
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
        $mediaToLink = $this->compareMultidimensionalArrays($this->medias, $this->originalMedias);

        $this->getLog()->debug('Linking %d media to Tag %s', count($mediaToLink), $this->tag);

        foreach ($mediaToLink as $mediaId => $value) {
            $this->getStore()->update('INSERT INTO `lktagmedia` (tagId, mediaId, value) VALUES (:tagId, :mediaId, :value) ON DUPLICATE KEY UPDATE mediaId = mediaId, value = :value', [
                'tagId' => $this->tagId,
                'mediaId' => $mediaId,
                'value' => $this->value
            ]);
        }
    }

    /**
     * Unlink all assigned media
     */
    private function unlinkMedia()
    {
        $mediaToUnlink = $this->compareMultidimensionalArrays($this->originalMedias, $this->medias, false);

        $this->getLog()->debug('Unlinking %d media from Tag %s', count($mediaToUnlink), $this->tag);

        // Unlink any layouts that are NOT in the collection
        if (count($mediaToUnlink) <= 0)
            return;

        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagmedia` WHERE tagId = :tagId AND mediaId IN (0';

        $i = 0;
        foreach ($mediaToUnlink as $mediaId => $value) {
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
        $displayGroupsToLink = $this->compareMultidimensionalArrays($this->displayGroups, $this->originalDisplayGroups);

        $this->getLog()->debug('Linking ' . count($displayGroupsToLink) . ' displayGroups to Tag ' . $this->tag);

        // DisplayGroups that are in $this->displayGroupIds but not in $this->originalDisplayGroupIds
        foreach ($displayGroupsToLink as $displayGroupId => $value) {
            $this->getStore()->update('INSERT INTO `lktagdisplaygroup` (tagId, displayGroupId, value) VALUES (:tagId, :displayGroupId, :value) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId, value = :value', [
                'tagId' => $this->tagId,
                'displayGroupId' => $displayGroupId,
                'value' => $this->value
            ]);
        }
    }

    /**
     * Unlink all assigned displayGroups
     */
    private function unlinkDisplayGroups()
    {
        // DisplayGroups that are in the $this->originalDisplayGroupIds but not in the current $this->displayGroupIds
        $displayGroupsToUnlink = $this->compareMultidimensionalArrays($this->originalDisplayGroups, $this->displayGroups, false);

        $this->getLog()->debug('Unlinking ' . count($displayGroupsToUnlink) . ' displayGroups from Tag ' . $this->tag);

        if (count($displayGroupsToUnlink) <= 0)
            return;

        // Unlink any displayGroups that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagdisplaygroup` WHERE tagId = :tagId AND displayGroupId IN (0';

        $i = 0;
        foreach ($displayGroupsToUnlink as $displayGroupId => $value) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * Is this tag a system tag?
     * @return bool
     * @throws \Xibo\Exception\NotFoundException
     */
    public function isSystemTag()
    {
        $tag = $this->tagFactory->getById($this->tagId);

        return $tag->isSystem === 1;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validateTagOptions()
    {
        if (isset($this->options)) {
            $tagOptionsString = implode(',', json_decode($this->options));
            $tagOptionsArray = explode(',', $tagOptionsString);

            if (isset($this->value) && !in_array($this->value, $tagOptionsArray)) {
                throw new InvalidArgumentException(sprintf(__('Provided tag value %s, not found in tag %s options',
                    $this->value, $this->tag)), 'tags');
            }

            if (!isset($this->value) && $this->isRequired == 1) {
                throw new InvalidArgumentException(sprintf(__('Provided Tag %s, requires a value', $this->tag)),
                    'tags');
            }
        }

        if(isset($this->value) && !isset($this->options)) {
            throw new InvalidArgumentException(sprintf(__('Provided Tag %s, does not have defined option values', $this->tag)), 'tags');
        }

    }

    /**
     * Removes Tag value from lktagtables
     *
     * @param array $values An Array of values that should be removed from assignment
     */
    public function updateTagValues($values)
    {
        $this->getLog()->debug('Tag options were changed, the following values need to be removed ' . json_encode($values));

        foreach ($values as $value) {
            $this->getLog()->debug('removing following value from lktag tables ' . $value);

            $this->getStore()->update('UPDATE `lktagcampaign` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagdisplaygroup` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktaglayout` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagmedia` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagplaylist` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

        }
    }
}