<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\LayoutBuildEvent;
use Xibo\Event\LayoutBuildRegionEvent;
use Xibo\Factory\ActionFactory;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\Profiler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\ModuleWidget;

/**
 * Class Layout
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 *
 * @property $isLocked
 */
class Layout implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The layoutId"
     * )
     * @var int
     */
    public $layoutId;

    /**
     * @var int
     * @SWG\Property(
     *  description="The userId of the Layout Owner"
     * )
     */
    public $ownerId;

    /**
     * @var int
     * @SWG\Property(
     *  description="The id of the Layout's dedicated Campaign"
     * )
     */
    public $campaignId;

    /**
     * @var int
     * @SWG\Property(
     *  description="The parentId, if this Layout has a draft"
     * )
     */
    public $parentId;

    /**
     * @var int
     * @SWG\Property(
     *  description="The Status Id"
     * )
     */
    public $publishedStatusId = 1;

    /**
     * @var string
     * @SWG\Property(
     *  description="The Published Status (Published, Draft or Pending Approval"
     * )
     */
    public $publishedStatus;

    /**
     * @var string
     * @SWG\Property(
     *  description="The Published Date"
     * )
     */
    public $publishedDate;

    /**
     * @var int
     * @SWG\Property(
     *  description="The id of the image media set as the background"
     * )
     */
    public $backgroundImageId;

    /**
     * @var int
     * @SWG\Property(
     *  description="The XLF schema version"
     * )
     */
    public $schemaVersion;

    /**
     * @var string
     * @SWG\Property(
     *  description="The name of the Layout"
     * )
     */
    public $layout;

    /**
     * @var string
     * @SWG\Property(
     *  description="The description of the Layout"
     * )
     */
    public $description;

    /**
     * @var string
     * @SWG\Property(
     *  description="A HEX string representing the Layout background color"
     * )
     */
    public $backgroundColor;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was created"
     * )
     */
    public $createdDt;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was last modified"
     * )
     */
    public $modifiedDt;

    /**
     * @var int
     * @SWG\Property(
     *  description="Flag indicating the Layout status"
     * )
     */
    public $status;

    /**
     * @var int
     * @SWG\Property(
     *  description="Flag indicating whether the Layout is retired"
     * )
     */
    public $retired;

    /**
     * @var int
     * @SWG\Property(
     *  description="The Layer that the background should occupy"
     * )
     */
    public $backgroundzIndex;

    /**
     * @var double
     * @SWG\Property(
     *  description="The Layout Width"
     * )
     */
    public $width;

    /**
     * @var double
     * @SWG\Property(
     *  description="The Layout Height"
     * )
     */
    public $height;

    /**
     * @var int
     * @SWG\Property(
     *  description="If this Layout has been requested by Campaign, then this is the display order of the Layout within the Campaign"
     * )
     */
    public $displayOrder;

    /**
     * @var int
     * @SWG\Property(
     *  description="A read-only estimate of this Layout's total duration in seconds. This is equal to the longest region duration and is valid when the layout status is 1 or 2."
     * )
     */
    public $duration;

    /**
     * @var string
     * @SWG\Property(description="A status message detailing any errors with the layout")
     */
    public $statusMessage;

    /**
     * @var int
     * @SWG\Property(
     *  description="Flag indicating whether the Layout stat is enabled"
     * )
     */
    public $enableStat;

    /**
     * @var int
     * @SWG\Property(
     *  description="Flag indicating whether the default transitions should be applied to this Layout"
     * )
     */
    public $autoApplyTransitions;

    /**
     * @var string
     * @SWG\Property(description="Code identifier for this Layout")
     */
    public $code;

    // Child items
    /**
     * @SWG\Property(description="An array of Regions belonging to this Layout")
     * @var Region[]
     */
    public $regions = [];

    /**
     * @SWG\Property(description="An array of Tags belonging to this Layout")
     * @var \Xibo\Entity\Tag[]
     */
    public $tags = [];

    /** @var Region[]  */
    public $drawers = [];

    /** @var Action[] */
    public $actions = [];
    public $permissions = [];
    public $campaigns = [];

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    public $tagValues;

    /**
     * @SWG\Property(description="The id of the Folder this Layout belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Layout")
     * @var int
     */
    public $permissionsFolderId;

    // Private
    private $unassignTags = [];

    // Handle empty regions
    private $hasEmptyRegion = false;

    public static $loadOptionsMinimum = [
        'loadPlaylists' => false,
        'loadTags' => false,
        'loadPermissions' => false,
        'loadCampaigns' => false
    ];

    public static $saveOptionsMinimum = [
        'saveLayout' => true,
        'saveRegions' => false,
        'saveTags' => false,
        'setBuildRequired' => true,
        'validate' => false,
        'audit' => false,
        'notify' => false
    ];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var  EventDispatcherInterface */
    private $dispatcher;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var ActionFactory */
    private $actionFactory;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param EventDispatcherInterface $eventDispatcher
     * @param PermissionFactory $permissionFactory
     * @param RegionFactory $regionFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ModuleFactory $moduleFactory
     * @param PlaylistFactory $playlistFactory
     * @param ActionFactory $actionFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct($store, $log, $config, $eventDispatcher, $permissionFactory, $regionFactory, $tagFactory, $campaignFactory, $layoutFactory, $mediaFactory, $moduleFactory, $playlistFactory, $actionFactory, $folderFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->setPermissionsClass('Xibo\Entity\Campaign');
        $this->config = $config;
        $this->dispatcher = $eventDispatcher;
        $this->permissionFactory = $permissionFactory;
        $this->regionFactory = $regionFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->moduleFactory = $moduleFactory;
        $this->playlistFactory = $playlistFactory;
        $this->actionFactory = $actionFactory;
        $this->folderFactory = $folderFactory;
    }

    public function __clone()
    {
        // Clear the layout id
        $this->layoutId = null;
        $this->campaignId = null;
        $this->code = null;
        $this->hash = null;
        $this->permissions = [];

        // A normal clone (for copy) will set this to Published, so that the copy is published.
        $this->publishedStatusId = 1;

        // Clone the regions
        $this->regions = array_map(function ($object) { return clone $object; }, $this->regions);
        // Clone drawers
        $this->drawers = array_map(function ($object) { return clone $object; }, $this->drawers);
        // Clone actions
        $this->actions = array_map(function ($object) { return clone $object; }, $this->actions);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $countRegions = is_array($this->regions) ? count($this->regions) : 0;
        $countTags = is_array($this->tags) ? count($this->tags) : 0;
        $countDrawers = is_array($this->drawers) ? count($this->drawers) : 0;

        $statusMessages = $this->getStatusMessage();
        $countMessages = is_array($statusMessages) ? count($statusMessages) : 0;

        return sprintf('Layout %s - %d x %d. Regions = %d, Drawers = %d, Tags = %d. layoutId = %d. Status = %d, messages %d', $this->layout, $this->width, $this->height, $countRegions, $countDrawers, $countTags, $this->layoutId, $this->status, $countMessages);
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5($this->layoutId . $this->ownerId . $this->campaignId . $this->backgroundImageId . $this->backgroundColor . $this->width . $this->height . $this->status . $this->description . json_encode($this->statusMessage) . $this->publishedStatusId . json_encode($this->actions));
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->campaignId;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner of the Layout (including children)
     * @param int $ownerId
     * @param bool $cascade Cascade ownership change down to Playlist records
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function setOwner($ownerId, $cascade = false)
    {
        $this->ownerId = $ownerId;

        $this->load();

        $allRegions = array_merge($this->regions, $this->drawers);

        foreach ($allRegions as $region) {
            /* @var Region $region */
            $region->setOwner($ownerId, $cascade);
        }
    }

    /**
     * @return bool if this Layout has an empty Region.
     */
    public function hasEmptyRegion()
    {
        return $this->hasEmptyRegion;
    }

    /**
     * Set the status of this layout to indicate a build is required
     */
    private function setBuildRequired()
    {
        $this->status = 3;
    }

    /**
     * Load Regions from a Layout
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getRegion($regionId)
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            if ($region->regionId == $regionId) {
                return $region;
            }
        }

        throw new NotFoundException(__('Cannot find region'));
    }

    /**
     * Load Drawers from a Layout
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getDrawer($regionId)
    {
        foreach ($this->drawers as $drawer) {
            /* @var Region $drawer */
            if ($drawer->regionId == $regionId) {
                return $drawer;
            }
        }

        throw new NotFoundException(__('Cannot find drawer region'));
    }

    /**
     * Load both Regions and Drawers from a Layout
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getRegionOrDrawer($regionId)
    {
        /** @var Region[] $allRegions */
        $allRegions = array_merge($this->regions, $this->drawers);

        foreach ($allRegions as $region) {
            /* @var Region $region */
            if ($region->regionId == $regionId) {
                return $region;
            }
        }

        throw new NotFoundException(__('Cannot find Region or Drawer'));
    }

    /**
     * Get All Widgets assigned to this Layout
     * @return Widget[]
     * @throws NotFoundException
     */
    public function getAllWidgets()
    {
        $widgets = [];

        $allRegions = array_merge($this->regions, $this->drawers);

        /** @var Region $region */
        foreach ($allRegions as $region) {
            $widgets = array_merge($region->getPlaylist()->widgets, $widgets);
        }

        return $widgets;
    }

    /**
     * Get Region Widgets assigned to this Layout
     * @return Widget[]
     * @throws NotFoundException
     */
    public function getRegionWidgets()
    {
        $widgets = [];

        foreach ($this->regions as $region) {
            $widgets = array_merge($region->getPlaylist()->widgets, $widgets);
        }

        return $widgets;
    }

    /**
     * Get Drawer Widgets assigned to this Layout
     * @return Widget[]
     * @throws NotFoundException
     */
    public function getDrawerWidgets()
    {
        $widgets = [];

        foreach ($this->drawers as $drawer) {
            $widgets = array_merge($drawer->getPlaylist()->widgets, $widgets);
        }

        return $widgets;
    }

    /**
     * Is this Layout Editable - i.e. are we in a draft state or not.
     * @return bool true if this layout is editable
     */
    public function isEditable()
    {
        return ($this->publishedStatusId === 2); // Draft
    }

    /**
     * Is this Layout a Child?
     * @return bool
     */
    public function isChild()
    {
        return ($this->parentId !== null);
    }

    /**
     * Is this Layout a Template?
     * @return bool
     */
    public function isTemplate(): bool
    {
        // Tags might be an array (if we've called `load()` or a string if we've returned directly from the DB)
        $tagsArray = is_array($this->tags)
            ? $this->tags
            : explode(',', $this->tags);

        foreach ($tagsArray as $tag) {
            if ($tag === 'template') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getStatusMessage()
    {
        if ($this->statusMessage === null || empty($this->statusMessage)) {
            return [];
        }

        if (is_array($this->statusMessage)) {
            return $this->statusMessage;
        }

        $this->statusMessage = json_decode($this->statusMessage, true);

        return $this->statusMessage;
    }

    /**
     * Push a new message
     * @param $message
     */
    public function pushStatusMessage($message)
    {
        $this->getStatusMessage();

        $this->statusMessage[] = $message;
    }

    /**
     * Clear status message
     */
    private function clearStatusMessage()
    {
        $this->statusMessage = null;
    }

    /**
     * Load this Layout
     * @param array $options
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadPlaylists' => true,
            'loadTags' => true,
            'loadPermissions' => true,
            'loadCampaigns' => true,
            'loadActions' => true,
        ], $options);

        if ($this->loaded || $this->layoutId == 0) {
            return;
        }

        $this->getLog()->debug(sprintf('Loading Layout %d with options %s', $this->layoutId, json_encode($options)));

        // Load permissions
        if ($options['loadPermissions']) {
            $this->permissions = $this->permissionFactory->getByObjectId('Xibo\\Entity\\Campaign', $this->campaignId);
        }

        // Load all regions
        $this->regions = $this->regionFactory->getByLayoutId($this->layoutId);

        // load all drawers
        $this->drawers = $this->regionFactory->getDrawersByLayoutId($this->layoutId);

        if ($options['loadPlaylists']) {
            $this->loadPlaylists($options);
        }

        // Load all tags
        if ($options['loadTags']) {
            $this->tags = $this->tagFactory->loadByLayoutId($this->layoutId);
        }

        // Load Campaigns
        if ($options['loadCampaigns']) {
            $this->campaigns = $this->campaignFactory->getByLayoutId($this->layoutId);
        }

        // Load Actions
        if ($options['loadActions']) {
            $this->actions = $this->actionFactory->getBySourceAndSourceId('layout', $this->layoutId);
        }

        // Set the hash
        $this->hash = $this->hash();
        $this->loaded = true;

        $this->getLog()->debug('Loaded ' . $this->layoutId . ' with hash ' . $this->hash . ', status ' . $this->status);
    }

    /**
     * Load All Playlists
     * @param array $options
     * @throws NotFoundException
     */
    public function loadPlaylists($options = [])
    {
        $allRegions = array_merge($this->regions, $this->drawers);

        foreach ($allRegions as $region) {
            /* @var Region $region */
            $region->load($options);
        }
    }

    /**
     * Load Region Playlists
     * @param array $options
     * @throws NotFoundException
     */
    public function loadDrawerPlaylists($options = [])
    {
        foreach ($this->drawers as $drawer) {
            /* @var Region $region */
            $drawer->load($options);
        }
    }

    /**
     * Load Drawer Playlists
     * @param array $options
     * @throws NotFoundException
     */
    public function loadRegionPlaylists($options = [])
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->load($options);
        }
    }

    /**
     * Get this Layout's Campaign
     * @return \Xibo\Entity\Campaign
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getCampaign()
    {
        return $this->campaignFactory->getById($this->campaignId);
    }

    /**
     * Save this Layout
     * @param array $options
     * @throws GeneralException
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveLayout' => true,
            'saveRegions' => true,
            'saveTags' => true,
            'setBuildRequired' => true,
            'validate' => true,
            'notify' => true,
            'audit' => true,
            'import' => false
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($options['setBuildRequired']) {
            $this->setBuildRequired();
        }

        $this->getLog()->debug('Saving ' . $this . ' with options ' . json_encode($options, JSON_PRETTY_PRINT));

        // New or existing layout
        if ($this->layoutId == null || $this->layoutId == 0) {
            $this->add();

            if ($options['audit']) {
                if ($this->parentId === null) {
                    $this->audit($this->layoutId, 'Added', ['layoutId' => $this->layoutId, 'layout' => $this->layout, 'campaignId' => $this->campaignId]);
                } else {
                    $this->audit($this->layoutId, 'Checked out', ['layoutId' => $this->parentId, 'layout' => $this->layout, 'campaignId' => $this->campaignId]);
                }
            }

        } else if (($this->hash() != $this->hash && $options['saveLayout']) || $options['setBuildRequired']) {
            $this->update($options);

            if ($options['audit'] && count($this->getChangedProperties()) > 0) {
                $change = $this->getChangedProperties();
                $change['campaignId'][] = $this->campaignId;

                if ($this->parentId === null) {
                    $this->audit($this->layoutId, 'Updated', $change);
                } else {
                    $this->audit($this->layoutId, 'Updated Draft', $change);
                }
            }

        } else {
            $this->getLog()->info('Save layout properties unchanged for layoutId ' . $this->layoutId . ', status = ' . $this->status);
        }

        if ($options['saveRegions']) {
            $this->getLog()->debug('Saving Regions on ' . $this);

            $allRegions = array_merge($this->regions, $this->drawers);

            // Update all regions
            foreach ($allRegions as $region) {
                /* @var Region $region */

                // Assert the Layout Id
                $region->layoutId = $this->layoutId;
                $region->save($options);
            }
        }

        if ($options['saveTags']) {
            $this->getLog()->debug('Saving tags on ' . $this);

            // if we are saving new Layout after import, we need to go through all Tags on the Layout
            // double check if any of them match Tags added from imported Playlists or Media
            // otherwise we would end up with duplicated Tags
            if ($options['import']) {
                $importedTags = $this->tags;
                $this->tags = [];
                foreach ($importedTags as $importedTag) {
                    $this->tags[] = $this->tagFactory->tagFromString(
                        $importedTag->tag . (!empty($importedTag->value) ? '|' . $importedTag->value : '')
                    );
                }
            }

            // Remove unwanted ones
            if (is_array($this->unassignTags)) {
                foreach ($this->unassignTags as $tag) {
                    /* @var Tag $tag */
                    $this->getLog()->debug('Unassigning tag ' . $tag->tag);

                    $tag->unassignLayout($this->layoutId);
                    $tag->save();
                }
            }

            // Save the tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag ' . $tag->tag);

                    $tag->assignLayout($this->layoutId);
                    $tag->save();
                }
            }
        }

        $this->getLog()->debug('Save finished for ' . $this);
    }

    /**
     * Delete Layout
     * @param array $options
     * @throws GeneralException
     */
    public function delete($options = [])
    {
        // We must ensure everything is loaded before we delete
        if (!$this->loaded) {
            $this->load();
        }

        $this->getLog()->debug('Deleting ' . $this);

        // We cannot delete the default default
        if ($this->layoutId == $this->config->getSetting('DEFAULT_LAYOUT')) {
            throw new InvalidArgumentException(__('This layout is used as the global default and cannot be deleted'), 'layoutId');
        }

        // Delete our draft if we have one
        // this is recursive, so be careful!
        if ($this->parentId === null && $this->publishedStatusId === 2) {
            try {
                $draft = $this->layoutFactory->getByParentId($this->layoutId);
                $draft->delete(['notify' => false]);
            } catch (NotFoundException $notFoundException) {
                $this->getLog()->info('No draft to delete for a Layout in the Draft state, odd!');
            }
        }

        // Unassign all Tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignLayout($this->layoutId);
            $tag->save();
        }

        $allRegions = array_merge($this->regions, $this->drawers);

        // Delete Regions
        foreach ($allRegions as $region) {
            /* @var Region $region */
            $region->delete($options);
        }

        // If we are the top level parent we also delete objects that sit on the top-level
        if ($this->parentId === null) {

            // Delete Permissions
            foreach ($this->permissions as $permission) {
                /* @var Permission $permission */
                $permission->deleteAll();
            }

            // Delete widget history
            $this->getStore()->update('DELETE FROM `widgethistory` WHERE layoutHistoryId IN (SELECT layoutHistoryId FROM `layouthistory` WHERE campaignId = :campaignId)', ['campaignId' => $this->campaignId]);

            // Delete layout history
            $this->getStore()->update('DELETE FROM `layouthistory` WHERE campaignId = :campaignId', ['campaignId' => $this->campaignId]);

            // Unassign from all Campaigns
            foreach ($this->campaigns as $campaign) {
                /* @var Campaign $campaign */
                $campaign->setChildObjectDependencies($this->layoutFactory);
                $campaign->unassignLayout($this, true);
                $campaign->save(['validate' => false]);
            }

            // Delete our own Campaign
            $campaign = $this->campaignFactory->getById($this->campaignId);
            $campaign->setChildObjectDependencies($this->layoutFactory);
            $campaign->delete();

            // Remove the Layout from any display defaults
            $this->getStore()->update('UPDATE `display` SET defaultlayoutid = :defaultLayoutId WHERE defaultlayoutid = :layoutId', [
                'layoutId' => $this->layoutId,
                'defaultLayoutId' => $this->config->getSetting('DEFAULT_LAYOUT')
            ]);

            // Remove any display group links
            $this->getStore()->update('DELETE FROM `lklayoutdisplaygroup` WHERE layoutId = :layoutId', ['layoutId' => $this->layoutId]);

        } else {
            // Remove the draft from any Campaign assignments
            $this->getStore()->update('DELETE FROM `lkcampaignlayout` WHERE layoutId = :layoutId', ['layoutId' => $this->layoutId]);
        }

        foreach ($this->actions as $action) {
            $action->delete();
        }

        // Remove the Layout (now it is orphaned it can be deleted safely)
        $this->getStore()->update('DELETE FROM `layout` WHERE layoutid = :layoutId', array('layoutId' => $this->layoutId));

        $this->getLog()->audit('Layout', $this->layoutId, 'Layout Deleted', ['layoutId' => $this->layoutId]);

        // Delete the cached file (if there is one)
        if (file_exists($this->getCachePath())) {
            @unlink($this->getCachePath());
        }

        // Audit the Delete
        $this->audit($this->layoutId, 'Deleted' . (($this->parentId !== null) ? ' draft for ' . $this->parentId : ''));
    }

    /**
     * Validate this layout
     * @throws GeneralException
     */
    public function validate()
    {
        // We must provide either a template or a resolution
        if ($this->width == 0 || $this->height == 0) {
            throw new InvalidArgumentException(__('The layout dimensions cannot be empty'), 'width/height');
        }

        // Validation
        if (strlen($this->layout) > 50 || strlen($this->layout) < 1) {
            throw new InvalidArgumentException(__("Layout Name must be between 1 and 50 characters"), 'name');
        }

        if (strlen($this->description) > 254) {
            throw new InvalidArgumentException(__("Description can not be longer than 254 characters"), 'description');
        }

        // Check for duplicates
        // exclude our own duplicate (if we're a draft)
        $duplicates = $this->layoutFactory->query(null, [
            'userId' => $this->ownerId,
            'layoutExact' => $this->layout,
            'notLayoutId' => ($this->parentId !== null) ? $this->parentId : $this->layoutId,
            'disableUserCheck' => 1,
            'excludeTemplates' => -1
        ]);

        if (count($duplicates) > 0) {
            throw new DuplicateEntityException(sprintf(__("You already own a Layout called '%s'. Please choose another name."), $this->layout));
        }

        // Check zindex is positive
        if ($this->backgroundzIndex < 0) {
            throw new InvalidArgumentException(__('Layer must be 0 or a positive number'), 'backgroundzIndex');
        }

        if ($this->code != null) {

            if (!v::alnum('_')->validate($this->code)) {
                throw new InvalidArgumentException(__('Please use only alphanumeric characters in Layout Code identifier', 'code'));
            }

            $duplicateCode = $this->layoutFactory->query(null, [
                'notLayoutId' => ($this->parentId !== null) ? $this->parentId : $this->layoutId,
                'disableUserCheck' => 1,
                'excludeTemplates' => -1,
                'retired' => -1,
                'code' => $this->code
            ]);

            if (count($duplicateCode) > 0) {
                throw new DuplicateEntityException(__("Layout with provided code already exists"));
            }
        }
    }

    /**
     * Does the layout have the provided tag?
     * @param $searchTag
     * @return bool
     * @throws NotFoundException
     */
    public function hasTag($searchTag)
    {
        $this->load();

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            if ($tag->tag == $searchTag)
                return true;
        }

        return false;
    }

    /**
     * Assign Tag
     * @param Tag $tag
     * @return $this
     * @throws NotFoundException
     */
    public function assignTag($tag)
    {
        $this->load();

        if ($this->tags != [$tag]) {

            if (!in_array($tag, $this->tags)) {
                $this->tags[] = $tag;
            } else {
                foreach ($this->tags as $currentTag) {
                    if ($currentTag === $tag->tagId && $currentTag->value !== $tag->value) {
                        $this->tags[] = $tag;
                    }
                }
            }
        } else {
            $this->getLog()->debug('No Tags to assign');
        }

        return $this;
    }

    /**
     * Add layout history
     *  this is called when a new Layout is added, and when a Draft Layout is published
     *  we can therefore expect to always have a Layout History record for a Layout
     */
    private function addLayoutHistory()
    {
        $this->getLog()->debug('Adding Layout History record for ' . $this->layoutId);

        // Add a record in layout history when a layout is added or published
        $this->getStore()->insert('
          INSERT INTO `layouthistory` (campaignId, layoutId, publishedDate)
            VALUES (:campaignId, :layoutId, :publishedDate)
        ', [
            'campaignId' => $this->campaignId,
            'layoutId' => $this->layoutId,
            'publishedDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /**
     * Add Widget History
     *  this should be called when the contents of a Draft Layout are destroyed during the publish process
     *  it preserves the current state of widgets before they are removed from the database
     *  that can then be used for proof of play stats, to get back to the original widget name/type and mediaId
     * @param \Xibo\Entity\Layout $parent
     * @throws NotFoundException
     */
    private function addWidgetHistory($parent)
    {
        // Get the most recent layout history record
        $layoutHistoryId = $this->getStore()->select('
            SELECT layoutHistoryId FROM `layouthistory` WHERE layoutId = :layoutId
        ', [
            'layoutId' => $parent->layoutId
        ]);

        if (count($layoutHistoryId) <= 0) {
            // We are missing the parent layout history record, which isn't good.
            // I think all we can do at this stage is log it
            $this->getLog()->alert('Missing Layout History for layoutId ' . $parent->layoutId . ' which is on campaignId ' . $parent->campaignId);
            return;
        }

        $layoutHistoryId = intval($layoutHistoryId[0]['layoutHistoryId']);

        // Add records in the widget history table representing all widgets on this Layout
        foreach ($parent->getAllWidgets() as $widget) {

            // Does this widget have a mediaId
            $mediaId = null;
            try {
                $mediaId = $widget->getPrimaryMediaId();
            } catch (NotFoundException $notFoundException) {
                // this is fine
            }

            $this->getStore()->insert('
                INSERT INTO `widgethistory` (layoutHistoryId, widgetId, mediaId, type, name) 
                    VALUES (:layoutHistoryId, :widgetId, :mediaId, :type, :name);
            ', [
                'layoutHistoryId' => $layoutHistoryId,
                'widgetId' => $widget->widgetId,
                'mediaId' => $mediaId,
                'type' => $widget->type,
                'name' => $widget->getOptionValue('name', null),
            ]);
        }
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function unassignTag($tag)
    {
        $this->load();

        foreach ($this->tags as $key => $currentTag) {
            if ($currentTag->tagId === $tag->tagId && $currentTag->value === $tag->value) {
                $this->unassignTags[] = $tag;
                array_splice($this->tags, $key, 1);
            }
        }

        $this->getLog()->debug('Tags after removal %s', json_encode($this->tags));

        return $this;
    }

    /**
     * @param array[Tag] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByLayoutId($this->layoutId);

        if ($this->tags != $tags) {
            $this->unassignTags = array_udiff($this->tags, $tags, function ($a, $b) {
                /* @var Tag $a */
                /* @var Tag $b */
                return $a->tagId - $b->tagId;
            });

            $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

            // Replace the arrays
            $this->tags = $tags;

            $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
        } else {
            $this->getLog()->debug('Tags were not changed');
        }
    }

    /**
     * Export the Layout as its XLF
     * @return string
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function toXlf()
    {
        Profiler::start('Layout::toXlf', $this->getLog());
        $this->getLog()->debug('Layout toXLF for Layout ' . $this->layout . ' - ' . $this->layoutId);

        $this->load(['loadPlaylists' => true]);

        // Keep track of whether this layout has an empty region
        $this->hasEmptyRegion = false;
        $layoutCountRegionsWithDuration = 0;

        $document = new \DOMDocument();
        $layoutNode = $document->createElement('layout');
        $layoutNode->setAttribute('width', $this->width);
        $layoutNode->setAttribute('height', $this->height);
        $layoutNode->setAttribute('bgcolor', $this->backgroundColor);
        $layoutNode->setAttribute('schemaVersion', $this->schemaVersion);

        // add Layout code only if code identifier is set on the Layout.
        if ($this->code != null) {
            $layoutNode->setAttribute('code', $this->code);
        }

        // Layout stat collection flag
        if (is_null($this->enableStat)) {
            $layoutEnableStat =  $this->config->getSetting('LAYOUT_STATS_ENABLED_DEFAULT');
            $this->getLog()->debug('Layout enableStat is empty. Get the default setting.');
        } else {
            $layoutEnableStat = $this->enableStat;
        }
        $layoutNode->setAttribute('enableStat', $layoutEnableStat);

        // Only set the z-index if present
        if ($this->backgroundzIndex != 0)
            $layoutNode->setAttribute('zindex', $this->backgroundzIndex);

        if ($this->backgroundImageId != 0) {
            // Get stored as
            $media = $this->mediaFactory->getById($this->backgroundImageId);

            $layoutNode->setAttribute('background', $media->storedAs);
        }

        $document->appendChild($layoutNode);

        // Track module status within the layout
        $status = 0;
        $this->clearStatusMessage();

        $layoutActionNode = null;
        if (is_array($this->actions) && count($this->actions) > 0) {
            // actions on Layout
            foreach ($this->actions as $action) {
                $layoutActionNode = $document->createElement('action');
                $layoutActionNode->setAttribute('layoutCode', $action->layoutCode);
                $layoutActionNode->setAttribute('widgetId', $action->widgetId);
                $layoutActionNode->setAttribute('triggerCode', $action->triggerCode);
                $layoutActionNode->setAttribute('targetId', $action->targetId);
                $layoutActionNode->setAttribute('target', $action->target);
                $layoutActionNode->setAttribute('sourceId', $action->sourceId);
                $layoutActionNode->setAttribute('source', $action->source);
                $layoutActionNode->setAttribute('actionType', $action->actionType);
                $layoutActionNode->setAttribute('triggerType', $action->triggerType);
                $layoutActionNode->setAttribute('id', $action->actionId);

                $layoutNode->appendChild($layoutActionNode);
            }
        }

        // merge regions and drawers into one array and go through it.
        $allRegions = array_merge($this->regions, $this->drawers);

        foreach ($allRegions as $region) {
            /* @var Region $region */

            // drawer
            if ($region->isDrawer === 1) {
                $regionNode = $document->createElement('drawer');
                // normal region
            } else {
                $regionNode = $document->createElement('region');
            }

            $regionNode->setAttribute('id', $region->regionId);
            $regionNode->setAttribute('width', $region->width);
            $regionNode->setAttribute('height', $region->height);
            $regionNode->setAttribute('top', $region->top);
            $regionNode->setAttribute('left', $region->left);

            // Only set the zIndex if present
            if ($region->zIndex != 0) {
                $regionNode->setAttribute('zindex', $region->zIndex);
            }

            $regionActionNode = null;

            foreach ($region->actions as $action) {
                $regionActionNode = $document->createElement('action');
                $regionActionNode->setAttribute('layoutCode', $action->layoutCode);
                $regionActionNode->setAttribute('widgetId', $action->widgetId);
                $regionActionNode->setAttribute('targetId', $action->targetId);
                $regionActionNode->setAttribute('target', $action->target);
                $regionActionNode->setAttribute('sourceId', $action->sourceId);
                $regionActionNode->setAttribute('source', $action->source);
                $regionActionNode->setAttribute('actionType', $action->actionType);
                $regionActionNode->setAttribute('triggerType', $action->triggerType);
                $regionActionNode->setAttribute('triggerCode', $action->triggerCode);
                $regionActionNode->setAttribute('id', $action->actionId);

                $regionNode->appendChild($regionActionNode);
            }

            $layoutNode->appendChild($regionNode);

            // Region Duration
            $region->duration = 0;

            // Region Options
            $regionOptionsNode = $document->createElement('options');

            foreach ($region->regionOptions as $regionOption) {
                $regionOptionNode = $document->createElement($regionOption->option, $regionOption->value);
                $regionOptionsNode->appendChild($regionOptionNode);
            }

            $regionNode->appendChild($regionOptionsNode);

            // Store region look to work out duration calc
            $regionLoop = $region->getOptionValue('loop', 0);

            // Get a count of widgets in this region
            $widgets = $region->getPlaylist()->setModuleFactory($this->moduleFactory)->expandWidgets();
            $countWidgets = count($widgets);

            // Check for empty Region, exclude Drawers from this check.
            if ($countWidgets <= 0 && $region->isDrawer == 0) {
                $this->getLog()->info('Layout has empty region - ' . $countWidgets . ' widgets. playlistId = ' . $region->getPlaylist()->getId());
                $this->hasEmptyRegion = true;
            }

            // Work out if we have any "lead regions", those are Widgets with a duration
            foreach ($widgets as $widget) {
                if ($widget->useDuration == 1 || $countWidgets > 1 || $regionLoop == 1 || $widget->type == 'video') {
                    $layoutCountRegionsWithDuration++;
                }
            }

            foreach ($widgets as $widget) {
                /* @var Widget $widget */
                $module = $this->moduleFactory->createWithWidget($widget, $region);

                // Set the Layout Status
                try {
                    $moduleStatus = $module->isValid();
                    if ($module->hasStatusMessage()) {
                        $this->pushStatusMessage($module->getStatusMessage());
                    }
                } catch (GeneralException $xiboException) {
                    $moduleStatus = ModuleWidget::$STATUS_INVALID;

                    // Include the exception on
                    $this->pushStatusMessage($xiboException->getMessage());
                }

                $status = ($moduleStatus > $status) ? $moduleStatus : $status;

                // Determine the duration of this widget
                // the calculated duration contains the best guess at this duration from the playlist's perspective
                // the only time we want to override this, is if we want it set to the Minimum Duration for the XLF
                $widgetDuration = $widget->calculatedDuration;

                // Is this Widget one that does not have a duration of its own?
                // Assuming we have at least 1 region with a set duration, then we ought to
                // Reset to the minimum duration
                // do not do that if we are in the drawer Region!
                if ($widget->useDuration == 0
                    && $countWidgets <= 1
                    && $regionLoop == 0
                    && $widget->type != 'video'
                    && $layoutCountRegionsWithDuration >= 1
                    && $region->isDrawer === 0
                ) {
                    // Make sure this Widget expires immediately so that the other Regions can be the leaders when
                    // it comes to expiring the Layout
                    $widgetDuration = Widget::$widgetMinDuration;
                }

                if ($region->isDrawer === 0) {
                    // Region duration
                    $region->duration = $region->duration + $widget->calculatedDuration;


                    // We also want to add any transition OUT duration
                    // only the OUT duration because IN durations do not get added to the widget duration by the player
                    // https://github.com/xibosignage/xibo/issues/705
                    if ($widget->getOptionValue('transOut', '') != '') {
                        // Transition durations are in milliseconds
                        $region->duration = $region->duration + ($widget->getOptionValue('transOutDuration', 0) / 1000);
                    }
                }

                // Create media xml node for XLF.
                $renderAs = $module->getModule()->renderAs;
                $mediaNode = $document->createElement('media');
                $mediaNode->setAttribute('id', $widget->widgetId);
                $mediaNode->setAttribute('type', $widget->type);
                $mediaNode->setAttribute('render', ($renderAs == '') ? 'native' : $renderAs);

                // to make the xml cleaner, add those nodes only on Widgets that were grouped in a subPlaylist Widget.
                if ($widget->tempId != $widget->widgetId) {
                    $mediaNode->setAttribute('playlist', $widget->playlist);
                    $mediaNode->setAttribute('displayOrder', $widget->displayOrder);
                    $mediaNode->setAttribute('parentWidgetId', $widget->tempId);
                }

                // Set the duration according to whether we are using widget duration or not
                $isEndDetectVideoWidget = ($widget->type === 'video' && $widget->useDuration === 0);
                $mediaNode->setAttribute('duration', ($isEndDetectVideoWidget ? 0 : $widgetDuration));
                $mediaNode->setAttribute('useDuration', $widget->useDuration);
                $widgetActionNode = null;

                foreach ($widget->actions as $action) {
                    $widgetActionNode = $document->createElement('action');
                    $widgetActionNode->setAttribute('layoutCode', $action->layoutCode);
                    $widgetActionNode->setAttribute('widgetId', $action->widgetId);
                    $widgetActionNode->setAttribute('targetId', $action->targetId);
                    $widgetActionNode->setAttribute('target', $action->target);
                    $widgetActionNode->setAttribute('sourceId', $action->sourceId);
                    $widgetActionNode->setAttribute('source', $action->source);
                    $widgetActionNode->setAttribute('actionType', $action->actionType);
                    $widgetActionNode->setAttribute('triggerType', $action->triggerType);
                    $widgetActionNode->setAttribute('triggerCode', $action->triggerCode);
                    $widgetActionNode->setAttribute('id', $action->actionId);

                    $mediaNode->appendChild($widgetActionNode);
                }

                // Set a from/to date
                if ($widget->fromDt != null || $widget->fromDt === Widget::$DATE_MIN) {
                    $mediaNode->setAttribute('fromDt', Carbon::createFromTimestamp($widget->fromDt)->format(DateFormatHelper::getSystemFormat()));
                }

                if ($widget->toDt != null || $widget->toDt === Widget::$DATE_MAX) {
                    $mediaNode->setAttribute('toDt', Carbon::createFromTimestamp($widget->toDt)->format(DateFormatHelper::getSystemFormat()));
                }

//                Logic Table
//
//                Widget With Media
//                LAYOUT	MEDIA	WIDGET	Media stats collected?
//                    ON	ON	    ON	    YES     Widget takes precedence     // Match - 1
//                    ON	OFF	    ON	    YES     Widget takes precedence     // Match - 1
//                    ON	INHERIT	ON	    YES     Widget takes precedence     // Match - 1
//
//                    OFF	ON	    ON	    YES     Widget takes precedence     // Match - 1
//                    OFF	OFF	    ON	    YES     Widget takes precedence     // Match - 1
//                    OFF	INHERIT	ON	    YES     Widget takes precedence     // Match - 1
//
//                    ON	ON	    OFF	    NO      Widget takes precedence     // Match - 2
//                    ON	OFF	    OFF	    NO      Widget takes precedence     // Match - 2
//                    ON	INHERIT	OFF	    NO      Widget takes precedence     // Match - 2
//
//                    OFF	ON	    OFF	    NO      Widget takes precedence     // Match - 2
//                    OFF	OFF	    OFF	    NO      Widget takes precedence     // Match - 2
//                    OFF	INHERIT	OFF	    NO      Widget takes precedence     // Match - 2
//
//                    ON	ON	    INHERIT	YES     Media takes precedence      // Match - 3
//                    ON	OFF	    INHERIT	NO      Media takes precedence      // Match - 4
//                    ON	INHERIT	INHERIT	YES     Media takes precedence and Inherited from Layout        // Match - 5
//
//                    OFF	ON	    INHERIT	YES     Media takes precedence      // Match - 3
//                    OFF	OFF	    INHERIT	NO      Media takes precedence      // Match - 4
//                    OFF	INHERIT	INHERIT	NO      Media takes precedence and Inherited from Layout        // Match - 6
//
//                Widget Without Media
//                LAYOUT	WIDGET		Widget stats collected?
//                    ON	ON		    YES	    Widget takes precedence     // Match - 1
//                    ON	OFF		    NO	    Widget takes precedence     // Match - 2
//                    ON	INHERIT		YES	    Inherited from Layout       // Match - 7
//                    OFF	ON		    YES	    Widget takes precedence     // Match - 1
//                    OFF	OFF		    NO	    Widget takes precedence     // Match - 2
//                    OFF	INHERIT		NO	    Inherited from Layout       // Match - 8


                // Widget stat collection flag
                $widgetEnableStat = $widget->getOptionValue('enableStat', $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));

                if(($widgetEnableStat === null) || ($widgetEnableStat === "")) {
                    $widgetEnableStat = $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT');
                }

                $enableStat = 0; // Match - 0

                if ($widgetEnableStat == 'On') {
                    $enableStat = 1; // Match - 1
                    $this->getLog()->debug('For '.$widget->widgetId.': Layout '. (($layoutEnableStat == 1) ? 'On': 'Off') . ' Widget '.$widgetEnableStat . '. Media node output '. $enableStat);
                } else if ($widgetEnableStat == 'Off') {
                    $enableStat = 0; // Match - 2
                    $this->getLog()->debug('For '.$widget->widgetId.': Layout '. (($layoutEnableStat == 1) ? 'On': 'Off') . ' Widget '.$widgetEnableStat . '. Media node output '. $enableStat);
                } else if ($widgetEnableStat == 'Inherit') {

                    try {
                        // Media enable stat flag - WIDGET WITH MEDIA
                        $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());

                        if (($media->enableStat === null) || ($media->enableStat === "")) {
                            $mediaEnableStat = $this->config->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
                            $this->getLog()->debug('Media enableStat is empty. Get the default setting.');
                        } else {
                            $mediaEnableStat = $media->enableStat;
                        }

                        if ($mediaEnableStat == 'On') {
                            $enableStat = 1; // Match - 3
                        } else if ($mediaEnableStat == 'Off') {
                            $enableStat = 0; // Match - 4
                        } else if ($mediaEnableStat == 'Inherit') {
                            $enableStat = $layoutEnableStat;  // Match - 5 and 6
                        }

                        $this->getLog()->debug('For '.$widget->widgetId.': Layout '. (($layoutEnableStat == 1) ? 'On': 'Off') . ((isset($mediaEnableStat)) ? (' Media '.$mediaEnableStat) : '') . ' Widget '.$widgetEnableStat . '. Media node output '. $enableStat);

                    } catch (\Exception $e) { //  - WIDGET WITHOUT MEDIA
                        $this->getLog()->debug($widget->widgetId. ' is not a library media and does not have a media id.');
                        $enableStat = $layoutEnableStat;  // Match - 7 and 8

                        $this->getLog()->debug('For '.$widget->widgetId.': Layout '. (($layoutEnableStat == 1) ? 'On': 'Off') . ' Widget '.$widgetEnableStat . '. Media node output '. $enableStat);
                    }
                }

                // automatically set the transitions on the layout xml, we are not saving widgets here to avoid deadlock issues.
                if ($this->autoApplyTransitions == 1) {
                    $widgetTransIn = $widget->getOptionValue('transIn', $this->config->getSetting('DEFAULT_TRANSITION_IN'));
                    $widgetTransOut = $widget->getOptionValue('transOut', $this->config->getSetting('DEFAULT_TRANSITION_OUT'));
                    $widgetTransInDuration = $widget->getOptionValue('transInDuration', $this->config->getSetting('DEFAULT_TRANSITION_DURATION'));
                    $widgetTransOutDuration = $widget->getOptionValue('transOutDuration', $this->config->getSetting('DEFAULT_TRANSITION_DURATION'));

                    $widget->setOptionValue('transIn', 'attrib', $widgetTransIn);
                    $widget->setOptionValue('transInDuration', 'attrib', $widgetTransInDuration);
                    $widget->setOptionValue('transOut', 'attrib', $widgetTransOut);
                    $widget->setOptionValue('transOutDuration', 'attrib', $widgetTransOutDuration);
                }

                // Set enable stat collection flag
                $mediaNode->setAttribute('enableStat', $enableStat);

                // Create options nodes
                $optionsNode = $document->createElement('options');
                $rawNode = $document->createElement('raw');

                $mediaNode->appendChild($optionsNode);
                $mediaNode->appendChild($rawNode);

                // Inject the URI
                $uriInjected = false;
                if ($module->getModule()->regionSpecific == 0) {
                    $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());
                    $optionNode = $document->createElement('uri', $media->storedAs);
                    $optionsNode->appendChild($optionNode);
                    $uriInjected = true;

                    // Add the fileId attribute to the media element
                    $mediaNode->setAttribute('fileId', $media->mediaId);
                }

                // Tracker whether or not we have an updateInterval configured.
                $hasUpdatedInterval = false;

                foreach ($widget->widgetOptions as $option) {

                    /* @var WidgetOption $option */
                    if (trim($option->value) === '')
                        continue;

                    if ($option->type == 'cdata') {
                        $optionNode = $document->createElement($option->option);
                        $cdata = $document->createCDATASection($option->value);
                        $optionNode->appendChild($cdata);
                        $rawNode->appendChild($optionNode);
                    }
                    else if ($option->type == 'attrib' || $option->type == 'attribute') {

                        if ($uriInjected && $option->option == 'uri')
                            continue;

                        $optionNode = $document->createElement($option->option, $option->value);
                        $optionsNode->appendChild($optionNode);
                    }

                    if ($option->option === 'updateInterval') {
                        $hasUpdatedInterval = true;
                    }
                }

                // If we do not have an update interval, should we set a default one?
                // https://github.com/xibosignage/xibo/issues/2319
                if (!$hasUpdatedInterval && $module->getModule()->regionSpecific == 1) {
                    // For the moment we will assume that all update intervals are the same as the cache duration
                    // remembering that the cache duration is in seconds and the updateInterval in minutes.
                    $optionsNode->appendChild(
                        $document->createElement('updateInterval', $module->getCacheDuration() / 60)
                    );
                }

                // Handle associated audio
                $audioNodes = null;
                foreach ($widget->audio as $audio) {
                    /** @var WidgetAudio $audio */
                    if ($audioNodes == null)
                        $audioNodes = $document->createElement('audio');

                    // Get the full media node for this audio element
                    $audioMedia = $this->mediaFactory->getById($audio->mediaId);

                    $audioNode = $document->createElement('uri', $audioMedia->storedAs);
                    $audioNode->setAttribute('volume', $audio->volume);
                    $audioNode->setAttribute('loop', $audio->loop);
                    $audioNode->setAttribute('mediaId', $audio->mediaId);
                    $audioNodes->appendChild($audioNode);
                }

                if ($audioNodes != null) {
                    $mediaNode->appendChild($audioNodes);
                }

                $regionNode->appendChild($mediaNode);
            }

            $this->getLog()->debug('Region duration on layout ' . $this->layoutId . ' is ' . $region->duration . '. Comparing to ' . $this->duration);

            // Track the max duration within the layout
            // Test this duration against the layout duration
            if ($this->duration < $region->duration) {
                $this->duration = $region->duration;
            }

            $event = new LayoutBuildRegionEvent($region->regionId, $regionNode);
            $this->dispatcher->dispatch($event::NAME, $event);
            // End of region loop.
        }

        $this->getLog()->debug('Setting Layout Duration to ' . $this->duration);

        $tagsNode = $document->createElement('tags');

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tagNode = $document->createElement('tag', $tag->tag . (!empty($tag->value) ? '|' . $tag->value : ''));

            $tagsNode->appendChild($tagNode);
        }

        $layoutNode->appendChild($tagsNode);

        // Update the layout status / duration accordingly
        $this->status = ($status < $this->status) ? $status : $this->status;

        // Fire a layout.build event, passing the layout and the generated document.
        $event = new LayoutBuildEvent($this, $document);
        $this->dispatcher->dispatch($event::NAME, $event);

        Profiler::end('Layout::toXlf', $this->getLog());
        return $document->saveXML();
    }

    /**
     * Export the Layout as a ZipArchive
     * @param DataSetFactory $dataSetFactory
     * @param string $fileName
     * @param array $options
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function toZip($dataSetFactory, $fileName, $options = [])
    {
        $options = array_merge([
            'includeData' => false
        ], $options);

        // Load the complete layout
        $this->load();

        // We export to a ZIP file
        $zip = new \ZipArchive();
        $result = $zip->open($fileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true)
            throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: ' . $result), 'fileName');

        // Add a mapping file for the region names
        $regionMapping = [];
        foreach ($this->regions as $region) {
            /** @var Region $region */
            $regionMapping[$region->regionId] = $region->name;
        }

        // Add a mapping file for the drawer region names
        $drawerMapping = [];
        foreach ($this->drawers as $drawer) {
            /** @var Region $region */
            $drawerMapping[$drawer->regionId] = $drawer->name;
        }

        // Add layout information to the ZIP
        $zip->addFromString('layout.json', json_encode([
            'layout' => $this->layout,
            'description' => $this->description,
            'regions' => $regionMapping,
            'drawers' => $drawerMapping,
            'layoutDefinitions' => $this
        ]));

        // Add the layout XLF
        $zip->addFile($this->xlfToDisk(), 'layout.xml');

        // Add all media
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        $mappings = [];

        foreach ($this->mediaFactory->getByLayoutId($this->layoutId, 1, 1) as $media) {
            /* @var Media $media */
            $zip->addFile($libraryLocation . $media->storedAs, 'library/' . $media->fileName);
            $media->load();

            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 0,
                'font' => 0,
                'tags' => $media->tags
            ];
        }

        // Add the background image
        if ($this->backgroundImageId != 0) {
            $media = $this->mediaFactory->getById($this->backgroundImageId);
            $zip->addFile($libraryLocation . $media->storedAs, 'library/' . $media->fileName);
            $media->load();
            
            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 1,
                'font' => 0,
                'tags' => $media->tags
            ];
        }

        // Add any fonts
        //  parse the XLF file for any font declarations contains therein
        //  get those font media files by name and add them to the zip
        $fonts = null;
        preg_match_all('/font-family:(.*?);/', $this->toXlf(), $fonts);

        if ($fonts != null) {

            $this->getLog()->debug('Matched fonts: %s', json_encode($fonts));

            foreach ($fonts[1] as $font) {
                $matches = $this->mediaFactory->query(null, array('disableUserCheck' => 1, 'nameExact' => $font, 'allModules' => 1, 'type' => 'font'));

                if (count($matches) <= 0) {
                    $this->getLog()->info('Unmatched font during export: %s', $font);
                    continue;
                }

                $media = $matches[0];

                $zip->addFile($libraryLocation . $media->storedAs, 'library/' . $media->fileName);

                $mappings[] = [
                    'file' => $media->fileName,
                    'mediaid' => $media->mediaId,
                    'name' => $media->name,
                    'type' => $media->mediaType,
                    'duration' => $media->duration,
                    'background' => 0,
                    'font' => 1
                ];
            }
        }

        // Add the mappings file to the ZIP
        $zip->addFromString('mapping.json', json_encode($mappings));

        // Handle any DataSet structures
        $dataSetIds = [];
        $dataSets = [];

        // Playlists
        $playlistMappings = [];
        $playlistDefinitions = [];
        $nestedPlaylistDefinitions = [];

        foreach ($this->getAllWidgets() as $widget) {
            /** @var Widget $widget */
            if ($widget->type == 'datasetview' || $widget->type == 'datasetticker' || $widget->type == 'chart') {
                $dataSetId = $widget->getOptionValue('dataSetId', 0);

                if ($dataSetId != 0) {

                    if (in_array($dataSetId, $dataSetIds)) {
                        continue;
                    }

                    // Export the structure for this dataSet
                    $dataSet = $dataSetFactory->getById($dataSetId);
                    $dataSet->load();

                    // Are we also looking to export the data?
                    if ($options['includeData']) {
                        $dataSet->data = $dataSet->getData([], ['includeFormulaColumns' => false]);
                    }

                    $dataSetIds[] = $dataSet->dataSetId;
                    $dataSets[] = $dataSet;
                }
            }   elseif ($widget->type == 'subplaylist') {
                $playlistIds = json_decode($widget->getOptionValue('subPlaylistIds', []), true);

                foreach ($playlistIds as $playlistId) {
                    $count = 1;
                    $playlist = $this->playlistFactory->getById($playlistId);

                    // include Widgets only for non dynamic Playlists #2392
                    $playlist->load(['loadWidgets' => ($playlist->isDynamic) ? false : true]);
                    if ($playlist->isDynamic === 0) {
                        $playlist->expandWidgets(0, false);
                    }

                    $playlistDefinitions[$playlist->playlistId] = $playlist;

                    // this is a recursive function, we are adding Playlist definitions, Playlist mappings and DataSets existing on the nested Playlist.
                    $playlist->generatePlaylistMapping($playlist->widgets, $playlist->playlistId,$playlistMappings, $count, $nestedPlaylistDefinitions, $dataSetIds, $dataSets, $dataSetFactory, $options['includeData']);
                }
            }
        }

        // Add the mappings file to the ZIP
        if ($dataSets != []) {
            $zip->addFromString('dataSet.json', json_encode($dataSets, JSON_PRETTY_PRINT));
        }

        // Add the Playlist definitions to the ZIP
        if ($playlistDefinitions != []) {
            $zip->addFromString('playlist.json', json_encode($playlistDefinitions, JSON_PRETTY_PRINT));
        }

        // Add the nested Playlist definitions to the ZIP
        if ($nestedPlaylistDefinitions != []) {
            $zip->addFromString('nestedPlaylist.json', json_encode($nestedPlaylistDefinitions, JSON_PRETTY_PRINT));
        }

        // Add Playlist mappings file to the ZIP
        if ($playlistMappings != []) {
            $zip->addFromString('playlistMappings.json', json_encode($playlistMappings, JSON_PRETTY_PRINT));
        }

        $zip->close();
    }

    /**
     * Save the XLF to disk if necessary
     * @param array $options
     * @return string the path
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function xlfToDisk($options = [])
    {
        $options = array_merge([
            'notify' => true,
            'collectNow' => true,
            'exceptionOnError' => false,
            'exceptionOnEmptyRegion' => true,
            'publishing' => false
        ], $options);

        Profiler::start('Layout::xlfToDisk', $this->getLog());

        $path = $this->getCachePath();

        if ($this->status == 3 || !file_exists($path)) {

            $this->getLog()->debug('XLF needs building for Layout ' . $this->layoutId);

            $this->load(['loadPlaylists' => true]);

            // Layout auto Publish
            if ($this->config->getSetting('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB') == 1 && $this->isChild()) {

                // we are editing a draft layout, the published date is set on the original layout, therefore we need our parent.
                $parent = $this->layoutFactory->loadById($this->parentId);

                $layoutCurrentPublishedDate = Carbon::createFromTimestamp($parent->publishedDate);
                $newPublishDateString =  Carbon::now()->addMinutes(30)->format(DateFormatHelper::getSystemFormat());
                $newPublishDate = Carbon::createFromTimeString($newPublishDateString);

                if ($layoutCurrentPublishedDate->format('U') > $newPublishDate->format('U')) {
                    // Layout is set to Publish manually on a date further than 30 min from now, we don't touch it in this case.
                    $this->getLog()->debug('Layout is set to Publish manually on a date further than 30 min from now, do not update');

                }  elseif ($parent->publishedDate != null &&  $layoutCurrentPublishedDate->format('U') < Carbon::now()->subMinutes(5)->format('U')) {
                    // Layout is set to Publish manually at least 5 min in the past at the moment, we expect the Regular Maintenance to build it before that happens
                    $this->getLog()->debug('Layout should be built by Regular Maintenance');

                } else {
                    $parent->setPublishedDate($newPublishDateString);
                    $this->getLog()->debug('Layout set to automatically Publish on ' . $newPublishDateString);
                }
            }

            // Assume error
            $this->status = ModuleWidget::$STATUS_INVALID;

            // Reset duration
            $this->duration = 0;

            // Save the resulting XLF
            try {
                file_put_contents($path, $this->toXlf());
            } catch (\Exception $e) {
                $this->getLog()->error('Cannot build Layout ' . $this->layoutId . '. error: ' . $e->getMessage());

                // Will continue and save the status as 4
                $this->status = ModuleWidget::$STATUS_INVALID;

                if ($e->getMessage() != '') {
                    $this->pushStatusMessage($e->getMessage());
                } else {
                    $this->pushStatusMessage('Unexpected Error');
                }
                // No need to notify on an errored build
                $options['notify'] = false;
            }

            if ($options['exceptionOnError']) {
                // Handle exception cases
                if ($this->status === ModuleWidget::$STATUS_INVALID
                    || ($options['exceptionOnEmptyRegion'] && $this->hasEmptyRegion())
                ) {
                    $this->audit($this->layoutId, 'Publish layout failed, rollback', ['layoutId' => $this->layoutId]);
                    throw new InvalidArgumentException(__('There is an error with this Layout: %s',
                        implode(',', $this->getStatusMessage())), 'status');
                }
            }

            // If we have an empty region and we've not exceptioned, then we need to record that in our status
            if ($this->hasEmptyRegion()) {
                $this->status = ModuleWidget::$STATUS_INVALID;
                $this->pushStatusMessage(__('Empty Region'));
            }

            $this->save([
                'saveRegions' => true,
                'saveRegionOptions' => false,
                'manageRegionAssignments' => false,
                'saveTags' => false,
                'setBuildRequired' => false,
                'audit' => false,
                'validate' => false,
                'notify' => $options['notify'],
                'collectNow' => $options['collectNow']
            ]);
        }

        Profiler::end('Layout::xlfToDisk', $this->getLog());
        return $path;
    }

    /**
     * @return string
     */
    private function getCachePath()
    {
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        return $libraryLocation . $this->layoutId . '.xlf';
    }

    /**
     * Publish the Draft
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function publishDraft()
    {
        // We are the draft - make sure we have a parent
        if (!$this->isChild())
            throw new InvalidArgumentException(__('Not a Draft'), 'statusId');

        // Get my parent for later
        $parent = $this->layoutFactory->loadById($this->parentId);

        // I am the draft, so I clear my parentId, and set the parentId of my parent, to myself (swapping us)
        // Make me the parent.
        $this->getStore()->update('UPDATE `layout` SET parentId = NULL WHERE layoutId = :layoutId', [
            'layoutId' => $this->layoutId
        ]);

        // Set my parent, to be my child.
        $this->getStore()->update('UPDATE `layout` SET parentId = :parentId WHERE layoutId = :layoutId', [
            'parentId' => $this->layoutId,
            'layoutId' => $this->parentId
        ]);

        // clear publishedDate
        $this->getStore()->update('UPDATE `layout` SET publishedDate = null WHERE layoutId = :layoutId', [
            'layoutId' => $this->layoutId
        ]);

        // Update any campaign links
        $this->getStore()->update('
          UPDATE `lkcampaignlayout` 
            SET layoutId = :layoutId 
           WHERE layoutId = :parentId 
            AND campaignId IN (SELECT campaignId FROM campaign WHERE isLayoutSpecific = 0)
        ', [
            'parentId' => $this->parentId,
            'layoutId' => $this->layoutId
        ]);

        // Persist things that might have changed
        // NOTE: permissions are managed on the campaign, so we do not need to worry.
        $this->layout = $parent->layout;
        $this->description = $parent->description;
        $this->retired = $parent->retired;
        $this->enableStat = $parent->enableStat;
        $this->code = $parent->code;
        $this->folderId = $parent->folderId;

        // Swap all tags over, any changes we've made to the parents tags should be moved to the child.
        $this->getStore()->update('UPDATE `lktaglayout` SET layoutId = :layoutId WHERE layoutId = :parentId', [
            'parentId' => $parent->layoutId,
            'layoutId' => $this->layoutId
        ]);

        // Update any Displays which use this as their default Layout
        $this->getStore()->update('UPDATE `display` SET defaultLayoutId = :layoutId WHERE defaultLayoutId = :parentId', [
            'parentId' => $parent->layoutId,
            'layoutId' => $this->layoutId
        ]);

        // Swap any display group links
        $this->getStore()->update('UPDATE `lklayoutdisplaygroup` SET layoutId = :layoutId WHERE layoutId = :parentId', [
            'layoutId' => $this->layoutId,
            'parentId' => $parent->layoutId
        ]);

        // If this is the global default layout, then add some special handling to make sure we swap the default over
        // to the incoming draft
        if ($this->parentId == $this->config->getSetting('DEFAULT_LAYOUT')) {
            // Change it over to me.
            $this->config->changeSetting('DEFAULT_LAYOUT', $this->layoutId);
        }

        // Preserve the widget information
        $this->addWidgetHistory($parent);

        // Delete the parent (make sure we set the parent to be a child of us, otherwise we will delete the linked
        // campaign
        $parent->parentId = $this->layoutId;
        $parent->tags = []; // Clear the tags so we don't attempt a delete.
        $parent->permissions = []; // Clear the permissions so we don't attempt a delete
        $parent->delete();

        // Set my statusId to published
        // we do not want to notify here as we should wait for the build to happen
        $this->publishedStatusId = 1;
        $this->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false,
            'validate' => false,
            'audit' => true,
            'notify' => false
        ]);

        // Nullify my parentId (I no longer have a parent)
        $this->parentId = null;

        // Add a layout history
        $this->addLayoutHistory();

    }

    public function setPublishedDate($publishedDate)
    {
        $this->publishedDate = $publishedDate;

        $this->getStore()->update('UPDATE `layout` SET publishedDate = :publishedDate WHERE layoutId = :layoutId', [
            'layoutId' => $this->layoutId,
            'publishedDate' => $this->publishedDate
        ]);
    }

    /**
     * Discard the Draft
     * @throws GeneralException
     */
    public function discardDraft()
    {
        // We are the draft - make sure we have a parent
        if (!$this->isChild()) {
            $this->getLog()->debug('Cant discard draft ' . $this->layoutId . '. publishedStatusId = ' . $this->publishedStatusId . ', parentId = ' . $this->parentId);
            throw new InvalidArgumentException(__('Not a Draft'), 'statusId');
        }

        // We just need to delete ourselves really
        $this->delete();

        // We also need to update the parent so that it is no longer draft
        $parent = $this->layoutFactory->getById($this->parentId);
        $parent->publishedStatusId = 1;
        $parent->save([
            self::$saveOptionsMinimum
        ]);
    }

    //
    // Add / Update
    //

    /**
     * Add
     * @throws GeneralException
     */
    private function add()
    {
        $this->getLog()->debug('Adding Layout' . $this->layout);

        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, publishedStatusId, status, width, height, schemaVersion, backgroundImageId, backgroundColor, backgroundzIndex, parentId, enableStat, retired, duration, autoApplyTransitions, code)
                  VALUES (:layout, :description, :userid, :createddt, :modifieddt, :publishedStatusId, :status, :width, :height, :schemaVersion, :backgroundImageId, :backgroundColor, :backgroundzIndex, :parentId, :enableStat, 0, 0, :autoApplyTransitions, :code)';

        $time = Carbon::now()->format(DateFormatHelper::getSystemFormat());

        $this->layoutId = $this->getStore()->insert($sql, array(
            'layout' => $this->layout,
            'description' => $this->description,
            'userid' => $this->ownerId,
            'createddt' => $time,
            'modifieddt' => $time,
            'publishedStatusId' => $this->publishedStatusId, // Default to 1 (published)
            'status' => 3,
            'width' => $this->width,
            'height' => $this->height,
            'schemaVersion' => Environment::$XLF_VERSION,
            'backgroundImageId' => $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
            'parentId' => ($this->parentId == null) ? null : $this->parentId,
            'enableStat' => $this->enableStat,
            'autoApplyTransitions' => ($this->autoApplyTransitions == null) ? 0 : $this->autoApplyTransitions,
            'code' => $this->code
        ));

        // Add a Campaign
        // we do not add a campaign record for draft layouts.
        if ($this->parentId === null) {

            $campaign = $this->campaignFactory->createEmpty();
            $campaign->campaign = $this->layout;
            $campaign->isLayoutSpecific = 1;
            $campaign->ownerId = $this->getOwnerId();
            $campaign->folderId = ($this->folderId == null) ? 1 : $this->folderId;

            // if user has disabled folder feature, presumably said user also has no permissions to folder
            // getById would fail here and prevent adding new Layout in web ui
            try {
                $folder = $this->folderFactory->getById($campaign->folderId);
                $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
            } catch (NotFoundException $exception) {
                $campaign->permissionsFolderId = 1;
            }
            $campaign->assignLayout($this);

            // Ready to save the Campaign
            // adding a Layout Specific Campaign shouldn't ever notify (it can't hit anything because we've only
            // just added it)
            $campaign->save([
                'notify' => false
            ]);

            // Assign the new campaignId to this layout
            $this->campaignId = $campaign->campaignId;

            // Add a layout history
            $this->addLayoutHistory();

        } else if ($this->campaignId == null) {
            throw new InvalidArgumentException(__('Draft Layouts must have a parent'), 'campaignId');
        } else {

            // Add this draft layout as a link to the campaign
            $campaign = $this->campaignFactory->getById($this->campaignId);
            $campaign->setChildObjectDependencies($this->layoutFactory);
            $campaign->assignLayout($this);
            $campaign->save([
                'notify' => false
            ]);
        }
    }

    /**
     * Update
     * @param array $options
     * @throws GeneralException
     */
    private function update($options = [])
    {
        $options = array_merge([
            'notify' => true,
            'collectNow' => true
        ], $options);

        $this->getLog()->debug('Editing Layout ' . $this->layout . '. Id = ' . $this->layoutId);

        $sql = '
        UPDATE layout
          SET layout = :layout,
              description = :description,
              duration = :duration,
              modifiedDT = :modifieddt,
              retired = :retired,
              width = :width,
              height = :height,
              backgroundImageId = :backgroundImageId,
              backgroundColor = :backgroundColor,
              backgroundzIndex = :backgroundzIndex,
              `status` = :status,
              publishedStatusId = :publishedStatusId,
              `userId` = :userId,
              `schemaVersion` = :schemaVersion,
              `statusMessage` = :statusMessage,
              enableStat = :enableStat,
              autoApplyTransitions = :autoApplyTransitions,
              code = :code
         WHERE layoutID = :layoutid
        ';

        $time = Carbon::now()->format(DateFormatHelper::getSystemFormat());

        $this->getStore()->update($sql, array(
            'layoutid' => $this->layoutId,
            'layout' => $this->layout,
            'description' => $this->description,
            'duration' => ($this->duration == null) ? 0 : $this->duration,
            'modifieddt' => $time,
            'retired' => ($this->retired == null) ? 0 : $this->retired,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundImageId' => ($this->backgroundImageId == null) ? null : $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
            'status' => $this->status,
            'publishedStatusId' => $this->publishedStatusId,
            'userId' => $this->ownerId,
            'schemaVersion' => ($this->schemaVersion == null) ? Environment::$XLF_VERSION : $this->schemaVersion,
            'statusMessage' => (empty($this->statusMessage)) ? null : json_encode($this->statusMessage),
            'enableStat' => $this->enableStat,
            'autoApplyTransitions' => $this->autoApplyTransitions,
            'code' => $this->code
        ));

        // Update the Campaign
        if ($this->parentId === null) {
            $campaign = $this->campaignFactory->getById($this->campaignId);
            $campaign->campaign = $this->layout;
            $campaign->ownerId = $this->ownerId;
            $campaign->folderId = $this->folderId;

            // if user has disabled folder feature, presumably said user also has no permissions to folder
            // getById would fail here and prevent adding new Layout in web ui
            try {
                $folder = $this->folderFactory->getById($campaign->folderId);
                $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
            } catch (NotFoundException $exception) {
                $campaign->permissionsFolderId = 1;
            }
            $campaign->save(['validate' => false, 'notify' => $options['notify'], 'collectNow' => $options['collectNow'], 'layoutCode' => $this->code]);
        }
    }

    /**
     * Handle the Playlist closure table for specified Layout object
     *
     * @param Layout $layout
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function managePlaylistClosureTable($layout)
    {

        // we only need to set the closure table records for the playlists assigned directly to the regionPlaylist here
        // all other relations between Playlists themselves are handled on import before layout is created
        // as the SQL we run here is recursive everything will end up with correct parent/child relation and depth level.
        foreach ($layout->getAllWidgets() as $widget) {
            if ($widget->type == 'subplaylist') {
                $assignedPlaylists = json_decode($widget->getOptionValue('subPlaylistIds', '[]'));
                $assignedPlaylists = implode(',', $assignedPlaylists);

                foreach ($layout->regions as $region) {
                    $regionPlaylist = $region->regionPlaylist;

                    if ($widget->playlistId == $regionPlaylist->playlistId) {
                        $parentId = $regionPlaylist->playlistId;
                        $child[] = $assignedPlaylists;
                    }
                }
            }
        }

        if (isset($parentId) && isset($child)) {
            foreach ($child as $childId) {

                $this->getLog()->debug('Manage closure table for parent ' . $parentId . ' and child ' . $childId);

                if ($this->getStore()->exists('SELECT parentId, childId, depth FROM lkplaylistplaylist WHERE childId = :childId AND parentId = :parentId ', [
                    'parentId' => $parentId,
                    'childId' => $childId
                ])) {
                    throw new InvalidArgumentException(__('Cannot add the same SubPlaylist twice.'), 'playlistId');
                }

                $this->getStore()->insert('
                        INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                        SELECT p.parentId, c.childId, p.depth + c.depth + 1
                          FROM lkplaylistplaylist p, lkplaylistplaylist c
                         WHERE p.childId = :parentId AND c.parentId = :childId
                    ', [
                    'parentId' => $parentId,
                    'childId' => $childId
                ]);
            }
        }
    }

    /**
     * This function will adjust the Action sourceId and targetId in all relevant objects in our imported Layout
     *
     * @param Layout $layout
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function manageActions(Layout $layout)
    {
        $oldRegionIds = [];
        $newRegionIds = [];
        $newWidgetIds = [];
        $oldWidgetIds = [];

        // get all regionIds including drawers
        $allNewRegions = array_merge($layout->regions, $layout->drawers);

        // create an array of new and old (from import) Region and Widget ids
        /** @var Region $region */
        foreach ($allNewRegions as $region) {
            $newRegionIds[] = $region->regionId;
            $oldRegionIds[] = $region->tempId;

            /** @var Widget $widget */
            foreach ($region->getPlaylist()->widgets as $widget) {
                $newWidgetIds[] = $widget->widgetId;
                $oldWidgetIds[] = $widget->tempWidgetId;
            }
        }

        // combine the arrays into $old=>$new key value arrays
        $combined = array_combine($oldRegionIds, $newRegionIds);
        $combinedWidgets = array_combine($oldWidgetIds, $newWidgetIds);

        // get Actions with Layout
        $layoutActions = $this->actionFactory->query(null, ['source' => 'importLayout']);

        // go through all imported actions on a Layout and replace the source/target Ids with the new ones
        foreach ($layoutActions as $action) {
            $action->source = 'layout';
            $action->sourceId = $layout->layoutId;

            if ($action->targetId != null) {
                foreach ($combined as $old => $new) {
                    if ($old == $action->targetId) {
                        $this->getLog()->debug('Layout Import, switching Layout Action target ID from ' . $old . ' to ' . $new);
                        $action->targetId = $new;
                    }
                }
            }

            // switch widgetId
            if ($action->widgetId != null) {

                foreach ($combinedWidgets as $old => $new) {
                    if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                        $this->getLog()->debug('Layout Import, switching Widget Action widget ID from ' . $old . ' to ' . $new);
                        $action->widgetId = $new;
                    }
                }
            }

            $action->save();
        }

        // Actions with Region
        $regionActions = $this->actionFactory->query(null, ['source' => 'importRegion']);

        // go through all imported actions on a Region and replace the source/target Ids with the new ones
        foreach ($regionActions as $action) {
            $action->source = 'region';

            foreach ($combined as $old => $new) {
                if ($old == $action->targetId) {
                    $this->getLog()->debug('Layout Import, switching Region Action target ID from ' . $old . ' to ' . $new);
                    $action->targetId = $new;
                }

                if ($action->sourceId === $old) {
                    $this->getLog()->debug('Layout Import, switching Region Action source ID from ' . $old . ' to ' . $new);
                    $action->sourceId = $new;
                }
            }

            // switch widgetId
            if ($action->widgetId != null) {

                foreach ($combinedWidgets as $old => $new) {
                    if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                        $this->getLog()->debug('Layout Import, switching Widget Action widget ID from ' . $old . ' to ' . $new);
                        $action->widgetId = $new;
                    }
                }
            }

            $action->save();
        }

        // Actions with Widget
        $widgetActions = $this->actionFactory->query(null, ['source' => 'importWidget']);

        // go through all imported actions on a Widget and replace the source/target Ids with the new ones
        foreach ($widgetActions as $action) {
            $action->source = 'widget';

            // switch Action source Id and Action widget Id
            foreach ($combinedWidgets as $old => $new) {
                if ($action->sourceId == $old) {
                    $this->getLog()->debug('Layout Import, switching Widget Action source ID from ' . $old . ' to ' . $new);
                    $action->sourceId = $new;
                }

                if ($action->widgetId != null) {
                    if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                        $this->getLog()->debug('Layout Import, switching Widget Action widget ID from ' . $old . ' to ' . $new);
                        $action->widgetId = $new;
                    }
                }
            }

            // if we had targetId (regionId) then switch it
            if ($action->targetId != null) {
                foreach ($combined as $old => $new) {
                    if ($old == $action->targetId) {
                        $this->getLog()->debug('Layout Import, switching Widget Action target ID from ' . $old . ' to ' . $new);
                        $action->targetId = $new;
                    }
                }
            }

            $action->save();
        }

        // Make sure we update targetRegionId in Drawer Widgets.
        foreach ($allNewRegions as $region) {
            foreach ($region->getPlaylist()->widgets as $widget) {
                if ($region->isDrawer === 1) {
                    foreach ($combined as $old => $new) {
                        if ($widget->getOptionValue('targetRegionId', null) == $old) {
                            $this->getLog()->debug('Layout Import, switching Widget targetRegionId from ' . $old . ' to ' . $new);
                            $widget->setOptionValue('targetRegionId', 'attrib', $new);
                            $widget->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Adjust source and target id in copied Layout (checkout / copy )
     *
     * @param Layout $newLayout
     * @param Layout $originalLayout
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function copyActions(Layout $newLayout, Layout $originalLayout)
    {
        $oldRegionIds = [];
        $newRegionIds = [];
        $oldWidgetIds = [];
        $newWidgetIds = [];

        $this->getLog()->debug('Copy Actions from ' . $originalLayout->layoutId . ' To ' . $newLayout->layoutId);

        /** @var Region[] $allRegions */
        $allRegions = array_merge($newLayout->regions, $newLayout->drawers);

        // go through all layouts, regions, playlists and their widgets
        /** @var Region $region */
        foreach ($allRegions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $originalLayout->getRegionOrDrawer($region->getOriginalValue('regionId'));

            $oldRegionIds[] = (int)$original->regionId;
            $newRegionIds[] = $region->regionId;

            foreach ($region->getPlaylist()->widgets as $widget) {
                $originalWidget = $original->getPlaylist()->getWidget($widget->getOriginalValue('widgetId'));

                $oldWidgetIds[] = (int)$originalWidget->widgetId;
                $newWidgetIds[] = $widget->widgetId;
            }
        }

        // create $old=>$new arrays of all of them to later update the Actions
        $combinedRegionIds = array_combine($oldRegionIds, $newRegionIds);
        $combinedWidgetIds = array_combine($oldWidgetIds, $newWidgetIds);

        $this->getLog()->debug('Region Ids array ' . json_encode($combinedRegionIds));
        $this->getLog()->debug('Widget Ids array ' . json_encode($combinedWidgetIds));

        // Interactive Actions on Layout
        foreach ($newLayout->actions as $action) {

            // switch source Id
            if ($action->sourceId === $originalLayout->layoutId) {
                $action->sourceId = $newLayout->layoutId;
            }

            // if we had targetId (regionId) then switch it
            if ($action->targetId != null) {
                foreach ($combinedRegionIds as $old => $new) {
                    if ($old == $action->targetId) {
                        $action->targetId = $new;
                    }
                }
            }

            // switch Action widgetId
            if ($action->widgetId != null) {#

                foreach ($combinedWidgetIds as $old => $new) {
                    if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                        $action->widgetId = $new;
                    }
                }
            }
            $action->save();
        }

        // Region Actions
        foreach ($allRegions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $originalLayout->getRegionOrDrawer($region->getOriginalValue('regionId'));

            // Interactive Actions on Region
            foreach ($region->actions as $action) {

                // switch source Id
                if ($action->sourceId === $original->regionId) {
                    $action->sourceId = $region->regionId;
                }

                // if we had targetId (regionId) then switch it
                if ($action->targetId != null) {
                    foreach ($combinedRegionIds as $old => $new) {
                        if ($old == $action->targetId) {
                            $action->targetId = $new;
                        }
                    }
                }

                // switch Action widgetId
                if ($action->widgetId != null) {

                    foreach ($combinedWidgetIds as $old => $new) {
                        if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                            $action->widgetId = $new;
                        }
                    }
                }

                $action->save();
            }

            // Widget Actions
            foreach ($region->getPlaylist()->widgets as $widget) {
                $originalWidget = $original->getPlaylist()->getWidget($widget->getOriginalValue('widgetId'));

                // Make sure we update targetRegionId in Drawer Widgets on checkout.
                if ($region->isDrawer === 1) {
                    foreach ($combinedRegionIds as $old => $new) {
                        if ($widget->getOptionValue('targetRegionId', null) == $old) {
                            $widget->setOptionValue('targetRegionId', 'attrib', $new);
                            $widget->save();
                        }
                    }
                }
                // Interactive Actions on Widget
                foreach ($widget->actions as $action) {
                    // switch source Id
                    if ($action->sourceId === $originalWidget->widgetId) {
                        $action->sourceId = $widget->widgetId;
                    }

                    // if we had targetId (regionId) then switch it
                    if ($action->targetId != null) {
                        foreach ($combinedRegionIds as $old => $new) {
                            if ($old == $action->targetId) {
                                $action->targetId = $new;
                            }
                        }
                    }

                    // switch Action widgetId
                    if ($action->widgetId != null) {

                        foreach ($combinedWidgetIds as $old => $new) {
                            if ($old == $action->widgetId && $action->actionType == 'navWidget') {
                                $action->widgetId = $new;
                            }
                        }
                    }

                    $action->save();
                }
            }
        }
    }
}
