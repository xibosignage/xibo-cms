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

class CampaignFactory extends BaseFactory
{
    /**
     * Create Campaign
     * @param string $name
     * @param int $userId
     * @return Campaign
     */
    public function create($name, $userId)
    {
        $campaign = new Campaign();
        $campaign->setContainer($this->getContainer());
        $campaign->ownerId = $userId;
        $campaign->campaign = $name;

        return $campaign;
    }

    /**
     * Get Campaign by ID
     * @param int $campaignId
     * @return Campaign
     * @throws NotFoundException
     */
    public function getById($campaignId)
    {
        $this->getLog()->debug('CampaignFactory getById(%d)', $campaignId);

        $campaigns = $this->query(null, array('disableUserCheck' => 1, 'campaignId' => $campaignId, 'isLayoutSpecific' => -1));

        if (count($campaigns) <= 0) {
            $this->getLog()->debug('Campaign not found with ID %d', $campaignId);
            throw new NotFoundException(\__('Campaign not found'));
        }

        // Set our layout
        return $campaigns[0];
    }

    /**
     * Get Campaign by Owner Id
     * @param int $ownerId
     * @return array[Campaign]
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('ownerId' => $ownerId));
    }

    /**
     * Get Campaign by Layout
     * @param int $layoutId
     * @return array[Campaign]
     */
    public function getByLayoutId($layoutId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId));
    }

    /**
     * Query Campaigns
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Campaign]
     */
    public function query($sortOrder = null, $filterBy = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('Campaign');

        $campaigns = array();
        $params = array();

        $select = '
        SELECT `campaign`.campaignId, `campaign`.campaign, `campaign`.isLayoutSpecific, `campaign`.userId AS ownerId,
              (
                SELECT COUNT(*)
                  FROM lkcampaignlayout
                 WHERE lkcampaignlayout.campaignId = `campaign`.campaignId
              ) AS numberLayouts
        ';

        $body  = '
            FROM `campaign`
              LEFT OUTER JOIN `lkcampaignlayout`
              ON lkcampaignlayout.CampaignID = campaign.CampaignID
              LEFT OUTER JOIN `layout`
              ON lkcampaignlayout.LayoutID = layout.LayoutID
           WHERE 1 = 1
        ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, '`campaign`.campaignId', '`campaign`.userId', $filterBy);

        if ($this->getSanitizer()->getString('isLayoutSpecific', 0, $filterBy) != -1) {
            // Exclude layout specific campaigns
            $body .= " AND `campaign`.isLayoutSpecific = :isLayoutSpecific ";
            $params['isLayoutSpecific'] = $this->getSanitizer()->getString('isLayoutSpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getString('campaignId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :campaignId ";
            $params['campaignId'] = $this->getSanitizer()->getString('campaignId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getString('ownerId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.userId = :ownerId ";
            $params['ownerId'] = $this->getSanitizer()->getString('ownerId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getString('layoutId', 0, $filterBy) != 0) {
            // Filter by Layout
            $body .= " AND `lkcampaignlayout`.layoutId = :layoutId ";
            $params['layoutId'] = $this->getSanitizer()->getString('layoutId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('name', $filterBy));

            $i = 0;
            foreach($names as $searchName) {
                $i++;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body .= " AND campaign.Campaign NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $body .= " AND campaign.Campaign LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
            }
        }

        $group = 'GROUP BY `campaign`.CampaignID, Campaign, IsLayoutSpecific, `campaign`.userId ';

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $group . $order . $limit;

        $intProperties = ['intProperties' => ['numberLayouts']];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $campaigns[] = (new Campaign())->hydrate($row, $intProperties)->setContainer($this->getContainer());
        }

        // Paging
        if ($limit != '' && count($campaigns) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(DISTINCT campaign.campaignId) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $campaigns;
    }
}