<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (CampaignFactory.php) is part of Xibo.
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


use Xibo\Entity\Campaign;
use Xibo\Exception\NotFoundException;

class CampaignFactory
{
    /**
     * Get Campaign by ID
     * @param int $campaignId
     * @return Campaign
     * @throws NotFoundException
     */
    public static function getById($campaignId)
    {
        $campaigns = CampaignFactory::query(null, array('campaignId' => $campaignId));

        if (count($campaigns) <= 0) {
            throw new NotFoundException(\__('Campaign not found'));
        }

        // Set our layout
        return $campaigns[0];
    }

    /**
     * Query Campaigns
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Campaign]
     */
    public static function query($sortOrder = null, $filterBy = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('Campaign');

        $campaigns = array();
        $params = array();

        $sql  = "SELECT campaign.CampaignID, Campaign, IsLayoutSpecific, COUNT(lkcampaignlayout.LayoutID) AS NumLayouts, MIN(layout.retired) AS Retired, `campaign`.userId ";
        $sql .= "  FROM `campaign` ";
        $sql .= "   LEFT OUTER JOIN `lkcampaignlayout` ";
        $sql .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $sql .= "   LEFT OUTER JOIN `layout` ";
        $sql .= "   ON lkcampaignlayout.LayoutID = layout.LayoutID ";
        $sql .= " WHERE 1 = 1 ";

        if (\Xibo\Helper\Sanitize::string('campaignId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $sql .= " AND `campaign`.campaignId = :campaignId ";
            $params['campaignId'] = \Xibo\Helper\Sanitize::string('campaignId', 0, $filterBy);
        }

        if (\Kit::GetParam('name', $filterBy, _STRING) != '') {
            // convert into a space delimited array
            $names = explode(' ', \Kit::GetParam('name', $filterBy, _STRING));

            $i = 0;
            foreach($names as $searchName) {
                $i++;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $sql .= " AND campaign.Campaign NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $sql .= " AND campaign.Campaign LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
            }
        }

        $sql .= 'GROUP BY campaign.CampaignID, Campaign, IsLayoutSpecific, `campaign`.userId ';

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        \Xibo\Helper\Log::sql($sql, $params);

        foreach (\Xibo\Storage\PDOConnect::select($sql, $params) as $row) {

            $campaign = new Campaign();

            // Validate each param and add it to the array.
            $campaign->campaignId = \Xibo\Helper\Sanitize::int($row['CampaignID']);
            $campaign->campaign = \Xibo\Helper\Sanitize::string($row['Campaign']);
            $campaign->numberLayouts = \Xibo\Helper\Sanitize::int($row['NumLayouts']);
            $campaign->isLayout = (\Xibo\Helper\Sanitize::int($row['IsLayoutSpecific']) == 1);
            $campaign->retired = \Xibo\Helper\Sanitize::int($row['Retired']);
            $campaign->ownerId = \Xibo\Helper\Sanitize::int($row['userId']);

            // Filter out campaigns that have all retired layouts
            if (\Xibo\Helper\Sanitize::int('retired', -1, $filterBy) != -1) {
                if ($row['Retired'] != \Xibo\Helper\Sanitize::int('retired', $filterBy))
                    continue;
            }

            $campaigns[] = $campaign;
        }

        return $campaigns;
    }
}