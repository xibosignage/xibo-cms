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


use Xibo\Entity\Layout;
use Xibo\Entity\Widget;
use Xibo\Entity\WidgetOption;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

/**
 * Class LayoutFactory
 * @package Xibo\Factory
 */
class LayoutFactory extends BaseFactory
{
    /**
     * Create Layout from Resolution
     * @param int $resolutionId
     * @param int $ownerId
     * @param string $name
     * @param string $description
     * @param string $tags
     * @return Layout
     */
    public static function createFromResolution($resolutionId, $ownerId, $name, $description, $tags)
    {
        $resolution = ResolutionFactory::getById($resolutionId);

        // Create a new Layout
        $layout = new Layout();
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
        $layout->tags = TagFactory::tagsFromString($tags);

        // Add a blank, full screen region
        $layout->regions[] = RegionFactory::create($ownerId, $name . '-1', $layout->width, $layout->height, 0, 0);

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
    public static function createFromTemplate($layoutId, $ownerId, $name, $description, $tags)
    {
        // Load the template
        $template = LayoutFactory::loadById($layoutId);
        $template->load();

        // Empty all of the ID's
        $layout = clone $template;

        // Overwrite our new properties
        $layout->layout = $name;
        $layout->description = $description;

        // Create some tags (overwriting the old ones)
        $layout->tags = TagFactory::tagsFromString($tags);

        // Set the owner
        $layout->setOwner($ownerId);

        // Fresh layout object, entirely new and ready to be saved
        return $layout;
    }

    /**
     * Load a layout by its ID
     * @param int $layoutId
     * @return Layout The Layout
     * @throws NotFoundException
     */
    public static function loadById($layoutId)
    {
        // Get the layout
        $layout = LayoutFactory::getById($layoutId);

        // LEGACY: What happens if we have a legacy layout (a layout that still contains its own XML)
        if ($layout->legacyXml != null && $layout->legacyXml != '') {
            $layoutFromXml = LayoutFactory::loadByXlf($layout->legacyXml);

            // Add the information we know from the layout we originally parsed from the DB
            $layoutFromXml->layoutId = $layout->layoutId;
            $layoutFromXml->layout = $layout->layout;
            $layoutFromXml->description = $layout->description;
            $layoutFromXml->status = $layout->status;
            $layoutFromXml->campaignId = $layout->campaignId;
            $layoutFromXml->backgroundImageId = $layout->backgroundImageId;
            $layoutFromXml->ownerId = $layout->ownerId;
            $layoutFromXml->schemaVersion = 3;

            // TODO: Save this so that it gets converted to the DB format.
            //$layoutFromXml->save();

            // TODO: somehow we need to map the old permissions over to the new permissions model.

            $layout = $layoutFromXml;
        }
        else {
            // Load the layout
            $layout->load();
        }

        return $layout;
    }

    /**
     * Loads only the layout information
     * @param int $layoutId
     * @return Layout
     * @throws NotFoundException
     */
    public static function getById($layoutId)
    {
        $layouts = LayoutFactory::query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => 0, 'retired' => -1));

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
    public static function getByOwnerId($ownerId)
    {
        return LayoutFactory::query(null, array('userId' => $ownerId, 'excludeTemplates' => 0, 'retired' => -1));
    }

    /**
     * Get by CampaignId
     * @param int $campaignId
     * @return array[Layout]
     * @throws NotFoundException
     */
    public static function getByCampaignId($campaignId)
    {
        return LayoutFactory::query(['displayOrder'], array('campaignId' => $campaignId, 'retired' => -1));
    }

    /**
     * Load a layout by its XLF
     * @param string $layoutXlf
     * @return Layout
     */
    public static function loadByXlf($layoutXlf)
    {
        Log::debug('Loading Layout by XLF');

        // New Layout
        $layout = new Layout();

        // Get a list of modules for us to use
        $modules = ModuleFactory::get();

        // Parse the XML and fill in the details for this layout
        $document = new \DOMDocument();
        $document->loadXML($layoutXlf);

        $layout->schemaVersion = (int)$document->documentElement->getAttribute('schemaVersion');
        $layout->width = $document->documentElement->getAttribute('width');
        $layout->height = $document->documentElement->getAttribute('height');
        $layout->backgroundColor = $document->documentElement->getAttribute('bgcolor');
        $layout->backgroundzIndex = $document->documentElement->getAttribute('zindex');

        // Xpath to use when getting media
        $xpath = new \DOMXPath($document);

        // Populate Region Nodes
        foreach ($document->getElementsByTagName('region') as $regionNode) {
            /* @var \DOMElement $regionNode */
            Log::debug('Found Region');

            $region = RegionFactory::create(
                (int)$regionNode->getAttribute('userId'),
                $regionNode->getAttribute('name'),
                (double)$regionNode->getAttribute('width'),
                (double)$regionNode->getAttribute('height'),
                (double)$regionNode->getAttribute('top'),
                (double)$regionNode->getAttribute('left')
                );

            // Use the regionId locally to parse the rest of the XLF
            $regionId = $regionNode->getAttribute('id');

            // Set the region name if empty
            if ($region->name == '')
                $region->name = count($layout->regions) + 1;

            // Populate Playlists (XLF doesn't contain any playlists)
            $playlist = $region->playlists[0];

            // Get all widgets
            foreach ($xpath->query('//region[@id="' . $regionId . '"]/media') as $mediaNode) {
                /* @var \DOMElement $mediaNode */
                $widget = new Widget();
                $widget->type = $mediaNode->getAttribute('type');
                $widget->ownerId = $mediaNode->getAttribute('userid');
                $widget->duration = $mediaNode->getAttribute('duration');
                $xlfMediaId = $mediaNode->getAttribute('id');

                Log::debug('Adding Widget to object model. %s', $widget);

                // Does this module type exist?
                if (!array_key_exists($widget->type, $modules)) {
                    Log::error('Module Type [%s] in imported Layout does not exist. Allowable types: %s', $widget->type, json_encode(array_keys($modules)));
                    continue;
                }

                $module = $modules[$widget->type];
                /* @var \Xibo\Entity\Module $module */

                if ($module->regionSpecific == 0) {
                    $widget->assignMedia($xlfMediaId);
                }

                // Get all widget options
                foreach ($xpath->query('//region[@id="' . $regionId . '"]/media[@id="' . $xlfMediaId . '"]/options') as $optionsNode) {
                    /* @var \DOMElement $optionsNode */
                    foreach ($optionsNode->childNodes as $mediaOption) {
                        /* @var \DOMElement $mediaOption */
                        $widgetOption = new WidgetOption();
                        $widgetOption->type = 'attribute';
                        $widgetOption->option = $mediaOption->nodeName;
                        $widgetOption->value = $mediaOption->textContent;

                        $widget->widgetOptions[] = $widgetOption;
                    }
                }

                // Get all widget raw content
                foreach ($xpath->query('//region[@id="' . $regionId . '"]/media[@id="' . $xlfMediaId . '"]/raw') as $rawNode) {
                    /* @var \DOMElement $rawNode */
                    // Get children
                    foreach ($rawNode->childNodes as $mediaOption) {
                        /* @var \DOMElement $mediaOption */
                        $widgetOption = new WidgetOption();
                        $widgetOption->type = 'cdata';
                        $widgetOption->option = $mediaOption->nodeName;
                        $widgetOption->value = $mediaOption->textContent;

                        $widget->widgetOptions[] = $widgetOption;
                    }
                }

                // Add the widget to the playlist
                $playlist->widgets[] = $widget;
            }

            $region->playlists[] = $playlist;

            $layout->regions[] = $region;
        }

        Log::debug('Finished loading layout - there are %d regions.', count($layout->regions));

        // TODO: Load any existing tags


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
     * @return Layout
     */
    public static function createFromZip($zipFile, $layoutName, $userId, $template, $replaceExisting, $importTags)
    {
        Log::debug('Create Layout from ZIP File: %s, imported name will be %s.', $zipFile, $layoutName);

        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/';

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
        $layout = LayoutFactory::loadByXlf($zip->getFromName('layout.xml'));

        // Override the name/description
        $layout->layout = (($layoutName != '') ? $layoutName : $layoutDetails['layout']);
        $layout->description = (isset($layoutDetails['description']) ? $layoutDetails['description'] : '');

        // Remove the tags if necessary
        if (!$importTags) {
            $layout->tags = [];
        }

        // Add the template tag if we are importing a template
        if ($template) {
            $layout->tags[] = TagFactory::getByTag('template');
        }

        // Tag as imported
        $layout->tags[] = TagFactory::tagFromString('imported');

        // Set the owner
        $layout->setOwner($userId);

        Log::debug('Process mapping.json file.');

        // Go through each region and add the media (updating the media ids)
        $mappings = json_decode($zip->getFromName('mapping.json'), true);

        foreach ($mappings as $file) {
            // Import the Media File
            $intendedMediaName = $file['name'];
            $temporaryFileName = $libraryLocation . $file['file'];
            if (file_put_contents($temporaryFileName, $zip->getFromName('library/' . $file['file'])) === false)
                throw new \InvalidArgumentException(__('Cannot save media file from ZIP file'));

            // Check we don't already have one
            try {
                $media = MediaFactory::getByName($intendedMediaName);

                Log::debug('Media already exists with name: %s', $intendedMediaName);

                if ($replaceExisting) {
                    // Media with this name already exists, but we don't want to use it.
                    $intendedMediaName = 'import_' . $layout . '_' . uniqid();
                    throw new NotFoundException();
                }

            } catch (NotFoundException $e) {
                // Create it instead
                Log::debug('Media does not exist in Library, add it. %s', $file['file']);

                $media = MediaFactory::create($intendedMediaName, $file['file'], $file['type'], $userId, $file['duration']);
                $media->tags[] = TagFactory::tagFromString('imported');
                $media->save();
            }

            // Find where this is used and swap for the real mediaId
            $widgets = $layout->getWidgets();
            $oldMediaId = $file['mediaid'];
            $newMediaId = $media->mediaId;

            Log::debug('Layout has %d widgets', count($widgets));

            if ($file['background'] == 1) {
                $layout->backgroundImageId = $newMediaId;
            }
            else {
                // Go through all widgets and replace if necessary
                // Keep the keys the same? Doesn't matter
                foreach ($widgets as $widget) {
                    /* @var Widget $widget */

                    Log::debug('Checking Widget for the old mediaID [%d] so we can replace it with the new mediaId [%d] and storedAs [%s]. Media assigned to widget %s.', $oldMediaId, $newMediaId, $media->storedAs, json_encode($widget->mediaIds));

                    if (in_array($oldMediaId, $widget->mediaIds)) {

                        Log::debug('Removing %d and replacing with %d', $oldMediaId, $newMediaId);

                        // Unassign the old ID
                        $widget->unassignMedia($oldMediaId);

                        // Assign the new ID
                        $widget->assignMedia($newMediaId);

                        // Update the widget option with the new ID
                        $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                    }
                }
            }
        }

        Log::debug('Finished creating from Zip');

        // Finished
        $zip->close();

        return $layout;
    }

    /**
     * Query for all Layouts
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Layout]
     * @throws NotFoundException
     */
    public static function query($sortOrder = null, $filterBy = null)
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
        $select .= "        layout.xml AS legacyXml, ";
        $select .= "        layout.status, ";
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

        if (Sanitize::getInt('campaignId', 0, $filterBy) != 0) {
            $select .= ' lkcl.displayOrder, ';
        }
        else {
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

        if (Sanitize::getInt('campaignId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $body .= " INNER JOIN `lkcampaignlayout` lkcl ON lkcl.layoutid = layout.layoutid AND lkcl.CampaignID = :campaignId ";
            $params['campaignId'] = Sanitize::getInt('campaignId', 0, $filterBy);
        }

        // MediaID
        if (Sanitize::getInt('mediaId', 0, $filterBy) != 0) {
            $body .= " INNER JOIN `lklayoutmedia` ON lklayoutmedia.layoutid = layout.layoutid AND lklayoutmedia.mediaid = :mediaId";
            $body .= " INNER JOIN `media` ON lklayoutmedia.mediaid = media.mediaid ";
            $params['mediaId'] = Sanitize::getInt('mediaId', 0, $filterBy);
        }

        $body .= " WHERE 1 = 1 ";

        // Logged in user view permissions
        self::viewPermissionSql('Xibo\Entity\Campaign', $body, $params, 'campaign.campaignId', 'layout.userId', $filterBy);

        // Layout Like
        if (Sanitize::getString('layout', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', Sanitize::getString('layout', $filterBy));

            foreach($names as $searchName)
            {
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body.= " AND  layout.layout NOT LIKE :search ";
                    $params['search'] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $body.= " AND  layout.layout LIKE :search ";
                    $params['search'] = '%' . $searchName . '%';
                }
            }
        }

        if (Sanitize::getString('layoutExact', $filterBy) != '') {
            $body.= " AND layout.layout = :exact ";
            $params['exact'] = Sanitize::getString('layoutExact', $filterBy);
        }

        // Layout
        if (Sanitize::getInt('layoutId', 0, $filterBy) != 0) {
            $body .= " AND layout.layoutId = :layoutId ";
            $params['layoutId'] = Sanitize::getInt('layoutId', 0, $filterBy);
        }

        // Background Image
        if (Sanitize::getInt('backgroundImageId', $filterBy) !== null) {
            $body .= " AND layout.backgroundImageId = :backgroundImageId ";
            $params['backgroundImageId'] = Sanitize::getInt('backgroundImageId', 0, $filterBy);
        }

        // Not Layout
        if (Sanitize::getInt('notLayoutId', 0, $filterBy) != 0) {
            $body .= " AND layout.layoutId <> :notLayoutId ";
            $params['notLayoutId'] = Sanitize::getInt('notLayoutId', 0, $filterBy);
        }

        // Owner filter
        if (Sanitize::getInt('userId', 0, $filterBy) != 0) {
            $body .= " AND layout.userid = :userId ";
            $params['userId'] = Sanitize::getInt('userId', 0, $filterBy);
        }

        // Retired options (default to 0 - provide -1 to return all
        if (Sanitize::getInt('retired', 0, $filterBy) != -1) {
            $body .= " AND layout.retired = :retired ";
            $params['retired'] = Sanitize::getInt('retired', 0, $filterBy);
        }

        // Tags
        if (Sanitize::getString('tags', $filterBy) != '') {
            $body .= " AND layout.layoutID IN (
                SELECT lktaglayout.layoutId
                  FROM tag
                    INNER JOIN lktaglayout
                    ON lktaglayout.tagId = tag.tagId
                WHERE tag LIKE :tags
                ) ";
            $params['tags'] =  '%' . Sanitize::getString('tags', $filterBy) . '%';
        }

        // Exclude templates by default
        if (Sanitize::getInt('excludeTemplates', 1, $filterBy) == 1) {
            $body .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
        }

        // Show All, Used or UnUsed
        if (Sanitize::getInt('filterLayoutStatusId', 1, $filterBy) != 1)  {
            if (Sanitize::getInt('filterLayoutStatusId', $filterBy) == 2) {
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

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $layout = new Layout();

            // Validate each param and add it to the array.
            $layout->layoutId = Sanitize::int($row['layoutID']);
            $layout->schemaVersion = Sanitize::int($row['schemaVersion']);
            $layout->layout = Sanitize::string($row['layout']);
            $layout->description = Sanitize::string($row['description']);
            $layout->duration = Sanitize::int($row['duration']);
            $layout->tags = Sanitize::string($row['tags']);
            $layout->backgroundColor = Sanitize::string($row['backgroundColor']);
            $layout->owner = Sanitize::string($row['owner']);
            $layout->ownerId = Sanitize::int($row['userID']);
            $layout->campaignId = Sanitize::int($row['CampaignID']);
            $layout->retired = Sanitize::int($row['retired']);
            $layout->status = Sanitize::int($row['status']);
            $layout->backgroundImageId = Sanitize::int($row['backgroundImageId']);
            $layout->backgroundzIndex = Sanitize::int($row['backgroundzIndex']);
            $layout->width = Sanitize::double($row['width']);
            $layout->height = Sanitize::double($row['height']);
            $layout->createdDt = $row['createdDt'];
            $layout->modifiedDt = $row['modifiedDt'];
            $layout->displayOrder = $row['displayOrder'];

            if (Sanitize::int('showLegacyXml', $filterBy) == 1)
                $layout->legacyXml = $row['legacyXml'];

            $layout->groupsWithPermissions = $row['groupsWithPermissions'];

            $entries[] = $layout;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}