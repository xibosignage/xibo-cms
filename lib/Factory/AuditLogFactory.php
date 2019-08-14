<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (AuditTrailFactory.php) is part of Xibo.
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
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class AuditLogFactory
 * @package Xibo\Factory
 */
class AuditLogFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return AuditLog
     */
    public function create()
    {
        return new AuditLog($this->getStore(), $this->getLog());
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $this->getLog()->debug('AuditLog Factory with filter: %s', var_export($filterBy, true));

        $entries = [];
        $params = [];

        $select = ' SELECT logId, logDate, user.userName, message, objectAfter, entity, entityId, auditlog.userId ';
        $body = 'FROM `auditlog` LEFT OUTER JOIN user ON user.userId = auditlog.userId WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('fromTimeStamp', $filterBy) !== null) {
            $body .= ' AND `auditlog`.logDate >= :fromTimeStamp ';
            $params['fromTimeStamp'] = $this->getSanitizer()->getInt('fromTimeStamp', $filterBy);
        }

        if ($this->getSanitizer()->getInt('toTimeStamp', $filterBy) !== null) {
            $body .= ' AND `auditlog`.logDate < :toTimeStamp ';
            $params['toTimeStamp'] = $this->getSanitizer()->getInt('toTimeStamp', $filterBy);
        }

        if ($this->getSanitizer()->getString('entity', $filterBy) != null) {
            $body .= ' AND `auditlog`.entity LIKE :entity ';
            $params['entity'] = '%' . $this->getSanitizer()->getString('entity', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('userName', $filterBy) != null) {
            $body .= ' AND `user`.userName LIKE :userName ';
            $params['userName'] = '%' . $this->getSanitizer()->getString('userName', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('message', $filterBy) != null) {
            $body .= ' AND `auditlog`.message LIKE :message ';
            $params['message'] = '%' . $this->getSanitizer()->getString('message', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getInt('entityId', $filterBy) !== null) {
            $body .= ' AND ( `auditlog`.entityId = :entityId  ' ;
            $params['entityId'] = $this->getSanitizer()->getInt('entityId', $filterBy);

            $entity = $this->getSanitizer()->getString('entity', $filterBy);

            // if we were supplied with both layout entity and entityId (layoutId), expand the results
            // we want to get all actions issued on this layout from the moment it was added
            if (stripos('layout', $entity ) !== false) {

                $sqlLayoutHistory = 'SELECT campaign.campaignId FROM layout INNER JOIN lkcampaignlayout on layout.layoutId = lkcampaignlayout.layoutId INNER JOIN campaign ON campaign.campaignId = lkcampaignlayout.campaignId WHERE campaign.isLayoutSpecific = 1 AND layout.layoutId = :layoutId';
                $paramsLayoutHistory = ['layoutId' => $params['entityId']];
                $results = $this->getStore()->select($sqlLayoutHistory, $paramsLayoutHistory);
                foreach ($results as $row) {
                    $campaignId = $row['campaignId'];
                }

                if (isset($campaignId)) {
                    $body .= ' OR auditlog.entityId IN (SELECT layouthistory.layoutId FROM layouthistory WHERE layouthistory.campaignId = :campaignId) ) ';
                    $params['campaignId'] = $campaignId;
                } else {
                    $body .= ' ) ';
                }
            } else {
                $body .= ' ) ';
            }
        }

        $order = '';
        if (is_array($sortOrder) && count($sortOrder) > 0) {
            $order .= 'ORDER BY ' . implode(', ', $sortOrder) . ' ';
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;



        $dbh = $this->getStore()->getConnection();

        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        foreach ($sth->fetchAll() as $row) {
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