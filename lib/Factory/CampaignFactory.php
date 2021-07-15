<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Xibo\Entity\Campaign;
use Xibo\Entity\User;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\NotFoundException;

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

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;
    
    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @param $tagFactory
     */
    public function __construct($user, $userFactory, $permissionFactory, $scheduleFactory, $displayNotifyService, $tagFactory)
    {
        $this->setAclDependencies($user, $userFactory);
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayNotifyService = $displayNotifyService;
        $this->tagFactory = $tagFactory;
    }

    /**
     * @return Campaign
     */
    public function createEmpty()
    {
        return new Campaign($this->getStore(), $this->getLog(), $this->permissionFactory, $this->scheduleFactory, $this->displayNotifyService, $this->tagFactory);
    }

    /**
     * Create Campaign
     * @param string $name
     * @param int $userId
     * @param string $tags
     * @param int $folderId
     * @return Campaign
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function create($name, $userId, $tags, $folderId)
    {
        $campaign = $this->createEmpty();
        $campaign->ownerId = $userId;
        $campaign->campaign = $name;
        $campaign->folderId = $folderId;
        
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
        $this->getLog()->debug(sprintf('CampaignFactory getById(%d)', $campaignId));

        $campaigns = $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId, 'isLayoutSpecific' => -1, 'excludeTemplates' => -1]);

        if (count($campaigns) <= 0) {
            $this->getLog()->debug(sprintf('Campaign not found with ID %d', $campaignId));
            throw new NotFoundException(__('Campaign not found'));
        }

        // Set our layout
        return $campaigns[0];
    }

    /**
     * Get Campaign by Owner Id
     * @param int $ownerId
     * @return array[Campaign]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, array('ownerId' => $ownerId, 'excludeTemplates' => -1));
    }

    /**
     * Get Campaign by Layout
     * @param int $layoutId
     * @return array[Campaign]
     * @throws NotFoundException
     */
    public function getByLayoutId($layoutId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'layoutId' => $layoutId, 'excludeTemplates' => -1));
    }

    /**
     * Query Campaigns
     * @param array $sortOrder
     * @param array $filterBy
     * @param array $options
     * @return array[Campaign]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['campaign'];
        }

        $campaigns = [];
        $params = [];

        $select = '
        SELECT `campaign`.campaignId, `campaign`.campaign, `campaign`.isLayoutSpecific, `campaign`.userId AS ownerId, `campaign`.folderId, campaign.permissionsFolderId,
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

        if ($sanitizedFilter->getInt('isLayoutSpecific', ['default' => 0]) != -1) {
            // Exclude layout specific campaigns
            $body .= " AND `campaign`.isLayoutSpecific = :isLayoutSpecific ";
            $params['isLayoutSpecific'] = $sanitizedFilter->getInt('isLayoutSpecific', ['default' => 0]);
        }

        if ($sanitizedFilter->getInt('campaignId', ['default' => 0]) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.campaignId = :campaignId ";
            $params['campaignId'] = $sanitizedFilter->getInt('campaignId', ['default' => 0]);
        }

        if ($sanitizedFilter->getInt('ownerId', ['default' => 0]) != 0) {
            // Join Campaign back onto it again
            $body .= " AND `campaign`.userId = :ownerId ";
            $params['ownerId'] = $sanitizedFilter->getInt('ownerId', ['default' => 0]);
        }

        if ($sanitizedFilter->getInt('layoutId', ['default' => 0]) != 0) {
            // Filter by Layout
            $body .= " AND `lkcampaignlayout`.layoutId = :layoutId ";
            $params['layoutId'] = $sanitizedFilter->getInt('layoutId', ['default' => 0]);
        }
        
        if ($sanitizedFilter->getInt('hasLayouts', ['default' => 0]) != 0) {

            $body .= " AND (
                SELECT COUNT(*)
                FROM lkcampaignlayout
                WHERE lkcampaignlayout.campaignId = `campaign`.campaignId
                )";
    
            $body .= ($sanitizedFilter->getInt('hasLayouts', ['default' => 0]) == 1) ? " = 0 " : " > 0";
        }

        // Tags
        if ($sanitizedFilter->getString('tags') != '') {

            $tagFilter = $sanitizedFilter->getString('tags');

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `campaign`.campaignID NOT IN (
                    SELECT `lktagcampaign`.campaignId
                     FROM `tag`
                        INNER JOIN `lktagcampaign`
                        ON `lktagcampaign`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $sanitizedFilter->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

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

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $this->nameFilter('campaign', 'Campaign', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        // Exclude templates by default
        if ($sanitizedFilter->getInt('excludeTemplates', ['default' => 1]) != -1) {
            if ($sanitizedFilter->getInt('excludeTemplates', ['default' => 1]) == 1) {
                $body .= " AND `campaign`.campaignId NOT IN (SELECT `campaignId` FROM `lkcampaignlayout` WHERE layoutId IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template')) ";
            } else {
                $body .= " AND `campaign`.campaignId IN (SELECT `campaignId` FROM `lkcampaignlayout` WHERE layoutId IN (SELECT layoutId FROM lktaglayout INNER JOIN tag ON lktaglayout.tagId = tag.tagId WHERE tag = 'template')) ";
            }
        }

        if ($sanitizedFilter->getInt('folderId') !== null) {
            $body .= " AND campaign.folderId = :folderId ";
            $params['folderId'] = $sanitizedFilter->getInt('folderId');
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Campaign', $body, $params, '`campaign`.campaignId', '`campaign`.userId', $filterBy, '`campaign`.permissionsFolderId');

        $group = 'GROUP BY `campaign`.CampaignID, Campaign, IsLayoutSpecific, `campaign`.userId ';

        if ($sanitizedFilter->getInt('retired', ['default' => -1]) != -1) {
            $group .= ' HAVING retired = :retired ';
            $params['retired'] = $sanitizedFilter->getInt('retired');

            if ($sanitizedFilter->getInt('includeCampaignId') !== null) {
                $group .= ' OR campaign.campaignId = :includeCampaignId ';
                $params['includeCampaignId'] = $sanitizedFilter->getInt('includeCampaignId');
            }
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $intProperties = ['intProperties' => ['numberLayouts', 'isLayoutSpecific']];

        // Layout durations
        if ($sanitizedFilter->getInt('totalDuration', ['default' => 0]) != 0) {
            $select .= ", SUM(`layout`.duration) AS totalDuration";
            $intProperties = ['intProperties' => ['numberLayouts', 'totalDuration', 'displayOrder']];
        }

        $sql = $select . $body . $group . $order . $limit;


        foreach ($this->getStore()->select($sql, $params) as $row) {
            $campaigns[] = $this->createEmpty()->hydrate($row, $intProperties);
        }

        // Paging
        if ($limit != '' && count($campaigns) > 0) {
            if ($sanitizedFilter->getInt('retired', ['default' => -1]) != -1) {
                $body .= ' AND layout.retired = :retired ';
            }

            $results = $this->getStore()->select('SELECT COUNT(DISTINCT campaign.campaignId) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $campaigns;
    }
}
