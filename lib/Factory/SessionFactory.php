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

use Xibo\Entity\Session;
use Xibo\Helper\DateFormatHelper;

/**
 * Class SessionFactory
 * @package Xibo\Factory
 */
class SessionFactory extends BaseFactory
{
    /** @var callable|null Adds to listed sessions (see setDecorator), nothing when null */
    private $decorator = null;

    /**
     * Adds to the sessions query() lists, for an extension that knows more about some of them (CE has none). The
     * decorator is given the listed Session entities and may set unmatched properties, userName and isExpired.
     * @param callable(Session[]): void $decorator
     * @return $this
     */
    public function setDecorator(callable $decorator): static
    {
        $this->decorator = $decorator;
        return $this;
    }

    /**
     * @return Session
     */
    public function createEmpty(): Session
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
     * @param string $exceptSessionId
     */
    public function expireByUserIdExceptSessionId(int $userId, string $exceptSessionId): void
    {
        $this->getStore()->update(
            'UPDATE `session` SET IsExpired = 1 WHERE userID = :userId AND session_id <> :sessionId ',
            ['userId' => $userId, 'sessionId' => $exceptSessionId]
        );
    }

    /**
     * @param int $userId
     * @return int loggedIn
     */
    public function getActiveSessionsForUser(int $userId): int
    {
        $userSession = $this->query(null, ['userId' => $userId, 'type' => 'active']);

        return (count($userSession) > 0) ? 1 : 0;
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return Session[]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        // Sorting
        $allowedColumns = ['lastAccessed', 'isExpired', 'userName', 'remoteAddress', 'userAgent', 'expiresAt'];
        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['lastAccessed ASC'],
            uniqueColumn: 'sessionId'
        );

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
            $params['lastAccessed'] =
                $sanitizedFilter->getDate('fromDt')
                ->setTime(0, 0, 0)
                ->format(DateFormatHelper::getSystemFormat());
        }

        // Accessed Date filter
        if ($sanitizedFilter->getDate('lastAccessedDateFrom') !== null) {
            $body .= ' AND session.LastAccessed >= :lastAccessedDateFrom ';
            $params['lastAccessedDateFrom'] = $sanitizedFilter->getDate('lastAccessedDateFrom');
        }

        if ($sanitizedFilter->getDate('lastAccessedDateTo') !== null) {
            $body .= ' AND session.LastAccessed <= :lastAccessedDateTo ';
            $params['lastAccessedDateTo'] = $sanitizedFilter->getDate('lastAccessedDateTo');
        }

        if ($sanitizedFilter->getString('type') != null) {
            $body .= match ($sanitizedFilter->getString('type')) {
                'active' => ' AND IsExpired = 0 ',
                'expired' => ' AND IsExpired = 1 ',
                'guest' => ' AND IFNULL(session.userID, 0) = 0 ',
                default => '',
            };
        }

        if ($sanitizedFilter->getInt('userId') != null) {
            $body .= ' AND user.userID = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // Sorting?
        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        $limit = '';
        // Paging
        if ($filterBy !== null
            && $sanitizedFilter->getInt('start') !== null
            && $sanitizedFilter->getInt('length') !== null
        ) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' .
                $sanitizedFilter->getInt('length', ['default' => 10]);
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

        if ($this->decorator !== null && count($entries) > 0) {
            ($this->decorator)($entries);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
