<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (LayoutFactory.php) is part of Xibo.
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


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\DataSet;
use Xibo\Entity\DataSetColumn;
use Xibo\Entity\Layout;
use Xibo\Entity\Playlist;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class LayoutFactory
 * @package Xibo\Factory
 */
class LayoutFactory extends BaseFactory
{
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
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var ResolutionFactory
     */
    private $resolutionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var WidgetOptionFactory
     */
    private $widgetOptionFactory;

    /** @var  WidgetAudioFactory */
    private $widgetAudioFactory;

    /** @var  PlaylistFactory */
    private $playlistFactory;

    /**
     * @return DateServiceInterface
     */
    private function getDate()
    {
        return $this->date;
    }

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param EventDispatcherInterface $dispatcher
     * @param PermissionFactory $permissionFactory
     * @param RegionFactory $regionFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     * @param MediaFactory $mediaFactory
     * @param ModuleFactory $moduleFactory
     * @param ResolutionFactory $resolutionFactory
     * @param WidgetFactory $widgetFactory
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param PlaylistFactory $playlistFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $date, $dispatcher, $permissionFactory,
                                $regionFactory, $tagFactory, $campaignFactory, $mediaFactory, $moduleFactory, $resolutionFactory,
                                $widgetFactory, $widgetOptionFactory, $playlistFactory, $widgetAudioFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->config = $config;
        $this->date = $date;
        $this->dispatcher = $dispatcher;
        $this->permissionFactory = $permissionFactory;
        $this->regionFactory = $regionFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
        $this->mediaFactory = $mediaFactory;
        $this->moduleFactory = $moduleFactory;
        $this->resolutionFactory = $resolutionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
    }

    /**
     * Create an empty layout
     * @return Layout
     */
    public function createEmpty()
    {
        return new Layout(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->date,
            $this->dispatcher,
            $this->permissionFactory,
            $this->regionFactory,
            $this->tagFactory,
            $this->campaignFactory,
            $this,
            $this->mediaFactory,
            $this->moduleFactory,
            $this->playlistFactory
        );
    }

    /**
     * Create Layout from Resolution
     * @param int $resolutionId
     * @param int $ownerId
     * @param string $name
     * @param string $description
     * @param string $tags
     * @return Layout
     *
     * @throws XiboException
     */
    public function createFromResolution($resolutionId, $ownerId, $name, $description, $tags)
    {
        $resolution = $this->resolutionFactory->getById($resolutionId);

        // Create a new Layout
        $layout = $this->createEmpty();
        $layout->width = $resolution->width;
        $layout->height = $resolution->height;

        // Set the properties
        $layout->layout = $name;
        $layout->description = $description;
        $layout->backgroundzIndex = 0;
        $layout->backgroundColor = '#000';

        // Set the owner
        $layout->setOwner($ownerId);

        // Create some tags
        $layout->tags = $this->tagFactory->tagsFromString($tags);

        // Add a blank, full screen region
        $layout->regions[] = $this->regionFactory->create($ownerId, $name . '-1', $layout->width, $layout->height, 0, 0);

        return $layout;
    }

    /**
     * Load a layout by its ID
     * @param int $layoutId
     * @return Layout The Layout
     * @throws NotFoundException
     */
    public function loadById($layoutId)
    {
        // Get the layout
        $layout = $this->getById($layoutId);
        // Load the layout
        $layout->load();

        return $layout;
    }

    /**
     * Loads only the layout information
     * @param int $layoutId
     * @return Layout
     * @throws NotFoundException
     */
    public function getById($layoutId)
    {
        if ($layoutId == 0)
            throw new NotFoundException(\__('LayoutId is 0'));

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get CampaignId from layout history
     * @param int $layoutId
     * @return int campaignId
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function getCampaignIdFromLayoutHistory($layoutId)
    {
        if ($layoutId == null) {
            throw new InvalidArgumentException('Invalid Input', 'layoutId');
        }

        $row = $this->getStore()->select('SELECT campaignId FROM `layouthistory` WHERE layoutId = :layoutId LIMIT 1', ['layoutId' => $layoutId]);

        if (count($row) <= 0) {
            throw new NotFoundException(__('Layout does not exist'));
        }

        return intval($row[0]['campaignId']);
    }


    /**
     * Get layout by layout history
     * @param int $layoutId
     * @return Layout
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\InvalidArgumentException
     * @throws NotFoundException
     */
    public function getByLayoutHistory($layoutId)
    {
        // Get a Layout by its Layout HistoryId
        $layouts = $this->query(null, array('disableUserCheck' => 1, 'layoutHistoryId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get latest layoutId by CampaignId from layout history
     * @param int campaignId
     * @return int layoutId
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function getLatestLayoutIdFromLayoutHistory($campaignId)
    {
        if ($campaignId == null) {
            throw new InvalidArgumentException('Invalid Input', 'campaignId');
        }

        $row = $this->getStore()->select('SELECT MAX(layoutId) AS layoutId FROM `layouthistory` WHERE campaignId = :campaignId  ', ['campaignId' => $campaignId]);

        if (count($row) <= 0) {
            throw new NotFoundException(__('Layout does not exist'));
        }

        // Set our Layout ID
        return intval($row[0]['layoutId']);
    }

    /**
     * Loads only the layout information
     * @param int $layoutId
     * @return Layout
     * @throws NotFoundException
     */
    public function getByParentId($layoutId)
    {
        if ($layoutId == 0)
            throw new NotFoundException();

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'parentId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get a Layout by its Layout Specific Campaign OwnerId
     * @param int $campaignId
     * @return Layout
     * @throws NotFoundException
     */
    public function getByParentCampaignId($campaignId)
    {
        if ($campaignId == 0)
            throw new NotFoundException();

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'ownerCampaignId' => $campaignId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return Layout[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('userId' => $ownerId, 'excludeTemplates' => -1, 'retired' => -1, 'showDrafts' => 1));
    }

    /**
     * Get by CampaignId
     * @param int $campaignId
     * @param bool $permissionsCheck Should we check permissions?
     * @param bool $includeDrafts Should we include draft Layouts in the results?
     * @return Layout[]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId, $permissionsCheck = true, $includeDrafts = false)
    {
        return $this->query(['displayOrder'], [
            'campaignId' => $campaignId,
            'excludeTemplates' => -1,
            'retired' => -1,
            'disableUserCheck' => $permissionsCheck ? 0 : 1,
            'showDrafts' => $includeDrafts ? 1 : 0
        ]);
    }

    /**
     * Get by RegionId
     * @param int $regionId
     * @param bool $permissionsCheck Should we check permissions?
     * @return Layout
     * @throws NotFoundException
     */
    public function getByRegionId($regionId, $permissionsCheck = true)
    {
        $layouts = $this->query(['displayOrder'], [
            'regionId' => $regionId,
            'excludeTemplates' => -1,
            'retired' => -1,
            'disableUserCheck' => $permissionsCheck ? 0 : 1
        ]);

        if (count($layouts) <= 0) {
            throw new NotFoundException(__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return Layout[]
     * @throws XiboException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get by Background Image Id
     * @param int $backgroundImageId
     * @return Layout[]
     * @throws XiboException
     */
    public function getByBackgroundImageId($backgroundImageId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'backgroundImageId' => $backgroundImageId, 'showDrafts' => 1]);
    }

    /**
     * @param string $tag
     * @return Layout[]
     * @throws NotFoundException
     */
    public function getByTag($tag)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'tags' => $tag, 'exactTags' => 1]);
    }

    /**
     * Load a layout by its XLF
     * @param string $layoutXlf
     * @param Layout[Optional] $layout
     * @return Layout
     */
    public function loadByXlf($layoutXlf, $layout = null)
    {
        $this->getLog()->debug('Loading Layout by XLF');

        // New Layout
        if ($layout == null)
            $layout = $this->createEmpty();

        // Get a list of modules for us to use
        $modules = $this->moduleFactory->get();

        // Parse the XML and fill in the details for this layout
        $document = new \DOMDocument();
        $document->loadXML($layoutXlf);

        $layout->schemaVersion = (int)$document->documentElement->getAttribute('schemaVersion');
        $layout->width = $document->documentElement->getAttribute('width');
        $layout->height = $document->documentElement->getAttribute('height');
        $layout->backgroundColor = $document->documentElement->getAttribute('bgcolor');
        $layout->backgroundzIndex = (int)$document->documentElement->getAttribute('zindex');

        // Xpath to use when getting media
        $xpath = new \DOMXPath($document);

        // Populate Region Nodes
        foreach ($document->getElementsByTagName('region') as $regionNode) {
            /* @var \DOMElement $regionNode */
            $this->getLog()->debug('Found Region');

            // Get the ownerId
            $regionOwnerId = $regionNode->getAttribute('userId');
            if ($regionOwnerId == null)
                $regionOwnerId = $layout->ownerId;

            // Create the region
            $region = $this->regionFactory->create(
                $regionOwnerId,
                $regionNode->getAttribute('name'),
                (double)$regionNode->getAttribute('width'),
                (double)$regionNode->getAttribute('height'),
                (double)$regionNode->getAttribute('top'),
                (double)$regionNode->getAttribute('left'),
                (int)$regionNode->getAttribute('zindex')
                );

            // Use the regionId locally to parse the rest of the XLF
            $region->tempId = $regionNode->getAttribute('id');

            // Set the region name if empty
            if ($region->name == '')
                $region->name = count($layout->regions) + 1;

            // Populate Playlists (XLF doesn't contain any playlists)
            $playlist = $this->playlistFactory->create($region->name, $regionOwnerId);

            // Populate region options.
            foreach ($xpath->query('//region[@id="' . $region->tempId . '"]/options') as $regionOptionsNode) {
                /* @var \DOMElement $regionOptionsNode */
                foreach ($regionOptionsNode->childNodes as $regionOption) {
                    /* @var \DOMElement $regionOption */
                    $region->setOptionValue($regionOption->nodeName, $regionOption->textContent);
                }
            }

            // Get all widgets
            foreach ($xpath->query('//region[@id="' . $region->tempId . '"]/media') as $mediaNode) {
                /* @var \DOMElement $mediaNode */

                $mediaOwnerId = $mediaNode->getAttribute('userId');
                if ($mediaOwnerId == null)
                    $mediaOwnerId = $regionOwnerId;

                $widget = $this->widgetFactory->createEmpty();
                $widget->type = $mediaNode->getAttribute('type');
                $widget->ownerId = $mediaOwnerId;
                $widget->duration = $mediaNode->getAttribute('duration');
                $widget->useDuration = $mediaNode->getAttribute('useDuration');
                // Additional check for importing layouts from 1.7 series, where the useDuration did not exist
                $widget->useDuration = ($widget->useDuration === '') ? 1 : $widget->useDuration;
                $widget->tempId = $mediaNode->getAttribute('fileId');
                $widgetId = $mediaNode->getAttribute('id');

                // Widget from/to dates.
                $widget->fromDt = ($mediaNode->getAttribute('fromDt') === '') ? Widget::$DATE_MIN : $mediaNode->getAttribute('fromDt');
                $widget->toDt = ($mediaNode->getAttribute('toDt') === '') ? Widget::$DATE_MAX : $mediaNode->getAttribute('toDt');

                $this->setWidgetExpiryDatesOrDefault($widget);

                $this->getLog()->debug('Adding Widget to object model. ' . $widget);

                // Does this module type exist?
                if (!array_key_exists($widget->type, $modules)) {
                    $this->getLog()->error('Module Type [%s] in imported Layout does not exist. Allowable types: %s', $widget->type, json_encode(array_keys($modules)));
                    continue;
                }

                $module = $modules[$widget->type];
                /* @var \Xibo\Entity\Module $module */

                //
                // Get all widget options
                //
                $xpathQuery = '//region[@id="' . $region->tempId . '"]/media[@id="' . $widgetId . '"]/options';
                foreach ($xpath->query($xpathQuery) as $optionsNode) {
                    /* @var \DOMElement $optionsNode */
                    foreach ($optionsNode->childNodes as $mediaOption) {
                        /* @var \DOMElement $mediaOption */
                        $widgetOption = $this->widgetOptionFactory->createEmpty();
                        $widgetOption->type = 'attrib';
                        $widgetOption->option = $mediaOption->nodeName;
                        $widgetOption->value = $mediaOption->textContent;

                        $widget->widgetOptions[] = $widgetOption;

                        // Convert the module type of known legacy widgets
                        if ($widget->type == 'ticker' && $widgetOption->option == 'sourceId' && $widgetOption->value == '2') {
                            $widget->type = 'datasetticker';
                            $module = $modules[$widget->type];
                        }
                    }
                }

                $this->getLog()->debug('Added %d options with xPath query: %s', count($widget->widgetOptions), $xpathQuery);

                //
                // Get the MediaId associated with this widget (using the URI)
                //
                if ($module->regionSpecific == 0) {
                    $this->getLog()->debug('Library Widget, getting mediaId');

                    if (empty($widget->tempId)) {
                        $this->getLog()->debug('FileId node is empty, setting tempId from uri option. Options: %s', json_encode($widget->widgetOptions));
                        $mediaId = explode('.', $widget->getOptionValue('uri', '0.*'));
                        $widget->tempId = $mediaId[0];
                    }

                    $this->getLog()->debug('Assigning mediaId %d', $widget->tempId);
                    $widget->assignMedia($widget->tempId);
                }

                //
                // Get all widget raw content
                //
                foreach ($xpath->query('//region[@id="' . $region->tempId . '"]/media[@id="' . $widgetId . '"]/raw') as $rawNode) {
                    /* @var \DOMElement $rawNode */
                    // Get children
                    foreach ($rawNode->childNodes as $mediaOption) {
                        /* @var \DOMElement $mediaOption */
                        if ($mediaOption->textContent == null)
                            continue;

                        $widgetOption = $this->widgetOptionFactory->createEmpty();
                        $widgetOption->type = 'cdata';
                        $widgetOption->option = $mediaOption->nodeName;
                        $widgetOption->value = $mediaOption->textContent;

                        $widget->widgetOptions[] = $widgetOption;
                    }
                }

                //
                // Audio
                //
                foreach ($xpath->query('//region[@id="' . $region->tempId . '"]/media[@id="' . $widgetId . '"]/audio') as $rawNode) {
                    /* @var \DOMElement $rawNode */
                    // Get children
                    foreach ($rawNode->childNodes as $audioNode) {
                        /* @var \DOMElement $audioNode */
                        if ($audioNode->textContent == null)
                            continue;

                        $audioMediaId = $audioNode->getAttribute('mediaId');

                        if (empty($audioMediaId)) {
                            // Try to parse it from the text content
                            $audioMediaId = explode('.', $audioNode->textContent)[0];
                        }

                        $widgetAudio = $this->widgetAudioFactory->createEmpty();
                        $widgetAudio->mediaId = $audioMediaId;
                        $widgetAudio->volume = $audioNode->getAttribute('volume');
                        $widgetAudio->loop = $audioNode->getAttribute('loop');

                        $widget->assignAudio($widgetAudio);
                    }
                }

                // Add the widget to the playlist
                $playlist->assignWidget($widget);
            }

            // Assign Playlist to the Region
            $region->regionPlaylist = $playlist;

            // Assign the region to the Layout
            $layout->regions[] = $region;
        }

        $this->getLog()->debug('Finished loading layout - there are %d regions.', count($layout->regions));

        // Load any existing tags
        if (!is_array($layout->tags))
            $layout->tags = $this->tagFactory->tagsFromString($layout->tags);

        foreach ($xpath->query('//tags/tag') as $tagNode) {
            /* @var \DOMElement $tagNode */
            if (trim($tagNode->textContent) == '')
                continue;

            $layout->tags[] = $this->tagFactory->tagFromString($tagNode->textContent);
        }

        // The parsed, finished layout
        return $layout;
    }

    /**
     * @param $layoutJson
     * @param null $layout
     * @param null $playlistJson
     * @param null $nestedPlaylistJson
     * @param bool $importTags
     * @return array
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function loadByJson($layoutJson, $layout = null, $playlistJson, $nestedPlaylistJson, $importTags = false)
    {
        $this->getLog()->debug('Loading Layout by JSON');

        // New Layout
        if ($layout == null)
            $layout = $this->createEmpty();

        if ($playlistJson == null) {
            throw new InvalidArgumentException(__('playlist.json not found in the archive'), 'playlistJson');
        }

        $playlists = [];
        $oldIds = [];
        $newIds = [];
        $widgets = [];
        // Get a list of modules for us to use
        $modules = $this->moduleFactory->get();

        $layout->schemaVersion = (int)$layoutJson['layoutDefinitions']['schemaVersion'];
        $layout->width = $layoutJson['layoutDefinitions']['width'];
        $layout->height = $layoutJson['layoutDefinitions']['height'];
        $layout->backgroundColor = $layoutJson['layoutDefinitions']['backgroundColor'];
        $layout->backgroundzIndex = (int)$layoutJson['layoutDefinitions']['backgroundzIndex'];

        // Nested Playlists are Playlists which exist below the first level of Playlists in Sub-Playlist Widgets
        // we need to import and save them first.
        if ($nestedPlaylistJson != null) {
            $this->getLog()->debug('Layout import, creating nested Playlists from JSON, there are ' . count($nestedPlaylistJson) . ' Playlists to create');

            // create all nested Playlists, save their widgets to key=>value array
            foreach ($nestedPlaylistJson as $nestedPlaylist) {
                $newPlaylist = $this->playlistFactory->createEmpty()->hydrate($nestedPlaylist);
                $newPlaylist->tags = [];

                // Populate tags
                if ($nestedPlaylist['tags'] !== null && count($nestedPlaylist['tags']) > 0 && $importTags) {
                    foreach ($nestedPlaylist['tags'] as $tag) {
                        $newPlaylist->tags[] = $this->tagFactory->tagFromString(
                            $tag['tag'] . (!empty($tag['value']) ? '|' . $tag['value'] : '')
                        );
                    }
                }

                $oldIds[] = $newPlaylist->playlistId;
                $widgets[$newPlaylist->playlistId] = $newPlaylist->widgets;

                $this->setOwnerAndSavePlaylist($newPlaylist);

                $newIds[] = $newPlaylist->playlistId;
            }

            $combined = array_combine($oldIds, $newIds);

            // this function will go through all widgets assigned to the nested Playlists, create the widgets, adjust the Ids and return an array of Playlists
            // then the Playlists array is used later on to adjust mediaIds if needed
            $playlists = $this->createNestedPlaylistWidgets($widgets, $combined, $playlists);

            $this->getLog()->debug('Finished creating nested playlists there are ' . count($playlists) . ' Playlists created');
        }

        // Populate Region Nodes
        foreach ($layoutJson['layoutDefinitions']['regions'] as $regionJson) {
            $this->getLog()->debug('Found Region ' . json_encode($regionJson));

            // Get the ownerId
            $regionOwnerId = $regionJson['ownerId'];
            if ($regionOwnerId == null)
                $regionOwnerId = $layout->ownerId;

            // Create the region
            $region = $this->regionFactory->create(
                $regionOwnerId,
                $regionJson['name'],
                (double)$regionJson['width'],
                (double)$regionJson['height'],
                (double)$regionJson['top'],
                (double)$regionJson['left'],
                (int)$regionJson['zIndex']
            );

            // Use the regionId locally to parse the rest of the JSON
            $region->tempId = $regionJson['tempId'];

            // Set the region name if empty
            if ($region->name == '')
                $region->name = count($layout->regions) + 1;

            // Populate Playlists
            $playlist = $this->playlistFactory->create($region->name, $regionOwnerId);

            foreach ($regionJson['regionOptions'] as $regionOption) {
                $region->setOptionValue($regionOption['option'], $regionOption['value']);
            }

            // Get all widgets
            foreach ($regionJson['regionPlaylist']['widgets'] as $mediaNode) {

                $mediaOwnerId = $mediaNode['ownerId'];
                if ($mediaOwnerId == null) {
                    $mediaOwnerId = $regionOwnerId;
                }

                $widget = $this->widgetFactory->createEmpty();
                $widget->type = $mediaNode['type'];
                $widget->ownerId = $mediaOwnerId;
                $widget->duration = $mediaNode['duration'];
                $widget->useDuration = $mediaNode['useDuration'];
                $widget->tempId = (int)implode(',', $mediaNode['mediaIds']);
                $widgetId = $mediaNode['widgetId'];

                // Widget from/to dates.
                $widget->fromDt = ($mediaNode['fromDt'] === '') ? Widget::$DATE_MIN : $mediaNode['fromDt'];
                $widget->toDt = ($mediaNode['toDt'] === '') ? Widget::$DATE_MAX : $mediaNode['toDt'];

                $this->setWidgetExpiryDatesOrDefault($widget);

                $this->getLog()->debug('Adding Widget to object model. ' . $widget);

                // Does this module type exist?
                if (!array_key_exists($widget->type, $modules)) {
                    $this->getLog()->error('Module Type [%s] in imported Layout does not exist. Allowable types: %s', $widget->type, json_encode(array_keys($modules)));
                    continue;
                }

                $module = $modules[$widget->type];
                /* @var \Xibo\Entity\Module $module */

                //
                // Get all widget options
                //
                $layoutSubPlaylistId = null;
                foreach ($mediaNode['widgetOptions'] as $optionsNode) {

                    if ($optionsNode['option'] == 'subPlaylistOptions') {
                        $subPlaylistOptions = json_decode($optionsNode['value']);
                    }

                    if ($optionsNode['option'] == 'subPlaylistIds') {
                        $layoutSubPlaylistId = json_decode($optionsNode['value']);
                    }

                    $widgetOption = $this->widgetOptionFactory->createEmpty();
                    $widgetOption->type = $optionsNode['type'];
                    $widgetOption->option = $optionsNode['option'];
                    $widgetOption->value = $optionsNode['value'];

                    $widget->widgetOptions[] = $widgetOption;

                    // Convert the module type of known legacy widgets
                    if ($widget->type == 'ticker' && $widgetOption->option == 'sourceId' && $widgetOption->value == '2') {
                        $widget->type = 'datasetticker';
                        $module = $modules[$widget->type];
                    }
                }

                //
                // Get the MediaId associated with this widget
                //
                if ($module->regionSpecific == 0) {
                    $this->getLog()->debug('Library Widget, getting mediaId');

                    $this->getLog()->debug('Assigning mediaId %d', $widget->tempId);
                    $widget->assignMedia($widget->tempId);
                }

                //
                // Audio
                //
                foreach ($mediaNode['audio'] as $audioNode) {
                    if ($audioNode == []) {
                        continue;
                    }

                    $audioMediaId = implode(',', $audioNode);

                    $widgetAudio = $this->widgetAudioFactory->createEmpty();
                    $widgetAudio->mediaId = $audioMediaId;
                    $widgetAudio->volume = $mediaNode['volume'];;
                    $widgetAudio->loop = $mediaNode['loop'];;

                    $widget->assignAudio($widgetAudio);
                }

                // Sub-Playlist widgets with Playlists
                if ($widget->type == 'subplaylist') {

                    $layoutSubPlaylistIds = [];
                    $subPlaylistOptionsUpdated = [];
                    $widgets = [];
                    $this->getLog()->debug('Layout import, creating layout Playlists from JSON, there are ' . count($playlistJson) . ' Playlists to create');

                    foreach ($playlistJson as $playlistDetail) {

                        $newPlaylist = $this->playlistFactory->createEmpty()->hydrate($playlistDetail);
                        $newPlaylist->tags = [];

                        // Populate tags
                        if ($playlistDetail['tags'] !== null && count($playlistDetail['tags']) > 0 && $importTags) {
                            foreach ($playlistDetail['tags'] as $tag) {
                                $newPlaylist->tags[] = $this->tagFactory->tagFromString(
                                    $tag['tag'] . (!empty($tag['value']) ? '|' . $tag['value'] : '')
                                );
                            }
                        }

                        // Check to see if it matches our Sub-Playlist widget config
                        if (in_array($newPlaylist->playlistId, $layoutSubPlaylistId)) {

                            // Store the oldId to swap permissions later
                            $oldIds[] = $newPlaylist->playlistId;

                            // Store the Widgets on the Playlist
                            $widgets[$newPlaylist->playlistId] = $newPlaylist->widgets;

                            // Save a new Playlist and capture the Id
                            $this->setOwnerAndSavePlaylist($newPlaylist);

                            $newIds[] = $newPlaylist->playlistId;
                        }
                    }

                    $oldAssignedIds = $layoutSubPlaylistId;
                    $combined = array_combine($oldIds, $newIds);

                    $playlists = $this->createNestedPlaylistWidgets($widgets, $combined, $playlists);

                    foreach ($combined as $old => $new) {
                        if (in_array($old, $oldAssignedIds)) {
                            $layoutSubPlaylistIds[] = $new;
                        }
                    }

                    $widget->setOptionValue('subPlaylistIds', 'attrib', json_encode($layoutSubPlaylistIds));

                    foreach ($layoutSubPlaylistIds as $value) {

                        foreach ($subPlaylistOptions as $playlistId => $options) {

                            foreach ($options as $optionName => $optionValue) {
                                if ($optionName == 'subPlaylistIdSpots') {
                                    $spots = $optionValue;
                                } elseif ($optionName == 'subPlaylistIdSpotLength') {
                                    $spotsLength = $optionValue;
                                } elseif ($optionName == 'subPlaylistIdSpotFill') {
                                    $spotFill = $optionValue;
                                }
                            }
                        }

                        $subPlaylistOptionsUpdated[$value] = [
                            'subPlaylistIdSpots' => isset($spots) ? $spots : '',
                            'subPlaylistIdSpotLength' => isset($spotsLength) ? $spotsLength : '',
                            'subPlaylistIdSpotFill' => isset($spotFill) ? $spotFill : ''
                        ];
                    }

                    $widget->setOptionValue('subPlaylistOptions', 'attrib', json_encode($subPlaylistOptionsUpdated));
                }

                // Add the widget to the regionPlaylist
                $playlist->assignWidget($widget);
            }

            // Assign Playlist to the Region
            $region->regionPlaylist = $playlist;

            // Assign the region to the Layout
            $layout->regions[] = $region;
        }

        $this->getLog()->debug('Finished loading layout - there are %d regions.', count($layout->regions));

        if ($importTags) {
            foreach ($layoutJson['layoutDefinitions']['tags'] as $tagNode) {
                if ($tagNode == []) {
                    continue;
                }

                $layout->tags[] = $this->tagFactory->tagFromString(
                    $tagNode['tag'] . (!empty($tagNode['value']) ? '|' . $tagNode['value'] : '')
                );

            }
        }

        // The parsed, finished layout
        return [$layout, $playlists];
    }

    /**
     * Create Layout from ZIP File
     * @param string $zipFile
     * @param string $layoutName
     * @param int $userId
     * @param int $template
     * @param int $replaceExisting
     * @param int $importTags
     * @param bool $useExistingDataSets
     * @param bool $importDataSetData
     * @param \Xibo\Controller\Library $libraryController
     * @param $tags
     * @return Layout
     * @throws XiboException
     */
    public function createFromZip($zipFile, $layoutName, $userId, $template, $replaceExisting, $importTags, $useExistingDataSets, $importDataSetData, $libraryController, $tags)
    {
        $this->getLog()->debug('Create Layout from ZIP File: %s, imported name will be %s.', $zipFile, $layoutName);

        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/';

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile))
            throw new \InvalidArgumentException(__('File does not exist'));

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile)) {
            throw new \InvalidArgumentException(__('Unable to open ZIP'));
        }

        // Get the layout details
        $layoutDetails = json_decode($zip->getFromName('layout.json'), true);

        // Get the Playlist details
        $playlistDetails = $zip->getFromName('playlist.json');
        $nestedPlaylistDetails = $zip->getFromName('nestedPlaylist.json');

        // Construct the Layout
        if ($playlistDetails !== false) {
            $playlistDetails = json_decode(($playlistDetails), true);

            if ($nestedPlaylistDetails !== false) {
                $nestedPlaylistDetails = json_decode($nestedPlaylistDetails, true);
            }

            $jsonResults = $this->loadByJson($layoutDetails, null, $playlistDetails, $nestedPlaylistDetails, $importTags);
            $layout = $jsonResults[0];
            $playlists = $jsonResults[1];

        } else {
            $layout = $this->loadByXlf($zip->getFromName('layout.xml'));
        }

        $this->getLog()->debug('Layout Loaded: ' . $layout);
        // Ensure width and height are integer type for resolution validation purpose xibosignage/xibo#1648
        $layout->width = (int)$layout->width;
        $layout->height = (int)$layout->height;

        // Override the name/description
        $layout->layout = (($layoutName != '') ? $layoutName : $layoutDetails['layout']);
        $layout->description = (isset($layoutDetails['description']) ? $layoutDetails['description'] : '');

        // Get global stat setting of layout to on/off proof of play statistics
        $layout->enableStat = $this->config->getSetting('LAYOUT_STATS_ENABLED_DEFAULT');

        $this->getLog()->debug('Layout Loaded: ' . $layout);

        // Check that the resolution we have in this layout exists, and if not create it.
        try {
            if ($layout->schemaVersion < 2)
                $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
            else
                $this->resolutionFactory->getByDimensions($layout->width, $layout->height);

        } catch (NotFoundException $notFoundException) {
            $this->getLog()->info('Import is for an unknown resolution, we will create it with name: ' . $layout->width . ' x ' . $layout->height);

            $resolution = $this->resolutionFactory->create($layout->width . ' x ' . $layout->height, $layout->width, $layout->height);
            $resolution->userId = $userId;
            $resolution->save();
        }

        // Update region names
        if (isset($layoutDetails['regions']) && count($layoutDetails['regions']) > 0) {
            $this->getLog()->debug('Updating region names according to layout.json');
            foreach ($layout->regions as $region) {
                if (array_key_exists($region->tempId, $layoutDetails['regions']) && !empty($layoutDetails['regions'][$region->tempId])) {
                    $region->name = $layoutDetails['regions'][$region->tempId];
                    $region->regionPlaylist->name = $layoutDetails['regions'][$region->tempId];
                }
            }
        }

        // Remove the tags if necessary
        if (!$importTags) {
            $this->getLog()->debug('Removing tags from imported layout');
            $layout->tags = [];
        }

        // Add the template tag if we are importing a template
        if ($template) {
            $layout->tags[] = $this->tagFactory->getByTag('template');
        }

        // Tag as imported
        $layout->tags[] = $this->tagFactory->tagFromString('imported');

        // Tag from the upload form
        $tagsFromForm = (($tags != '') ? $this->tagFactory->tagsFromString($tags) : []);
        foreach ($tagsFromForm as $tagFromForm) {
            $layout->tags[] = $tagFromForm;
        }

        // Set the owner
        $layout->setOwner($userId, true);

        // Track if we've added any fonts
        $fontsAdded = false;

        $widgets = $layout->getWidgets();
        $this->getLog()->debug('Layout has ' . count($widgets) . ' widgets');

        $this->getLog()->debug('Process mapping.json file.');

        // Go through each region and add the media (updating the media ids)
        $mappings = json_decode($zip->getFromName('mapping.json'), true);

        foreach ($mappings as $file) {
            // Import the Media File
            $intendedMediaName = $file['name'];
            $temporaryFileName = $libraryLocation . $file['file'];

            // Get the file from the ZIP
            $fileStream = $zip->getStream('library/' . $file['file']);

            if ($fileStream === false) {
                // Log out the entire ZIP file and all entries.
                $log = 'Problem getting library/' . $file['file'] . '. Files: ';
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $log .= $zip->getNameIndex($i) . ', ';
                }

                $this->getLog()->error($log);

                throw new \InvalidArgumentException(__('Empty file in ZIP'));
            }

            // Open a file pointer to stream into
            if (!$temporaryFileStream = fopen($temporaryFileName, 'w')) {
                throw new InvalidArgumentException(__('Cannot save media file from ZIP file'), 'temp');
            }

            // Loop over the file and write into the stream
            while (!feof($fileStream)) {
                fwrite($temporaryFileStream, fread($fileStream, 8192));
            }

            fclose($fileStream);
            fclose($temporaryFileStream);

            // Check we don't already have one
            $newMedia = false;
            $isFont = (isset($file['font']) && $file['font'] == 1);

            try {
                $media = $this->mediaFactory->getByName($intendedMediaName);

                $this->getLog()->debug('Media already exists with name: %s', $intendedMediaName);

                if ($replaceExisting && !$isFont) {
                    // Media with this name already exists, but we don't want to use it.
                    $intendedMediaName = 'import_' . $layout->layout . '_' . uniqid();
                    throw new NotFoundException();
                }

            } catch (NotFoundException $e) {
                // Create it instead
                $this->getLog()->debug('Media does not exist in Library, add it. %s', $file['file']);

                $media = $this->mediaFactory->create($intendedMediaName, $file['file'], $file['type'], $userId, $file['duration']);
                $media->tags[] = $this->tagFactory->tagFromString('imported');

                // Get global stat setting of media to set to on/off/inherit
                $media->enableStat = $this->config->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
                $media->save();

                $newMedia = true;
            }

            // Find where this is used and swap for the real mediaId
            $oldMediaId = $file['mediaid'];
            $newMediaId = $media->mediaId;

            if ($file['background'] == 1) {
                // Set the background image on the new layout
                $layout->backgroundImageId = $newMediaId;
            } else if ($isFont) {
                // Just raise a flag to say that we've added some fonts to the library
                if ($newMedia) {
                    $fontsAdded = true;
                }
            } else {
                // Go through all widgets and replace if necessary
                // Keep the keys the same? Doesn't matter
                foreach ($widgets as $widget) {
                    /* @var Widget $widget */
                    $audioIds = $widget->getAudioIds();

                    $this->getLog()->debug('Checking Widget for the old mediaID [%d] so we can replace it with the new mediaId [%d] and storedAs [%s]. Media assigned to widget %s.', $oldMediaId, $newMediaId, $media->storedAs, json_encode($widget->mediaIds));

                    if (in_array($oldMediaId, $widget->mediaIds)) {

                        $this->getLog()->debug('Removing %d and replacing with %d', $oldMediaId, $newMediaId);

                        // Are we an audio record?
                        if (in_array($oldMediaId, $audioIds)) {
                            // Swap the mediaId on the audio record
                            foreach ($widget->audio as $widgetAudio) {
                                if ($widgetAudio->mediaId == $oldMediaId) {
                                    $widgetAudio->mediaId = $newMediaId;
                                    break;
                                }
                            }

                        } else {
                            // Non audio
                            $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                        }

                        // Always manage the assignments
                        // Unassign the old ID
                        $widget->unassignMedia($oldMediaId);

                        // Assign the new ID
                        $widget->assignMedia($newMediaId);
                    }
                }
            }

            // Playlists with media widgets
            // We will iterate through all Playlists we've created during layout import here and replace any mediaIds if needed
            if (isset($playlists) && $playlistDetails !== false) {
                foreach ($playlists as $playlist) {
                    /** @var $playlist Playlist */
                    foreach ($playlist->widgets as $widget) {
                        $audioIds = $widget->getAudioIds();

                        if (in_array($oldMediaId, $widget->mediaIds)) {

                            $this->getLog()->debug('Playlist import Removing %d and replacing with %d', $oldMediaId, $newMediaId);

                            // Are we an audio record?
                            if (in_array($oldMediaId, $audioIds)) {
                                // Swap the mediaId on the audio record
                                foreach ($widget->audio as $widgetAudio) {
                                    if ($widgetAudio->mediaId == $oldMediaId) {
                                        $widgetAudio->mediaId = $newMediaId;
                                        break;
                                    }
                                }

                            } else {
                                // Non audio
                                $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                            }

                            // Always manage the assignments
                            // Unassign the old ID
                            $widget->unassignMedia($oldMediaId);

                            // Assign the new ID
                            $widget->assignMedia($newMediaId);
                            $widget->save();

                            if (!in_array($widget, $playlist->widgets)) {
                                $playlist->assignWidget($widget);
                                $playlist->requiresDurationUpdate = 1;
                                $playlist->save();
                            }
                        }

                        // add Playlist widgetsto the $widgets (which already has all widgets from layout regionPlaylists)
                        // this will be needed if any Playlist has widgets with dataSets
                        if ($widget->type == 'datasetview' || $widget->type == 'datasetticker' || $widget->type == 'chart') {
                            $widgets[] = $widget;
                            $playlistWidgets[] = $widget;
                        }
                    }
                }
            }
        }

        // Handle any datasets provided with the layout
        $dataSets = $zip->getFromName('dataSet.json');

        if ($dataSets !== false) {

            $dataSets = json_decode($dataSets, true);

            $this->getLog()->debug('There are ' . count($dataSets) . ' DataSets to import.');

            foreach ($dataSets as $item) {
                // Hydrate a new dataset object with this json object
                $dataSet = $libraryController->getDataSetFactory()->createEmpty()->hydrate($item);
                $dataSet->columns = [];
                $dataSetId = $dataSet->dataSetId;

                // We must null the ID so that we don't try to load the dataset when we assign columns
                $dataSet->dataSetId = null;
                
                // Hydrate the columns
                foreach ($item['columns'] as $columnItem) {
                    $this->getLog()->debug('Assigning column: %s', json_encode($columnItem));
                    $dataSet->assignColumn($libraryController->getDataSetFactory()->getDataSetColumnFactory()->createEmpty()->hydrate($columnItem));
                }

                /** @var DataSet $existingDataSet */
                $existingDataSet = null;

                // Do we want to try and use a dataset that already exists?
                if ($useExistingDataSets) {
                    // Check to see if we already have a dataset with the same code/name, prefer code.
                    if ($dataSet->code != '') {
                        try {
                            // try and get by code
                            $existingDataSet = $libraryController->getDataSetFactory()->getByCode($dataSet->code);
                        } catch (NotFoundException $e) {
                            $this->getLog()->debug('Existing dataset not found with code %s', $dataSet->code);

                        }
                    }

                    if ($existingDataSet === null) {
                        // try by name
                        try {
                            $existingDataSet = $libraryController->getDataSetFactory()->getByName($dataSet->dataSet);
                        } catch (NotFoundException $e) {
                            $this->getLog()->debug('Existing dataset not found with name %s', $dataSet->code);
                        }
                    }
                }

                if ($existingDataSet === null) {

                    $this->getLog()->debug('Matching DataSet not found, will need to add one. useExistingDataSets = %s', $useExistingDataSets);

                    // We want to add the dataset we have as a new dataset.
                    // we will need to make sure we clear the ID's and save it
                    $existingDataSet = clone $dataSet;
                    $existingDataSet->userId = $this->getUser()->userId;
                    $existingDataSet->save();

                    // Do we need to add data
                    if ($importDataSetData) {

                        // Import the data here
                        $this->getLog()->debug('Importing data into new DataSet %d', $existingDataSet->dataSetId);

                        foreach ($item['data'] as $itemData) {
                            if (isset($itemData['id']))
                                unset($itemData['id']);

                            $existingDataSet->addRow($itemData);
                        }
                    }

                } else {

                    $this->getLog()->debug('Matching DataSet found, validating the columns');

                    // Load the existing dataset
                    $existingDataSet->load();

                    // Validate that the columns are the same
                    if (count($dataSet->columns) != count($existingDataSet->columns)) {
                        $this->getLog()->debug('Columns for Imported DataSet = %s', json_encode($dataSet->columns));
                        throw new \InvalidArgumentException(sprintf(__('DataSets have different number of columns imported = %d, existing = %d'), count($dataSet->columns), count($existingDataSet->columns)));
                    }

                    // Check the column headings
                    $diff = array_udiff($dataSet->columns, $existingDataSet->columns, function ($a, $b) {
                        /** @var DataSetColumn $a */
                        /** @var DataSetColumn $b */
                        return $a->heading == $b->heading;
                    });

                    if (count($diff) > 0)
                        throw new \InvalidArgumentException(__('DataSets have different column names'));

                    // Set the prior dataSetColumnId on each column.
                    foreach ($existingDataSet->columns as $column) {
                        // Lookup the matching column in the external dataSet definition.
                        foreach ($dataSet->columns as $externalColumn) {
                            if ($externalColumn->heading == $column->heading) {
                                $column->priorDatasetColumnId = $externalColumn->dataSetColumnId;
                                break;
                            }
                        }
                    }
                }

                // Replace instances of this dataSetId with the existing dataSetId, which will either be the existing
                // dataSet or one we've added above.
                // Also make sure we replace the columnId's with the columnId's in the new "existing" DataSet.
                foreach ($widgets as $widget) {
                    /* @var Widget $widget */
                    if ($widget->type == 'datasetview' || $widget->type == 'datasetticker' || $widget->type == 'chart') {
                        $widgetDataSetId = $widget->getOptionValue('dataSetId', 0);

                        if ($widgetDataSetId != 0 && $widgetDataSetId == $dataSetId) {
                            // Widget has a dataSet and it matches the one we've just actioned.
                            $widget->setOptionValue('dataSetId', 'attrib', $existingDataSet->dataSetId);

                            // Check for and replace column references.
                            // We are looking in the "columns" option for datasetview
                            // and the "template" option for datasetticker
                            // and the "config" option for chart
                            if ($widget->type == 'datasetview') {
                                // Get the columns option
                                $columns = explode(',', $widget->getOptionValue('columns', ''));

                                $this->getLog()->debug('Looking to replace columns from %s', json_encode($columns));

                                foreach ($existingDataSet->columns as $column) {
                                    foreach ($columns as $index => $col) {
                                        if ($col == $column->priorDatasetColumnId) {
                                            $columns[$index] = $column->dataSetColumnId;
                                        }
                                    }
                                }

                                $columns = implode(',', $columns);

                                $widget->setOptionValue('columns', 'attrib', $columns);

                                $this->getLog()->debug('Replaced columns with %s', $columns);

                            } else if ($widget->type == 'datasetticker') {
                                // Get the template option
                                $template = $widget->getOptionValue('template', '');

                                $this->getLog()->debug('Looking to replace columns from %s', $template);

                                foreach ($existingDataSet->columns as $column) {
                                    // We replace with the |%d] so that we dont experience double replacements
                                    $template = str_replace('|' . $column->priorDatasetColumnId . ']', '|' . $column->dataSetColumnId . ']', $template);
                                }

                                $widget->setOptionValue('template', 'cdata', $template);

                                $this->getLog()->debug('Replaced columns with %s', $template);
                            } else if ($widget->type == 'chart') {
                                // get the config for the chart widget
                                $oldConfig = json_decode($widget->getOptionValue('config', '[]'), true);
                                $newConfig = [];
                                $this->getLog()->debug('Looking to replace config from %s', json_encode($oldConfig));

                                // go through the chart config and our dataSet
                                foreach ($oldConfig as $config) {
                                    foreach ($existingDataSet->columns as $column) {

                                        // replace with this condition to avoid double replacements
                                        if ($config['dataSetColumnId'] == $column->priorDatasetColumnId) {

                                            // create our new config, with replaced dataSetColumnIds
                                            $newConfig[] = [
                                                'columnType' => $config['columnType'],
                                                'dataSetColumnId' => $column->dataSetColumnId
                                            ];
                                        }
                                    }
                                }

                                $this->getLog()->debug('Replaced config with %s', json_encode($newConfig));

                                // json encode our newConfig and set it as config attribute in the imported chart widget.
                                $widget->setOptionValue('config', 'attrib', json_encode($newConfig));
                            }
                        }

                        // save widgets with dataSets on Playlists, widgets directly on the layout are saved later on.
                        if (isset($playlistWidgets) && in_array($widget, $playlistWidgets)) {
                            $widget->save();
                        }
                    }
                }
            }
        }


        $this->getLog()->debug('Finished creating from Zip');

        // Finished
        $zip->close();

        // We need one final pass through all widgets on the layout so that we can set the durations properly.
        foreach ($layout->getWidgets() as $widget) {
            $module = $this->moduleFactory->createWithWidget($widget);
            $widget->calculateDuration($module, true);

            // Get global stat setting of widget to set to on/off/inherit
            $widget->setOptionValue('enableStat', 'attrib', $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));
        }

        if ($fontsAdded) {
            $this->getLog()->debug('Fonts have been added');
            $libraryController->installFonts();
        }

        return $layout;
    }

    /**
     * Create widgets in nested Playlists and handle their closure table
     *
     * @param $widgets array An array of playlist widgets with old playlistId as key
     * @param $combined array An array of key and value pairs with oldPlaylistId => newPlaylistId
     * @param $playlists array An array of Playlist objects
     * @return array An array of Playlist objects with widgets
     * @throws NotFoundException
     */
    public function createNestedPlaylistWidgets($widgets, $combined, &$playlists)
    {
        foreach ($widgets as $playlistId => $widgetsDetails ) {

            foreach ($combined as $old => $new) {
                if ($old == $playlistId) {
                    $playlistId = $new;
                }
            }

            $playlist = $this->playlistFactory->getById($playlistId);

            foreach ($widgetsDetails as $widgetsDetail) {

                $modules = $this->moduleFactory->get();
                $playlistWidget = $this->widgetFactory->createEmpty();
                $playlistWidget->playlistId = $playlistId;
                $playlistWidget->widgetId = null;
                $playlistWidget->type = $widgetsDetail['type'];
                $playlistWidget->ownerId = $playlist->ownerId;
                $playlistWidget->displayOrder = $widgetsDetail['displayOrder'];
                $playlistWidget->duration = $widgetsDetail['duration'];
                $playlistWidget->useDuration = $widgetsDetail['useDuration'];
                $playlistWidget->calculatedDuration = $widgetsDetail['calculatedDuration'];
                $playlistWidget->fromDt = $widgetsDetail['fromDt'];
                $playlistWidget->toDt = $widgetsDetail['toDt'];
                $playlistWidget->tempId = $widgetsDetail['tempId'];
                $playlistWidget->mediaIds = $widgetsDetail['mediaIds'];
                $playlistWidget->widgetOptions = [];

                $nestedSubPlaylistOptions = [];
                $nestedSubPlaylistId = [];

                foreach ($widgetsDetail['widgetOptions'] as $widgetOptionE) {
                    if ($playlistWidget->type == 'subplaylist') {

                        if ($widgetOptionE['option'] == 'subPlaylistOptions') {
                            $nestedSubPlaylistOptions = json_decode($widgetOptionE['value']);
                        }

                        if ($widgetOptionE['option'] == 'subPlaylistIds') {
                            $nestedSubPlaylistId = json_decode($widgetOptionE['value']);
                        }
                    }

                    $widgetOption = $this->widgetOptionFactory->createEmpty();
                    $widgetOption->type = $widgetOptionE['type'];
                    $widgetOption->option = $widgetOptionE['option'];
                    $widgetOption->value = $widgetOptionE['value'];

                    $playlistWidget->widgetOptions[] = $widgetOption;
                }

                $module = $modules[$playlistWidget->type];
                $subPlaylistIds = [];
                $nestedPlaylistOptionsUpdated = [];

                if ($playlistWidget->type == 'subplaylist') {
                    $oldAssignedIds = $nestedSubPlaylistId;

                    foreach ($combined as $old => $new) {
                        if (in_array($old, $oldAssignedIds)) {
                            $subPlaylistIds[] = $new;
                        }
                    }

                    $playlistWidget->setOptionValue('subPlaylistIds', 'attrib', json_encode($subPlaylistIds));

                    foreach ($subPlaylistIds as $value) {

                        foreach ($nestedSubPlaylistOptions as $playlistId => $options) {

                                foreach ($options as $optionName => $optionValue) {
                                    if ($optionName == 'subPlaylistIdSpots') {
                                        $spots = $optionValue;
                                    } elseif ($optionName == 'subPlaylistIdSpotLength') {
                                        $spotsLength = $optionValue;
                                    } elseif ($optionName == 'subPlaylistIdSpotFill') {
                                        $spotFill = $optionValue;
                                    }
                                }

                        }

                        $nestedPlaylistOptionsUpdated[$value] = [
                            'subPlaylistIdSpots' => isset($spots) ? $spots : '',
                            'subPlaylistIdSpotLength' => isset($spotsLength) ? $spotsLength : '',
                            'subPlaylistIdSpotFill' => isset($spotFill) ? $spotFill : ''
                        ];

                        $this->getStore()->insert('
                                                INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                                                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                                                  FROM lkplaylistplaylist p, lkplaylistplaylist c
                                                 WHERE p.childId = :parentId AND c.parentId = :childId
                                            ', [
                            'parentId' => $playlist->playlistId,
                            'childId' => $value
                        ]);
                    }
                    $playlistWidget->setOptionValue('subPlaylistOptions', 'attrib', json_encode($nestedPlaylistOptionsUpdated));
                }

                $playlist->assignWidget($playlistWidget);
                $playlist->requiresDurationUpdate = 1;

                // save non-media based widget, we can't save media based widgets here as we don't have updated mediaId yet.
                // double check if we have any medias assigned to a Widget, if so, we cannot save it here.
                if ($module->regionSpecific == 1 && $playlistWidget->mediaIds == []) {
                    $playlistWidget->save();
                }
            }

            $playlists[] = $playlist;
            $this->getLog()->debug('Finished creating Playlist added the following Playlist ' . json_encode($playlist));
        }

        return $playlists;
    }

    /**
     * Query for all Layouts
     * @param array $sortOrder
     * @param array $filterBy
     * @return Layout[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        if ($sortOrder === null)
            $sortOrder = ['layout'];

        $select  = "";
        $select .= "SELECT layout.layoutID, ";
        $select .= "        layout.parentId, ";
        $select .= "        layout.layout, ";
        $select .= "        layout.description, ";
        $select .= "        layout.duration, ";
        $select .= "        layout.userID, ";
        $select .= "        `user`.UserName AS owner, ";
        $select .= "        campaign.CampaignID, ";
        $select .= "        layout.status, ";
        $select .= "        layout.statusMessage, ";
        $select .= "        layout.enableStat, ";
        $select .= "        layout.width, ";
        $select .= "        layout.height, ";
        $select .= "        layout.retired, ";
        $select .= "        layout.createdDt, ";
        $select .= "        layout.modifiedDt, ";
        $select .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tags, ";
        $select .= " (SELECT GROUP_CONCAT(IFNULL(value, 'NULL')) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tagValues, ";
        $select .= "        layout.backgroundImageId, ";
        $select .= "        layout.backgroundColor, ";
        $select .= "        layout.backgroundzIndex, ";
        $select .= "        layout.schemaVersion, ";
        $select .= "        layout.publishedStatusId, ";
        $select .= "        `status`.status AS publishedStatus, ";
        $select .= "        layout.publishedDate, ";
        $select .= "        layout.autoApplyTransitions, ";

        if ($this->getSanitizer()->getInt('campaignId', $filterBy) !== null) {
            $select .= ' lkcl.displayOrder, ';
        } else {
            $select .= ' NULL as displayOrder, ';
        }

        $select .= "     (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityForGroup
                            AND objectId = campaign.CampaignID
                            AND view = 1
                        ) AS groupsWithPermissions ";
        $params['permissionEntityForGroup'] = 'Xibo\\Entity\\Campaign';

        $body  = "   FROM layout ";
        $body .= '  INNER JOIN status ON status.id = layout.publishedStatusId ';
        $body .= "  INNER JOIN `lkcampaignlayout` ";
        $body .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
        $body .= "   INNER JOIN `campaign` ";
        $body .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $body .= "       AND campaign.IsLayoutSpecific = 1";
        $body .= "   INNER JOIN `user` ON `user`.userId = `campaign`.userId ";

        if ($this->getSanitizer()->getInt('campaignId', $filterBy) !== null) {
            // Join Campaign back onto it again
            $body .= " 
                INNER JOIN `lkcampaignlayout` lkcl 
                ON lkcl.layoutid = layout.layoutid 
                    AND lkcl.CampaignID = :campaignId 
            ";
            $params['campaignId'] = $this->getSanitizer()->getInt('campaignId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lklayoutdisplaygroup`
                ON lklayoutdisplaygroup.layoutId = `layout`.layoutId
                    AND lklayoutdisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('activeDisplayGroupId', $filterBy) !== null) {
            $displayGroupIds = [];
            $displayId = null;

            // get the displayId if we were provided with display specific displayGroup in the filter
            $sql = 'SELECT display.displayId FROM display INNER JOIN lkdisplaydg ON lkdisplaydg.displayId = display.displayId INNER JOIN displaygroup ON displaygroup.displayGroupId = lkdisplaydg.displayGroupId WHERE displaygroup.displayGroupId = :displayGroupId AND displaygroup.isDisplaySpecific = 1';

            foreach ($this->getStore()->select($sql, ['displayGroupId' => $this->getSanitizer()->getInt('activeDisplayGroupId', $filterBy)]) as $row) {
                $displayId = $row['displayId'];
            }

            // if we have displayId, get all displayGroups to which the display is a member of
            if ($displayId !== null) {
                $sql = 'SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId';

                foreach ($this->getStore()->select($sql, ['displayId' => $displayId]) as $row) {
                    $displayGroupIds[] = $this->getSanitizer()->int($row['displayGroupId']);
                }
            }

            // if we are filtering by actual displayGroup, use just the displayGroupId in the param
            if ($displayGroupIds == []) {
                $displayGroupIds[] = $this->getSanitizer()->getInt('activeDisplayGroupId', $filterBy);
            }

            // get events for the selected displayGroup / Display and all displayGroups the display is member of
            $body .= '
                      INNER JOIN `lkscheduledisplaygroup` 
                        ON lkscheduledisplaygroup.displayGroupId IN ( ' . implode(',', $displayGroupIds) . ' )
                      INNER JOIN schedule 
                        ON schedule.eventId = lkscheduledisplaygroup.eventId
             ';
        }

        // MediaID
        if ($this->getSanitizer()->getInt('mediaId', 0, $filterBy) != 0) {
            $body .= ' INNER JOIN (
                SELECT DISTINCT `region`.layoutId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                    INNER JOIN `lkplaylistplaylist`
                    ON `widget`.playlistId = `lkplaylistplaylist`.childId
                    INNER JOIN `playlist`
                    ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                 WHERE `lkwidgetmedia`.mediaId = :mediaId
                ) layoutsWithMedia
                ON layoutsWithMedia.layoutId = `layout`.layoutId
            ';

            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', 0, $filterBy);
        }

        // Media Like
        if ($this->getSanitizer()->getString('mediaLike', $filterBy) !== null) {
            $body .= ' INNER JOIN (
                SELECT DISTINCT `region`.layoutId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                    INNER JOIN `lkplaylistplaylist`
                    ON `widget`.playlistId = `lkplaylistplaylist`.childId
                    INNER JOIN `playlist`
                    ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                    INNER JOIN `media` 
                    ON `lkwidgetmedia`.mediaId = `media`.mediaId
                 WHERE `media`.name LIKE :mediaLike
                ) layoutsWithMediaLike
                ON layoutsWithMediaLike.layoutId = `layout`.layoutId
            ';

            $params['mediaLike'] = '%' . $this->getSanitizer()->getString('mediaLike', $filterBy) . '%';
        }

        $body .= " WHERE 1 = 1 ";

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, 'campaign.campaignId', 'layout.userId', $filterBy);

        // Layout Like
        if ($this->getSanitizer()->getString('layout', $filterBy) != '') {
            $terms = explode(',', $this->getSanitizer()->getString('layout', $filterBy));
            $this->nameFilter('layout', 'layout', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getString('layoutExact', $filterBy) != '') {
            $body.= " AND layout.layout = :exact ";
            $params['exact'] = $this->getSanitizer()->getString('layoutExact', $filterBy);
        }

        // Layout
        if ($this->getSanitizer()->getInt('layoutId', 0, $filterBy) != 0) {
            $body .= " AND layout.layoutId = :layoutId ";
            $params['layoutId'] = $this->getSanitizer()->getInt('layoutId', 0, $filterBy);
        } else if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) != -1) {
            // Exclude templates by default
            if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) == 1) {
                $body .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template') ";
            } else {
                $body .= " AND layout.layoutID IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template') ";
            }
        }

        // Layout Draft
        if ($this->getSanitizer()->getInt('parentId', 0, $filterBy) != 0) {
            $body .= " AND layout.parentId = :parentId ";
            $params['parentId'] = $this->getSanitizer()->getInt('parentId', 0, $filterBy);
        } else if ($this->getSanitizer()->getInt('layoutId', 0, $filterBy) == 0
            && $this->getSanitizer()->getInt('showDrafts', 0, $filterBy) == 0) {
            // If we're not searching for a parentId and we're not searching for a layoutId, then don't show any
            // drafts (parentId will be empty on drafts)
            $body .= ' AND layout.parentId IS NULL ';
        }

        // Layout Published Status
        if ($this->getSanitizer()->getInt('publishedStatusId', $filterBy) !== null) {
            $body .= " AND layout.publishedStatusId = :publishedStatusId ";
            $params['publishedStatusId'] = $this->getSanitizer()->getInt('publishedStatusId', $filterBy);
        }

        // Layout Status
        if ($this->getSanitizer()->getInt('status', $filterBy) !== null) {
            $body .= " AND layout.status = :status ";
            $params['status'] = $this->getSanitizer()->getInt('status', $filterBy);
        }

        // Background Image
        if ($this->getSanitizer()->getInt('backgroundImageId', $filterBy) !== null) {
            $body .= " AND layout.backgroundImageId = :backgroundImageId ";
            $params['backgroundImageId'] = $this->getSanitizer()->getInt('backgroundImageId', 0, $filterBy);
        }

        // Not Layout
        if ($this->getSanitizer()->getInt('notLayoutId', 0, $filterBy) != 0) {
            $body .= " AND layout.layoutId <> :notLayoutId ";
            $params['notLayoutId'] = $this->getSanitizer()->getInt('notLayoutId', 0, $filterBy);
        }

        // Owner filter
        if ($this->getSanitizer()->getInt('userId', 0, $filterBy) != 0) {
            $body .= " AND layout.userid = :userId ";
            $params['userId'] = $this->getSanitizer()->getInt('userId', 0, $filterBy);
        }

        // User Group filter
        if ($this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy) != 0) {
            $body .= ' AND layout.userid IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy);
        }

        // Retired options (provide -1 to return all)
        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) != -1) {
            $body .= " AND layout.retired = :retired ";
            $params['retired'] = $this->getSanitizer()->getInt('retired', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerCampaignId', $filterBy) !== null) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :ownerCampaignId ";
            $params['ownerCampaignId'] = $this->getSanitizer()->getInt('ownerCampaignId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutHistoryId', $filterBy) !== null) {
            $body .= ' AND `campaign`.campaignId IN (
                SELECT MAX(campaignId) 
                  FROM `layouthistory` 
                 WHERE `layouthistory`.layoutId = :layoutHistoryId
                ) ';
            $params['layoutHistoryId'] = $this->getSanitizer()->getInt('layoutHistoryId', 0, $filterBy);
        }

        // Get by regionId
        if ($this->getSanitizer()->getInt('regionId', $filterBy) !== null) {
            // Join Campaign back onto it again
            $body .= " AND `layout`.layoutId IN (SELECT layoutId FROM `region` WHERE regionId = :regionId) ";
            $params['regionId'] = $this->getSanitizer()->getInt('regionId', 0, $filterBy);
        }

        // Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {

            $tagFilter = $this->getSanitizer()->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `layout`.layoutID NOT IN (
                    SELECT `lktaglayout`.layoutId
                     FROM `tag`
                        INNER JOIN `lktaglayout`
                        ON `lktaglayout`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND layout.layoutID IN (
                SELECT lktaglayout.layoutId
                  FROM tag
                    INNER JOIN lktaglayout
                    ON lktaglayout.tagId = tag.tagId
                ";

                $tags = explode(',', $tagFilter);
                $this->tagFilter($tags, $operator, $body, $params);
            }
        }

        // Show All, Used or UnUsed
        // Used - In active schedule, scheduled in the future, directly assigned to displayGroup, default Layout.
        // Unused - Every layout NOT matching the Used ie not in active schedule, not scheduled in the future, not directly assigned to any displayGroup, not default layout.
        if ($this->getSanitizer()->getInt('filterLayoutStatusId', 1, $filterBy) != 1)  {
            if ($this->getSanitizer()->getInt('filterLayoutStatusId', $filterBy) == 2) {

                // Only show used layouts
                $now = $this->getDate()->parse()->format('U');
                $sql = 'SELECT DISTINCT schedule.CampaignID FROM schedule WHERE ( ( schedule.fromDt < '. $now . ' OR schedule.fromDt = 0 ) ' . ' AND schedule.toDt > ' . $now . ') OR schedule.fromDt > ' . $now;
                $campaignIds = [];
                foreach ($this->getStore()->select($sql, []) as $row) {
                    $campaignIds[] = $row['CampaignID'];
                }
                $body .= ' AND ('
                    . '      campaign.CampaignID IN ( ' . implode(',', array_filter($campaignIds)) . ' ) 
                             OR layout.layoutID IN (SELECT DISTINCT defaultlayoutid FROM display) 
                             OR layout.layoutID IN (SELECT DISTINCT layoutId FROM lklayoutdisplaygroup)'
                    . ' ) ';
            }
            else {
                // Only show unused layouts
                $now = $this->getDate()->parse()->format('U');
                $sql = 'SELECT DISTINCT schedule.CampaignID FROM schedule WHERE ( ( schedule.fromDt < '. $now . ' OR schedule.fromDt = 0 ) ' . ' AND schedule.toDt > ' . $now . ') OR schedule.fromDt > ' . $now;
                $campaignIds = [];
                foreach ($this->getStore()->select($sql, []) as $row) {
                    $campaignIds[] = $row['CampaignID'];
                }

                $body .= ' AND campaign.CampaignID NOT IN ( ' . implode(',', array_filter($campaignIds)) . ' )  
                     AND layout.layoutID NOT IN (SELECT DISTINCT defaultlayoutid FROM display) 
                     AND layout.layoutID NOT IN (SELECT DISTINCT layoutId FROM lklayoutdisplaygroup) 
                     ';
            }
        }

        // PlaylistID
        if ($this->getSanitizer()->getInt('playlistId', 0, $filterBy) != 0) {
            $body .= ' AND layout.layoutId IN (SELECT DISTINCT `region`.layoutId
                    FROM `lkplaylistplaylist`
                      INNER JOIN `playlist`
                      ON `playlist`.playlistId = `lkplaylistplaylist`.parentId
                      INNER JOIN `region`
                      ON `region`.regionId = `playlist`.regionId
                   WHERE `lkplaylistplaylist`.childId = :playlistId )
            ';

            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', 0, $filterBy);
        }

        // publishedDate
        if ($this->getSanitizer()->getInt('havePublishDate', -1, $filterBy) != -1) {
            $body .= " AND `layout`.publishedDate IS NOT NULL ";
        }

        if ($this->getSanitizer()->getInt('activeDisplayGroupId', $filterBy) !== null) {

            $date = $this->getDate()->parse()->format('U');

            // for filter by displayGroup, we need to add some additional filters in WHERE clause to show only relevant Layouts at the time the Layout grid is viewed
            $body .= ' AND campaign.campaignId = schedule.campaignId 
                       AND ( schedule.fromDt < '. $date . ' OR schedule.fromDt = 0 ) ' . ' AND schedule.toDt > ' . $date;
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $layout = $this->createEmpty();

            // Validate each param and add it to the array.
            $layout->layoutId = $this->getSanitizer()->int($row['layoutID']);
            $layout->parentId = $this->getSanitizer()->int($row['parentId']);
            $layout->schemaVersion = $this->getSanitizer()->int($row['schemaVersion']);
            $layout->layout = $this->getSanitizer()->string($row['layout']);
            $layout->description = $this->getSanitizer()->string($row['description']);
            $layout->duration = $this->getSanitizer()->int($row['duration']);
            $layout->tags = $this->getSanitizer()->string($row['tags']);
            $layout->tagValues = $this->getSanitizer()->string($row['tagValues']);
            $layout->backgroundColor = $this->getSanitizer()->string($row['backgroundColor']);
            $layout->owner = $this->getSanitizer()->string($row['owner']);
            $layout->ownerId = $this->getSanitizer()->int($row['userID']);
            $layout->campaignId = $this->getSanitizer()->int($row['CampaignID']);
            $layout->retired = $this->getSanitizer()->int($row['retired']);
            $layout->status = $this->getSanitizer()->int($row['status']);
            $layout->backgroundImageId = $this->getSanitizer()->int($row['backgroundImageId']);
            $layout->backgroundzIndex = $this->getSanitizer()->int($row['backgroundzIndex']);
            $layout->width = $this->getSanitizer()->double($row['width']);
            $layout->height = $this->getSanitizer()->double($row['height']);
            $layout->createdDt = $row['createdDt'];
            $layout->modifiedDt = $row['modifiedDt'];
            $layout->displayOrder = $row['displayOrder'];
            $layout->statusMessage = $row['statusMessage'];
            $layout->enableStat = $this->getSanitizer()->int($row['enableStat']);
            $layout->publishedStatusId = $this->getSanitizer()->int($row['publishedStatusId']);
            $layout->publishedStatus = $this->getSanitizer()->string($row['publishedStatus']);
            $layout->publishedDate = $this->getSanitizer()->string($row['publishedDate']);
            $layout->autoApplyTransitions = $this->getSanitizer()->int($row['autoApplyTransitions']);

            $layout->groupsWithPermissions = $row['groupsWithPermissions'];
            $layout->setOriginals();

            $entries[] = $layout;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * @param \Xibo\Entity\Widget $widget
     * @return \Xibo\Entity\Widget
     */
    private function setWidgetExpiryDatesOrDefault($widget)
    {
        $minSubYear = $this->getDate()->parse($this->getDate()->getLocalDate(Widget::$DATE_MIN))->subYear()->format('U');
        $minAddYear = $this->getDate()->parse($this->getDate()->getLocalDate(Widget::$DATE_MIN))->addYear()->format('U');
        $maxSubYear = $this->getDate()->parse($this->getDate()->getLocalDate(Widget::$DATE_MAX))->subYear()->format('U');
        $maxAddYear = $this->getDate()->parse($this->getDate()->getLocalDate(Widget::$DATE_MAX))->addYear()->format('U');

        // convert the date string to a unix timestamp, if the layout xlf does not contain dates, then set it to the $DATE_MIN / $DATE_MAX which are already unix timestamps, don't attempt to convert them
        // we need to check if provided from and to dates are within $DATE_MIN +- year to avoid issues with CMS Instances in different timezones https://github.com/xibosignage/xibo/issues/1934

        if ($widget->fromDt === Widget::$DATE_MIN || ($this->getDate()->parse($widget->fromDt)->format('U') > $minSubYear && $this->getDate()->parse($widget->fromDt)->format('U') < $minAddYear)) {
            $widget->fromDt = Widget::$DATE_MIN;
        } else {
            $widget->fromDt = $this->getDate()->parse($widget->fromDt)->format('U');
        }

        if ($widget->toDt === Widget::$DATE_MAX || ($this->getDate()->parse($widget->toDt)->format('U') > $maxSubYear && $this->getDate()->parse($widget->toDt)->format('U') < $maxAddYear)) {
            $widget->toDt = Widget::$DATE_MAX;
        } else {
            $widget->toDt = $this->getDate()->parse($widget->toDt)->format('U');
        }

        return $widget;
    }

    /**
     * @param \Xibo\Entity\Playlist $newPlaylist
     * @return \Xibo\Entity\Playlist
     * @throws \Xibo\Exception\DuplicateEntityException
     */
    private function setOwnerAndSavePlaylist($newPlaylist)
    {
        // try to save with the name from import, if it already exists add "imported - "  to the name
        try {
            // The new Playlist should be owned by the importing user
            $newPlaylist->ownerId = $this->getUser()->getId();
            $newPlaylist->playlistId = null;
            $newPlaylist->widgets = [];
            $newPlaylist->save();
        } catch (DuplicateEntityException $e) {
            $newPlaylist->name = 'imported - ' . $newPlaylist->name;
            $newPlaylist->save();
        }

        return $newPlaylist;
    }
}