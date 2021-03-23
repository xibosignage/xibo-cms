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
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class CampaignFactory
 * @package Xibo\Factory
 */
class CampaignFactory extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;
    
    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory, $scheduleFactory, $displayFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * @return Campaign
     */
    public function createEmpty()
    {
        return new Campaign($this->getStore(), $this->getLog(), $this->permissionFactory, $this->scheduleFactory, $this->displayFactory, $this->tagFactory);
    }

    /**
     * Create Campaign
     * @param string $name
     * @param int $userId
     * @param string $tags
     * @return Campaign
     */
    public function create($name, $userId, $tags)
    {
        $campaign = $this->createEmpty();
        $campaign->ownerId = $userId;
        $campaign->campaign = $name;
        
        // Create some tags
        $campaign->tags = $this->tagFactory->tagsFromString($tags);

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

        $campaigns = $this->query(null, array('disableUserCheck' => 1, 'campaignId' => $campaignId, 'isLayoutSpecific' => -1, 'excludeTemplates' => -1));

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
        return $this->query(null, array('ownerId' => $ownerId, 'excludeTemplates' => -1));
    }

    /**
     * Get Campaign by Layout
     * @param int $layoutId
     * @return array[Campaign]
     */
    public function getByLayoutId($layoutId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => -1));
    }

    /**
     * Query Campaigns
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Campaign]
     */
    public function query($sortOrder = null, $filterBy = array(), $options = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('campaign');

        $campaigns = array();
        $params = array();

        $select = '
        SELECT `campaign`.campaignId, `campaign`.campaign, `campaign`.isLayoutSpecific, `campaign`.userId AS ownerId,
            (
                SELECT COUNT(*)
                FROM lkcampaignlayout
                WHERE lkcampaignlayout.campaignId = `campaign`.campaignId
            ) AS numberLayouts,
            MAX(CASE WHEN `campaign`.IsLayoutSpecific = 1 THEN `layout`.retired ELSE 0 END) AS retired,
            (
                SELECT GROUP_CONCAT(DISTINCT tag) 
                FROM tag INNER JOIN lktagcampaign ON lktagcampaign.tagId = tag.tagId 
                WHERE lktagcampaign.campaignId = campaign.CampaignID 
                GROUP BY lktagcampaign.campaignId
            ) AS tags,
            
            (
                SELECT GROUP_CONCAT(IFNULL(value, \'NULL\')) 
                FROM tag INNER JOIN lktagcampaign ON lktagcampaign.tagId = tag.tagId 
                WHERE lktagcampaign.campaignId = campaign.CampaignID 
                GROUP BY lktagcampaign.campaignId
            ) AS tagValues
        ';

        $body  = '
            FROM `campaign`
              LEFT OUTER JOIN `lkcampaignlayout`
              ON lkcampaignlayout.CampaignID = campaign.CampaignID
              LEFT OUTER JOIN `layout`
              ON lkcampaignlayout.LayoutID = layout.LayoutID
              INNER JOIN `user`
              ON user.userId = campaign.userId 
           WHERE 1 = 1
        ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, '`campaign`.campaignId', '`campaign`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('isLayoutSpecific', 0, $filterBy) != -1) {
            // Exclude layout specific campaigns
            $body .= " AND `campaign`.isLayoutSpecific = :isLayoutSpecific ";
            $params['isLayoutSpecific'] = $this->getSanitizer()->getString('isLayoutSpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('campaignId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :campaignId ";
            $params['campaignId'] = $this->getSanitizer()->getString('campaignId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('ownerId', 0, $filterBy) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.userId = :ownerId ";
            $params['ownerId'] = $this->getSanitizer()->getString('ownerId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutId', 0, $filterBy) != 0) {
            // Filter by Layout
            $body .= " AND `lkcampaignlayout`.layoutId = :layoutId ";
            $params['layoutId'] = $this->getSanitizer()->getString('layoutId', 0, $filterBy);
        }
        
        if ($this->getSanitizer()->getInt('hasLayouts', 0, $filterBy) != 0) {

            $body .= " AND (
                SELECT COUNT(*)
                FROM lkcampaignlayout
                WHERE lkcampaignlayout.campaignId = `campaign`.campaignId
                )";
    
            $body .= ($this->getSanitizer()->getInt('hasLayouts', 0, $filterBy) == 1) ? " = 0 " : " > 0";
        }

        // Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {

            $tagFilter = $this->getSanitizer()->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `campaign`.campaignID NOT IN (
                    SELECT `lktagcampaign`.campaignId
                     FROM `tag`
                        INNER JOIN `lktagcampaign`
                        ON `lktagcampaign`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND campaign.campaignID IN (
                SELECT lktagcampaign.campaignId
                  FROM tag
                    INNER JOIN lktagcampaign
                    ON lktagcampaign.tagId = tag.tagId
                ";

                $tags = explode(',', $tagFilter);
                $this->tagFilter($tags, $operator, $body, $params);
            }
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != '') {
            $terms = explode(',', $this->getSanitizer()->getString('name', $filterBy));
            $this->nameFilter('campaign', 'Campaign', $terms, $body, $params,  ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        // Exclude templates by default
        if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) != -1) {
            if ($this->getSanitizer()->getInt('excludeTemplates', 1, $filterBy) == 1) {
                $body .= " AND `campaign`.campaignId NOT IN (SELECT `campaignId` FROM `lkcampaignlayout` WHERE layoutId IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template')) ";
            } else {
                $body .= " AND `campaign`.campaignId IN (SELECT `campaignId` FROM `lkcampaignlayout` WHERE layoutId IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template')) ";
            }
        }

        $group = 'GROUP BY `campaign`.CampaignID, Campaign, IsLayoutSpecific, `campaign`.userId ';

        if ($this->getSanitizer()->getInt('retired', -1, $filterBy) != -1) {
            $group .= ' HAVING retired = :retired ';
            $params['retired'] = $this->getSanitizer()->getInt('retired', $filterBy);

            if ($this->getSanitizer()->getInt('includeCampaignId', $filterBy) !== null) {
                $group .= ' OR campaign.campaignId = :includeCampaignId ';
                $params['includeCampaignId'] = $this->getSanitizer()->getInt('includeCampaignId', $filterBy);
            }
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

        $intProperties = ['intProperties' => ['numberLayouts', 'isLayoutSpecific']];

        // Layout durations
        if ($this->getSanitizer()->getInt('totalDuration', 0, $options) != 0) {
            $select .= ", SUM(`layout`.duration) AS totalDuration";
            $intProperties = ['intProperties' => ['numberLayouts', 'totalDuration', 'displayOrder']];
        }

        $sql = $select . $body . $group . $order . $limit;


        foreach ($this->getStore()->select($sql, $params) as $row) {
            $campaigns[] = $this->createEmpty()->hydrate($row, $intProperties);
        }

        // Paging
        if ($limit != '' && count($campaigns) > 0) {
            if ($this->getSanitizer()->getInt('retired', -1, $filterBy) != -1) {
                $body .= ' AND layout.retired = :retired ';
            }

            $results = $this->getStore()->select('SELECT COUNT(DISTINCT campaign.campaignId) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $campaigns;
    }

}
