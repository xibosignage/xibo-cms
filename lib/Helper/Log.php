<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Log.php) is part of Xibo.
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


namespace Xibo\Helper;


class Log
{
    private static $_auditLogStatement;

    /**
     * Audit Log
     * @param string $entity
     * @param int $entityId
     * @param string $message
     * @param string|object|array $object
     */
    public static function audit($entity, $entityId, $message, $object)
    {
        \Debug::Audit(sprintf('Audit Trail message recorded for %s with id %d. Message: %s', $entity, $entityId, $message));

        if (self::$_auditLogStatement == null) {
            $dbh = \PDOConnect::newConnection();
            self::$_auditLogStatement = $dbh->prepare('
                INSERT INTO `auditlog` (logDate, userId, entity, message, entityId, objectAfter)
                  VALUES (:logDate, :userId, :entity, :message, :entityId, :objectAfter)
            ');
        }

        // If we aren't a string then encode
        if (!is_string($object))
            $object = json_encode($object);

        self::$_auditLogStatement->execute(array(
            'logDate' => time(),
            'userId' => \Kit::GetParam('userid', _SESSION, _INT, 0),
            'entity' => $entity,
            'message' => $message,
            'entityId' => $entityId,
            'objectAfter' => $object
        ));
    }
}