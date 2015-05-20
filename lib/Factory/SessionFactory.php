<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (SessionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Session;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class SessionFactory
{
    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Session]
     * @throws NotFoundException
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        try {
            $sql = '
            SELECT session.userId, user.userName, isExpired, lastPage, session.lastAccessed, remoteAddr AS remoteAddress, userAgent
              FROM `session`
                LEFT OUTER JOIN user ON user.userID = session.userID
             WHERE 1 = 1
            ';

            if (Sanitize::getString('fromDt', $filterBy) != '') {
                $sql .= ' AND session.LastAccessed < :lastAccessed ';
                $params['lastAccessed'] = Date::getMidnightSystemDate(Date::getTimestampFromString(Sanitize::getString('fromDt', $filterBy)));
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $sql .= ' AND IsExpired = 0 ';
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $sql .= ' AND IsExpired = 1 ';
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $sql .= ' AND session.userID IS NULL ';
            }

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new Session())->hydrate($row);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}