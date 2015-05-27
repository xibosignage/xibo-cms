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

class AuditLogFactory
{
    private static $_countLast = 0;

    /**
     * Count of records returned for the last query.
     * @return int
     */
    public static function countLast()
    {
        return self::$_countLast;
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = ' SELECT logId, logDate, user.userName, message, objectAfter, entity, entityId, auditlog.userId ';
        $body = 'FROM `auditlog` LEFT OUTER JOIN user ON user.userId = auditlog.userId WHERE 1 = 1 ';

        if (\Kit::GetParam('search', $filterBy, _STRING) != '') {
            // tokenize
            $i = 0;
            foreach (explode(' ', \Kit::GetParam('search', $filterBy, _STRING)) as $searchTerm) {
                $i++;

                if (stripos($searchTerm, '|') > -1) {
                    $complexTerm = explode('|', $searchTerm);

                    if (!isset($complexTerm[1]) || $complexTerm[1] == '')
                        continue;

                    $like = false;

                    switch (strtolower($complexTerm[0])) {
                        case 'fromtimestamp':
                            $body .= ' AND auditlog.logDate >= :search' . $i;
                            break;

                        case 'totimestamp':
                            $body .= ' AND auditlog.logDate < :search' . $i;
                            break;

                        case 'entity':
                            $like = true;
                            $body .= ' AND auditlog.entity LIKE :search' . $i;
                            break;

                        case 'username':
                            $like = true;
                            $body .= ' AND user.userName LIKE :search' . $i;
                            break;

                        default:
                            $like = true;
                            $body .= ' AND auditlog.message LIKE :search' . $i;
                    }

                    $params['search' . $i] = (($like) ? '%' . $complexTerm[1] . '%' : $complexTerm[1]);
                }
                else {
                    $body .= ' AND auditlog.message LIKE :search' . $i;
                    $params['search' . $i] = '%' . $searchTerm . '%';
                }
            }
            $body .= ' ';
        }

        $order = '';
        if (is_array($sortOrder) && count($sortOrder) > 0) {
            $order .= 'ORDER BY ' . implode(', ', $sortOrder) . ' ';
        }

        // The final statements
        $sql = $select . $body . $order;

        \Debug::sql($sql, $params);

        $dbh = \PDOConnect::init();

        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        foreach ($sth->fetchAll() as $row) {
            $auditLog = new AuditLog();
            $auditLog->logId = $row['logId'];
            $auditLog->logDate = $row['logDate'];
            $auditLog->entity = $row['entity'];
            $auditLog->userId = $row['userId'];
            $auditLog->userName = $row['userName'];
            $auditLog->message = $row['message'];
            $auditLog->entityId = $row['entityId'];
            $auditLog->objectAfter = $row['objectAfter'];

            $entries[] = $auditLog;
        }
        return $entries;
    }
}