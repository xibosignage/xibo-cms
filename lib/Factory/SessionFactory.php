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

class SessionFactory extends BaseFactory
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
            $select = '
            SELECT session.userId, user.userName, isExpired, lastPage, session.lastAccessed, remoteAddr AS remoteAddress, userAgent ';

            $body = '
              FROM `session`
                LEFT OUTER JOIN user ON user.userID = session.userID
             WHERE 1 = 1
            ';

            if (Sanitize::getString('fromDt', $filterBy) != '') {
                $body .= ' AND session.LastAccessed < :lastAccessed ';
                $params['lastAccessed'] = Date::getLocalDate(Sanitize::getDate('fromDt', $filterBy)->setTime(0, 0, 0));
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $body .= ' AND IsExpired = 0 ';
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $body .= ' AND IsExpired = 1 ';
            }

            if (Sanitize::getString('type', $filterBy) == 'active') {
                $body .= ' AND session.userID IS NULL ';
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder))
                $order .= 'ORDER BY ' . implode(',', $sortOrder);

            $limit = '';
            // Paging
            if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
                $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
            }

            $sql = $select . $body . $order . $limit;

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new Session())->hydrate($row);
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
                self::$_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}