<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\Factory;

use Xibo\Entity\AuditLog;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class AuditLogFactory
 * @package Xibo\Factory
 */
class AuditLogFactory extends BaseFactory
{
    /** @var AuditLogExtensionInterface|null Extends the listing and export, none when null */
    private ?AuditLogExtensionInterface $extension = null;

    /**
     * Extends the audit log listing and export (CE has no extension).
     * @param AuditLogExtensionInterface|null $extension
     * @return $this
     */
    public function setExtension(?AuditLogExtensionInterface $extension): static
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * The extra filter parameters an extension accepts.
     * @return string[]
     */
    public function getExtensionFilterNames(): array
    {
        return $this->extension?->getFilterNames() ?? [];
    }

    /**
     * The extra CSV export headers an extension adds.
     * @return string[]
     */
    public function getExtensionExportHeaders(): array
    {
        return $this->extension?->getExportHeaders() ?? [];
    }

    /**
     * The extra CSV export values an extension adds for this entry.
     * @param AuditLog $entry
     * @return array
     */
    public function getExtensionExportValues(AuditLog $entry): array
    {
        return $this->extension?->getExportValues($entry) ?? [];
    }

    /**
     * The CSV export's User column for this entry: its userName, unless an extension set that.
     * @param AuditLog $entry
     * @return string|null
     */
    public function getExportUserName(AuditLog $entry): ?string
    {
        return $this->extension?->getExportUserName($entry) ?? $entry->userName;
    }

    /**
     * One whole CSV export row, as an extension makes it safe to open in a spreadsheet (unchanged without one).
     * @param array $cells
     * @return array
     */
    public function getExportSafeRow(array $cells): array
    {
        return $this->extension?->getExportSafeRow($cells) ?? $cells;
    }

    /**
     * Decorates audit entries read some other way than query() (reports), as query() does.
     * @param AuditLog[] $entries
     */
    public function decorate(array $entries): void
    {
        $this->extension?->decorate($entries);
    }

    /**
     * Who wrote other rows that carry a session history (debug log rows, session history rows), as decorate()
     * attributes audit rows: the fields to set on each, keyed as the rows were; nothing for rows left as they are.
     * @param array<int|string, array{sessionHistoryId: int|null, userId: int|null, userName: string|null}> $rows
     * @return array<int|string, array<string, mixed>>
     */
    public function getAttributions(array $rows): array
    {
        return $this->extension?->getAttributions($rows) ?? [];
    }

    /**
     * @return AuditLog
     */
    public function create()
    {
        return new AuditLog($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param int $logId
     * @return AuditLog
     * @throws NotFoundException
     */
    public function getById(int $logId): AuditLog
    {
        $logs = $this->query(null, ['logId' => $logId]);

        if (count($logs) <= 0) {
            throw new NotFoundException(null, 'AuditLog');
        }

        return $logs[0];
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
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

        if ($sanitizedFilter->getInt('logId') !== null) {
            $body .= ' AND `auditlog`.`logId` = :logId ';
            $params['logId'] = $sanitizedFilter->getInt('logId');
        }

        // Extension filters
        if ($this->extension !== null) {
            [$extensionSql, $extensionParams] = $this->extension->filter($sanitizedFilter);
            $body .= $extensionSql;
            $params = array_merge($params, $extensionParams);
        }

        // Sorting?
        $allowedColumns = [
            'logId',
            'logDate',
            'userName',
            'entity',
            'entityId',
            'ipAddress',
            'message',
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['logId DESC'],
            uniqueColumn: 'logId'
        );

        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

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

        $this->extension?->decorate($entries);

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
