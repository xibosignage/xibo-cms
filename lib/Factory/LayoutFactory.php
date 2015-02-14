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
use Xibo\Entity\Media;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Exception\NotFoundException;

class LayoutFactory
{
    /**
     * Load a layout by its ID
     * @param int $layoutId
     * @return Layout The Layout
     * @throws NotFoundException
     */
    public static function loadById($layoutId)
    {
        $layouts = LayoutFactory::query(null, array('layoutId' => $layoutId));

        if (count($layouts) > 0) {
            return $layouts[0];
        }
        else
            throw new NotFoundException(\__('Layout not found'));
    }

    /**
     * Load a layout by its XLF
     * @param string $layoutXlf
     * @param int[Optional] $layoutId
     * @return string
     */
    public static function loadByXlf($layoutXlf, $layoutId = 0)
    {
        // This layout is actually in the database, so we can load those items we know about
        if ($layoutId != 0) {
            $layout = LayoutFactory::loadById($layoutId);
        }
        else {
            $layout = new Layout();
        }

        // Parse the XML and fill in the details for this layout
        $document = new \DOMDocument();
        $document->loadXML($layoutXlf);

        $layout->width = $document->documentElement->getAttribute('width');
        $layout->height = $document->documentElement->getAttribute('height');

        // Xpath to use when getting media
        $xpath = new \DOMXPath($document);

        // Populate Region Nodes
        foreach ($document->getElementsByTagName('region') as $regionNode) {
            /* @var \DOMElement $regionNode */
            $region = new Region();
            $region->width = $regionNode->getAttribute('width');
            $region->height = $regionNode->getAttribute('height');
            $region->left = $regionNode->getAttribute('left');
            $region->top = $regionNode->getAttribute('top');
            $region->regionId = $regionNode->getAttribute('id');
            $region->ownerId = $regionNode->getAttribute('userId');
            $region->name = $regionNode->getAttribute('name');

            // Set the region name if empty
            if ($region->name == '')
                $region->name = count($layout->regions) + 1;

            // Populate Playlists (XLF doesn't contain any playlists)
            $playlist = new Playlist();
            $playlist->playlist = $layout->layout . ' ' . $region->name . '-1';

            // Get all widgets
            foreach ($xpath->query('//region[@id="' . $region->regionId . '"]/media') as $mediaNode) {
                /* @var \DOMElement $mediaNode */
                $widget = new Widget();
                $media = new Media();
                $media->mediaId = $mediaNode->getAttribute('id');
                $widget->media[] = $media;

                $widget->type = $mediaNode->getAttribute('type');
                $widget->ownerId = $mediaNode->getAttribute('userid');

                // Get all widget options

                // Add the widget to the playlist
                $playlist->widgets[] = $widget;
            }

            $region->playlists[] = $playlist;

            $layout->regions[] = $region;
        }


        return $layout;
    }

    public static function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        try {
            $dbh = \PDOConnect::init();

            $params = array();
            $sql  = "";
            $sql .= "SELECT layout.layoutID, ";
            $sql .= "        layout.layout, ";
            $sql .= "        layout.description, ";
            $sql .= "        layout.userID, ";
            $sql .= "        campaign.CampaignID, ";
            $sql .= "        layout.status, ";
            $sql .= "        layout.retired, ";
            if (\Kit::GetParam('showTags', $filterBy, _INT) == 1)
                $sql .= " tag.tag AS tags, ";
            else
                $sql .= " (SELECT GROUP_CONCAT(DISTINCT tag) FROM tag INNER JOIN lktaglayout ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = layout.LayoutID GROUP BY lktaglayout.layoutId) AS tags, ";
            $sql .= "        layout.backgroundImageId ";

            $sql .= "   FROM layout ";
            $sql .= "  INNER JOIN `lkcampaignlayout` ";
            $sql .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $sql .= "   INNER JOIN `campaign` ";
            $sql .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $sql .= "       AND campaign.IsLayoutSpecific = 1";

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

            // Retired options
            if (\Kit::GetParam('retired', $filterBy, _INT, -1) != -1) {
                $sql .= " AND layout.retired = :retired ";
                $params['retired'] = \Kit::GetParam('retired', $filterBy, _INT);
            }
            else
                $sql .= " AND layout.retired = 0 ";

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

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            foreach ($sth->fetchAll() as $row) {
                $layout = new Layout();

                // Validate each param and add it to the array.
                $layout->layoutId = \Kit::ValidateParam($row['layoutID'], _INT);
                $layout->layout = \Kit::ValidateParam($row['layout'], _STRING);
                $layout->description = \Kit::ValidateParam($row['description'], _STRING);
                $layout->tags = \Kit::ValidateParam($row['tags'], _STRING);
                $layout->ownerId = \Kit::ValidateParam($row['userID'], _INT);
                $layout->campaignId = \Kit::ValidateParam($row['CampaignID'], _INT);
                $layout->retired = \Kit::ValidateParam($row['retired'], _INT);
                $layout->status = \Kit::ValidateParam($row['status'], _INT);
                $layout->backgroundImageId = \Kit::ValidateParam($row['backgroundImageId'], _INT);
                $layout->basicInfoLoaded = true;

                $entries[] = $layout;
            }

            return $entries;
        }
        catch (\Exception $e) {

            \Debug::Error($e->getMessage());

            return false;
        }
    }
}