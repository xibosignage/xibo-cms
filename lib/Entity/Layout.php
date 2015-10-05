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

use Xibo\Exception\NotFoundException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

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
     * Legacy XML
     * @var string
     */
    public $legacyXml;

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

    // Child items
    public $regions = [];
    public $tags = [];
    public $permissions = [];
    public $campaigns = [];

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

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
        'setBuildRequired' => true
    ];

    public function __construct()
    {
        $this->setPermissionsClass('Xibo\Entity\Campaign');
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

    public function __toString()
    {
        return sprintf('Layout %s - %d x %d. Regions = %d, Tags = %d. layoutId = %d. Status = %d', $this->layout, $this->width, $this->height, count($this->regions), count($this->tags), $this->layoutId, $this->status);
    }

    private function hash()
    {
        return md5($this->layoutId . $this->ownerId . $this->campaignId . $this->backgroundImageId . $this->backgroundColor . $this->width . $this->height . $this->status . $this->description);
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
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        $this->load();

        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->setOwner($ownerId);
        }
    }

    /**
     * Set the status of this layout to indicate a build is required
     */
    public function setBuildRequired()
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
     * @return array[Widget]
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

        Log::debug('Loading Layout %d with options %s', $this->layoutId, json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = PermissionFactory::getByObjectId('Xibo\\Entity\\Campaign', $this->campaignId);

        // Load all regions
        $this->regions = RegionFactory::getByLayoutId($this->layoutId);

        if ($options['loadPlaylists'])
            $this->loadPlaylists();

        // Load all tags
        if ($options['loadTags'])
            $this->tags = TagFactory::loadByLayoutId($this->layoutId);

        // Load Campaigns
        if ($options['loadCampaigns'])
            $this->campaigns = CampaignFactory::getByLayoutId($this->layoutId);

        // Set the hash
        $this->hash = $this->hash();
        $this->loaded = true;

        Log::debug('Loaded %s' . $this->layoutId);
    }

    /**
     * Load Playlists
     */
    public function loadPlaylists()
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->load();
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
            'validate' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($options['setBuildRequired'])
            $this->setBuildRequired();

        Log::debug('Saving %s with options', $this, json_encode($options));

        // New or existing layout
        if ($this->layoutId == null || $this->layoutId == 0) {
            $this->add();
        } else if ($this->hash() != $this->hash && $options['saveLayout']) {
            $this->update();
        }

        if ($options['saveRegions']) {
            Log::debug('Saving Regions on %s', $this);

            // Update the regions
            foreach ($this->regions as $region) {
                /* @var Region $region */

                // Assert the Layout Id
                $region->layoutId = $this->layoutId;
                $region->save($options);
            }
        }

        if ($options['saveTags']) {
            Log::debug('Saving tags on %s', $this);

            // Save the tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $tag->assignLayout($this->layoutId);
                    $tag->save();
                }
            }
        }

        Log::debug('Save finished for %s', $this);
    }

    /**
     * Delete Layout
     * @param array $options
     * @throws \Exception
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'deleteOrphanedPlaylists' => true
        ], $options);

        Log::debug('Deleting %s', $this);

        // We must ensure everything is loaded before we delete
        if (!$this->loaded)
            $this->load();

        Log::debug('Deleting ' . $this);

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
            $campaign->unassignLayout($this->layoutId);
            $campaign->save(false);
        }

        // Delete our own Campaign
        $campaign = CampaignFactory::getById($this->campaignId);
        $campaign->delete();

        // Remove the Layout from any display defaults
        PDOConnect::update('UPDATE `display` SET defaultlayoutid = 4 WHERE defaultlayoutid = :layoutId', array('layoutId' => $this->layoutId));

        // Remove the Layout (now it is orphaned it can be deleted safely)
        PDOConnect::update('DELETE FROM `layout` WHERE layoutid = :layoutId', array('layoutId' => $this->layoutId));

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
            throw new \InvalidArgumentException(__('The layout dimensions cannot be empty'));

        // Validation
        if (strlen($this->layout) > 50 || strlen($this->layout) < 1)
            throw new \InvalidArgumentException(__("Layout Name must be between 1 and 50 characters"));

        if (strlen($this->description) > 254)
            throw new \InvalidArgumentException(__("Description can not be longer than 254 characters"));

        // Check for duplicates
        $duplicates = LayoutFactory::query(null, array('userId' => $this->ownerId, 'layoutExact' => $this->layout, 'notLayoutId' => $this->layoutId));

        if (count($duplicates) > 0)
            throw new \InvalidArgumentException(sprintf(__("You already own a layout called '%s'. Please choose another name."), $this->layout));
    }

    /**
     * Export the Layout as its XLF
     * @return string
     */
    public function toXlf()
    {
        $this->load(['loadPlaylists' => true]);

        $document = new \DOMDocument();
        $layoutNode = $document->createElement('layout');
        $layoutNode->setAttribute('width', $this->width);
        $layoutNode->setAttribute('height', $this->height);
        $layoutNode->setAttribute('bgcolor', $this->backgroundColor);
        $layoutNode->setAttribute('schemaVersion', $this->schemaVersion);

        if ($this->backgroundImageId != 0) {
            // Get stored as
            $media = MediaFactory::getById($this->backgroundImageId);

            $layoutNode->setAttribute('background', $media->storedAs);
        }

        $document->appendChild($layoutNode);

        // Track module status within the layout
        $status = 0;

        foreach ($this->regions as $region) {
            /* @var Region $region */
            $regionNode = $document->createElement('region');
            $regionNode->setAttribute('id', $region->regionId);
            $regionNode->setAttribute('width', $region->width);
            $regionNode->setAttribute('height', $region->height);
            $regionNode->setAttribute('top', $region->top);
            $regionNode->setAttribute('left', $region->left);

            $layoutNode->appendChild($regionNode);

            // Region Duration
            $region->duration = 0;

            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                foreach ($playlist->widgets as $widget) {
                    /* @var Widget $widget */
                    $module = ModuleFactory::createWithWidget($widget, $region);

                    // Set the Layout Status
                    $status = ($module->isValid() > $status) ? $module->isValid() : $status;

                    // Set the region duration
                    $region->duration = $region->duration + $module->getDuration(['real' => true]);

                    // Create media xml node for XLF.
                    $mediaNode = $document->createElement('media');
                    $mediaNode->setAttribute('id', $widget->widgetId);
                    $mediaNode->setAttribute('duration', $widget->duration);
                    $mediaNode->setAttribute('type', $widget->type);
                    $mediaNode->setAttribute('render', $module->getModule()->renderAs);

                    // Create options nodes
                    $optionsNode = $document->createElement('options');
                    $rawNode = $document->createElement('raw');

                    $mediaNode->appendChild($optionsNode);
                    $mediaNode->appendChild($rawNode);

                    // Inject the URI
                    if ($module->getModule()->regionSpecific == 0) {
                        $media = MediaFactory::getById($widget->mediaIds[0]);
                        $optionNode = $document->createElement('uri', $media->storedAs);
                        $optionsNode->appendChild($optionNode);
                    }

                    foreach ($widget->widgetOptions as $option) {
                        /* @var WidgetOption $option */
                        if ($option->type == 'cdata') {
                            $optionNode = $document->createElement($option->option);
                            $cdata = $document->createCDATASection($option->value);
                            $optionNode->appendChild($cdata);
                            $rawNode->appendChild($optionNode);
                        }
                        else if ($option->type == 'attrib') {
                            $optionNode = $document->createElement($option->option, $option->value);
                            $optionsNode->appendChild($optionNode);
                        }
                    }

                    $regionNode->appendChild($mediaNode);
                }
            }

            // Track the max duration within the layout
            // Test this duration against the layout duration
            if ($this->duration < $region->duration)
                $this->duration = $region->duration;

            // End of region loop.
        }

        $tagsNode = $document->createElement('tags');

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tagNode = $document->createElement('tag', $tag->tag);
            $tagsNode->appendChild($tagNode);
        }

        $layoutNode->appendChild($tagsNode);

        // Update the layout status / duration accordingly
        $this->status = ($status < $this->status) ? $status : $this->status;

        return $document->saveXML();
    }

    /**
     * Export the Layout as a ZipArchive
     * @param string $fileName
     */
    public function toZip($fileName)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($fileName, \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE);
        if ($result !== true)
            throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: ' . $result));

        // Add layout information to the ZIP
        $zip->addFromString('layout.json', json_encode([
            'layout' => $this->layout,
            'description' => $this->description
        ]));

        // Add the layout XLF
        $zip->addFile($this->xlfToDisk(), 'layout.xml');

        // Add all media
        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION');
        $mappings = [];

        foreach (MediaFactory::getByLayoutId($this->layoutId) as $media) {
            /* @var Media $media */
            $zip->addFile($libraryLocation . $media->storedAs, $media->fileName);

            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 0
            ];
        }

        // Add the background image
        if ($this->backgroundImageId != 0) {
            $media = MediaFactory::getById($this->backgroundImageId);
            $zip->addFile($libraryLocation . $media->storedAs, $media->fileName);

            $mappings[] = [
                'file' => $media->fileName,
                'mediaid' => $media->mediaId,
                'name' => $media->name,
                'type' => $media->mediaType,
                'duration' => $media->duration,
                'background' => 1
            ];
        }

        // Add the mappings file to the ZIP
        $zip->addFromString('mapping.json', json_encode($mappings));

        $zip->close();
    }

    /**
     * Save the XLF to disk
     * @return string the path
     */
    public function xlfToDisk()
    {
        $path = $this->getCachePath();

        if ($this->status == 3 || !file_exists($path)) {

            Log::debug('XLF needs building for Layout %d', $this->layoutId);

            // Assume error
            $this->status = 4;

            // Save the resulting XLF
            file_put_contents($path, $this->toXlf());

            $this->save([
                'saveRegions' => true,
                'saveRegionOptions' => false,
                'manageRegionAssignments' => false,
                'saveTags' => false,
                'setBuildRequired' => false
            ]);
        }

        return $path;
    }

    /**
     * @return string
     */
    private function getCachePath()
    {
        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION');
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
        Log::debug('Adding Layout ' . $this->layout);

        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, status, width, height, schemaVersion, backgroundImageId, backgroundColor, backgroundzIndex)
                  VALUES (:layout, :description, :userid, :createddt, :modifieddt, :status, :width, :height, 3, :backgroundImageId, :backgroundColor, :backgroundzIndex)';

        $time = Date::getLocalDate();

        $this->layoutId = PDOConnect::insert($sql, array(
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
        $campaign = new Campaign();
        $campaign->campaign = $this->layout;
        $campaign->isLayoutSpecific = 1;
        $campaign->ownerId = $this->getOwnerId();
        $campaign->assignLayout($this);

        // Ready to save the Campaign
        $campaign->save();
    }

    /**
     * Update
     * NOTE: We set the XML to NULL during this operation as we will always convert old layouts to the new structure
     */
    private function update()
    {
        Log::debug('Editing Layout ' . $this->layout . '. Id = ' . $this->layoutId);

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
              `xml` = NULL,
              `status` = :status,
              `userId` = :userId
         WHERE layoutID = :layoutid
        ';

        $time = Date::getLocalDate();

        PDOConnect::update($sql, array(
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
            'userId' => $this->ownerId
        ));

        // Update the Campaign
        $campaign = CampaignFactory::getById($this->campaignId);
        $campaign->campaign = $this->layout;
        $campaign->ownerId = $this->ownerId;
        $campaign->save(false);
    }
}