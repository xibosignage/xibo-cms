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


use Slim\Slim;
use Xibo\Storage\PDOConnect;

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
        Log::debug(sprintf('Audit Trail message recorded for %s with id %d. Message: %s', $entity, $entityId, $message));

        if (self::$_auditLogStatement == null) {
            $dbh = PDOConnect::newConnection();
            self::$_auditLogStatement = $dbh->prepare('
                INSERT INTO `auditlog` (logDate, userId, entity, message, entityId, objectAfter)
                  VALUES (:logDate, :userId, :entity, :message, :entityId, :objectAfter)
            ');
        }

        // If we aren't a string then encode
        if (!is_string($object))
            $object = json_encode($object);

        $app = Slim::getInstance();

        self::$_auditLogStatement->execute([
            'logDate' => time(),
            'userId' => $app->user->userId,
            'entity' => $entity,
            'message' => $message,
            'entityId' => $entityId,
            'objectAfter' => $object
        ]);
    }

    public static function sql($sql, $params)
    {
        $app = Slim::getInstance();
        if (strtolower($app->getMode()) == 'test')
            $app->log->debug(sprintf('SQL = %s. Params = %s.', $sql, var_export($params, true)));
    }

    public static function debug($object)
    {
        // Get the calling class / function
        $app = Slim::getInstance();
        $app->log->debug(Log::prepare($object, func_get_args()));
    }

    public static function notice($object)
    {
        $app = Slim::getInstance();
        $app->log->notice(Log::prepare($object, func_get_args()));
    }
    public static function info($object)
    {
        $app = Slim::getInstance();
        $app->log->info(Log::prepare($object, func_get_args()));
    }

    public static function warning($object)
    {
        $app = Slim::getInstance();
        $app->log->warning(Log::prepare($object, func_get_args()));
    }

    public static function error($object)
    {
        $app = Slim::getInstance();
        $app->log->error(Log::prepare($object, func_get_args()));
    }

    public static function critical($object)
    {
        $app = Slim::getInstance();
        $app->log->critical(Log::prepare($object, func_get_args()));
    }

    public static function alert($object)
    {
        $app = Slim::getInstance();
        $app->log->alert(Log::prepare($object, func_get_args()));
    }

    public static function emergency($object)
    {
        $app = Slim::getInstance();
        $app->log->emergency(Log::prepare($object, func_get_args()));
    }
    
    /**
     * Stringify string objects
     * @param $object
     * @return string
     */
    private static function prepare($object, $args)
    {
        if (is_string($object)) {
            array_shift($args);

            if (count($args) > 0)
                $object = vsprintf($object, $args);
        }

        return $object;
    }

    /**
     * Resolve the log level
     * @param string $level
     * @return int
     */
    public static function resolveLogLevel($level)
    {
        switch (strtolower($level)) {

            case 'emergency':
                return \Slim\Log::EMERGENCY;

            case 'alert':
                return \Slim\Log::ALERT;

            case 'critical':
                return \Slim\Log::CRITICAL;

            case 'error':
                return \Slim\Log::ERROR;

            case 'warning':
                return \Slim\Log::WARN;

            case 'notice':
                return \Slim\Log::NOTICE;

            case 'info':
                return \Slim\Log::INFO;

            case 'debug':
                return \Slim\Log::DEBUG;

            default:
                return \Slim\Log::ERROR;
        }
    }
}
