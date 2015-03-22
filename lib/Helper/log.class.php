<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
namespace Xibo\Helper;
use PDOException;
use Slim\Slim;

class Log
{
    public static function debug($object)
    {
        $app = Slim::getInstance();
        $app->log->debug($object);
    }

    public static function info($object)
    {
        $app = Slim::getInstance();
        $app->log->info($object);
    }

    public static function notice($object)
    {
        $app = Slim::getInstance();
        $app->log->notice($object);
    }

    public static function warning($object)
    {
        $app = Slim::getInstance();
        $app->log->warning($object);
    }

    public static function error($object)
    {
        $app = Slim::getInstance();
        $app->log->error($object);
    }

    public static function critical($object)
    {
        $app = Slim::getInstance();
        $app->log->critical($object);
    }

    public static function alert($object)
    {
        $app = Slim::getInstance();
        $app->log->alert($object);
    }

    public static function emergency($object)
    {
        $app = Slim::getInstance();
        $app->log->emergency($object);
    }

    /**
     * Log
     * @param string $type
     * @param string $message
     * @param string $page [Optional]
     * @param string $function [Optional]
     * @param string $logDate [Optional]
     * @param int $displayId [Optional]
     * @param int $scheduleId [Optional]
     * @param int $layoutId [Optional]
     * @param string $mediaId [Optional]
     */
    public static function log($type, $message, $page = null, $function = null, $logDate = null, $displayId = 0, $scheduleId = 0, $layoutId = 0, $mediaId = null)
    {
        if (self::$pdo == NULL)
            self::$pdo = \Xibo\Storage\PDOConnect::newConnection();

        $currentDate = date("Y-m-d H:i:s");
        $requestUri = \Kit::GetParam('REQUEST_URI', $_SERVER, _STRING, 'Not Supplied');
        $requestIp = \Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING, 'Not Supplied');
        $requestUserAgent = \Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'Not Supplied');
        $requestUserAgent = substr($requestUserAgent, 0, 253);
        $userId = \Kit::GetParam('userid', _SESSION, _INT, 0);
        $message = \Kit::ValidateParam($message, _HTMLSTRING);

        // Prepare the variables
        if ($logDate == null)
            $logDate = $currentDate;

        if ($page == null)
            $page = \Kit::GetParam('p', _GET, _WORD);

        // Insert into the DB
        try {
            $dbh = self::$pdo;

            $SQL = 'INSERT INTO log (logdate, type, page, function, message, requesturi, remoteaddr, useragent, userid, displayid, scheduleid, layoutid, mediaid)
                      VALUES (:logdate, :type, :page, :function, :message, :requesturi, :remoteaddr, :useragent, :userid, :displayid, :scheduleid, :layoutid, :mediaid) ';

            $sth = $dbh->prepare($SQL);

            $params = array(
                'logdate' => $logDate,
                'type' => $type,
                'page' => $page,
                'function' => $function,
                'message' => $message,
                'requesturi' => $requestUri,
                'remoteaddr' => $requestIp,
                'useragent' => $requestUserAgent,
                'userid' => $userId,
                'displayid' => $displayId,
                'scheduleid' => $scheduleId,
                'layoutid' => $layoutId,
                'mediaid' => $mediaId
            );

            $sth->execute($params);
        } catch (PDOException $e) {
            // In this case just silently log the error
            error_log($message . '\n\n', 3, './err_log.xml');
            error_log($e->getMessage() . '\n\n', 3, './err_log.xml');
        }
    }
}

?>
