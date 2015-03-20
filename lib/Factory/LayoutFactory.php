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
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Entity\WidgetOption;
use Xibo\Exception\NotFoundException;

/**
 * Class LayoutFactory
 * @package Xibo\Factory
 */
class LayoutFactory
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
        $layouts = LayoutFactory::query(null, array('layoutId' => $layoutId, 'excludeTemplates' => 0, 'retired' => -1));

        if (count($layouts) <= 0) {
            throw new NotFoundException(\__('Layout not found'));
        }

        // Set our layout
        return $layouts[0];
    }

    /**
     * Load a layout by its XLF
     * @param string $layoutXlf
     * @return Layout
     */
    public static function loadByXlf($layoutXlf)
    {
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

        // Xpath to use when getting media
        $xpath = new \DOMXPath($document);

        // Populate Region Nodes
        foreach ($document->getElementsByTagName('region') as $regionNode) {
            /* @var \DOMElement $regionNode */
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

                // Is this stored media?
                if (!array_key_exists($widget->type, $modules))
                    continue;

                $module = $modules[$widget->type];
                /* @var \Xibo\Entity\Module $module */

                if ($module->regionSpecific == 0) {
                    $widget->mediaIds[] = $xlfMediaId;
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

        // TODO: Load any existing tags


        // The parsed, finished layout
        return $layout;
    }

    /**
     * Query for all Layouts
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Layout]
     * @throws NotFoundException
     */
    public static function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $params = array();
            $sql  = "";
            $sql .= "SELECT layout.layoutID, ";
            $sql .= "        layout.layout, ";
            $sql .= "        layout.description, ";
            $sql .= "        layout.userID, ";
            $sql .= "        `user`.UserName AS owner, ";
            $sql .= "        campaign.CampaignID, ";
            $sql .= "        layout.xml AS legacyXml, ";
            $sql .= "        layout.status, ";
            $sql .= "        layout.width, ";
            $sql .= "        layout.height, ";
            $sql .= "        layout.retired, ";
            if (\Kit::GetParam('showTags', $filterBy, _INT) == 1)
                $sql .= " tag.tag AS tags, ";
            else
                $sql .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tags, ";
            $sql .= "        layout.backgroundImageId, ";
            $sql .= "        layout.backgroundColor, ";
            $sql .= "        layout.backgroundzIndex, ";
            $sql .= "        layout.schemaVersion, ";
            $sql .= "     (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = campaign.CampaignID
                            ) AS groupsWithPermissions ";
            $params['entity'] = 'Xibo\\Entity\\Campaign';
            $sql .= "   FROM layout ";
            $sql .= "  INNER JOIN `lkcampaignlayout` ";
            $sql .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $sql .= "   INNER JOIN `campaign` ";
            $sql .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $sql .= "       AND campaign.IsLayoutSpecific = 1";
            $sql .= "   INNER JOIN `user` ON `user`.userId = `campaign`.userId ";

            if (\Kit::GetParam('showTags', $filterBy, _INT) == 1) {
                $sql .= " LEFT OUTER JOIN lktaglayout ON lktaglayout.layoutId = layout.layoutId ";
                $sql .= " LEFT OUTER JOIN tag ON tag.tagId = lktaglayout.tagId ";
            }

            if (\Kit::GetParam('campaignId', $filterBy, _INT, 0) != 0) {
                // Join Campaign back onto it again
                $sql .= " INNER JOIN `lkcampaignlayout` lkcl ON lkcl.layoutid = layout.layoutid AND lkcl.CampaignID = :campaignId ";
                $params['campaignId'] = \Kit::GetParam('campaignId', $filterBy, _INT, 0);
            }

            // MediaID
            if (\Kit::GetParam('mediaId', $filterBy, _INT, 0) != 0) {
                $sql .= " INNER JOIN `lklayoutmedia` ON lklayoutmedia.layoutid = layout.layoutid AND lklayoutmedia.mediaid = :mediaId";
                $sql .= " INNER JOIN `media` ON lklayoutmedia.mediaid = media.mediaid ";
                $params['mediaId'] = \Kit::GetParam('mediaId', $filterBy, _INT, 0);
            }

            $sql .= " WHERE 1 = 1 ";

            if (\Kit::GetParam('layout', $filterBy, _STRING) != '')
            {
                // convert into a space delimited array
                $names = explode(' ', \Kit::GetParam('layout', $filterBy, _STRING));

                foreach($names as $searchName)
                {
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $sql.= " AND  layout.layout NOT LIKE :search ";
                        $params['search'] = '%' . ltrim($searchName) . '%';
                    }
                    else {
                        $sql.= " AND  layout.layout LIKE :search ";
                        $params['search'] = '%' . $searchName . '%';
                    }
                }
            }

            if (\Kit::GetParam('layoutExact', $filterBy, _STRING) != '') {
                $sql.= " AND layout.layout = :exact ";
                $params['exact'] = \Kit::GetParam('layoutExact', $filterBy, _STRING);
            }

            // Layout
            if (\Kit::GetParam('layoutId', $filterBy, _INT, 0) != 0) {
                $sql .= " AND layout.layoutId = :layoutId ";
                $params['layoutId'] = \Kit::GetParam('layoutId', $filterBy, _INT, 0);
            }

            // Not Layout
            if (\Kit::GetParam('notLayoutId', $filterBy, _INT, 0) != 0) {
                $sql .= " AND layout.layoutId <> :notLayoutId ";
                $params['notLayoutId'] = \Kit::GetParam('notLayoutId', $filterBy, _INT, 0);
            }

            // Owner filter
            if (\Kit::GetParam('userId', $filterBy, _INT, 0) != 0) {
                $sql .= " AND layout.userid = :userId ";
                $params['userId'] = \Kit::GetParam('userId', $filterBy, _INT, 0);
            }

            // Retired options (default to 0 - provide -1 to return all
            if (\Kit::GetParam('retired', $filterBy, _INT, 0) != -1) {
                $sql .= " AND layout.retired = :retired ";
                $params['retired'] = \Kit::GetParam('retired', $filterBy, _INT);
            }

            // Tags
            if (\Kit::GetParam('tags', $filterBy, _STRING) != '') {
                $sql .= " AND layout.layoutID IN (
                    SELECT lktaglayout.layoutId
                      FROM tag
                        INNER JOIN lktaglayout
                        ON lktaglayout.tagId = tag.tagId
                    WHERE tag LIKE :tags
                    ) ";
                $params['tags'] =  '%' . \Kit::GetParam('tags', $filterBy, _STRING) . '%';
            }

            // Exclude templates by default
            if (\Kit::GetParam('excludeTemplates', $filterBy, _INT, 1) == 1) {
                $sql .= " AND layout.layoutID NOT IN (SELECT layoutId FROM lktaglayout WHERE tagId = 1) ";
            }

            // Show All, Used or UnUsed
            if (\Kit::GetParam('filterLayoutStatusId', $filterBy, _INT, 1) != 1)  {
                if (\Kit::GetParam('filterLayoutStatusId', $filterBy, _INT) == 2) {
                    // Only show used layouts
                    $sql .= ' AND ('
                        . '     campaign.CampaignID IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                        . '     OR layout.layoutID IN (SELECT DISTINCT defaultlayoutid FROM display) '
                        . ' ) ';
                }
                else {
                    // Only show unused layouts
                    $sql .= ' AND campaign.CampaignID NOT IN (SELECT DISTINCT schedule.CampaignID FROM schedule) '
                        . ' AND layout.layoutID NOT IN (SELECT DISTINCT defaultlayoutid FROM display) ';
                }
            }

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);

            \Debug::sql($sql, $params);

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            foreach ($sth->fetchAll() as $row) {
                $layout = new Layout();

                // Validate each param and add it to the array.
                $layout->layoutId = \Kit::ValidateParam($row['layoutID'], _INT);
                $layout->schemaVersion = \Kit::ValidateParam($row['schemaVersion'], _INT);
                $layout->layout = \Kit::ValidateParam($row['layout'], _STRING);
                $layout->description = \Kit::ValidateParam($row['description'], _STRING);
                $layout->tags = \Kit::ValidateParam($row['tags'], _STRING);
                $layout->backgroundColor = \Kit::ValidateParam($row['backgroundColor'], _STRING);
                $layout->owner = \Kit::ValidateParam($row['owner'], _STRING);
                $layout->ownerId = \Kit::ValidateParam($row['userID'], _INT);
                $layout->campaignId = \Kit::ValidateParam($row['CampaignID'], _INT);
                $layout->retired = \Kit::ValidateParam($row['retired'], _INT);
                $layout->status = \Kit::ValidateParam($row['status'], _INT);
                $layout->backgroundImageId = \Kit::ValidateParam($row['backgroundImageId'], _INT);
                $layout->backgroundzIndex = \Kit::ValidateParam($row['backgroundzIndex'], _INT);
                $layout->width = \Kit::ValidateParam($row['width'], _DOUBLE);
                $layout->height = \Kit::ValidateParam($row['height'], _DOUBLE);

                if (\Kit::GetParam('showLegacyXml', $filterBy, _INT) == 1)
                    $layout->legacyXml = \Kit::ValidateParam($row['legacyXml'], _HTMLSTRING);

                $layout->groupsWithPermissions = \Kit::ValidateParam($row['groupsWithPermissions'], _STRING);

                $entries[] = $layout;
            }

            return $entries;
        }
        catch (\Exception $e) {

            \Debug::Error($e->getMessage());

            throw new NotFoundException(__('Layout Not Found'));
        }
    }
}