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

use Xibo\Entity\AuditLog;

/**
 * Class AuditLogFactory
 * @package Xibo\Factory
 */
class AuditLogFactory extends BaseFactory
{
    /**
     * @return AuditLog
     */
    public function create()
    {
        return new AuditLog($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $this->getLog()->debug(sprintf('AuditLog Factory with filter: %s', var_export($filterBy, true)));
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $entries = [];
        $params = [];

        $select = '
            SELECT `logId`,
                `logDate`,
                `user`.`userName`,
                `message`,
                `objectAfter`,
                `entity`,
                `entityId`,
                `auditlog`.userId,
                `auditlog`.ipAddress,
                `auditlog`.sessionHistoryId
        ';

        $body = '
            FROM `auditlog`
                LEFT OUTER JOIN `user`
                ON `user`.`userId` = `auditlog`.`userId`
             WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('fromTimeStamp') !== null) {
            $body .= ' AND `auditlog`.`logDate` >= :fromTimeStamp ';
            $params['fromTimeStamp'] = $sanitizedFilter->getInt('fromTimeStamp');
        }

        if ($sanitizedFilter->getInt('toTimeStamp') !== null) {
            $body .= ' AND `auditlog`.`logDate` < :toTimeStamp ';
            $params['toTimeStamp'] = $sanitizedFilter->getInt('toTimeStamp');
        }

        if ($sanitizedFilter->getString('entity') != null) {
            $body .= ' AND `auditlog`.`entity` LIKE :entity ';
            $params['entity'] = '%' . $sanitizedFilter->getString('entity') . '%';
        }

        if ($sanitizedFilter->getString('userName') != null) {
            $body .= ' AND `user`.`userName` LIKE :userName ';
            $params['userName'] = '%' . $sanitizedFilter->getString('userName') . '%';
        }

        if ($sanitizedFilter->getString('message') != null) {
            $body .= ' AND `auditlog`.`message` LIKE :message ';
            $params['message'] = '%' . $sanitizedFilter->getString('message') . '%';
        }

        if ($sanitizedFilter->getString('ipAddress') != null) {
            $body .= ' AND `auditlog`.`ipAddress` LIKE :ipAddress ';
            $params['ipAddress'] = '%' . $sanitizedFilter->getString('ipAddress') . '%';
        }

        if ($sanitizedFilter->getInt('entityId') !== null) {
            $body .= ' AND ( `auditlog`.`entityId` = :entityId  ' ;
            $params['entityId'] = $sanitizedFilter->getInt('entityId');

            $entity = $sanitizedFilter->getString('entity');

            // if we were supplied with both layout entity and entityId (layoutId), expand the results
            // we want to get all actions issued on this layout from the moment it was added
            if (stripos($entity, 'layout') !== false) {
                $sqlLayoutHistory = '
                    SELECT `campaign`.campaignId
                      FROM `layout`
                          INNER JOIN `lkcampaignlayout`
                          ON `layout`.layoutId = `lkcampaignlayout`.layoutId
                          INNER JOIN `campaign`
                          ON `campaign`.campaignId = `lkcampaignlayout`.campaignId
                     WHERE `campaign`.isLayoutSpecific = 1 
                        AND `layout`.layoutId = :layoutId
                ';

                $results = $this->getStore()->select($sqlLayoutHistory, ['layoutId' => $params['entityId']]);
                foreach ($results as $row) {
                    $campaignId = $row['campaignId'];
                }

                if (isset($campaignId)) {
                    $body .= '
                        OR `auditlog`.`entityId` IN (
                            SELECT `layouthistory`.`layoutId`
                              FROM `layouthistory`
                             WHERE `layouthistory`.`campaignId` = :campaignId
                        )) ';
                    $params['campaignId'] = $campaignId;
                } else {
                    $body .= ' ) ';
                }
            } else {
                $body .= ' ) ';
            }
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `auditlog`.`userId` = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        if ($sanitizedFilter->getInt('sessionHistoryId') !== null) {
            $body .= ' AND `auditlog`.`sessionHistoryId` = :sessionHistoryId ';
            $params['sessionHistoryId'] = $sanitizedFilter->getInt('sessionHistoryId');
        }

        $order = '';
        if (is_array($sortOrder) && count($sortOrder) > 0) {
            $order .= 'ORDER BY ' . implode(', ', $sortOrder) . ' ';
        }

        // Paging
        $limit = '';
        if ($filterBy !== null
            && $sanitizedFilter->getInt('start') !== null
            && $sanitizedFilter->getInt('length') !== null
        ) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0])
                . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->create()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
