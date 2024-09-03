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


use Carbon\Carbon;
use Xibo\Entity\LogEntry;
use Xibo\Helper\DateFormatHelper;

/**
 * Class LogFactory
 * @package Xibo\Factory
 */
class LogFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return LogEntry
     */
    public function createEmpty()
    {
        return new LogEntry($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Query
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[\Xibo\Entity\Log]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['logId DESC'];
        }

        $entries = [];
        $params = [];
        $order = '';
        $limit = '';

        $select = '
            SELECT `logId`,
                `runNo`,
                `logDate`,
                `channel`,
                `page`,
                `function`,
                `message`,
                `display`.`displayId`,
                `display`.`display`,
                `type`,
                `userId`,
                `sessionHistoryId`
        ';

        $body = '
              FROM `log`
                  LEFT OUTER JOIN `display`
                  ON `display`.`displayid` = `log`.`displayid`
                  ';
        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON `lkdisplaydg`.`DisplayID` = `log`.`displayid` ';
        }

        $body .= ' WHERE 1 = 1 ';


        if ($parsedFilter->getInt('fromDt') !== null) {
            $body .= ' AND `logdate` > :fromDt ';
            $params['fromDt'] = Carbon::createFromTimestamp(
                $parsedFilter->getInt('fromDt')
            )->format(DateFormatHelper::getSystemFormat());
        }

        if ($parsedFilter->getInt('toDt') !== null) {
            $body .= ' AND `logdate` <= :toDt ';
            $params['toDt'] = Carbon::createFromTimestamp(
                $parsedFilter->getInt('toDt')
            )->format(DateFormatHelper::getSystemFormat());
        }

        if ($parsedFilter->getString('runNo') != null) {
            $body .= ' AND `runNo` = :runNo ';
            $params['runNo'] = $parsedFilter->getString('runNo');
        }

        if ($parsedFilter->getString('type') != null) {
            $body .= ' AND `type` = :type ';
            $params['type'] = $parsedFilter->getString('type');
        }

        if ($parsedFilter->getString('channel') != null) {
            $body .= ' AND `channel` LIKE :channel ';
            $params['channel'] = '%' . $parsedFilter->getString('channel') . '%';
        }

        if ($parsedFilter->getString('page') != null) {
            $body .= ' AND `page` LIKE :page ';
            $params['page'] = '%' . $parsedFilter->getString('page') . '%';
        }

        if ($parsedFilter->getString('function') != null) {
            $body .= ' AND `function` LIKE :function ';
            $params['function'] = '%' . $parsedFilter->getString('function') . '%';
        }

        if ($parsedFilter->getString('message') != null) {
            $body .= ' AND `message` LIKE :message ';
            $params['message'] = '%' . $parsedFilter->getString('message') . '%';
        }

        if ($parsedFilter->getInt('displayId') !== null) {
            $body .= ' AND `log`.`displayId` = :displayId ';
            $params['displayId'] = $parsedFilter->getInt('displayId');
        }

        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND `log`.`userId` = :userId ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        if ($parsedFilter->getCheckbox('excludeLog') == 1) {
            $body .= ' AND (`log`.`page` NOT LIKE \'/log%\' OR `log`.`page` = \'/login\') ';
            $body .= ' AND `log`.`page` NOT IN(\'/user/pref\', \'/clock\', \'/fonts/fontcss\') ';
        }

        // Filter by Display Name?
        if ($parsedFilter->getString('display') != null) {
            $terms = explode(',', $parsedFilter->getString('display'));
            $this->nameFilter(
                'display',
                'display',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1)
            );
        }

        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= ' AND `lkdisplaydg`.`displaygroupid` = :displayGroupId ';
            $params['displayGroupId'] = $parsedFilter->getInt('displayGroupId');
        }

        if ($parsedFilter->getInt('sessionHistoryId') !== null) {
            $body .= ' AND `log`.`sessionHistoryId` = :sessionHistoryId ';
            $params['sessionHistoryId'] = $parsedFilter->getInt('sessionHistoryId');
        }

        // Sorting?
        if (is_array($sortOrder)) {
            $order = ' ORDER BY ' . implode(',', $sortOrder);
        }

        // Paging
        if ($filterBy !== null
            && $parsedFilter->getInt('start') !== null
            && $parsedFilter->getInt('length', ['default' => 10]) !== null
        ) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) . ', '
                . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['htmlStringProperties' => ['message']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
