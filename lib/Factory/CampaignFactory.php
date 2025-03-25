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

use Xibo\Entity\Campaign;
use Xibo\Entity\LayoutOnCampaign;
use Xibo\Entity\User;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CampaignFactory
 * @package Xibo\Factory
 */
class CampaignFactory extends BaseFactory
{
    use TagTrait;

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
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct($user, $userFactory, $permissionFactory, $scheduleFactory, $displayNotifyService)
    {
        $this->setAclDependencies($user, $userFactory);
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * @return Campaign
     */
    private function createEmpty()
    {
        return new Campaign(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this,
            $this->permissionFactory,
            $this->scheduleFactory,
            $this->displayNotifyService
        );
    }

    /**
     * @return \Xibo\Entity\LayoutOnCampaign
     */
    public function createEmptyLayoutAssignment(): LayoutOnCampaign
    {
        return new LayoutOnCampaign();
    }

    /**
     * Create Campaign
     * @param string $type
     * @param string $name
     * @param int $userId
     * @param int $folderId
     * @return Campaign
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function create($type, $name, $userId, $folderId)
    {
        $campaign = $this->createEmpty();
        $campaign->type = $type;
        $campaign->ownerId = $userId;
        $campaign->campaign = $name;
        $campaign->folderId = $folderId;

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
     * @param $folderId
     * @return Campaign[]
     * @throws NotFoundException
     */
    public function getByFolderId($folderId, $isLayoutSpecific = -1)
    {
        return $this->query(null, [
            'disableUserCheck' => 1,
            'folderId' => $folderId,
            'isLayoutSpecific' => $isLayoutSpecific
        ]);
    }

    /**
     * Query Campaigns
     * @param array $sortOrder
     * @param array $filterBy
     * @param array $options
     * @return Campaign[]
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
        SELECT `campaign`.campaignId,
           `campaign`.campaign,
           `campaign`.type,
           `campaign`.isLayoutSpecific,
           `campaign`.userId AS ownerId,
           `campaign`.folderId,
           `campaign`.permissionsFolderId,
           `campaign`.cyclePlaybackEnabled,
           `campaign`.playCount,
           `campaign`.listPlayOrder,
           `campaign`.targetType,
           `campaign`.target,
           `campaign`.startDt,
           `campaign`.endDt,
           `campaign`.plays,
           `campaign`.spend,
           `campaign`.impressions,
           `campaign`.lastPopId,
           `campaign`.ref1,
           `campaign`.ref2,
           `campaign`.ref3,
           `campaign`.ref4,
           `campaign`.ref5,
           `campaign`.createdAt,
           `campaign`.modifiedAt,
           `campaign`.modifiedBy,
           modifiedBy.userName AS modifiedByName,
            (
                SELECT COUNT(*)
                FROM lkcampaignlayout
                WHERE lkcampaignlayout.campaignId = `campaign`.campaignId
            ) AS numberLayouts,
            MAX(CASE WHEN `campaign`.IsLayoutSpecific = 1 THEN `layout`.retired ELSE 0 END) AS retired
        ';

        $body  = '
            FROM `campaign`
              LEFT OUTER JOIN `lkcampaignlayout`
              ON lkcampaignlayout.CampaignID = campaign.CampaignID
              LEFT OUTER JOIN `layout`
              ON lkcampaignlayout.LayoutID = layout.LayoutID
              INNER JOIN `user`
              ON user.userId = campaign.userId 
              LEFT OUTER JOIN `user` modifiedBy
              ON modifiedBy.userId = campaign.modifiedBy 
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

        if ($sanitizedFilter->getString('type') != null) {
            $body .= ' AND campaign.type = :type ';
            $params['type'] = $sanitizedFilter->getString('type');
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
                $logicalOperator = $sanitizedFilter->getString('logicalOperator', ['default' => 'OR']);
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
                    $body .= ' AND campaign.campaignID NOT IN (
                    SELECT lktagcampaign.campaignId
                      FROM tag
                        INNER JOIN lktagcampaign
                        ON lktagcampaign.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $notTags,
                        'lktagcampaign',
                        'lkTagCampaignId',
                        'campaignId',
                        $logicalOperator,
                        $operator,
                        true,
                        $body,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $body .= ' AND campaign.campaignID IN (
                    SELECT lktagcampaign.campaignId
                      FROM tag
                        INNER JOIN lktagcampaign
                        ON lktagcampaign.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $tags,
                        'lktagcampaign',
                        'lkTagCampaignId',
                        'campaignId',
                        $logicalOperator,
                        $operator,
                        false,
                        $body,
                        $params
                    );
                }
            }
        }

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'campaign',
                'Campaign',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
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

        // startDt
        if ($sanitizedFilter->getInt('startDt') !== null) {
            $body .= ' AND campaign.startDt <= :startDt ';
            $params['startDt'] = $sanitizedFilter->getInt('startDt');
        }

        // endDt
        if ($sanitizedFilter->getInt('endDt') !== null) {
            $body .= ' AND campaign.endDt > :endDt ';
            $params['endDt'] = $sanitizedFilter->getInt('endDt');
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

        if ($sanitizedFilter->getInt('cyclePlaybackEnabled') != null) {
            $body .= ' AND `campaign`.cyclePlaybackEnabled = :cyclePlaybackEnabled ';
            $params['cyclePlaybackEnabled'] = $sanitizedFilter->getInt('cyclePlaybackEnabled');
        }

        if ($sanitizedFilter->getInt('excludeMedia', ['default' => 0]) == 1) {
            $body .= ' AND `campaign`.type != \'media\' ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        // Layout durations
        if ($sanitizedFilter->getInt('totalDuration', ['default' => 0]) != 0) {
            $select .= ", SUM(`layout`.duration) AS totalDuration";
        }

        $sql = $select . $body . $group . $order . $limit;
        $campaignIds = [];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $campaign = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'numberLayouts',
                    'isLayoutSpecific',
                    'totalDuration',
                    'displayOrder',
                    'cyclePlaybackEnabled',
                    'playCount',
                    'target',
                    'startDt',
                    'endDt',
                    'modifiedBy',
                ],
                'stringProperties' => [
                    'lastPopId'
                ],
                'doubleProperties' => [
                    'spend',
                    'impressions',
                ],
            ]);
            $campaignIds[] = $campaign->getId();
            $campaigns[] = $campaign;
        }

        // decorate with TagLinks
        if (count($campaigns) > 0) {
            $this->decorateWithTagLinks('lktagcampaign', 'campaignId', $campaignIds, $campaigns);
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

    /**
     * Get layouts linked to the campaignId provided.
     * @param int $campaignId
     * @return LayoutOnCampaign[]
     */
    public function getLinkedLayouts(int $campaignId): array
    {
        $layouts = [];
        foreach ($this->getStore()->select('
            SELECT lkcampaignlayout.lkCampaignLayoutId,
                   lkcampaignlayout.displayOrder,
                   lkcampaignlayout.layoutId,
                   lkcampaignlayout.campaignId,
                   lkcampaignlayout.dayPartId,
                   lkcampaignlayout.daysOfWeek,
                   lkcampaignlayout.geoFence,
                   layout.layout,
                   layout.userId AS ownerId,
                   layout.duration,
                   daypart.name AS dayPart,
                   campaign.campaignId AS layoutCampaignId
              FROM lkcampaignlayout
                INNER JOIN layout 
                ON layout.layoutId = lkcampaignlayout.layoutId
                INNER JOIN lkcampaignlayout layoutspecific
                ON layoutspecific.layoutId = layout.layoutId
                INNER JOIN campaign 
                ON layoutspecific.campaignId = campaign.campaignId
                    AND campaign.isLayoutSpecific = 1
                LEFT OUTER JOIN daypart
                ON daypart.dayPartId = lkcampaignlayout.dayPartId
             WHERE lkcampaignlayout.campaignId = :campaignId
            ORDER BY displayOrder
        ', [
            'campaignId' => $campaignId,
        ]) as $row) {
            $link = (new LayoutOnCampaign())->hydrate($row, [
                'intProperties' => ['displayOrder', 'duration'],
            ]);

            if (!empty($link->geoFence)) {
                $link->geoFence = json_decode($link->geoFence, true);
            }

            $layouts[] = $link;
        }

        return $layouts;
    }

    /**
     * Get the campaignId for a Layout
     * @param int $layoutId
     * @return int
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getCampaignIdForLayout(int $layoutId): int
    {
        $results = $this->getStore()->select('
                SELECT campaign.campaignId
                  FROM layout
                    INNER JOIN lkcampaignlayout
                    ON layout.layoutId = lkcampaignlayout.layoutId
                    INNER JOIN campaign
                    ON campaign.campaignId = lkcampaignlayout.campaignId
                 WHERE campaign.isLayoutSpecific = 1
                   AND layout.layoutId = :layoutId
            ', [
            'layoutId' => $layoutId
        ]);

        if (count($results) <= 0) {
            throw new NotFoundException();
        }

        return intval($results[0]['campaignId']);
    }
}
