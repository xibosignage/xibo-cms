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
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class AuditLogFactory extends BaseFactory
{
    public static function query($sortOrder = null, $filterBy = null)
    {
        Log::debug('AuditLog Factory with filter: %s', var_export($filterBy, true));

        $entries = array();
        $params = [];

        $select = ' SELECT logId, logDate, user.userName, message, objectAfter, entity, entityId, auditlog.userId ';
        $body = 'FROM `auditlog` LEFT OUTER JOIN user ON user.userId = auditlog.userId WHERE 1 = 1 ';

        if (Sanitize::getInt('fromTimeStamp', $filterBy) !== null) {
            $body .= ' AND `auditlog`.logDate >= :fromTimeStamp ';
            $params['fromTimeStamp'] = Sanitize::getInt('fromTimeStamp', $filterBy);
        }

        if (Sanitize::getInt('toTimeStamp', $filterBy) !== null) {
            $body .= ' AND `auditlog`.logDate < :toTimeStamp ';
            $params['toTimeStamp'] = Sanitize::getInt('toTimeStamp', $filterBy);
        }

        if (Sanitize::getString('entity', $filterBy) != null) {
            $body .= ' AND `auditlog`.entity LIKE :entity ';
            $params['entity'] = '%' . Sanitize::getString('entity', $filterBy) . '%';
        }

        if (Sanitize::getString('userName', $filterBy) != null) {
            $body .= ' AND `auditlog`.userName LIKE :userName ';
            $params['userName'] = '%' . Sanitize::getString('userName', $filterBy) . '%';
        }

        if (Sanitize::getString('message', $filterBy) != null) {
            $body .= ' AND `auditlog`.message LIKE :message ';
            $params['message'] = '%' . Sanitize::getString('message', $filterBy) . '%';
        }

        $order = '';
        if (is_array($sortOrder) && count($sortOrder) > 0) {
            $order .= 'ORDER BY ' . implode(', ', $sortOrder) . ' ';
        }

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        $dbh = PDOConnect::init();

        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        foreach ($sth->fetchAll() as $row) {
            $entries[] = (new AuditLog())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}