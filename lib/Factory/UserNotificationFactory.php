<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use Xibo\Entity\User;
use Xibo\Entity\UserNotification;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class UserGroupNotificationFactory
 * @package Xibo\Factory
 */
class UserNotificationFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct(User $user, $userFactory)
    {
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * @return UserNotification
     */
    public function createEmpty(): UserNotification
    {
        return new UserNotification($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Create User Notification
     * @param $subject
     * @param string $body
     * @return UserNotification
     */
    public function create($subject, string $body = ''): UserNotification
    {
        $notification = $this->createEmpty();
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->userId = $this->getUser()->userId;
        $notification->releaseDt = Carbon::now()->format('U');

        return $notification;
    }

    /**
     * Get by NotificationId
     * @param int $notificationId
     * @return UserNotification
     * @throws AccessDeniedException
     */
    public function getByNotificationId(int $notificationId): UserNotification
    {
        $notifications = $this->query(null, [
            'userId' => $this->getUser()->userId,
            'notificationId' => $notificationId,
        ]);

        if (count($notifications) <= 0) {
            throw new AccessDeniedException();
        }

        return $notifications[0];
    }

    /**
     * Get my notifications
     * @param int $length
     * @return UserNotification[]
     */
    public function getMine(int $length = 5): array
    {
        return $this->query(null, ['userId' => $this->getUser()->userId, 'start' => 0, 'length' => $length]);
    }

    /**
     * Get email notification queue
     * @return UserNotification[]
     */
    public function getEmailQueue(): array
    {
        return $this->query(null, ['isEmailed' => 0, 'checkRetired' => 1]);
    }

    /**
     * Count My Unread
     * @return int
     */
    public function countMyUnread(): int
    {
        return $this->getStore()->select('
            SELECT COUNT(*) AS Cnt
              FROM `lknotificationuser`
                INNER JOIN `notification`
                ON `notification`.notificationId = `lknotificationuser`.notificationId
             WHERE `lknotificationuser`.`userId` = :userId
              AND `lknotificationuser`.`read` = 0
              AND `notification`.releaseDt < :now
          ', [
            'now' => Carbon::now()->format('U'),
            'userId' => $this->getUser()->userId,
        ])[0]['Cnt'];
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return UserNotification[]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $entries = [];
        $parsedBody = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['releaseDt DESC'];
        }

        $params = ['now' => Carbon::now()->format('U')];
        $select = 'SELECT `lknotificationuser`.lknotificationuserId,
            `lknotificationuser`.notificationId,
            `lknotificationuser`.userId,
            `lknotificationuser`.read,
            `lknotificationuser`.readDt,
            `lknotificationuser`.emailDt,
             `notification`.subject,
             `notification`.body,
             `notification`.releaseDt,
             `notification`.isInterrupt,
             `notification`.isSystem,
             `notification`.filename,
             `notification`.originalFileName,
             `notification`.nonusers,
             `notification`.type,
             `user`.email,
             `user`.retired
        ';

        $body = ' FROM `notification`
                LEFT OUTER JOIN `lknotificationuser`
                    ON `notification`.notificationId = `lknotificationuser`.notificationId
                LEFT OUTER JOIN `user`
                    ON `user`.userId = `lknotificationuser`.userId
         ';

        $body .= ' WHERE `notification`.releaseDt < :now ';

        if ($parsedBody->getInt('notificationId') !== null) {
            $body .= ' AND `notification`.notificationId = :notificationId ';
            $params['notificationId'] = $parsedBody->getInt('notificationId');
        }

        if ($parsedBody->getInt('userId') !== null) {
            $body .= ' AND (`lknotificationuser`.userId = :userId OR `notification`.userId = :userId) ';
            $params['userId'] = $parsedBody->getInt('userId');
        }

        if ($parsedBody->getInt('read') !== null) {
            $body .= ' AND `lknotificationuser`.read = :read ';
            $params['read'] = $parsedBody->getInt('read');
        }

        if ($parsedBody->getInt('isEmailed') !== null) {
            if ($parsedBody->getInt('isEmailed') == 0) {
                $body .= ' AND `lknotificationuser`.emailDt = 0 ';
            } else {
                $body .= ' AND `lknotificationuser`.emailDt <> 0 ';
            }
        }

        if ($parsedBody->getInt('checkRetired') === 1) {
            $body .= ' AND `user`.retired = 0 ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedBody->getInt('start') !== null && $parsedBody->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedBody->getInt('start', ['default' => 0]) . ', ' . $parsedBody->getInt('length');
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}