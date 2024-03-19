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


use Xibo\Entity\Session;
use Xibo\Helper\DateFormatHelper;

/**
 * Class SessionFactory
 * @package Xibo\Factory
 */
class SessionFactory extends BaseFactory
{
    /**
     * @return Session
     */
    public function createEmpty()
    {
        return new Session($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param int $userId
     */
    public function expireByUserId(int $userId): void
    {
        $this->getStore()->update(
            'UPDATE `session` SET IsExpired = 1 WHERE userID = :userId ',
            ['userId' => $userId]
        );
    }

    /**
     * @param int $userId
     * @return int loggedIn
     */
    public function getActiveSessionsForUser($userId)
    {
        $userSession = $this->query(null, ['userId' => $userId, 'type' => 'active']);

        return (count($userSession) > 0) ? 1 : 0;
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Session[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $select = '
            SELECT `session`.session_id AS sessionId, 
                session.userId, 
                user.userName, 
                isExpired, 
                session.lastAccessed, 
                remoteAddr AS remoteAddress, 
                userAgent, 
                user.userId AS userId,
                `session`.session_expiration AS expiresAt
        ';

        $body = '
          FROM `session`
            LEFT OUTER JOIN user ON user.userID = session.userID
         WHERE 1 = 1
        ';

        if ($sanitizedFilter->getString('sessionId') != null) {
            $body .= ' AND session.session_id = :sessionId ';
            $params['sessionId'] = $sanitizedFilter->getString('sessionId');
        }

        if ($sanitizedFilter->getString('fromDt') != null) {
            $body .= ' AND session.LastAccessed >= :lastAccessed ';
            $params['lastAccessed'] = $sanitizedFilter->getDate('fromDt')->setTime(0, 0, 0)->format(DateFormatHelper::getSystemFormat());
        }

        if ($sanitizedFilter->getString('type') != null) {

            if ($sanitizedFilter->getString('type') == 'active') {
                $body .= ' AND IsExpired = 0 ';
            }

            if ($sanitizedFilter->getString('type') == 'expired') {
                $body .= ' AND IsExpired = 1 ';
            }

            if ($sanitizedFilter->getString('type') == 'guest') {
                $body .= ' AND IFNULL(session.userID, 0) = 0 ';
            }
        }

        if ($sanitizedFilter->getInt('userId') != null) {
            $body .= ' AND user.userID = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $session = $this->createEmpty()->hydrate($row, [
                'stringProperties' => ['sessionId'],
                'intProperties' => ['isExpired'],
            ]);
            $session->userAgent = htmlspecialchars($session->userAgent);
            $session->remoteAddress = filter_var($session->remoteAddress, FILTER_VALIDATE_IP);
            $session->excludeProperty('sessionId');
            $entries[] = $session;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}