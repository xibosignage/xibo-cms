<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\LayoutBuildEvent;
use Xibo\Event\LayoutBuildRegionEvent;
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Layout
 * @package Xibo\Entity
 *
 * @SWG\Definition()
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

    // Child items
    /** @var Region[]  */
    public $regions = [];

    public $tags = [];
    public $permissions = [];
    public $campaigns = [];

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    // Private
    private $unassignTags = [];

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

    /**
     * @var DateServiceInterface
     */
    private $date;

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

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param EventDispatcherInterface $eventDispatcher
     * @param PermissionFactory $permissionFactory
     * @param RegionFactory $regionFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ModuleFactory $moduleFactory
     */
    public function __construct($store, $log, $config, $date, $eventDispatcher, $permissionFactory, $regionFactory, $tagFactory, $campaignFactory, $layoutFactory, $mediaFactory, $moduleFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->setPermissionsClass('Xibo\Entity\Campaign');
        $this->config = $config;
        $this->date = $date;
        $this->dispatcher = $eventDispatcher;
        $this->permissionFactory = $permissionFactory;
        $this->regionFactory = $regionFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->moduleFactory = $moduleFactory;
    }

    public function __clone()
    {
        // Clear the layout id
        $this->layoutId = null;
        $this->campaignId = null;
        $this->hash = null;
        $this->permissions = [];

        // Clone the regions
        $this->regions = array_map(function ($object) { return clone $object; }, $this->regions);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('Layout %s - %d x %d. Regions = %d, Tags = %d. layoutId = %d. Status = %d, messages %d', $this->layout, $this->width, $this->height, count($this->regions), count($this->tags), $this->layoutId, $this->status, count($this->getStatusMessage()));
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5($this->layoutId . $this->ownerId . $this->campaignId . $this->backgroundImageId . $this->backgroundColor . $this->width . $this->height . $this->status . $this->description . json_encode($this->statusMessage));
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->campaignId;
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
     */
    public function setOwner($ownerId, $cascade = false)
    {
        $this->ownerId = $ownerId;

        $this->load();

        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->setOwner($ownerId, $cascade);
        }
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
            if ($region->regionId == $regionId)
                return $region;
        }

        throw new NotFoundException(__('Cannot find region'));
    }

    /**
     * Get Widgets assigned to this Layout
     * @return Widget[]
     */
    public function getWidgets()
    {
        $widgets = [];

        foreach ($this->regions as $region) {
            /* @var Region $region */
            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                $widgets = array_merge($playlist->widgets, $widgets);
            }
        }

        return $widgets;
    }

    /**
     * @return array
     */
    public function getStatusMessage()
    {
        if (empty($this->statusMessage))
            return [];

        if (is_array($this->statusMessage))
            return $this->statusMessage;

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
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadPlaylists' => true,
            'loadTags' => true,
            'loadPermissions' => true,
            'loadCampaigns' => true
        ], $options);

        if ($this->loaded || $this->layoutId == 0)
            return;

        $this->getLog()->debug('Loading Layout %d with options %s', $this->layoutId, json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId('Xibo\\Entity\\Campaign', $this->campaignId);

        // Load all regions
        $this->regions = $this->regionFactory->getByLayoutId($this->layoutId);

        if ($options['loadPlaylists'])
            $this->loadPlaylists($options);

        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByLayoutId($this->layoutId);

        // Load Campaigns
        if ($options['loadCampaigns'])
            $this->campaigns = $this->campaignFactory->getByLayoutId($this->layoutId);

        // Set the hash
        $this->hash = $this->hash();
        $this->loaded = true;

        $this->getLog()->debug('Loaded %s', $this->layoutId);
    }

    /**
     * Load Playlists
     * @param array $options
     */
    public function loadPlaylists($options = [])
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->load($options);
        }
    }

    /**
     * Save this Layout
     * @param array $options
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
            'audit' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($options['setBuildRequired'])
            $this->setBuildRequired();

        $this->getLog()->debug('Saving ' . $this . ' with options ' . json_encode($options, JSON_PRETTY_PRINT));

        // New or existing layout
        if ($this->layoutId == null || $this->layoutId == 0) {
            $this->add();

            if ($options['audit'])
                $this->audit($this->layoutId, 'Added', ['layoutId' => $this->layoutId, 'layout' => $this->layout]);

        } else if (($this->hash() != $this->hash && $options['saveLayout']) || $options['setBuildRequired']) {
            $this->update($options);

            if ($options['audit'])
                $this->audit($this->layoutId, 'Updated');

        } else {
            $this->getLog()->info('Save layout properties unchanged for layoutId ' . $this->layoutId);
        }

        if ($options['saveRegions']) {
            $this->getLog()->debug('Saving Regions on ' . $this);

            // Update the regions
            foreach ($this->regions as $region) {
                /* @var Region $region */

                // Assert the Layout Id
                $region->layoutId = $this->layoutId;
                $region->save($options);
            }
        }

        if ($options['saveTags']) {
            $this->getLog()->debug('Saving tags on ' . $this);

            // Save the tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag ' . $tag->tag);

                    $tag->assignLayout($this->layoutId);
                    $tag->save();
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
        }

        $this->getLog()->debug('Save finished for ' . $this);
    }

    /**
     * Delete Layout
     * @param array $options
     * @throws XiboException
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'deleteOrphanedPlaylists' => true
        ], $options);

        // We must ensure everything is loaded before we delete
        if (!$this->loaded)
            $this->load();

        $this->getLog()->debug('Deleting %s', $this);

        // We cannot delete the default default
        if ($this->layoutId == $this->config->GetSetting('DEFAULT_LAYOUT'))
            throw new InvalidArgumentException(__('This layout is used as the global default and cannot be deleted'), 'layoutId');

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Unassign all Tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignLayout($this->layoutId);
            $tag->save();
        }

        // Delete Regions
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->delete($options);
        }

        // Unassign from all Campaigns
        foreach ($this->campaigns as $campaign) {
            /* @var Campaign $campaign */
            $campaign->setChildObjectDependencies($this->layoutFactory);
            $campaign->unassignLayout($this);
            $campaign->save(['validate' => false]);
        }

        // Delete our own Campaign
        $campaign = $this->campaignFactory->getById($this->campaignId);
        $campaign->setChildObjectDependencies($this->layoutFactory);
        $campaign->delete();

        // Remove the Layout from any display defaults
        $this->getStore()->update('UPDATE `display` SET defaultlayoutid = :defaultLayoutId WHERE defaultlayoutid = :layoutId', [
            'layoutId' => $this->layoutId,
            'defaultLayoutId' => $this->config->GetSetting('DEFAULT_LAYOUT')
        ]);

        // Remove the Layout (now it is orphaned it can be deleted safely)
        $this->getStore()->update('DELETE FROM `layout` WHERE layoutid = :layoutId', array('layoutId' => $this->layoutId));

        $this->getLog()->audit('Layout', $this->layoutId, 'Layout Deleted', ['layoutId' => $this->layoutId]);

        // Delete the cached file (if there is one)
        if (file_exists($this->getCachePath()))
            @unlink($this->getCachePath());
    }

    /**
     * Validate this layout
     * @throws NotFoundException
     */
    public function validate()
    {
        // We must provide either a template or a resolution
        if ($this->width == 0 || $this->height == 0)
            throw new InvalidArgumentException(__('The layout dimensions cannot be empty'), 'width/height');

        // Validation
        if (strlen($this->layout) > 50 || strlen($this->layout) < 1)
            throw new InvalidArgumentException(__("Layout Name must be between 1 and 50 characters"), 'name');

        if (strlen($this->description) > 254)
            throw new InvalidArgumentException(__("Description can not be longer than 254 characters"), 'description');

        // Check for duplicates
        $duplicates = $this->layoutFactory->query(null, array('userId' => $this->ownerId, 'layoutExact' => $this->layout, 'notLayoutId' => $this->layoutId, 'disableUserCheck' => 1));

        if (count($duplicates) > 0)
            throw new DuplicateEntityException(sprintf(__("You already own a layout called '%s'. Please choose another name."), $this->layout));

        // Check zindex is positive
        if ($this->backgroundzIndex < 0)
            throw new InvalidArgumentException(__('Layer must be 0 or a positive number'), 'backgroundzIndex');
    }

    /**
     * Does the layout have the provided tag?
     * @param $searchTag
     * @return bool
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
     */
    public function assignTag($tag)
    {
        $this->load();

        if (!in_array($tag, $this->tags))
            $this->tags[] = $tag;

        return $this;
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     */
    public function unassignTag($tag)
    {
        $this->load();

        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->unassignTags[] = $tag;

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

        $this->unassignTags = array_udiff($this->tags, $tags, function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

        // Replace the arrays
        $this->tags = $tags;

        $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
    }

    /**
     * Export the Layout as its XLF
     * @return string
     */
    public function toXlf()
    {
        $this->getLog()->debug('Layout toXLF for Layout %s, %d', $this->layout, $this->layoutId);

        $this->load(['loadPlaylists' => true]);

        $document = new \DOMDocument();
        $layoutNode = $document->createElement('layout');
        $layoutNode->setAttribute('width', $this->width);
        $layoutNode->setAttribute('height', $this->height);
        $layoutNode->setAttribute('bgcolor', $this->backgroundColor);
        $layoutNode->setAttribute('schemaVersion', $this->schemaVersion);

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

        // We need to make some assessment based on the duration
        //  1. Find out whether any of the regions have more than 1 widget
        //      If they do, then we will always have a region that controls duration.
        //  2. If we don't, then find out if any of the single item regions have a duration specified
        //      If they do, then we will always have a region that controls duration.
        //  3. Go through each region and assess whether they are single widget regions or not.
        //      If they are, then check to see if we have a region that governs duration and if we do set them
        //      to expire after 1 second
        //  4. If they are not single widget regions, then set their duration to be either the duration specified, or
        //      the default duration if none has been specified.
        //  5. In either case, add the duration from #4 to the region duration

        $layoutHasRegionControllingDuration = false;
        $layoutHasEmptyRegion = false;

        foreach ($this->regions as $region) {
            /* @var Region $region */
            // Get a count of widgets in this region
            $countWidgets = 0;
            $hasDuration = false;
            foreach ($region->playlists as $playlist) {
                $countWidgets = $countWidgets + count($playlist->widgets);

                foreach ($playlist->widgets as $widget) {
                    /* @var Widget $widget */
                    if ($widget->useDuration == 1) {
                        $hasDuration = true;
                        break;
                    }
                }
            }

            // Record whether there is an empty region
            if ($countWidgets <= 0)
                $layoutHasEmptyRegion = true;

            // Any with more than one widget
            // Any with duration specified?
            if ($countWidgets > 1 || $hasDuration) {
                $layoutHasRegionControllingDuration = true;
            }
        }

        if ($layoutHasEmptyRegion)
            $this->getLog()->alert('Layout ID ' . $this->layoutId . ' has an empty region');

        foreach ($this->regions as $region) {
            /* @var Region $region */
            $regionNode = $document->createElement('region');
            $regionNode->setAttribute('id', $region->regionId);
            $regionNode->setAttribute('width', $region->width);
            $regionNode->setAttribute('height', $region->height);
            $regionNode->setAttribute('top', $region->top);
            $regionNode->setAttribute('left', $region->left);

            // Only set the zIndex if present
            if ($region->zIndex != 0)
                $regionNode->setAttribute('zindex', $region->zIndex);

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
            $countWidgets = 0;
            foreach ($region->playlists as $playlist) {
                $countWidgets = $countWidgets + count($playlist->widgets);
            }

            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                foreach ($playlist->widgets as $widget) {
                    /* @var Widget $widget */
                    $module = $this->moduleFactory->createWithWidget($widget, $region);

                    // Set the Layout Status
                    $moduleStatus = $module->isValid();
                    $status = ($moduleStatus > $status) ? $moduleStatus : $status;

                    if ($moduleStatus > 1 && $module->getStatusMessage() != '')
                        $this->pushStatusMessage($module->getStatusMessage());

                    // Determine the duration of this widget
                    if ($widget->useDuration == 1) {
                        // Widget duration is as specified
                        $widgetDuration = $widget->duration;

                        // The calculated duration is the provided one
                        $widget->calculatedDuration = $widgetDuration;

                    } else if (!$layoutHasRegionControllingDuration || $countWidgets > 1 || $regionLoop == 1 || $widget->type === 'video') {
                        // No specified duration, but we've detected that we need to use the default duration
                        // Edge case being video - we must ensure that the default duration for video is always 0.
                        $widgetDuration = $module->getModule()->defaultDuration;

                        // The calculated duration is the "real" duration (caters for 0 videos)
                        $widget->calculatedDuration = (($widgetDuration == 0) ? $module->getDuration(['real' => true]) : $widgetDuration);

                    } else {
                        // No specified duration, add nothing to region duration and expire the widget in 1 second
                        $widgetDuration = Widget::$widgetMinDuration;

                        // The calculated duration is 0
                        $widget->calculatedDuration = 0;
                    }

                    // Does our widget have a durationIsPerItem and a Number of Items?
                    $numItems = $widget->getOptionValue('numItems', 0);
                    $itemsPerPage = $widget->getOptionValue('itemsPerPage', 0);
                    if ($widget->getOptionValue('durationIsPerItem', 0) == 1 && $numItems > 1) {
                        // If we have paging involved then work out the page count.
                        if ($itemsPerPage > 0)
                            $numItems = ceil($numItems / $itemsPerPage);

                        $widget->calculatedDuration = (($widget->useDuration == 1) ? $widget->duration : $module->getModule()->defaultDuration) * $numItems;
                    }

                    // Region duration
                    $region->duration = $region->duration + $widget->calculatedDuration;

                    // We also want to add any transition OUT duration
                    // only the OUT duration because IN durations do not get added to the widget duration by the player
                    // https://github.com/xibosignage/xibo/issues/705
                    if ($widget->getOptionValue('transOut', '') != '') {
                        // Transition durations are in milliseconds
                        $region->duration = $region->duration + ($widget->getOptionValue('transOutDuration', 0) / 1000);
                    }

                    // Create media xml node for XLF.
                    $renderAs = $module->getModule()->renderAs;
                    $mediaNode = $document->createElement('media');
                    $mediaNode->setAttribute('id', $widget->widgetId);
                    $mediaNode->setAttribute('type', $widget->type);
                    $mediaNode->setAttribute('render', ($renderAs == '') ? 'native' : $renderAs);

                    // Set the duration according to whether we are using widget duration or not
                    $mediaNode->setAttribute('duration', $widgetDuration);
                    $mediaNode->setAttribute('useDuration', $widget->useDuration);

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

                    if ($audioNodes != null)
                        $mediaNode->appendChild($audioNodes);

                    // Save our widget
                    $widget->save([
                        'notify' => false,
                        'saveWidgetOptions' => false
                    ]);

                    $regionNode->appendChild($mediaNode);
                }
            }

            $this->getLog()->debug('Region duration on layout %d is %d. Comparing to %d.', $this->layoutId, $region->duration, $this->duration);

            // Track the max duration within the layout
            // Test this duration against the layout duration
            if ($this->duration < $region->duration)
                $this->duration = $region->duration;

            $event = new LayoutBuildRegionEvent($region->regionId, $regionNode);
            $this->dispatcher->dispatch($event::NAME, $event);
            // End of region loop.
        }

        $this->getLog()->debug('Setting Layout Duration to %d', $this->duration);

        $tagsNode = $document->createElement('tags');

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tagNode = $document->createElement('tag', $tag->tag);
            $tagsNode->appendChild($tagNode);
        }

        $layoutNode->appendChild($tagsNode);

        // Update the layout status / duration accordingly
        if ($layoutHasEmptyRegion)
            $status = 4;

        $this->status = ($status < $this->status) ? $status : $this->status;

        // Fire a layout.build event, passing the layout and the generated document.
        $event = new LayoutBuildEvent($this, $document);
        $this->dispatcher->dispatch($event::NAME, $event);

        return $document->saveXML();
    }

    /**
     * Export the Layout as a ZipArchive
     * @param DataSetFactory $dataSetFactory
     * @param string $fileName
     * @param array $options
     * @throws InvalidArgumentException
     * @throws XiboException
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

        // Add layout information to the ZIP
        $zip->addFromString('layout.json', json_encode([
            'layout' => $this->layout,
            'description' => $this->description,
            'regions' => $regionMapping
        ]));

        // Add the layout XLF
        $zip->addFile($this->xlfToDisk(), 'layout.xml');

        // Add all media
        $libraryLocation = $this->config->GetSetting('LIBRARY_LOCATION');
        $mappings = [];

        foreach ($this->mediaFactory->getByLayoutId($this->layoutId) as $media) {
            /* @var Media $media */
            $zip->addFile($libraryLocation . $media->storedAs, 'library/' . $media->fileName);

            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 0,
                'font' => 0
            ];
        }

        // Add the background image
        if ($this->backgroundImageId != 0) {
            $media = $this->mediaFactory->getById($this->backgroundImageId);
            $zip->addFile($libraryLocation . $media->storedAs, 'library/' . $media->fileName);

            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 1,
                'font' => 0
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

        foreach ($this->getWidgets() as $widget) {
            /** @var Widget $widget */
            if ($widget->type == 'datasetview' || $widget->type == 'ticker') {
                $dataSetId = $widget->getOptionValue('dataSetId', 0);

                if ($dataSetId != 0) {

                    if (in_array($dataSetId, $dataSetIds))
                        continue;

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
            }
        }

        // Add the mappings file to the ZIP
        $zip->addFromString('dataSet.json', json_encode($dataSets, JSON_PRETTY_PRINT));

        $zip->close();
    }

    /**
     * Save the XLF to disk if necessary
     * @param array $options
     * @return string the path
     */
    public function xlfToDisk($options = [])
    {
        $options = array_merge([
            'notify' => true,
            'collectNow' => true
        ], $options);

        $path = $this->getCachePath();

        if ($this->status == 3 || !file_exists($path)) {

            $this->getLog()->debug('XLF needs building for Layout %d', $this->layoutId);

            // Assume error
            $this->status = 4;

            // Reset duration
            $this->duration = 0;

            // Save the resulting XLF
            try {
                file_put_contents($path, $this->toXlf());
            } catch (\Exception $e) {
                $this->getLog()->error('Cannot build Layout ' . $this->layoutId . '. Unexpected error: ' . $e->getMessage());

                // Will continue and save the status as 4
                $this->status = 4;
                $this->statusMessage = 'Unexpected Error';
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

        return $path;
    }

    /**
     * @return string
     */
    private function getCachePath()
    {
        $libraryLocation = $this->config->GetSetting('LIBRARY_LOCATION');
        return $libraryLocation . $this->layoutId . '.xlf';
    }

    //
    // Add / Update
    //

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding Layout ' . $this->layout);

        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, status, width, height, schemaVersion, backgroundImageId, backgroundColor, backgroundzIndex)
                  VALUES (:layout, :description, :userid, :createddt, :modifieddt, :status, :width, :height, 3, :backgroundImageId, :backgroundColor, :backgroundzIndex)';

        $time = $this->date->getLocalDate();

        $this->layoutId = $this->getStore()->insert($sql, array(
            'layout' => $this->layout,
            'description' => $this->description,
            'userid' => $this->ownerId,
            'createddt' => $time,
            'modifieddt' => $time,
            'status' => 3,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundImageId' => $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
        ));

        // Add a Campaign
        $campaign = $this->campaignFactory->createEmpty();
        $campaign->campaign = $this->layout;
        $campaign->isLayoutSpecific = 1;
        $campaign->ownerId = $this->getOwnerId();
        $campaign->assignLayout($this);

        // Ready to save the Campaign
        $campaign->save();

        // Assign the new campaignId to this layout
        $this->campaignId = $campaign->campaignId;
    }

    /**
     * Update
     * @param array $options
     * NOTE: We set the XML to NULL during this operation as we will always convert old layouts to the new structure
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
              `userId` = :userId,
              `schemaVersion` = :schemaVersion,
              `statusMessage` = :statusMessage
         WHERE layoutID = :layoutid
        ';

        $time = $this->date->getLocalDate();

        $this->getStore()->update($sql, array(
            'layoutid' => $this->layoutId,
            'layout' => $this->layout,
            'description' => $this->description,
            'duration' => $this->duration,
            'modifieddt' => $time,
            'retired' => $this->retired,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundImageId' => $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
            'status' => $this->status,
            'userId' => $this->ownerId,
            'schemaVersion' => $this->schemaVersion,
            'statusMessage' => (empty($this->statusMessage)) ? null : json_encode($this->statusMessage)
        ));

        // Update the Campaign
        $campaign = $this->campaignFactory->getById($this->campaignId);
        $campaign->campaign = $this->layout;
        $campaign->ownerId = $this->ownerId;
        $campaign->save(['validate' => false, 'notify' => $options['notify'], 'collectNow' => $options['collectNow']]);
    }
}