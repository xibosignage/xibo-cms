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
use Xibo\Entity\User;
use Xibo\Entity\Widget;
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
            $this->moduleFactory
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
     * Create Layout from Template
     * @param int $layoutId
     * @param int $ownerId
     * @param string $name
     * @param string $description
     * @param string $tags
     * @return Layout
     * @throws NotFoundException
     */
    public function createFromTemplate($layoutId, $ownerId, $name, $description, $tags)
    {
        // Load the template
        $template = $this->loadById($layoutId);
        $template->load();

        // Empty all of the ID's
        $layout = clone $template;

        // Overwrite our new properties
        $layout->layout = $name;
        $layout->description = $description;

        // Create some tags (overwriting the old ones)
        $layout->tags = $this->tagFactory->tagsFromString($tags);

        // Set the owner
        $layout->setOwner($ownerId);

        // Ensure we have Playlists for each region
        foreach ($layout->regions as $region) {

            // Set the ownership of this region to the user creating from template
            $region->setOwner($ownerId, true);

            if (count($region->playlists) <= 0) {
                // Create a Playlist for this region
                $playlist = $this->playlistFactory->create($name, $ownerId);
                $region->assignPlaylist($playlist);
            }
        }

        // Fresh layout object, entirely new and ready to be saved
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
            throw new NotFoundException();

        $layouts = $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => -1, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return array[Layout]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('userId' => $ownerId, 'excludeTemplates' => -1, 'retired' => -1));
    }

    /**
     * Get by CampaignId
     * @param int $campaignId
     * @param bool $permissionsCheck Should we check permissions?
     * @return Layout[]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId, $permissionsCheck = true)
    {
        return $this->query(['displayOrder'], [
            'campaignId' => $campaignId,
            'excludeTemplates' => -1,
            'retired' => -1,
            'disableUserCheck' => $permissionsCheck ? 0 : 1
        ]);
    }

    /**
     * Get by Display Group Id
     * @param int $displayGroupId
     * @return array[Media]
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get by Background Image Id
     * @param int $backgroundImageId
     * @return array[Media]
     */
    public function getByBackgroundImageId($backgroundImageId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'backgroundImageId' => $backgroundImageId]);
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
            $playlist = $region->playlists[0];
            $playlist->ownerId = $regionOwnerId;

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
                $widget->useDuration = ($widget->useDuration == '') ? 1 : 0;
                $widget->tempId = $mediaNode->getAttribute('fileId');
                $widgetId = $mediaNode->getAttribute('id');

                $this->getLog()->debug('Adding Widget to object model. %s', $widget);

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

            $region->playlists[] = $playlist;

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
     * @return Layout
     * @throws XiboException
     */
    public function createFromZip($zipFile, $layoutName, $userId, $template, $replaceExisting, $importTags, $useExistingDataSets, $importDataSetData, $libraryController)
    {
        $this->getLog()->debug('Create Layout from ZIP File: %s, imported name will be %s.', $zipFile, $layoutName);

        $libraryLocation = $this->config->GetSetting('LIBRARY_LOCATION') . 'temp/';

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile))
            throw new \InvalidArgumentException(__('File does not exist'));

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile))
            throw new \InvalidArgumentException(__('Unable to open ZIP'));

        // Get the layout details
        $layoutDetails = json_decode($zip->getFromName('layout.json'), true);

        // Construct the Layout
        $layout = $this->loadByXlf($zip->getFromName('layout.xml'));

        $this->getLog()->debug('Layout Loaded: ' . $layout);

        // Override the name/description
        $layout->layout = (($layoutName != '') ? $layoutName : $layoutDetails['layout']);
        $layout->description = (isset($layoutDetails['description']) ? $layoutDetails['description'] : '');

        // Check that the resolution we have in this layout exists, and if not create it.
        try {
            if ($layout->schemaVersion < 2)
                $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
            else
                $this->resolutionFactory->getByDimensions($layout->width, $layout->height);

        } catch (NotFoundException $notFoundException) {
            $this->getLog()->info('Import is for an unknown resolution, we will create it with name: ' . $layout->width . ' x ' . $layout->height);

            $resolution = $this->resolutionFactory->create($layout->width . ' x ' . $layout->height, $layout->width, $layout->height);
            $resolution->userId = $this->getUser()->userId;
            $resolution->save();
        }

        // Update region names
        if (isset($layoutDetails['regions']) && count($layoutDetails['regions']) > 0) {
            $this->getLog()->debug('Updating region names according to layout.json');
            foreach ($layout->regions as $region) {
                if (array_key_exists($region->tempId, $layoutDetails['regions'])) {
                    $region->name = $layoutDetails['regions'][$region->tempId];
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

        // Set the owner
        $layout->setOwner($userId, true);

        // Track if we've added any fonts
        $fontsAdded = false;

        $widgets = $layout->getWidgets();
        $this->getLog()->debug('Layout has %d widgets', count($widgets));

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
            if (!$temporaryFileStream = fopen($temporaryFileName, 'w'))
                throw new InvalidArgumentException(__('Cannot save media file from ZIP file'), 'temp');

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
                    $intendedMediaName = 'import_' . $layout . '_' . uniqid();
                    throw new NotFoundException();
                }

            } catch (NotFoundException $e) {
                // Create it instead
                $this->getLog()->debug('Media does not exist in Library, add it. %s', $file['file']);

                $media = $this->mediaFactory->create($intendedMediaName, $file['file'], $file['type'], $userId, $file['duration']);
                $media->tags[] = $this->tagFactory->tagFromString('imported');
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
                if ($newMedia)
                    $fontsAdded = true;
            }
            else {
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
                    if ($widget->type == 'datasetview' || $widget->type == 'ticker') {
                        $widgetDataSetId = $widget->getOptionValue('dataSetId', 0);

                        if ($widgetDataSetId != 0 && $widgetDataSetId == $dataSetId) {
                            // Widget has a dataSet and it matches the one we've just actioned.
                            $widget->setOptionValue('dataSetId', 'attrib', $existingDataSet->dataSetId);

                            // Check for and replace column references.
                            // We are looking in the "columns" option for datasetview
                            // and the "template" option for ticker
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
                                
                            } else if ($widget->type == 'ticker') {
                                // Get the template option
                                $template = $widget->getOptionValue('template', '');

                                $this->getLog()->debug('Looking to replace columns from %s', $template);

                                foreach ($existingDataSet->columns as $column) {
                                    // We replace with the |%d] so that we dont experience double replacements
                                    $template = str_replace('|' . $column->priorDatasetColumnId . ']', '|' . $column->dataSetColumnId . ']', $template);
                                }

                                $widget->setOptionValue('template', 'raw', $template);

                                $this->getLog()->debug('Replaced columns with %s', $template);
                            }
                        }
                    }
                }
            }
        }


        $this->getLog()->debug('Finished creating from Zip');

        // Finished
        $zip->close();

        if ($fontsAdded) {
            $this->getLog()->debug('Fonts have been added');
            $libraryController->installFonts();
        }

        return $layout;
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
        $select .= "        layout.layout, ";
        $select .= "        layout.description, ";
        $select .= "        layout.duration, ";
        $select .= "        layout.userID, ";
        $select .= "        `user`.UserName AS owner, ";
        $select .= "        campaign.CampaignID, ";
        $select .= "        layout.status, ";
        $select .= "        layout.statusMessage, ";
        $select .= "        layout.width, ";
        $select .= "        layout.height, ";
        $select .= "        layout.retired, ";
        $select .= "        layout.createdDt, ";
        $select .= "        layout.modifiedDt, ";
        $select .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tags, ";
        $select .= "        layout.backgroundImageId, ";
        $select .= "        layout.backgroundColor, ";
        $select .= "        layout.backgroundzIndex, ";
        $select .= "        layout.schemaVersion, ";

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

        $body .= " WHERE 1 = 1 ";

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, 'campaign.campaignId', 'layout.userId', $filterBy);

        // Layout Like
        if ($this->getSanitizer()->getString('layout', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('layout', $filterBy));

            $i = 0;
            foreach($names as $searchName)
            {
                $i++;

                // Ignore if the word is empty
                if($searchName == '')
                  continue;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body.= " AND  layout.layout NOT LIKE (:search$i) ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $body.= " AND  layout.layout LIKE (:search$i) ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        if ($this->getSanitizer()->getString('layoutExact', $filterBy) != '') {
            $body.= " AND layout.layout = :exact ";
            $params['exact'] = $this->getSanitizer()->getString('layoutExact', $filterBy);
        }

        // Layout
        if ($this->getSanitizer()->getInt('layoutId', 0, $filterBy) != 0) {
            $body .= " AND layout.layoutId = :layoutId ";
            $params['layoutId'] = $this->getSanitizer()->getInt('layoutId', 0, $filterBy);
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

        // Retired options (default to 0 - provide -1 to return all
        if ($this->getSanitizer()->getInt('retired', 0, $filterBy) != -1) {
            $body .= " AND layout.retired = :retired ";
            $params['retired'] = $this->getSanitizer()->getInt('retired', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerCampaignId', $filterBy) !== null) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :ownerCampaignId ";
            $params['ownerCampaignId'] = $this->getSanitizer()->getInt('ownerCampaignId', 0, $filterBy);
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
                $i = 0;
                foreach (explode(',', $tagFilter) as $tag) {
                    $i++;

                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $body .= " ) ";
            }
        }

        // Exclude templates by default
        if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) != -1) {
            if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) == 1) {
                $body .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
            } else {
                $body .= " AND layout.layoutID IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
            }
        }

        // Show All, Used or UnUsed
        if ($this->getSanitizer()->getInt('filterLayoutStatusId', 1, $filterBy) != 1)  {
            if ($this->getSanitizer()->getInt('filterLayoutStatusId', $filterBy) == 2) {
                // Only show used layouts
                $body .= ' AND ('
                    . '     campaign.CampaignID IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                    . '     OR layout.layoutID IN (SELECT DISTINCT defaultlayoutid FROM display) '
                    . ' ) ';
            }
            else {
                // Only show unused layouts
                $body .= ' AND campaign.CampaignID NOT IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                    . ' AND layout.layoutID NOT IN (SELECT DISTINCT defaultlayoutid FROM display) ';
            }
        }

        // PlaylistID
        if ($this->getSanitizer()->getInt('playlistId', 0, $filterBy) != 0) {
            $body .= ' AND layout.layoutId IN (
                SELECT DISTINCT `region`.layoutId
                   FROM `lkregionplaylist`
                    INNER JOIN `region`
                    ON `region`.regionId = `lkregionplaylist`.regionId
                 WHERE `lkregionplaylist`.playlistId = :playlistId
                )
            ';

            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', 0, $filterBy);
        }

        // MediaID
        if ($this->getSanitizer()->getInt('mediaId', 0, $filterBy) != 0) {
            $body .= ' AND layout.layoutId IN (
                SELECT DISTINCT `region`.layoutId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                    INNER JOIN `lkregionplaylist`
                    ON `lkregionplaylist`.playlistId = `widget`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `lkregionplaylist`.regionId
                 WHERE `lkwidgetmedia`.mediaId = :mediaId
                )
            ';

            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', 0, $filterBy);
        }

        // Media Like
        if ($this->getSanitizer()->getString('mediaLike', $filterBy) !== null) {
            $body .= ' AND layout.layoutId IN (
                SELECT DISTINCT `region`.layoutId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                    INNER JOIN `lkregionplaylist`
                    ON `lkregionplaylist`.playlistId = `widget`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `lkregionplaylist`.regionId
                    INNER JOIN `media` 
                    ON `lkwidgetmedia`.mediaId = `media`.mediaId
                 WHERE `media`.name LIKE :mediaLike
                )
            ';

            $params['mediaLike'] = '%' . $this->getSanitizer()->getString('mediaLike', $filterBy) . '%';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

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
            $layout->schemaVersion = $this->getSanitizer()->int($row['schemaVersion']);
            $layout->layout = $this->getSanitizer()->string($row['layout']);
            $layout->description = $this->getSanitizer()->string($row['description']);
            $layout->duration = $this->getSanitizer()->int($row['duration']);
            $layout->tags = $this->getSanitizer()->string($row['tags']);
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
}