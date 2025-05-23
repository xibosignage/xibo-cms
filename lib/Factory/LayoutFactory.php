<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Factory;

use Carbon\Carbon;
use Stash\Invalidation;
use Stash\Pool;
use Xibo\Entity\DataSet;
use Xibo\Entity\Folder;
use Xibo\Entity\Layout;
use Xibo\Entity\Module;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class LayoutFactory
 * @package Xibo\Factory
 */
class LayoutFactory extends BaseFactory
{
    use TagTrait;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

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
     * @var ModuleTemplateFactory
     */
    private $moduleTemplateFactory;

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

    /** @var ActionFactory */
    private $actionFactory;

    /** @var FolderFactory */
    private $folderFactory;
    /**
     * @var FontFactory
     */
    private $fontFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PermissionFactory $permissionFactory
     * @param RegionFactory $regionFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     * @param MediaFactory $mediaFactory
     * @param ModuleFactory $moduleFactory
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param ResolutionFactory $resolutionFactory
     * @param WidgetFactory $widgetFactory
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param PlaylistFactory $playlistFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param ActionFactory $actionFactory
     * @param FolderFactory $folderFactory
     * @param FontFactory $fontFactory
     */
    public function __construct(
        $user,
        $userFactory,
        $config,
        $permissionFactory,
        $regionFactory,
        $tagFactory,
        $campaignFactory,
        $mediaFactory,
        $moduleFactory,
        $moduleTemplateFactory,
        $resolutionFactory,
        $widgetFactory,
        $widgetOptionFactory,
        $playlistFactory,
        $widgetAudioFactory,
        $actionFactory,
        $folderFactory,
        FontFactory $fontFactory,
        private readonly WidgetDataFactory $widgetDataFactory
    ) {
        $this->setAclDependencies($user, $userFactory);
        $this->config = $config;
        $this->permissionFactory = $permissionFactory;
        $this->regionFactory = $regionFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
        $this->mediaFactory = $mediaFactory;
        $this->moduleFactory = $moduleFactory;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->resolutionFactory = $resolutionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->actionFactory = $actionFactory;
        $this->folderFactory = $folderFactory;
        $this->fontFactory = $fontFactory;
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
            $this->getDispatcher(),
            $this->config,
            $this->permissionFactory,
            $this->regionFactory,
            $this->tagFactory,
            $this->campaignFactory,
            $this,
            $this->mediaFactory,
            $this->moduleFactory,
            $this->moduleTemplateFactory,
            $this->playlistFactory,
            $this->actionFactory,
            $this->folderFactory,
            $this->fontFactory
        );
    }

    /**
     * Create Layout from Resolution
     * @param int $resolutionId
     * @param int $ownerId
     * @param string $name
     * @param string $description
     * @param string|array $tags
     * @param string $code
     * @param bool $addRegion
     * @return Layout
     *
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function createFromResolution($resolutionId, $ownerId, $name, $description, $tags, $code, $addRegion = true)
    {
        $resolution = $this->resolutionFactory->getById($resolutionId);

        // Create a new Layout
        $layout = $this->createEmpty();
        $layout->width = $resolution->width;
        $layout->height = $resolution->height;
        $layout->orientation = ($layout->width >= $layout->height) ? 'landscape' : 'portrait';

        // Set the properties
        $layout->layout = $name;
        $layout->description = $description;
        $layout->backgroundzIndex = 0;
        $layout->backgroundColor = '#000';
        $layout->code = $code;

        // Set the owner
        $layout->setOwner($ownerId);

        // Create some tags
        if (is_array($tags)) {
            $layout->updateTagLinks($tags);
        } else {
            $layout->updateTagLinks($this->tagFactory->tagsFromString($tags));
        }

        // Add a blank, full screen region
        if ($addRegion) {
            $layout->regions[] = $this->regionFactory->create(
                'zone',
                $ownerId,
                $name . '-1',
                $layout->width,
                $layout->height,
                0,
                0
            );
        }

        return $layout;
    }

    /**
     * @param \Xibo\Entity\Layout $layout
     * @param string $type
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @return \Xibo\Entity\Layout
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function addRegion(Layout $layout, string $type, int $width, int $height, int $top, int $left): Layout
    {
        $layout->regions[] = $this->regionFactory->create(
            $type,
            $layout->ownerId,
            $layout->layout . '-' . count($layout->regions),
            $width,
            $height,
            $top,
            $left
        );

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
        if (empty($layoutId)) {
            throw new NotFoundException(__('LayoutId is 0'));
        }

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get CampaignId from layout history
     * @param int $layoutId
     * @return int campaignId
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getCampaignIdFromLayoutHistory($layoutId)
    {
        if ($layoutId == null) {
            throw new InvalidArgumentException(__('Invalid Input'), 'layoutId');
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
     * @throws NotFoundException
     */
    public function getByLayoutHistory($layoutId)
    {
        // Get a Layout by its Layout HistoryId
        $layouts = $this->query(null, array('disableUserCheck' => 1, 'layoutHistoryId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get latest layoutId by CampaignId from layout history
     * @param int campaignId
     * @return int layoutId
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getLatestLayoutIdFromLayoutHistory($campaignId)
    {
        if ($campaignId == null) {
            throw new InvalidArgumentException(__('Invalid Input'), 'campaignId');
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
        if (empty($layoutId)) {
            throw new NotFoundException();
        }

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'parentId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(__('Layout not found'));
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
            throw new NotFoundException(__('Layout not found'));
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
            'disableUserCheck' => $permissionsCheck ? 0 : 1,
            'showDrafts' => 1
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
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        if ($displayGroupId == null) {
            return [];
        }

        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get by Background Image Id
     * @param int $backgroundImageId
     * @return Layout[]
     * @throws NotFoundException
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
     * Get by Code identifier
     * @param string $code
     * @return Layout
     * @throws NotFoundException
     */
    public function getByCode($code)
    {
        $layouts = $this->query(null, ['disableUserCheck' => 1, 'code' => $code, 'excludeTemplates' => -1, 'retired' => -1]);

        if (count($layouts) <= 0) {
            throw new NotFoundException(__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * @param string $type
     * @param int $id
     * @return Layout|null
     * @throws NotFoundException
     */
    public function getLinkedFullScreenLayout(string $type, int $id): ?Layout
    {
        $layouts = null;

        if ($type === 'media') {
            $layouts = $this->query(
                null,
                [
                    'mediaId' => $id,
                    'campaignType' => $type
                ]
            );
        } else if ($type === 'playlist') {
            $layouts = $this->query(
                null,
                [
                    'playlistId' => $id,
                    'campaignType' => $type
                ]
            );
        }

        if (count($layouts) <= 0) {
            return null;
        }

        return $layouts[0];
    }

    /**
     * @param int $campaignId
     * @return int|null
     */
    public function getLinkedFullScreenMediaId(int $campaignId): ?int
    {
        $mediaId = $this->getStore()->select('SELECT `lkwidgetmedia`.mediaId
                    FROM region
                     INNER JOIN playlist
                            ON playlist.regionId = region.regionId
                     INNER JOIN lkplaylistplaylist
                            ON lkplaylistplaylist.parentId = playlist.playlistId
                     INNER JOIN widget
                            ON widget.playlistId = lkplaylistplaylist.childId
                     INNER JOIN lkwidgetmedia
                            ON widget.widgetId = lkwidgetmedia.widgetId
                     INNER JOIN `lkcampaignlayout` lkcl 
                            ON lkcl.layoutid = region.layoutid AND lkcl.CampaignID = :campaignId',
            ['campaignId' => $campaignId]
        );

        if (count($mediaId) <= 0) {
            return null;
        }

        return $mediaId[0]['mediaId'];
    }

    /**
     * @param int $campaignId
     * @return int|null
     */
    public function getLinkedFullScreenPlaylistId(int $campaignId): ?int
    {
        $playlistId = $this->getStore()->select('SELECT `lkplaylistplaylist`.childId
                    FROM region
                    INNER JOIN playlist
                        ON `playlist`.regionId = `region`.regionId
                    INNER JOIN lkplaylistplaylist
                        ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                    INNER JOIN widget
                        ON `widget`.playlistId = `lkplaylistplaylist`.childId
                    INNER JOIN lkwidgetmedia
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId        
                    INNER JOIN `lkcampaignlayout` lkcl
                            ON lkcl.layoutid = region.layoutid
                            AND lkcl.CampaignID = :campaignId',
            ['campaignId' => $campaignId]
        );

        if (count($playlistId) <= 0) {
            return null;
        }

        return $playlistId[0]['playlistId'];
    }

    /**
     * Load a layout by its XLF
     * @param string $layoutXlf
     * @param null $layout
     * @return \Xibo\Entity\Layout
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function loadByXlf($layoutXlf, $layout = null)
    {
        $this->getLog()->debug('Loading Layout by XLF');

        // New Layout
        if ($layout == null) {
            $layout = $this->createEmpty();
        }

        // Parse the XML and fill in the details for this layout
        $document = new \DOMDocument();
        if ($document->loadXML($layoutXlf) === false) {
            throw new InvalidArgumentException(__('Layout import failed, invalid xlf supplied'));
        }

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
            if ($regionOwnerId == null) {
                $regionOwnerId = $layout->ownerId;
            }

            // Create the region
            //  we only import from XLF for older layouts which only had playlist type regions.
            //  we start assuming this will be a playlist and update it later if necessary
            $region = $this->regionFactory->create(
                'playlist',
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
            if ($region->name == '') {
                $region->name = count($layout->regions) + 1;
                // make sure we have a string as the region name, otherwise sanitizer will get confused.
                $region->name = (string)$region->name;
            }
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
                if ($mediaOwnerId == null) {
                    $mediaOwnerId = $regionOwnerId;
                }
                $widget = $this->widgetFactory->createEmpty();
                $widget->type = $mediaNode->getAttribute('type');
                $widget->ownerId = $mediaOwnerId;
                $widget->duration = $mediaNode->getAttribute('duration');
                $widget->useDuration = $mediaNode->getAttribute('useDuration');
                // Additional check for importing layouts from 1.7 series, where the useDuration did not exist
                $widget->useDuration = ($widget->useDuration === '') ? 1 : $widget->useDuration;
                $widget->tempId = $mediaNode->getAttribute('fileId');
                $widget->schemaVersion = (int)$mediaNode->getAttribute('schemaVersion');
                $widgetId = $mediaNode->getAttribute('id');

                // Widget from/to dates.
                $widget->fromDt = ($mediaNode->getAttribute('fromDt') === '')
                    ? Widget::$DATE_MIN
                    : $mediaNode->getAttribute('fromDt');
                $widget->toDt = ($mediaNode->getAttribute('toDt') === '')
                    ? Widget::$DATE_MAX
                    : $mediaNode->getAttribute('toDt');

                $this->setWidgetExpiryDatesOrDefault($widget);

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
                        if ($widget->type == 'ticker'
                            && $widgetOption->option == 'sourceId'
                            && $widgetOption->value == '2'
                        ) {
                            $widget->type = 'datasetticker';
                        }
                    }
                }

                $this->getLog()->debug(sprintf(
                    'Added %d options with xPath query: %s',
                    count($widget->widgetOptions),
                    $xpathQuery
                ));

                // Check legacy types from conditions, set widget type and upgrade
                try {
                    $module = $this->prepareWidgetAndGetModule($widget);
                } catch (NotFoundException) {
                    // Skip this widget
                    $this->getLog()->info('loadByJson: ' . $widget->type . ' could not be found or resolved');
                    continue;
                }

                //
                // Get the MediaId associated with this widget (using the URI)
                //
                if ($module->regionSpecific == 0) {
                    $this->getLog()->debug('Library Widget, getting mediaId');

                    if (empty($widget->tempId)) {
                        $this->getLog()->debug(sprintf(
                            'FileId node is empty, setting tempId from uri option. Options: %s',
                            json_encode($widget->widgetOptions)
                        ));
                        $mediaId = explode('.', $widget->getOptionValue('uri', '0.*'));
                        $widget->tempId = $mediaId[0];
                    }

                    $this->getLog()->debug('Assigning mediaId %d', $widget->tempId);
                    $widget->assignMedia($widget->tempId);
                }

                //
                // Get all widget raw content
                //
                $rawNodes = $xpath->query('//region[@id="' . $region->tempId . '"]/media[@id="' . $widgetId . '"]/raw');
                foreach ($rawNodes as $rawNode) {
                    /* @var \DOMElement $rawNode */
                    // Get children
                    foreach ($rawNode->childNodes as $mediaOption) {
                        /* @var \DOMElement $mediaOption */
                        if ($mediaOption->textContent == null) {
                            continue;
                        }
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
                $rawNodes = $xpath
                    ->query('//region[@id="' . $region->tempId . '"]/media[@id="' . $widgetId . '"]/audio');
                foreach ($rawNodes as $rawNode) {
                    /* @var \DOMElement $rawNode */
                    // Get children
                    foreach ($rawNode->childNodes as $audioNode) {
                        /* @var \DOMElement $audioNode */
                        if ($audioNode->textContent == null) {
                            continue;
                        }
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

            // See if this region can be converted to a frame or zone (it is already a playlist)
            if (count($playlist->widgets) === 1) {
                $region->type = 'frame';
            } else if (count($playlist->widgets) === 0) {
                $region->type = 'zone';
            }

            // Assign Playlist to the Region
            $region->regionPlaylist = $playlist;

            // Assign the region to the Layout
            $layout->regions[] = $region;
        }

        $this->getLog()->debug(sprintf('Finished loading layout - there are %d regions.', count($layout->regions)));

        // Load any existing tags
        if (!is_array($layout->tags)) {
            $layout->tags = $this->tagFactory->tagsFromString($layout->tags);
        }

        foreach ($xpath->query('//tags/tag') as $tagNode) {
            /* @var \DOMElement $tagNode */
            if (trim($tagNode->textContent) == '') {
                continue;
            }
            $layout->tags[] = $this->tagFactory->tagFromString($tagNode->textContent);
        }

        // The parsed, finished layout
        return $layout;
    }

    /**
     * @param $layoutJson
     * @param $playlistJson
     * @param $nestedPlaylistJson
     * @param Folder $folder
     * @param null $layout
     * @param bool $importTags
     * @return array
     * @throws DuplicateEntityException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function loadByJson($layoutJson, $playlistJson, $nestedPlaylistJson, Folder $folder, $layout = null, $importTags = false): array
    {
        $this->getLog()->debug('Loading Layout by JSON');

        // New Layout
        if ($layout == null) {
            $layout = $this->createEmpty();
        }

        $playlists = [];
        $oldIds = [];
        $newIds = [];
        $widgets = [];

        $layout->schemaVersion = (int)$layoutJson['layoutDefinitions']['schemaVersion'];
        $layout->width = $layoutJson['layoutDefinitions']['width'];
        $layout->height = $layoutJson['layoutDefinitions']['height'];
        $layout->backgroundColor = $layoutJson['layoutDefinitions']['backgroundColor'];
        $layout->backgroundzIndex = (int)$layoutJson['layoutDefinitions']['backgroundzIndex'];
        $layout->actions = [];
        $layout->autoApplyTransitions = $layoutJson['layoutDefinitions']['autoApplyTransitions'] ?? 0;
        $actions = $layoutJson['layoutDefinitions']['actions'] ?? [];

        foreach ($actions as $action) {
            $newAction = $this->actionFactory->create(
                $action['triggerType'],
                $action['triggerCode'],
                $action['actionType'],
                'importLayout',
                $action['sourceId'],
                $action['target'],
                $action['targetId'],
                $action['widgetId'],
                $action['layoutCode'],
                $action['layoutId'] ?? null
            );
            $newAction->save(['validate' => false]);
        }


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

                $this->setOwnerAndSavePlaylist($newPlaylist, $folder);

                $newIds[] = $newPlaylist->playlistId;
            }

            $combined = array_combine($oldIds, $newIds);

            // this function will go through all widgets assigned to the nested Playlists, create the widgets, adjust the Ids and return an array of Playlists
            // then the Playlists array is used later on to adjust mediaIds if needed
            $playlists = $this->createNestedPlaylistWidgets($widgets, $combined, $playlists);

            $this->getLog()->debug('Finished creating nested playlists there are ' . count($playlists) . ' Playlists created');
        }

        $drawers = (array_key_exists('drawers', $layoutJson['layoutDefinitions'])) ? $layoutJson['layoutDefinitions']['drawers'] : [];

        // merge Layout Regions and Drawers into one array.
        $allRegions = array_merge($layoutJson['layoutDefinitions']['regions'], $drawers);

        // Populate Region Nodes
        foreach ($allRegions as $regionJson) {
            $this->getLog()->debug('Found Region');

            // Get the ownerId
            $regionOwnerId = $regionJson['ownerId'];
            if ($regionOwnerId == null) {
                $regionOwnerId = $layout->ownerId;
            }

            $regionIsDrawer = isset($regionJson['isDrawer']) ? (int)$regionJson['isDrawer'] : 0;
            $regionWidgets = $regionJson['regionPlaylist']['widgets'] ?? [];

            // Do we have a region type specified (i.e. is the export from v4)
            // Or determine the region type based on how many widgets we have and whether we're the drawer
            if (!empty($regionJson['type'] ?? null)) {
                $regionType = $regionJson['type'];
            } else if ($regionIsDrawer === 1) {
                $regionType = 'drawer';
            } else if (count($regionWidgets) === 1 && !$this->hasSubPlaylist($regionWidgets)) {
                $regionType = 'frame';
            } else if (count($regionWidgets) === 0) {
                $regionType = 'zone';
            } else {
                $regionType = 'playlist';
            }

            // Create the region
            $region = $this->regionFactory->create(
                $regionType,
                $regionOwnerId,
                $regionJson['name'],
                (double)$regionJson['width'],
                (double)$regionJson['height'],
                (double)$regionJson['top'],
                (double)$regionJson['left'],
                (int)$regionJson['zIndex'],
                $regionIsDrawer
            );

            // Use the regionId locally to parse the rest of the JSON
            $region->tempId = $regionJson['tempId'] ?? $regionJson['regionId'];

            // Set the region name if empty
            if ($region->name == '') {
                $region->name = count($layout->regions) + 1;
                // make sure we have a string as the region name, otherwise sanitizer will get confused.
                $region->name = (string)$region->name;
            }

            // Populate Playlists
            $playlist = $this->playlistFactory->create($region->name, $regionOwnerId);

            // interactive Actions
            $actions = $regionJson['actions'] ?? [];
            foreach ($actions as $action) {
                $newAction = $this->actionFactory->create(
                    $action['triggerType'],
                    $action['triggerCode'],
                    $action['actionType'],
                    'importRegion',
                    $action['sourceId'],
                    $action['target'],
                    $action['targetId'],
                    $action['widgetId'],
                    $action['layoutCode'],
                    $action['layoutId'] ?? null
                );
                $newAction->save(['validate' => false]);
            }

            foreach ($regionJson['regionOptions'] as $regionOption) {
                $region->setOptionValue($regionOption['option'], $regionOption['value']);
            }

            // Get all widgets
            foreach ($regionWidgets as $mediaNode) {
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
                $widget->tempWidgetId = $mediaNode['widgetId'];
                $widget->schemaVersion = isset($mediaNode['schemaVersion']) ? (int)$mediaNode['schemaVersion'] : 1;

                // Widget from/to dates.
                $widget->fromDt = ($mediaNode['fromDt'] === '') ? Widget::$DATE_MIN : $mediaNode['fromDt'];
                $widget->toDt = ($mediaNode['toDt'] === '') ? Widget::$DATE_MAX : $mediaNode['toDt'];

                $this->setWidgetExpiryDatesOrDefault($widget);

                $this->getLog()->debug('Adding Widget to object model. ' . $widget);

                // Prepare widget options
                foreach ($mediaNode['widgetOptions'] as $optionsNode) {
                    $widgetOption = $this->widgetOptionFactory->createEmpty();
                    $widgetOption->type = $optionsNode['type'];
                    $widgetOption->option = $optionsNode['option'];
                    $widgetOption->value = $optionsNode['value'];
                    $widget->widgetOptions[] = $widgetOption;
                }

                // Resolve the module
                try {
                    $module = $this->prepareWidgetAndGetModule($widget);
                } catch (NotFoundException) {
                    // Skip this widget
                    $this->getLog()->info('loadByJson: ' . $widget->type . ' could not be found or resolved');
                    continue;
                }

                //
                // Get the MediaId associated with this widget
                //
                if ($module->regionSpecific == 0) {
                    $this->getLog()->debug('Library Widget, getting mediaId');

                    $this->getLog()->debug(sprintf('Assigning mediaId %d', $widget->tempId));
                    $widget->assignMedia($widget->tempId);
                }

                // if we have any elements with mediaIds, make sure we assign them here
                if ($module->type === 'global' && !empty($mediaNode['mediaIds'])) {
                    foreach ($mediaNode['mediaIds'] as $mediaId) {
                        $this->getLog()->debug(sprintf('Assigning mediaId %d to element', $mediaId));
                        $widget->assignMedia($mediaId);
                    }
                }

                //
                // Audio
                //
                foreach ($mediaNode['audio'] as $audioNode) {
                    if ($audioNode == []) {
                        continue;
                    }

                    $widgetAudio = $this->widgetAudioFactory->createEmpty();
                    $widgetAudio->mediaId = $audioNode['mediaId'];
                    $widgetAudio->volume = $audioNode['volume'];
                    $widgetAudio->loop = $audioNode['loop'];
                    $widget->assignAudio($widgetAudio);
                }

                // Sub-Playlist widgets with Playlists
                if ($widget->type == 'subplaylist') {
                    $widgets = [];
                    $this->getLog()->debug(
                        'Layout import, creating layout Playlists from JSON, there are ' .
                        count($playlistJson) . ' Playlists to create'
                    );

                    // Get the subplaylists from widget option
                    $subPlaylistsOption = json_decode($widget->getOptionValue('subPlaylists', '[]'), true);

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
                        foreach ($subPlaylistsOption as $subPlaylistItem) {
                            if ($newPlaylist->playlistId === intval($subPlaylistItem['playlistId'])) {
                                // Store the oldId to swap permissions later
                                $oldIds[] = $newPlaylist->playlistId;

                                // Store the Widgets on the Playlist
                                $widgets[$newPlaylist->playlistId] = $newPlaylist->widgets;

                                // Save a new Playlist and capture the Id
                                $this->setOwnerAndSavePlaylist($newPlaylist, $folder);

                                $newIds[] = $newPlaylist->playlistId;
                            }
                        }
                    }

                    $combined = array_combine($oldIds, $newIds);

                    $playlists = $this->createNestedPlaylistWidgets($widgets, $combined, $playlists);
                    $updatedSubPlaylists = [];
                    foreach ($combined as $old => $new) {
                        foreach ($subPlaylistsOption as $subPlaylistItem) {
                            if (intval($subPlaylistItem['playlistId']) === $old) {
                                $subPlaylistItem['playlistId'] = $new;
                                $updatedSubPlaylists[] = $subPlaylistItem;
                            }
                        }
                    }

                    $widget->setOptionValue('subPlaylists', 'attrib', json_encode($updatedSubPlaylists));
                }

                // Add the widget to the regionPlaylist
                $playlist->assignWidget($widget);

                // interactive Actions
                $actions = $mediaNode['actions'] ?? [];
                foreach ($actions as $action) {
                    $newAction = $this->actionFactory->create(
                        $action['triggerType'],
                        $action['triggerCode'],
                        $action['actionType'],
                        'importWidget',
                        $action['sourceId'],
                        $action['target'],
                        $action['targetId'],
                        $action['widgetId'],
                        $action['layoutCode'],
                        $action['layoutId'] ?? null
                    );
                    $newAction->save(['validate' => false]);
                }
            }

            // Assign Playlist to the Region
            $region->regionPlaylist = $playlist;

            // Assign the region to the Layout
            if ($region->isDrawer === 1) {
                $layout->drawers[] = $region;
            } else {
                $layout->regions[] = $region;
            }
        }

        $this->getLog()->debug(sprintf('Finished loading layout - there are %d regions.', count($layout->regions)));

        $this->getLog()->debug(sprintf('Finished loading layout - there are %d drawer regions.', count($layout->drawers)));

        if ($importTags) {
            foreach ($layoutJson['layoutDefinitions']['tags'] as $tagNode) {
                if ($tagNode == []) {
                    continue;
                }

                $layout->assignTag($this->tagFactory->tagFromString(
                    $tagNode['tag'] . (!empty($tagNode['value']) ? '|' . $tagNode['value'] : '')
                ));
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
     * @param int $template Are we importing a layout to be used as a template?
     * @param int $replaceExisting
     * @param int $importTags
     * @param bool $useExistingDataSets
     * @param bool $importDataSetData
     * @param DataSetFactory $dataSetFactory
     * @param string $tags
     * @param MediaServiceInterface $mediaService
     * @param int $folderId
     * @param bool $isSystemTags Should we add the system tags (currently the "imported" tag)
     * @return Layout
     * @throws \FontLib\Exception\FontNotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function createFromZip(
        $zipFile,
        $layoutName,
        $userId,
        $template,
        $replaceExisting,
        $importTags,
        $useExistingDataSets,
        $importDataSetData,
        $dataSetFactory,
        $tags,
        MediaServiceInterface $mediaService,
        int $folderId,
        bool $isSystemTags = true,
    ) {
        $this->getLog()->debug(sprintf(
            'Create Layout from ZIP File: %s, imported name will be %s.',
            $zipFile,
            $layoutName
        ));

        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        $libraryLocationTemp = $libraryLocation . 'temp/';

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile)) {
            throw new InvalidArgumentException(__('File does not exist'));
        }

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile)) {
            throw new InvalidArgumentException(__('Unable to open ZIP'));
        }

        // Get the layout details
        $layoutJson = $zip->getFromName('layout.json');
        if (!$layoutJson) {
            throw new InvalidArgumentException(__('Unable to read layout details from ZIP'));
        }

        $layoutDetails = json_decode($layoutJson, true);

        // Get the Playlist details
        $playlistDetails = $zip->getFromName('playlist.json');
        $nestedPlaylistDetails = $zip->getFromName('nestedPlaylist.json');
        $folder = $this->folderFactory->getById($folderId);

        // it is no longer possible to re-create a Layout just from xlf
        // as such if layoutDefinitions are missing, we need to throw an error here.
        if (array_key_exists('layoutDefinitions', $layoutDetails)) {
            // Construct the Layout
            if ($playlistDetails !== false) {
                $playlistDetails = json_decode(($playlistDetails), true);
            } else {
                $playlistDetails = [];
            }

            if ($nestedPlaylistDetails !== false) {
                $nestedPlaylistDetails = json_decode($nestedPlaylistDetails, true);
            } else {
                $nestedPlaylistDetails = [];
            }

            $jsonResults = $this->loadByJson(
                $layoutDetails,
                $playlistDetails,
                $nestedPlaylistDetails,
                $folder,
                null,
                $importTags
            );
            /** @var Layout $layout */
            $layout = $jsonResults[0];
            /** @var Playlist[] $playlists */
            $playlists = $jsonResults[1];

            if (array_key_exists('code', $layoutDetails['layoutDefinitions'])) {
                // Layout code, remove it if Layout with the same code already exists in the CMS,
                // otherwise import would fail.
                // if the code does not exist, then persist it on import.
                try {
                    $this->getByCode($layoutDetails['layoutDefinitions']['code']);
                    $layout->code = null;
                } catch (NotFoundException $exception) {
                    $layout->code = $layoutDetails['layoutDefinitions']['code'];
                }
            }
        } else {
            throw new InvalidArgumentException(
                __('Unsupported format. Missing Layout definitions from layout.json file in the archive.')
            );
        }

        $this->getLog()->debug('Layout Loaded: ' . $layout);

        // Ensure width and height are integer type for resolution validation purpose xibosignage/xibo#1648
        $layout->width = (int)$layout->width;
        $layout->height = (int)$layout->height;

        // Override the name/description
        $layout->layout = (($layoutName != '') ? $layoutName : $layoutDetails['layout']);
        $layout->description = $layoutDetails['description'] ?? '';

        // Get global stat setting of layout to on/off proof of play statistics
        $layout->enableStat = $this->config->getSetting('LAYOUT_STATS_ENABLED_DEFAULT');

        $this->getLog()->debug('Layout Loaded: ' . $layout);

        // Check that the resolution we have in this layout exists, and if not create it.
        try {
            if ($layout->schemaVersion < 2) {
                $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
            } else {
                $this->resolutionFactory->getByDimensions($layout->width, $layout->height);
            }
        } catch (NotFoundException $notFoundException) {
            $this->getLog()->info('Import is for an unknown resolution, we will create it with name: '
                . $layout->width . ' x ' . $layout->height);

            $resolution = $this->resolutionFactory->create(
                $layout->width . ' x ' . $layout->height,
                (int)$layout->width,
                (int)$layout->height
            );
            $resolution->userId = $userId;
            $resolution->save();
        }

        // Update region names
        if (isset($layoutDetails['regions']) && count($layoutDetails['regions']) > 0) {
            $this->getLog()->debug('Updating region names according to layout.json');
            foreach ($layout->regions as $region) {
                if (array_key_exists($region->tempId, $layoutDetails['regions'])
                    && !empty($layoutDetails['regions'][$region->tempId])
                ) {
                    $region->name = $layoutDetails['regions'][$region->tempId];
                    $region->regionPlaylist->name = $layoutDetails['regions'][$region->tempId];
                }
            }
        }

        // Update drawer region names
        if (isset($layoutDetails['drawers']) && count($layoutDetails['drawers']) > 0) {
            $this->getLog()->debug('Updating drawer region names according to layout.json');
            foreach ($layout->drawers as $drawer) {
                if (array_key_exists($drawer->tempId, $layoutDetails['drawers'])
                    && !empty($layoutDetails['drawers'][$drawer->tempId])
                ) {
                    $drawer->name = $layoutDetails['drawers'][$drawer->tempId];
                    $drawer->regionPlaylist->name = $layoutDetails['drawers'][$drawer->tempId];
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
            $layout->assignTag($this->tagFactory->tagFromString('template'));
        }

        // Add system tags?
        if ($isSystemTags) {
            // Tag as imported
            $layout->assignTag($this->tagFactory->tagFromString('imported'));
        }

        // Tag from the upload form
        $tagsFromForm = (($tags != '') ? $this->tagFactory->tagsFromString($tags) : []);
        foreach ($tagsFromForm as $tagFromForm) {
            $layout->assignTag($tagFromForm);
        }

        // Set the owner
        $layout->setOwner($userId, true);

        // Track if we've added any fonts
        $fontsAdded = false;

        $widgets = $layout->getAllWidgets();
        $this->getLog()->debug('Layout has ' . count($widgets) . ' widgets');
        $this->getLog()->debug('Process mapping.json file.');

        // Go through each region and add the media (updating the media ids)
        $mappings = json_decode($zip->getFromName('mapping.json'), true);
        $oldMediaIds = [];
        $newMediaIds = [];
        foreach ($mappings as $file) {
            // Import the Media File
            $intendedMediaName = $file['name'];

            // Validate the file name
            $fileName = basename($file['file']);
            if (empty($fileName) || $fileName == '.' || $fileName == '..') {
                $this->getLog()->error('Skipping file on import due to invalid filename. ' . $fileName);
                continue;
            }

            $temporaryFileName = $libraryLocationTemp . $fileName;

            // Get the file from the ZIP
            $fileStream = $zip->getStream('library/' . $fileName);

            if ($fileStream === false) {
                // Log out the entire ZIP file and all entries.
                $log = 'Problem getting library/' . $fileName . '. Files: ';
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $log .= $zip->getNameIndex($i) . ', ';
                }

                $this->getLog()->error($log);

                throw new InvalidArgumentException(__('Empty file in ZIP'));
            }

            // Open a file pointer to stream into
            $temporaryFileStream = fopen($temporaryFileName, 'w');
            if (!$temporaryFileStream) {
                throw new InvalidArgumentException(__('Cannot save media file from ZIP file'), 'temp');
            }

            // Loop over the file and write into the stream
            while (!feof($fileStream)) {
                fwrite($temporaryFileStream, fread($fileStream, 8192));
            }

            fclose($fileStream);
            fclose($temporaryFileStream);

            // Check if it's a font file
            $isFont = (isset($file['font']) && $file['font'] == 1);

            if ($isFont) {
                try {
                    $font = $this->fontFactory->getByName($intendedMediaName);
                    if (count($font) <= 0) {
                        throw new NotFoundException();
                    }
                    $this->getLog()->debug('Font already exists with name: ' . $intendedMediaName);
                } catch (NotFoundException) {
                    $this->getLog()->debug('Font does not exist in Library, add it ' . $fileName);
                    // Add the Font
                    $font = $this->fontFactory->createFontFromUpload(
                        $temporaryFileName,
                        $file['name'],
                        $fileName,
                        $this->getUser()->userName,
                    );
                    $font->save();
                    $fontsAdded = true;

                    // everything is fine, move the file from temp folder.
                    rename($temporaryFileName, $libraryLocation . 'fonts/' . $font->fileName);
                }

                // Fonts do not create media records, so we have nothing left to do in the rest of this loop
                continue;
            } else {
                try {
                    $media = $this->mediaFactory->getByName($intendedMediaName);

                    $this->getLog()->debug('Media already exists with name: ' . $intendedMediaName);

                    if ($replaceExisting) {
                        // Media with this name already exists, but we don't want to use it.
                        $intendedMediaName = 'import_' . $layout->layout . '_' . uniqid();
                        throw new NotFoundException();
                    }
                } catch (NotFoundException $e) {
                    // Create it instead
                    $this->getLog()->debug('Media does not exist in Library, add it ' . $fileName);

                    $media = $this->mediaFactory->create(
                        $intendedMediaName,
                        $fileName,
                        $file['type'],
                        $userId,
                        $file['duration']
                    );

                    if ($importTags && isset($file['tags'])) {
                        foreach ($file['tags'] as $tagNode) {
                            if ($tagNode == []) {
                                continue;
                            }

                            $media->assignTag($this->tagFactory->tagFromString(
                                $tagNode['tag'] . (!empty($tagNode['value']) ? '|' . $tagNode['value'] : '')
                            ));
                        }
                    }

                    $media->assignTag($this->tagFactory->tagFromString('imported'));
                    $media->folderId = $folder->id;
                    $media->permissionsFolderId =
                        ($folder->permissionsFolderId == null) ? $folder->id : $folder->permissionsFolderId;
                    // Get global stat setting of media to set to on/off/inherit
                    $media->enableStat = $this->config->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
                    $media->save();
                }
            }

            // Find where this is used and swap for the real mediaId
            $oldMediaId = $file['mediaid'];
            $newMediaId = $media->mediaId;
            $oldMediaIds[] = $oldMediaId;
            $newMediaIds[] = $newMediaId;

            if ($file['background'] == 1) {
                // Set the background image on the new layout
                $layout->backgroundImageId = $newMediaId;
            } else {
                // Go through all widgets and replace if necessary
                // Keep the keys the same? Doesn't matter
                foreach ($widgets as $widget) {
                    $audioIds = $widget->getAudioIds();

                    $this->getLog()->debug(sprintf(
                        'Checking Widget for the old mediaID [%d] so we can replace it with the new mediaId '
                            . '[%d] and storedAs [%s]. Media assigned to widget %s.',
                        $oldMediaId,
                        $newMediaId,
                        $media->storedAs,
                        json_encode($widget->mediaIds)
                    ));

                    if (in_array($oldMediaId, $widget->mediaIds)) {
                        $this->getLog()->debug(sprintf('Removing %d and replacing with %d', $oldMediaId, $newMediaId));

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

                    // change mediaId references in applicable widgets, outside the if condition,
                    // because if the Layout is loadByXLF we will not have mediaIds set on Widget at this point
                    // the mediaIds array for Widgets with Library references will be correctly populated on
                    // getResource call from Player/CMS.
                    // if the Layout was loadByJson then it will already have correct mediaIds array at this point.
                    $this->handleWidgetMediaIdReferences($widget, $newMediaId, $oldMediaId);
                }
            }
        }
        $uploadedMediaIds = array_combine($oldMediaIds, $newMediaIds);

        foreach ($widgets as $widget) {
            // handle importing elements with image.
            // if we have multiple images in global widget
            // we need to go through them here and replace all old media with new ones
            // this cannot be done one by one in the loop when uploading from mapping
            // as one widget can have multiple elements with mediaId in it.
            if ($widget->type === 'global' && !empty($widget->getOptionValue('elements', []))) {
                $widgetElements = $widget->getOptionValue('elements', null);
                $widgetElements = json_decode($widgetElements, true);
                $updatedWidgetElements = [];
                $updatedElements = [];
                foreach (($widgetElements ?? []) as $widgetElement) {
                    foreach (($widgetElement['elements'] ?? []) as $element) {
                        if (isset($element['mediaId'])) {
                            foreach ($uploadedMediaIds as $old => $new) {
                                if ($element['mediaId'] === $old) {
                                    $element['mediaId'] = $new;
                                }
                            }
                        }
                        // if we have combo of say text element and image
                        // make sure we have the element updated here (outside the if condition),
                        // otherwise we would end up only with image elements in the options.
                        $updatedElements[] = $element;
                    }
                }

                if (!empty($updatedElements)) {
                    $updatedWidgetElements[]['elements'] = $updatedElements;
                    $widget->setOptionValue(
                        'elements',
                        'raw',
                        json_encode($updatedWidgetElements)
                    );
                }
            }
        }

        // Playlists with media widgets
        // We will iterate through all Playlists we've created during layout import here and
        // replace any mediaIds if needed
        if (isset($playlists) && $playlistDetails !== false) {
            foreach ($playlists as $playlist) {
                foreach ($playlist->widgets as $widget) {
                    $audioIds = $widget->getAudioIds();

                    foreach ($widget->mediaIds as $mediaId) {
                        foreach ($uploadedMediaIds as $old => $new) {
                            if ($mediaId == $old) {
                                $this->getLog()->debug(sprintf(
                                    'Playlist import Removing %d and replacing with %d',
                                    $old,
                                    $new
                                ));

                                // Are we an audio record?
                                if (in_array($old, $audioIds)) {
                                    // Swap the mediaId on the audio record
                                    foreach ($widget->audio as $widgetAudio) {
                                        if ($widgetAudio->mediaId == $old) {
                                            $widgetAudio->mediaId = $new;
                                            break;
                                        }
                                    }
                                } else {
                                    $addedMedia = $this->mediaFactory->getById($new);
                                    // Non audio
                                    $widget->setOptionValue('uri', 'attrib', $addedMedia->storedAs);
                                }

                                // Always manage the assignments
                                // Unassign the old ID
                                $widget->unassignMedia($old);

                                // Assign the new ID
                                $widget->assignMedia($new);

                                // change mediaId references in applicable widgets in all Playlists we have created
                                // on this import.
                                $this->handleWidgetMediaIdReferences($widget, $new, $old);
                            }
                        }
                    }
                    $widget->save();

                    if (!in_array($widget, $playlist->widgets)) {
                        $playlist->assignWidget($widget);
                        $playlist->requiresDurationUpdate = 1;
                        $playlist->save();
                    }

                    // add Playlist widgets to the $widgets (which already has all widgets from layout regionPlaylists)
                    // this will be needed if any Playlist has widgets with dataSets
                    if ($widget->type == 'datasetview'
                        || $widget->type == 'datasetticker'
                        || $widget->type == 'chart'
                    ) {
                        $widgets[] = $widget;
                        $playlistWidgets[] = $widget;
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
                $dataSet = $dataSetFactory->createEmpty()->hydrate($item);
                $dataSet->columns = [];
                $dataSetId = $dataSet->dataSetId;
                $columnWithImages = [];
                // We must null the ID so that we don't try to load the dataset when we assign columns
                $dataSet->dataSetId = null;

                // Hydrate the columns
                foreach ($item['columns'] as $columnItem) {
                    $this->getLog()->debug(sprintf('Assigning column: %s', json_encode($columnItem)));
                    if ($columnItem['dataTypeId'] === 5) {
                        $columnWithImages[] = $columnItem['heading'];
                    }
                    $dataSet->assignColumn($dataSetFactory
                        ->getDataSetColumnFactory()
                        ->createEmpty()
                        ->hydrate($columnItem));
                }

                /** @var DataSet $existingDataSet */
                $existingDataSet = null;

                // Do we want to try and use a dataset that already exists?
                if ($useExistingDataSets) {
                    // Check to see if we already have a dataset with the same code/name, prefer code.
                    if ($dataSet->code != '') {
                        try {
                            // try and get by code
                            $existingDataSet = $dataSetFactory->getByCode($dataSet->code);
                        } catch (NotFoundException $e) {
                            $this->getLog()->debug(sprintf('Existing dataset not found with code %s', $dataSet->code));
                        }
                    }

                    if ($existingDataSet === null) {
                        // try by name
                        try {
                            $existingDataSet = $dataSetFactory->getByName($dataSet->dataSet);
                        } catch (NotFoundException $e) {
                            $this->getLog()->debug(sprintf('Existing dataset not found with name %s', $dataSet->code));
                        }
                    }
                }

                if ($existingDataSet === null) {
                    $this->getLog()->debug(sprintf(
                        'Matching DataSet not found, will need to add one. useExistingDataSets = %s',
                        $useExistingDataSets
                    ));

                    // We want to add the dataset we have as a new dataset.
                    // we will need to make sure we clear the ID's and save it
                    $existingDataSet = clone $dataSet;
                    $existingDataSet->userId = $this->getUser()->userId;
                    $existingDataSet->folderId = $folder->id;
                    $existingDataSet->permissionsFolderId =
                        ($folder->permissionsFolderId == null) ? $folder->id : $folder->permissionsFolderId;

                    // Save to get the IDs created
                    $existingDataSet->save([
                        'activate' => false,
                        'notify' => false,
                        'testFormulas' => false,
                        'allowSpacesInHeading' => true,
                    ]);

                    // Do we need to add data
                    if ($importDataSetData) {
                        // Import the data here
                        $this->getLog()->debug(sprintf(
                            'Importing data into new DataSet %d',
                            $existingDataSet->dataSetId
                        ));

                        foreach (($item['data'] ?? []) as $itemData) {
                            if (isset($itemData['id'])) {
                                unset($itemData['id']);
                            }

                            foreach ($columnWithImages as $columnHeading) {
                                foreach ($uploadedMediaIds as $old => $new) {
                                    if ($itemData[$columnHeading] == $old) {
                                        $itemData[$columnHeading] = $new;
                                    }
                                }
                            }

                            $existingDataSet->addRow($itemData);
                        }
                    }
                } else {
                    $this->getLog()->debug('Matching DataSet found, validating the columns');

                    // Load the existing dataset
                    $existingDataSet->load();

                    // Validate that the columns are the same
                    if (count($dataSet->columns) != count($existingDataSet->columns)) {
                        $this->getLog()->debug(sprintf(
                            'Columns for Imported DataSet = %s',
                            json_encode($dataSet->columns)
                        ));
                        throw new InvalidArgumentException(sprintf(
                            __('DataSets have different number of columns imported = %d, existing = %d'),
                            count($dataSet->columns),
                            count($existingDataSet->columns)
                        ));
                    }

                    // Loop over the desired column headings and the ones in the existing dataset and error out
                    // as soon as we have one that isn't found.
                    foreach ($dataSet->columns as $column) {
                        // Loop through until we find it
                        foreach ($existingDataSet->columns as $existingDataSetColumn) {
                            if ($column->heading === $existingDataSetColumn->heading) {
                                // Drop out to the next column we want to find.
                                continue 2;
                            }
                        }
                        // We have not found that column in our existing data set
                        throw new InvalidArgumentException(__('DataSets have different column names'));
                    }
                }

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

                // Replace instances of this dataSetId with the existing dataSetId, which will either be the existing
                // dataSet or one we've added above.
                // Also make sure we replace the columnId's with the columnId's in the new "existing" DataSet.
                foreach ($widgets as $widget) {
                    if ($widget->type == 'dataset') {
                        $widgetDataSetId = $widget->getOptionValue('dataSetId', 0);

                        if ($widgetDataSetId != 0 && $widgetDataSetId == $dataSetId) {
                            // Widget has a dataSet, and it matches the one we've just actioned.
                            $widget->setOptionValue('dataSetId', 'attrib', $existingDataSet->dataSetId);

                            // Check for and replace column references.
                            // We are looking in the "columns" option for datasetview
                            // and the "template" option for datasetticker
                            // DataSetView (now just dataset)
                            $existingColumns = $widget->getOptionValue('columns', '');
                            if (!empty($existingColumns)) {
                                // Get the columns option
                                $columns = json_decode($existingColumns, true);

                                $this->getLog()->debug(sprintf(
                                    'Looking to replace columns from %s',
                                    $existingColumns
                                ));

                                foreach ($existingDataSet->columns as $column) {
                                    foreach ($columns as $index => $col) {
                                        if ($col == $column->priorDatasetColumnId) {
                                            // v4 uses integers as its column ids.
                                            $columns[$index] = $column->dataSetColumnId;
                                        }
                                    }
                                }

                                $columns = json_encode($columns);
                                $widget->setOptionValue('columns', 'attrib', $columns);

                                $this->getLog()->debug(sprintf('Replaced columns with %s', $columns));
                            }

                            // DataSetTicker (now just dataset)
                            $template = $widget->getOptionValue('template', '');
                            if (!empty($template)) {
                                $this->getLog()->debug(sprintf('Looking to replace columns from %s', $template));

                                foreach ($existingDataSet->columns as $column) {
                                    // We replace with the |%d] so that we don't experience double replacements
                                    $template = str_replace(
                                        '|' . $column->priorDatasetColumnId . ']',
                                        '|' . $column->dataSetColumnId . ']',
                                        $template
                                    );
                                }

                                $widget->setOptionValue('template', 'cdata', $template);

                                $this->getLog()->debug(sprintf('Replaced columns with %s', $template));
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

        // Load widget data into an array for processing outside (once the layout has been saved)
        $fallback = $zip->getFromName('fallback.json');
        if ($fallback !== false) {
            $layout->setUnmatchedProperty('fallback', json_decode($fallback, true));
        }

        // Save the thumbnail to a temporary location.
        $image_path = $zip->getFromName('library/thumbs/campaign_thumb.png');
        if ($image_path !== false) {
            $temporaryLayoutThumb = $libraryLocationTemp . $layout->layout . '-campaign_thumb.png';
            $layout->setUnmatchedProperty('thumbnail', $temporaryLayoutThumb);
            $image = imagecreatefromstring($image_path);
            imagepng($image, $temporaryLayoutThumb);
        }

        $this->getLog()->debug('Finished creating from Zip');

        // Finished
        $zip->close();

        // We need one final pass through all widgets on the layout so that we can set the durations properly.
        foreach ($layout->getAllWidgets() as $widget) {
            // By now we should not have any modules which don't exist.
            $module = $this->moduleFactory->getByType($widget->type);
            $widget->calculateDuration($module);

            // Get global stat setting of widget to set to on/off/inherit
            $widget->setOptionValue('enableStat', 'attrib', $this->config->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));
        }

        if ($fontsAdded) {
            $this->getLog()->debug('Fonts have been added');
            $mediaService->setUser($this->getUser())->updateFontsCss();
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
        foreach ($widgets as $playlistId => $widgetsDetails) {
            foreach ($combined as $old => $new) {
                if ($old == $playlistId) {
                    $playlistId = $new;
                }
            }

            $playlist = $this->playlistFactory->getById($playlistId);

            foreach ($widgetsDetails as $widgetsDetail) {
                $modules = $this->moduleFactory->getKeyedArrayOfModules();
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
                $playlistWidget->schemaVersion = isset($widgetsDetail['schemaVersion'])
                    ? (int)$widgetsDetail['schemaVersion']
                    : 1;

                // Prepare widget options
                foreach ($widgetsDetail['widgetOptions'] as $optionsNode) {
                    $widgetOption = $this->widgetOptionFactory->createEmpty();
                    $widgetOption->type = $optionsNode['type'];
                    $widgetOption->option = $optionsNode['option'];
                    $widgetOption->value = $optionsNode['value'];
                    $playlistWidget->widgetOptions[] = $widgetOption;
                }

                try {
                    $module = $this->prepareWidgetAndGetModule($playlistWidget);
                } catch (NotFoundException) {
                    // Skip this widget
                    $this->getLog()->info('createNestedPlaylistWidgets: ' . $playlistWidget->type
                        . ' could not be found or resolved');
                    continue;
                }

                if ($playlistWidget->type == 'subplaylist') {
                    // Get the subplaylists from widget option
                    $nestedSubPlaylists = json_decode($playlistWidget->getOptionValue('subPlaylists', '[]'), true);

                    $updatedSubPlaylists = [];
                    foreach ($combined as $old => $new) {
                        foreach ($nestedSubPlaylists as $subPlaylistItem) {
                            if (intval($subPlaylistItem['playlistId']) === $old) {
                                $subPlaylistItem['playlistId'] = $new;
                                $updatedSubPlaylists[] = $subPlaylistItem;
                            }
                        }
                    }

                    foreach ($updatedSubPlaylists as $updatedSubPlaylistItem) {
                        $this->getStore()->insert('
                            INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                            SELECT p.parentId, c.childId, p.depth + c.depth + 1
                              FROM lkplaylistplaylist p, lkplaylistplaylist c
                             WHERE p.childId = :parentId AND c.parentId = :childId
                        ', [
                            'parentId' => $playlist->playlistId,
                            'childId' => $updatedSubPlaylistItem['playlistId']
                        ]);
                    }

                    $playlistWidget->setOptionValue('subPlaylists', 'attrib', json_encode($updatedSubPlaylists));
                }

                $playlist->assignWidget($playlistWidget);
                $playlist->requiresDurationUpdate = 1;

                // save non-media based widget, we can't save media based widgets here as we don't have updated mediaId yet.
                if ($module->regionSpecific == 1 && $playlistWidget->mediaIds == []) {
                    $playlistWidget->save();
                }
            }

            $playlists[] = $playlist;
            $this->getLog()->debug('Finished creating Playlist added the following Playlist ' . json_encode($playlist));
        }

        return $playlists;
    }

    public function hasSubPlaylist(array $widgets)
    {
        $hasSubPlaylist = false;

        foreach ($widgets as $widget) {
            if ($widget['type'] === 'subplaylist') {
                $hasSubPlaylist = true;
            }
        }

        return $hasSubPlaylist;
    }

    /**
     * Get all Codes assigned to Layouts
     * @param array $filterBy
     * @return array
     */
    public function getLayoutCodes($filterBy = []): array
    {
        $parsedFilter = $this->getSanitizer($filterBy);
        $params = [];
        $select = 'SELECT DISTINCT code, `layout`.layout, `campaign`.CampaignID, `campaign`.permissionsFolderId ';
        $body = ' FROM layout INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.LayoutID = layout.LayoutID INNER JOIN `campaign` ON lkcampaignlayout.CampaignID = campaign.CampaignID AND campaign.IsLayoutSpecific = 1 WHERE `layout`.code IS NOT NULL AND `layout`.code <> \'\' ';

        // get by Code
        if ($parsedFilter->getString('code') != '') {
            $body.= ' AND layout.code LIKE :code ';
            $params['code'] = '%' . $parsedFilter->getString('code') . '%';
        }

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, 'campaign.campaignId', 'layout.userId', $filterBy, 'campaign.permissionsFolderId');

        $order = ' ORDER BY code';

        // Paging
        $limit = '';
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $entries = $this->getStore()->select($sql, $params);

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
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
        $parsedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        if ($sortOrder === null) {
            $sortOrder = ['layout'];
        }

        $select  = 'SELECT `layout`.layoutID, 
                        `layout`.parentId,
                        `layout`.layout,
                        `layout`.description,
                        `layout`.duration,
                        `layout`.userID,
                        `user`.userName as owner,
                        `campaign`.CampaignID,
                        `campaign`.type,
                        `layout`.status,
                        `layout`.statusMessage,
                        `layout`.enableStat,
                        `layout`.width,
                        `layout`.height,
                        `layout`.retired,
                        `layout`.createdDt,
                        `layout`.modifiedDt,
                        `layout`.backgroundImageId,
                        `layout`.backgroundColor,
                        `layout`.backgroundzIndex,
                        `layout`.schemaVersion,
                        `layout`.publishedStatusId,
                        `status`.status AS publishedStatus,
                        `layout`.publishedDate,
                        `layout`.autoApplyTransitions,
                        `layout`.code,
                        `campaign`.folderId,
                        `campaign`.permissionsFolderId,
                   ';

        if ($parsedFilter->getInt('campaignId') !== null) {
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

        if ($parsedFilter->getInt('campaignId') !== null) {
            // Join Campaign back onto it again
            $body .= " 
                INNER JOIN `lkcampaignlayout` lkcl 
                ON lkcl.layoutid = layout.layoutid 
                    AND lkcl.CampaignID = :campaignId 
            ";
            $params['campaignId'] = $parsedFilter->getInt('campaignId');
        }

        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= '
                INNER JOIN `lklayoutdisplaygroup`
                ON lklayoutdisplaygroup.layoutId = `layout`.layoutId
                    AND lklayoutdisplaygroup.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $parsedFilter->getInt('displayGroupId');
        }

        if ($parsedFilter->getInt('activeDisplayGroupId') !== null) {
            $displayGroupIds = [];
            $displayId = null;

            // get the displayId if we were provided with display specific displayGroup in the filter
            $sql = 'SELECT display.displayId FROM display INNER JOIN lkdisplaydg ON lkdisplaydg.displayId = display.displayId INNER JOIN displaygroup ON displaygroup.displayGroupId = lkdisplaydg.displayGroupId WHERE displaygroup.displayGroupId = :displayGroupId AND displaygroup.isDisplaySpecific = 1';

            foreach ($this->getStore()->select($sql, ['displayGroupId' => $parsedFilter->getInt('activeDisplayGroupId')]) as $row) {
                $displayId = $this->getSanitizer($row)->getInt('displayId');
            }

            // if we have displayId, get all displayGroups to which the display is a member of
            if ($displayId !== null) {
                $sql = 'SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId';

                foreach ($this->getStore()->select($sql, ['displayId' => $displayId]) as $row) {
                    $displayGroupIds[] = $this->getSanitizer($row)->getInt('displayGroupId');
                }
            }

            // if we are filtering by actual displayGroup, use just the displayGroupId in the param
            if ($displayGroupIds == []) {
                $displayGroupIds[] = $parsedFilter->getInt('activeDisplayGroupId');
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
        if ($parsedFilter->getInt('mediaId', ['default' => 0]) != 0) {
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

            $params['mediaId'] = $parsedFilter->getInt('mediaId', ['default' => 0]);
        }

        // Media Like
        if (!empty($parsedFilter->getString('mediaLike'))) {
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

            $params['mediaLike'] = '%' . $parsedFilter->getString('mediaLike') . '%';
        }

        $body .= " WHERE 1 = 1 ";

        // Layout Like
        if ($parsedFilter->getString('layout') != '') {
            $terms = explode(',', $parsedFilter->getString('layout'));
            $logicalOperator = $parsedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'layout',
                'layout',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($parsedFilter->getString('layoutExact') != '') {
            $body.= " AND layout.layout = :exact ";
            $params['exact'] = $parsedFilter->getString('layoutExact');
        }

        // Layout
        if ($parsedFilter->getInt('layoutId', ['default' => 0]) != 0) {
            $body .= " AND layout.layoutId = :layoutId ";
            $params['layoutId'] = $parsedFilter->getInt('layoutId', ['default' => 0]);
        } else if ($parsedFilter->getInt('excludeTemplates', ['default' => 1]) != -1) {
            // Exclude templates by default
            if ($parsedFilter->getInt('excludeTemplates', ['default' => 1]) == 1) {
                $body .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template') ";
            } else {
                $body .= " AND layout.layoutID IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template') ";
            }
        }

        // Layout Draft
        if ($parsedFilter->getInt('parentId', ['default' => 0]) != 0) {
            $body .= " AND layout.parentId = :parentId ";
            $params['parentId'] = $parsedFilter->getInt('parentId', ['default' => 0]);
        } else if ($parsedFilter->getInt('layoutId', ['default' => 0]) == 0
            && $parsedFilter->getInt('showDrafts', ['default' => 0]) == 0) {
            // If we're not searching for a parentId and we're not searching for a layoutId, then don't show any
            // drafts (parentId will be empty on drafts)
            $body .= ' AND layout.parentId IS NULL ';
        }

        // Layout Published Status
        if ($parsedFilter->getInt('publishedStatusId') !== null) {
            $body .= " AND layout.publishedStatusId = :publishedStatusId ";
            $params['publishedStatusId'] = $parsedFilter->getInt('publishedStatusId');
        }

        // Layout Status
        if ($parsedFilter->getInt('status') !== null) {
            $body .= " AND layout.status = :status ";
            $params['status'] = $parsedFilter->getInt('status');
        }

        // Background Image
        if ($parsedFilter->getInt('backgroundImageId') !== null) {
            $body .= " AND layout.backgroundImageId = :backgroundImageId ";
            $params['backgroundImageId'] = $parsedFilter->getInt('backgroundImageId', ['default' => 0]);
        }
        // Not Layout
        if ($parsedFilter->getInt('notLayoutId', ['default' => 0]) != 0) {
            $body .= " AND layout.layoutId <> :notLayoutId ";
            $params['notLayoutId'] = $parsedFilter->getInt('notLayoutId', ['default' => 0]);
        }

        // Owner filter
        if ($parsedFilter->getInt('userId', ['default' => 0]) != 0) {
            $body .= " AND layout.userid = :userId ";
            $params['userId'] = $parsedFilter->getInt('userId', ['default' => 0]);
        }

        if ($parsedFilter->getCheckbox('onlyMyLayouts') === 1) {
            $body .= ' AND layout.userid = :userId ';
            $params['userId'] = $this->getUser()->userId;
        }

        // User Group filter
        if ($parsedFilter->getInt('ownerUserGroupId', ['default' => 0]) != 0) {
            $body .= ' AND layout.userid IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $parsedFilter->getInt('ownerUserGroupId', ['default' => 0]);
        }

        // Retired options (provide -1 to return all)
        if ($parsedFilter->getInt('retired', ['default' => -1]) != -1) {
            $body .= " AND layout.retired = :retired ";
            $params['retired'] = $parsedFilter->getInt('retired',['default' => 0]);
        }

        // Modified Since?
        if ($parsedFilter->getDate('modifiedSinceDt') != null) {
            $body .= ' AND layout.modifiedDt > :modifiedSinceDt ';
            $params['modifiedSinceDt'] = $parsedFilter->getDate('modifiedSinceDt')
                ->format(DateFormatHelper::getSystemFormat());
        }

        if ($parsedFilter->getInt('ownerCampaignId') !== null) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :ownerCampaignId ";
            $params['ownerCampaignId'] = $parsedFilter->getInt('ownerCampaignId', ['default' => 0]);
        }

        if ($parsedFilter->getInt('layoutHistoryId') !== null) {
            $body .= ' AND `campaign`.campaignId IN (
                SELECT MAX(campaignId) 
                  FROM `layouthistory` 
                 WHERE `layouthistory`.layoutId = :layoutHistoryId
                ) ';
            $params['layoutHistoryId'] = $parsedFilter->getInt('layoutHistoryId');
        }

        // Get by regionId
        if ($parsedFilter->getInt('regionId') !== null) {
            // Join Campaign back onto it again
            $body .= " AND `layout`.layoutId IN (SELECT layoutId FROM `region` WHERE regionId = :regionId) ";
            $params['regionId'] = $parsedFilter->getInt('regionId', ['default' => 0]);
        }

        // get by Code
        if ($parsedFilter->getString('code') != '') {
            $body.= " AND layout.code = :code ";
            $params['code'] = $parsedFilter->getString('code');
        }

        if ($parsedFilter->getString('codeLike') != '') {
            $body.= ' AND layout.code LIKE :codeLike ';
            $params['codeLike'] = '%' . $parsedFilter->getString('codeLike') . '%';
        }

        // Tags
        if ($parsedFilter->getString('tags') != '') {
            $tagFilter = $parsedFilter->getString('tags');

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `layout`.layoutID NOT IN (
                    SELECT `lktaglayout`.layoutId
                     FROM `tag`
                        INNER JOIN `lktaglayout`
                        ON `lktaglayout`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $parsedFilter->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';
                $logicalOperator = $parsedFilter->getString('logicalOperator', ['default' => 'OR']);
                $allTags = explode(',', $tagFilter);
                $notTags = [];
                $tags = [];

                foreach ($allTags as $tag) {
                    if (str_starts_with($tag, '-')) {
                        $notTags[] = ltrim(($tag), '-');
                    } else {
                        $tags[] = $tag;
                    }
                }

                if (!empty($notTags)) {
                    $body .= ' AND layout.layoutID NOT IN (
                            SELECT lktaglayout.layoutId
                              FROM tag
                                INNER JOIN lktaglayout
                                ON lktaglayout.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $notTags,
                        'lktaglayout',
                        'lkTagLayoutId',
                        'layoutId',
                        $logicalOperator,
                        $operator,
                        true,
                        $body,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $body .= ' AND layout.layoutID IN (
                            SELECT lktaglayout.layoutId
                              FROM tag
                                INNER JOIN lktaglayout
                                ON lktaglayout.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $tags,
                        'lktaglayout',
                        'lkTagLayoutId',
                        'layoutId',
                        $logicalOperator,
                        $operator,
                        false,
                        $body,
                        $params
                    );
                }
            }
        }

        // Show All, Used or UnUsed
        // Used - In active schedule, scheduled in the future, directly assigned to displayGroup, default Layout.
        // Unused - Every layout NOT matching the Used ie not in active schedule, not scheduled in the future, not directly assigned to any displayGroup, not default layout.
        if ($parsedFilter->getInt('filterLayoutStatusId', ['default' => 1]) != 1)  {
            if ($parsedFilter->getInt('filterLayoutStatusId') == 2) {

                // Only show used layouts
                $now = Carbon::now()->format('U');
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
                $now = Carbon::now()->format('U');
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
        if ($parsedFilter->getInt('playlistId', ['default' => 0]) != 0) {
            $body .= ' AND layout.layoutId IN (SELECT DISTINCT `region`.layoutId
                    FROM `lkplaylistplaylist`
                      INNER JOIN `playlist`
                      ON `playlist`.playlistId = `lkplaylistplaylist`.parentId
                      INNER JOIN `region`
                      ON `region`.regionId = `playlist`.regionId
                   WHERE `lkplaylistplaylist`.childId = :playlistId )
            ';

            $params['playlistId'] = $parsedFilter->getInt('playlistId', ['default' => 0]);
        }

        // publishedDate
        if ($parsedFilter->getInt('havePublishDate', ['default' => -1]) != -1) {
            $body .= " AND `layout`.publishedDate IS NOT NULL ";
        }

        if ($parsedFilter->getInt('activeDisplayGroupId') !== null) {

            $date = Carbon::now()->format('U');

            // for filter by displayGroup, we need to add some additional filters in WHERE clause to show only relevant Layouts at the time the Layout grid is viewed
            $body .= ' AND campaign.campaignId = schedule.campaignId 
                       AND ( schedule.fromDt < '. $date . ' OR schedule.fromDt = 0 ) ' . ' AND schedule.toDt > ' . $date;
        }

        if ($parsedFilter->getInt('folderId') !== null) {
            $body .= " AND campaign.folderId = :folderId ";
            $params['folderId'] = $parsedFilter->getInt('folderId');
        }

        if ($parsedFilter->getString('orientation') !== null) {
            if ($parsedFilter->getString('orientation') === 'portrait') {
                $body .= ' AND layout.width < layout.height ';
            } else {
                $body .= ' AND layout.width >= layout.height ';
            }
        }

        if ($parsedFilter->getString('campaignType') != '') {
            $body .= ' AND campaign.type = :type ';
            $params['type'] = $parsedFilter->getString('campaignType');
        }

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, 'campaign.campaignId', 'layout.userId', $filterBy, 'campaign.permissionsFolderId');

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;
        $layoutIds = [];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $layout = $this->createEmpty();

            $parsedRow = $this->getSanitizer($row);

            // Validate each param and add it to the array.
            $layout->layoutId = $parsedRow->getInt('layoutID');
            $layout->parentId = $parsedRow->getInt('parentId');
            $layout->schemaVersion = $parsedRow->getInt('schemaVersion');
            $layout->layout = $parsedRow->getString('layout');
            $layout->description = $parsedRow->getString('description');
            $layout->duration = $parsedRow->getInt('duration');
            $layout->backgroundColor = $parsedRow->getString('backgroundColor');
            $layout->owner = $parsedRow->getString('owner');
            $layout->ownerId = $parsedRow->getInt('userID');
            $layout->campaignId = $parsedRow->getInt('CampaignID');
            $layout->retired = $parsedRow->getInt('retired');
            $layout->status = $parsedRow->getInt('status');
            $layout->backgroundImageId = $parsedRow->getInt('backgroundImageId');
            $layout->backgroundzIndex = $parsedRow->getInt('backgroundzIndex');
            $layout->width = $parsedRow->getDouble('width');
            $layout->height = $parsedRow->getDouble('height');
            $layout->orientation = $layout->width >= $layout->height ? 'landscape' : 'portrait';
            $layout->createdDt = $parsedRow->getString('createdDt');
            $layout->modifiedDt = $parsedRow->getString('modifiedDt');
            $layout->displayOrder = $parsedRow->getInt('displayOrder');
            $layout->statusMessage = $parsedRow->getString('statusMessage');
            $layout->enableStat = $parsedRow->getInt('enableStat');
            $layout->publishedStatusId = $parsedRow->getInt('publishedStatusId');
            $layout->publishedStatus = $parsedRow->getString('publishedStatus');
            $layout->publishedDate = $parsedRow->getString('publishedDate');
            $layout->autoApplyTransitions = $parsedRow->getInt('autoApplyTransitions');
            $layout->code = $parsedRow->getString('code');
            $layout->folderId = $parsedRow->getInt('folderId');
            $layout->permissionsFolderId = $parsedRow->getInt('permissionsFolderId');

            $layout->groupsWithPermissions = $row['groupsWithPermissions'];
            $layout->setOriginals();

            $entries[] = $layout;
            $layoutIds[] = $layout->layoutId;
        }

        // decorate with TagLinks
        if (count($entries) > 0) {
            $this->decorateWithTagLinks('lktaglayout', 'layoutId', $layoutIds, $entries);
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
        $minSubYear = Carbon::createFromTimestamp(Widget::$DATE_MIN)->subYear()->format('U');
        $minAddYear = Carbon::createFromTimestamp(Widget::$DATE_MIN)->addYear()->format('U');
        $maxSubYear = Carbon::createFromTimestamp(Widget::$DATE_MAX)->subYear()->format('U');
        $maxAddYear = Carbon::createFromTimestamp(Widget::$DATE_MAX)->addYear()->format('U');

        // if we are importing from layout.json the Widget from/to expiry dates are already timestamps
        // for old Layouts when the Widget from/to dt are missing we set them to timestamps as well.
        $timestampFromDt = is_integer($widget->fromDt) ? $widget->fromDt : Carbon::createFromTimeString($widget->fromDt)->format('U');
        $timestampToDt =  is_integer($widget->toDt) ? $widget->toDt : Carbon::createFromTimeString($widget->toDt)->format('U');

        // convert the date string to a unix timestamp, if the layout xlf does not contain dates, then set it to the $DATE_MIN / $DATE_MAX which are already unix timestamps, don't attempt to convert them
        // we need to check if provided from and to dates are within $DATE_MIN +- year to avoid issues with CMS Instances in different timezones https://github.com/xibosignage/xibo/issues/1934
        if ($widget->fromDt === Widget::$DATE_MIN || ($timestampFromDt > $minSubYear && $timestampFromDt < $minAddYear)) {
            $widget->fromDt = Widget::$DATE_MIN;
        } else {
            $widget->fromDt = $timestampFromDt;
        }

        if ($widget->toDt === Widget::$DATE_MAX || ($timestampToDt > $maxSubYear && $timestampToDt < $maxAddYear)) {
            $widget->toDt = Widget::$DATE_MAX;
        } else {
            $widget->toDt = $timestampToDt;
        }

        return $widget;
    }

    /**
     * @param \Xibo\Entity\Playlist $newPlaylist
     * @param Folder $folder
     * @return \Xibo\Entity\Playlist
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function setOwnerAndSavePlaylist($newPlaylist, Folder $folder)
    {
        // try to save with the name from import, if it already exists add "imported - "  to the name
        try {
            // The new Playlist should be owned by the importing user
            $newPlaylist->ownerId = $this->getUser()->getId();
            $newPlaylist->playlistId = null;
            $newPlaylist->widgets = [];
            $newPlaylist->folderId = $folder->id;
            $newPlaylist->permissionsFolderId =
                ($folder->permissionsFolderId == null) ? $folder->id : $folder->permissionsFolderId;
            $newPlaylist->save();
        } catch (DuplicateEntityException $e) {
            $newPlaylist->name = 'imported - ' . $newPlaylist->name;
            $newPlaylist->save();
        }

        return $newPlaylist;
    }

    /**
     * Checkout a Layout
     * @param \Xibo\Entity\Layout $layout
     * @param bool $returnDraft Should we return the Draft or the pre-checkout Layout
     * @return \Xibo\Entity\Layout
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function checkoutLayout($layout, $returnDraft = true)
    {
        // Load the Layout
        $layout->load();

        // Make a skeleton copy of the Layout
        $draft = clone $layout;
        $draft->parentId = $layout->layoutId;
        $draft->campaignId = $layout->campaignId;
        $draft->publishedStatusId = 2; // Draft
        $draft->publishedStatus = __('Draft');
        $draft->autoApplyTransitions = $layout->autoApplyTransitions;
        $draft->code = $layout->code;
        $draft->folderId = $layout->folderId;

        // Save without validation or notification.
        $draft->save([
            'validate' => false,
            'notify' => false
        ]);

        // Update the original
        $layout->publishedStatusId = 2; // Draft
        $layout->publishedStatus = __('Draft');
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false,
            'validate' => false,
            'notify' => false
        ]);

        /** @var Region[] $allRegions */
        $allRegions = array_merge($draft->regions, $draft->drawers);
        $draft->copyActions($draft, $layout);

        // Permissions && Sub-Playlists
        // Layout level permissions are managed on the Campaign entity, so we do not need to worry about that
        // Regions/Widgets need to copy down our layout permissions
        foreach ($allRegions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $layout->getRegionOrDrawer($region->getOriginalValue('regionId'));

            // Make sure Playlist closure table from the published one are copied over
            $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);

            // Copy over original permissions
            foreach ($original->permissions as $permission) {
                $new = clone $permission;
                $new->objectId = $region->regionId;
                $new->save();
            }

            // Playlist
            foreach ($original->getPlaylist()->permissions as $permission) {
                $new = clone $permission;
                $new->objectId = $region->getPlaylist()->playlistId;
                $new->save();
            }

            // Widgets
            foreach ($region->getPlaylist()->widgets as $widget) {
                $originalWidget = $original->getPlaylist()->getWidget($widget->getOriginalValue('widgetId'));
                // Copy over original permissions
                foreach ($originalWidget->permissions as $permission) {
                    $new = clone $permission;
                    $new->objectId = $widget->widgetId;
                    $new->save();
                }

                // Copy widget data
                $this->widgetDataFactory->copyByWidgetId($originalWidget->widgetId, $widget->widgetId);
            }
        }

        return $returnDraft ? $draft : $layout;
    }

    /**
     * Function called during Layout Import
     * Check if provided Widget has options to have Library references
     * if it does, then go through them find and replace old media references
     *
     * @param Widget $widget
     * @param int $newMediaId
     * @param int $oldMediaId
     * @throws NotFoundException
     */
    public function handleWidgetMediaIdReferences(Widget $widget, int $newMediaId, int $oldMediaId)
    {
        $module = $this->moduleFactory->getByType($widget->type);

        foreach ($module->getPropertiesAllowingLibraryRefs() as $property) {
            $widget->setOptionValue(
                $property->id,
                'cdata',
                str_replace(
                    '[' . $oldMediaId . ']',
                    '[' . $newMediaId . ']',
                    $widget->getOptionValue($property->id, null)
                )
            );
        }
    }

    /**
     * @param int $layoutId
     * @param array $actionLayoutIds
     * @param array $processedLayoutIds
     * @return array
     */
    public function getActionPublishedLayoutIds(int $layoutId, array &$actionLayoutIds, array &$processedLayoutIds): array
    {
        // if Layout was already processed, do not attempt to do it again
        // we should have all actionLayoutsIds from it at this point, there is no need to process it again
        if (!in_array($layoutId, $processedLayoutIds)) {
            // Get Layout Codes set in Actions on this Layout
            // Actions directly on this Layout
            $sql = '
                SELECT DISTINCT `action`.layoutCode
                  FROM `action`
                    INNER JOIN `layout`
                    ON `layout`.layoutId = `action`.sourceId
                 WHERE `action`.actionType = :actionType
                    AND `layout`.layoutId = :layoutId
                    AND `layout`.parentId IS NULL
            ';

            // Actions on this Layout's Regions
            $sql .= '
                UNION
                SELECT DISTINCT `action`.layoutCode
                  FROM `action`
                    INNER JOIN `region`
                    ON `region`.regionId = `action`.sourceId
                    INNER JOIN `layout`
                    ON `layout`.layoutId = `region`.layoutId
                 WHERE `action`.actionType = :actionType
                    AND `layout`.layoutId = :layoutId
                    AND `layout`.parentId IS NULL
            ';

            // Actions on this Layout's Widgets
            $sql .= '
                UNION
                SELECT DISTINCT `action`.layoutCode
                  FROM `action`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `action`.sourceId
                    INNER JOIN `playlist`
                    ON `playlist`.playlistId = `widget`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                    INNER JOIN `layout`
                    ON `layout`.layoutId = `region`.layoutId
                 WHERE `action`.actionType = :actionType
                    AND `layout`.layoutId = :layoutId
                    AND `layout`.parentId IS NULL
            ';

            // Join them together and get the Layout's referenced by those codes
            $actionLayoutCodes = $this->getStore()->select('
                SELECT `layout`.layoutId
                  FROM `layout`
                 WHERE `layout`.code IN (
                     ' . $sql . '
                 )
            ', [
                'actionType' => 'navLayout',
                'layoutId' => $layoutId,
            ]);

            $processedLayoutIds[] = $layoutId;

            foreach ($actionLayoutCodes as $row) {
                // if we have not processed this Layout yet, do it now
                if (!in_array($row['layoutId'], $actionLayoutIds)) {
                    $actionLayoutIds[] = $row['layoutId'];
                    // check if this layout is linked with any further navLayout actions
                    $this->getActionPublishedLayoutIds($row['layoutId'], $actionLayoutIds, $processedLayoutIds);
                }
            }
        }

        return $actionLayoutIds;
    }

    // <editor-fold desc="Concurrency Locking">

    /**
     * @param \Stash\Interfaces\PoolInterface|null $pool
     * @return $this
     */
    public function usePool($pool)
    {
        $this->pool = $pool;
        return $this;
    }

    /**
     * @return \Stash\Interfaces\PoolInterface|\Stash\Pool
     */
    private function getPool()
    {
        if ($this->pool === null) {
            $this->pool = new Pool();
        }
        return $this->pool;
    }

    /**
     * @param \Xibo\Entity\Layout $layout
     * @return \Xibo\Entity\Layout
     */
    public function decorateLockedProperties(Layout $layout): Layout
    {
        $locked = $this->pool->getItem('locks/layout/' . $layout->layoutId);
        $layout->isLocked = $locked->isMiss() ? [] : $locked->get();
        if (!empty($layout->isLocked)) {
            $layout->isLocked->lockedUser = ($layout->isLocked->userId != $this->getUser()->userId);
        }

        return $layout;
    }

    /**
     * Hold a lock on concurrent requests
     *  blocks if the request is locked
     * @param int $ttl seconds
     * @param int $wait seconds
     * @param int $tries
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function concurrentRequestLock(Layout $layout, $force = false, $pass = 1, $ttl = 300, $wait = 6, $tries = 10): Layout
    {
        // Does this layout require building?
        if (!$force && !$layout->isBuildRequired()) {
            return $layout;
        }

        $lock = $this->getPool()->getItem('locks/layout_build/' . $layout->campaignId);

        // Set the invalidation method to simply return the value (not that we use it, but it gets us a miss on expiry)
        // isMiss() returns false if the item is missing or expired, no exceptions.
        $lock->setInvalidationMethod(Invalidation::NONE);

        // Get the lock
        // other requests will wait here until we're done, or we've timed out
        $locked = $lock->get();

        // Did we get a lock?
        // if we're a miss, then we're not already locked
        if ($lock->isMiss() || $locked === false) {
            $this->getLog()->debug('Lock miss or false. Locking for ' . $ttl . ' seconds. $locked is '. var_export($locked, true));

            // so lock now
            $lock->set(true);
            $lock->expiresAfter($ttl);
            $lock->save();

            // If we have been locked previously, then reload our layout before passing back out.
            if ($pass > 1) {
                $layout = $this->getById($layout->layoutId);
            }

            return $layout;
        } else {
            // We are a hit - we must be locked
            $this->getLog()->debug('LOCK hit for ' . $layout->campaignId . ' expires '
                . $lock->getExpiration()->format('Y-m-d H:i:s') . ', created '
                . $lock->getCreation()->format('Y-m-d H:i:s'));

            // Try again?
            $tries--;

            if ($tries <= 0) {
                // We've waited long enough
                throw new GeneralException('Concurrent record locked, time out.');
            } else {
                $this->getLog()->debug('Unable to get a lock, trying again. Remaining retries: ' . $tries);

                // Hang about waiting for the lock to be released.
                sleep($wait);

                // Recursive request (we've decremented the number of tries)
                $pass++;
                return $this->concurrentRequestLock($layout, $force, $pass, $ttl, $wait, $tries);
            }
        }
    }

    /**
     * Release a lock on concurrent requests
     */
    public function concurrentRequestRelease(Layout $layout, bool $force = false)
    {
        if (!$force && !$layout->hasBuilt()) {
            return;
        }

        $this->getLog()->debug('Releasing lock ' . $layout->campaignId);

        $lock = $this->getPool()->getItem('locks/layout_build/' . $layout->campaignId);

        // Release lock
        $lock->set(false);
        $lock->expiresAfter(10); // Expire straight away (but give it time to save the thing)

        $this->getPool()->save($lock);
    }

    public function convertOldPlaylistOptions($playlistIds, $playlistOptions)
    {
        $convertedPlaylistOption = [];
        $i = 0;
        foreach ($playlistIds as $playlistId) {
            $i++;
            $convertedPlaylistOption[] = [
                'rowNo' => $i,
                'playlistId' => $playlistId,
                'spotFill' => $playlistOptions[$playlistId]['subPlaylistIdSpotFill'] ?? null,
                'spotLength' => $playlistOptions[$playlistId]['subPlaylistIdSpotLength'] ?? null,
                'spots' => $playlistOptions[$playlistId]['subPlaylistIdSpots'] ?? null,
            ];
        }

        return $convertedPlaylistOption;
    }

    /**
     * Prepare widget options, check legacy types from conditions, set widget type and upgrade
     * @throws NotFoundException
     */
    private function prepareWidgetAndGetModule(Widget $widget): Module
    {
        // Form conditions from the widget's option and value, e.g, templateId==worldclock1
        $widgetConditionMatch = [];
        foreach ($widget->widgetOptions as $option) {
            $widgetConditionMatch[] = $option->option . '==' . $option->value;
        }

        // Get module
        try {
            $module = $this->moduleFactory->getByType($widget->type, $widgetConditionMatch);
        } catch (NotFoundException $notFoundException) {
            throw new NotFoundException(__('Module not found'));
        }

        // Set the widget type and then assert the new one
        $widget->setOriginalValue('type', $widget->type);
        $widget->type = $module->type;

        // Upgrade if necessary
        // We do not upgrade widgets which are already at the right schema version
        if ($widget->schemaVersion < $module->schemaVersion && $module->isWidgetCompatibilityAvailable()) {
            // Grab a widget compatibility interface, if there is one
            $widgetCompatibilityInterface = $module->getWidgetCompatibilityOrNull();
            if ($widgetCompatibilityInterface !== null) {
                try {
                    // We will leave the widget save for later
                    $upgraded = $widgetCompatibilityInterface->upgradeWidget(
                        $widget,
                        $widget->schemaVersion,
                        $module->schemaVersion
                    );

                    if ($upgraded) {
                        $widget->schemaVersion = $module->schemaVersion;
                    }
                } catch (\Exception $e) {
                    $this->getLog()->error('Error upgrading widget '. $e->getMessage());
                }
            }
        }

        return $module;
    }

    // </editor-fold>
}
